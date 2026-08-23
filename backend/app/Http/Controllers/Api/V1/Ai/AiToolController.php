<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Services\Agents\AgentToolGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The `ai_agent` identity's own tool-calling surface - used by the
 * customer chatbot's tool needs (Phase 12) and, from Phase 13 onward, any
 * future named agent (Sales/Support/Order/Payment/Inventory/Marketing/
 * Dispatch/Analytics) provisioned via `php artisan agent:provision`. Every
 * call goes through AgentToolGateway's Approved-Tool -> Validation ->
 * Authorization -> Business-Logic pipeline - this controller does nothing
 * but pass the request through and translate the result to JSON.
 */
class AiToolController extends Controller
{
    public function __construct(private readonly AgentToolGateway $gateway) {}

    public function invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'string'],
            'input' => ['sometimes', 'array'],
        ]);

        $output = $this->gateway->invoke($data['tool'], $data['input'] ?? [], $request->user());

        return response()->json(['tool' => $data['tool'], 'output' => $output]);
    }
}
