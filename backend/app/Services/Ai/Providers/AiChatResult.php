<?php

namespace App\Services\Ai\Providers;

final class AiChatResult
{
    public function __construct(
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly string $model,
    ) {}
}
