<?php

namespace App\Services\Agents\Tools;

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Agents\AgentType;
use App\Services\Agents\Contracts\AgentToolInterface;
use App\Services\Inventory\InventoryService;
use Illuminate\Validation\Rule;

/** Read-only, low-risk - the Sales/Inventory agents' own stock lookup (distinct from the chatbot's public-facing availability check: this returns the exact on-hand/reserved/available breakdown, not just a yes/no). */
class GetInventoryLevelTool implements AgentToolInterface
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function name(): string
    {
        return 'get_inventory_level';
    }

    public function allowedAgentTypes(): array
    {
        return [AgentType::INVENTORY, AgentType::SALES];
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
        $onHand = (int) $variant->inventory()->sum('quantity_on_hand');

        return [
            'sku' => $variant->sku,
            'quantity_on_hand' => $onHand,
            'quantity_available' => max($this->inventory->availableQuantity($variant), 0),
        ];
    }
}
