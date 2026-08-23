<?php

namespace App\Services\Ai;

/**
 * Customer Query -> Intent Detection (the pipeline's first step). Plain
 * keyword/regex matching, deliberately not a model call - classifying
 * "which of a handful of known categories does this belong to" doesn't
 * need AI, and doing it with AI would mean paying for a model call on
 * every single message just to decide whether a model call is needed at
 * all, defeating the entire cost architecture this phase exists to build.
 *
 * Ordered by specificity: order-status/contact/hours/payment checks come
 * before the broader price/stock checks so a message like "what's your
 * phone number for order status questions" doesn't misfire as a price
 * lookup.
 */
class ChatIntentDetector
{
    public const PRICE = 'price';

    public const STOCK = 'stock';

    public const DELIVERY = 'delivery';

    public const BUSINESS_HOURS = 'business_hours';

    public const PAYMENT_METHODS = 'payment_methods';

    public const ORDER_STATUS = 'order_status';

    public const CONTACT = 'contact';

    public const UNKNOWN = 'unknown';

    private const PATTERNS = [
        // Bug fixes (QA pass): "order status" alone doesn't cover the
        // equally natural "what's the status of order X" phrasing (status
        // before order, not after) - added as its own alternative rather
        // than a broad "status.*order" pattern that could misfire on
        // unrelated messages mentioning both words.
        self::ORDER_STATUS => '/\b(order\s*status|status\s+of\s+(my\s+)?order|track(ing)?\s*(my\s*)?order|where\s+is\s+my\s+order|my\s+order)\b/i',
        self::CONTACT => '/\b(contact|phone\s*number|email\s*address|whatsapp\s*number|reach\s+you|customer\s+service\s+number)\b/i',
        self::BUSINESS_HOURS => '/\b(business\s*hours|opening\s*hours|open(ed)?\s+(today|now)|what\s+time.*(open|close)|are\s+you\s+open)\b/i',
        // "methods?" (bug fix, QA pass): the trailing \b previously
        // required "method" to be immediately followed by a non-word
        // character, so the natural plural "payment methods" never
        // actually matched this pattern at all.
        self::PAYMENT_METHODS => '/\b(payment\s*methods?|how\s+(can|do)\s+i\s+pay|pay\s+by|cash\s+on\s+delivery|\bcod\b|easypaisa|jazzcash)\b/i',
        self::DELIVERY => '/\b(deliver(y|ies)?|shipping|ship\s+to|how\s+long.*(deliver|arrive)|delivery\s*(fee|charge|time|area))\b/i',
        self::STOCK => '/\b(in\s*stock|out\s*of\s*stock|available|availability|do\s+you\s+have)\b/i',
        self::PRICE => '/\b(price|cost|how\s+much|rate)\b/i',
    ];

    public function detect(string $message): string
    {
        foreach (self::PATTERNS as $intent => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return $intent;
            }
        }

        return self::UNKNOWN;
    }
}
