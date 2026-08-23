<?php

namespace App\Services\Agents\Tools;

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Agents\Contracts\AgentToolInterface;
use App\Services\Inventory\InventoryService;
use Illuminate\Validation\Rule;

/** Read-only, low-risk, unrestricted (ported from the pre-Phase-13 AiToolService - see that class's own note). */
class CheckProductAvailabilityTool implements AgentToolInterface
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function name(): string
    {
        return 'check_product_availability';
    }

    public function allowedAgentTypes(): array
    {
        return [];
    }

    public function isHighRisk(): bool
    {
        return false;
    }

    public function requiredApprovalPermission(): string
    {
        return ''; // never queued for approval - isHighRisk() is false
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
        ];
    }

    public function handle(array $input, User $actor): array
    {
        $variant = ProductVariant::findOrFail($input['product_variant_id']);
        $available = $this->inventory->availableQuantity($variant);

        return ['sku' => $variant->sku, 'available' => $available > 0, 'quantity' => max($available, 0)];
    }
}
