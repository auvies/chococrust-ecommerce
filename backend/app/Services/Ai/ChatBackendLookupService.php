<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\DeliveryRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic Backend Lookup (the pipeline's second step) - the
 * business's own list of what must never cost a model call: prices,
 * stock, delivery rules, business hours, payment methods, order status,
 * contact details. Every method here either fully answers the question
 * from data already in Postgres/config, or returns null so
 * AiChatService knows to fall through to the AI step - it never partially
 * answers and never guesses.
 *
 * `retrieveRelevantContext()` is the pipeline's third step ("Relevant Data
 * Retrieval") - used only when AI genuinely is necessary, and even then
 * bounded to a handful of short product snippets, never the catalog.
 */
class ChatBackendLookupService
{
    private const STOPWORDS = [
        'price', 'cost', 'how', 'much', 'is', 'the', 'a', 'an', 'of', 'for',
        'rate', 'in', 'stock', 'available', 'availability', 'do', 'you',
        'have', 'i', 'want', 'to', 'know', 'what', 'whats', 'please', 'and',
    ];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PromptInjectionGuard $guard,
    ) {}

    public function respond(string $intent, string $message, ?Customer $customer): ?string
    {
        return match ($intent) {
            ChatIntentDetector::PRICE => $this->priceAnswer($message),
            ChatIntentDetector::STOCK => $this->stockAnswer($message),
            ChatIntentDetector::DELIVERY => $this->deliveryAnswer(),
            ChatIntentDetector::BUSINESS_HOURS => $this->businessHoursAnswer(),
            ChatIntentDetector::PAYMENT_METHODS => $this->paymentMethodsAnswer(),
            ChatIntentDetector::ORDER_STATUS => $this->orderStatusAnswer($message, $customer),
            ChatIntentDetector::CONTACT => $this->contactAnswer(),
            default => null,
        };
    }

    private function priceAnswer(string $message): ?string
    {
        $matches = $this->searchProducts($message);

        if ($matches->isEmpty()) {
            return null; // let AI help - could be a recommendation ask, not a specific product
        }

        if ($matches->count() > 1) {
            return $this->clarifyChoices($matches, 'Which one would you like the price for?');
        }

        $product = $matches->first();
        $prices = $product->variants->where('is_active', true)->pluck('price');
        if ($prices->isEmpty()) {
            return "\"{$product->name}\" doesn't have any orderable variants right now.";
        }

        $min = $prices->min();
        $max = $prices->max();
        $range = $min == $max ? "PKR {$min}" : "PKR {$min} - PKR {$max}";

        return "\"{$product->name}\" is {$range}, depending on the variant you choose.";
    }

    private function stockAnswer(string $message): ?string
    {
        $matches = $this->searchProducts($message);

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() > 1) {
            return $this->clarifyChoices($matches, 'Which one would you like stock for?');
        }

        $product = $matches->first();
        $totalAvailable = $product->variants->where('is_active', true)
            ->sum(fn ($variant) => max($this->inventory->availableQuantity($variant), 0));

        return $totalAvailable > 0
            ? "Yes, \"{$product->name}\" is currently in stock."
            : "Sorry, \"{$product->name}\" is currently out of stock.";
    }

    /**
     * Reads the same `delivery_rules` data OrderService::resolveDelivery()
     * itself checks - a real backend lookup, not a hardcoded paragraph, so
     * an admin editing delivery rules through the admin panel changes what
     * the chatbot says too, with no code change.
     */
    private function deliveryAnswer(): string
    {
        $local = DeliveryRule::where('scope', 'global')->where('delivery_type', 'local')->where('is_active', true)->first();
        $nationwide = DeliveryRule::where('scope', 'global')->where('delivery_type', 'nationwide')->where('is_active', true)->first();

        $parts = [];

        if ($local?->is_deliverable) {
            $areas = is_array($local->local_areas) && $local->local_areas !== [] ? implode(', ', $local->local_areas) : 'our local delivery zone';
            $eta = $local->estimated_minutes ? ' in about '.round($local->estimated_minutes / 60, 1).' hours' : '';
            $fee = $local->flat_fee !== null ? " (delivery fee: PKR {$local->flat_fee})" : '';
            $parts[] = "We deliver locally to {$areas}{$eta}{$fee}.";
        }

        if ($nationwide?->is_deliverable) {
            $fee = $nationwide->flat_fee !== null ? " (shipping fee: PKR {$nationwide->flat_fee})" : '';
            $parts[] = "For everywhere else in Pakistan, we ship non-perishable items nationwide{$fee}.";
        }

        if ($parts === []) {
            return "Delivery details aren't configured yet - please contact us directly for delivery information.";
        }

        return implode(' ', $parts);
    }

    private function businessHoursAnswer(): string
    {
        // ->first()?->value (not the query builder's ->value('value')
        // shortcut) deliberately - `value` is cast to `array` on the
        // model, and the raw query-builder shortcut bypasses Eloquent
        // casting entirely, which would return the still-JSON-encoded
        // string instead of the plain text.
        $hours = SystemSetting::where('key', 'business_hours')->where('is_public', true)->first()?->value;

        return $hours
            ? "Our business hours are: {$hours}."
            : "We haven't published our business hours yet - please contact us directly and we'll get back to you.";
    }

    private function paymentMethodsAnswer(): string
    {
        $methods = collect(config('payments.methods'))->map(function (string $method) {
            return in_array($method, config('payments.cod_methods'), true)
                ? 'Cash on Delivery'
                : ucfirst($method).' (coming soon)';
        });

        return 'We currently accept: '.$methods->implode(', ').'.';
    }

    /**
     * Deliberately narrow, same shape as GetOrderStatusTool::handle()
     * (status only, never contact/address PII) - but additionally scoped
     * to the *asking customer's own orders*, since this is reached
     * directly from a customer-facing chat message, not the allow-listed
     * AI tool endpoint. An anonymous or mismatched customer never learns
     * anything about any order, even their own order number typed by
     * someone else.
     */
    private function orderStatusAnswer(string $message, ?Customer $customer): string
    {
        if (! $customer) {
            return 'Please log in to your account so I can look up your order status.';
        }

        if (preg_match('/\bCC-\d{8}-[A-Z0-9]{6}\b/i', $message, $found) === 1) {
            $order = $customer->orders()->where('order_number', strtoupper($found[0]))->first();

            return $order
                ? "Order {$order->order_number} is currently: {$order->status}."
                : "I couldn't find that order number on your account - please double-check it.";
        }

        $latest = $customer->orders()->latest('placed_at')->first();

        return $latest
            ? "Your most recent order ({$latest->order_number}) is currently: {$latest->status}."
            : "I don't see any orders on your account yet.";
    }

    private function contactAnswer(): string
    {
        $settings = SystemSetting::whereIn('key', ['contact.phone', 'contact.email', 'contact.address'])
            ->where('is_public', true)
            ->pluck('value', 'key');

        $parts = array_filter([
            $settings->get('contact.phone') ? 'phone: '.$settings->get('contact.phone') : null,
            $settings->get('contact.email') ? 'email: '.$settings->get('contact.email') : null,
            $settings->get('contact.address') ? 'address: '.$settings->get('contact.address') : null,
        ]);

        return $parts === []
            ? "Contact details aren't published yet - please check the Contact page."
            : 'You can reach us at '.implode(', ', $parts).'.';
    }

    /**
     * Relevant Data Retrieval (pipeline step 3) - a handful of short
     * product snippets, capped and truncated, never the catalog. Only
     * called once AiChatService has already decided AI is genuinely
     * necessary, so this is the *only* database content that ever reaches
     * the model alongside the conversation itself.
     *
     * @return array<int, array{name: string, price_range: string, description: string}>
     */
    public function retrieveRelevantContext(string $message): array
    {
        return $this->searchProducts($message, 3)
            ->map(function (Product $product) {
                $prices = $product->variants->where('is_active', true)->pluck('price');
                $description = Str::limit((string) ($product->short_description ?? $product->description ?? ''), 150);

                return [
                    // Sanitized (SECURITY_AUDIT.md): this text is
                    // concatenated into the system prompt, not sent as a
                    // user-role message - unlike a customer's own chat
                    // text, it was never scanned before. A product's name
                    // is a much narrower field than its free-text
                    // description but is sanitized too for consistency and
                    // because nothing prevents an equally long name.
                    'name' => $this->guard->sanitizeRetrievedContext($product->name),
                    'price_range' => $prices->isEmpty() ? 'unavailable' : ('PKR '.$prices->min().'-'.$prices->max()),
                    'description' => $this->guard->sanitizeRetrievedContext($description),
                ];
            })
            ->values()
            ->all();
    }

    private function searchProducts(string $message, int $limit = 5): Collection
    {
        $term = $this->extractSearchTerm($message);

        if ($term === '') {
            return collect();
        }

        return Product::query()
            ->where('status', 'active')
            ->where('name', 'like', "%{$term}%")
            ->with('variants')
            ->limit($limit)
            ->get();
    }

    private function extractSearchTerm(string $message): string
    {
        $words = collect(preg_split('/\s+/', Str::lower(preg_replace('/[^\w\s]/', ' ', $message) ?? '')))
            ->filter(fn ($word) => $word !== '' && ! in_array($word, self::STOPWORDS, true));

        return trim($words->implode(' '));
    }

    private function clarifyChoices(Collection $products, string $prompt): string
    {
        $names = $products->pluck('name')->map(fn ($name) => "\"{$name}\"")->implode(', ');

        return "I found a few matches: {$names}. {$prompt}";
    }
}
