<?php

namespace App\Services\Agents;

use App\Services\Agents\Contracts\AgentToolInterface;
use App\Services\Agents\Tools\CheckProductAvailabilityTool;
use App\Services\Agents\Tools\GetInventoryLevelTool;
use App\Services\Agents\Tools\GetOrderStatusTool;
use App\Services\Agents\Tools\GetRecentEventsTool;
use App\Services\Agents\Tools\RequestOrderCancellationTool;
use App\Services\Agents\Tools\RequestRefundTool;

/**
 * The "Approved Tool" step of the pipeline: the fixed, explicit allow-list
 * of every tool an agent identity can ever name. A tool not listed here
 * cannot be invoked no matter what an agent or a future caller sends -
 * there is no dynamic registration, no plugin mechanism, nothing an agent
 * could ever influence about which tools exist.
 *
 * Deliberately a short list, not one entry per named agent type - "Do not
 * build unnecessary AI agents now. Only create the architecture and safe
 * interfaces": this registers the tools that exist today (2 ported from
 * the pre-Phase-13 chatbot tool surface, 4 new ones demonstrating
 * agent-type scoping and the high-risk approval gate), not a placeholder
 * per agent.
 */
class AgentToolRegistry
{
    /** @var array<int, class-string<AgentToolInterface>> */
    private const TOOL_CLASSES = [
        CheckProductAvailabilityTool::class,
        GetOrderStatusTool::class,
        GetInventoryLevelTool::class,
        GetRecentEventsTool::class,
        RequestRefundTool::class,
        RequestOrderCancellationTool::class,
    ];

    public function find(string $name): ?AgentToolInterface
    {
        foreach (self::TOOL_CLASSES as $class) {
            /** @var AgentToolInterface $tool */
            $tool = app($class);
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /** @return array<int, string> every registered tool's name, for diagnostics/testing only. */
    public function names(): array
    {
        return array_map(fn (string $class) => app($class)->name(), self::TOOL_CLASSES);
    }
}
