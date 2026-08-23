<?php

namespace App\Services\Ai\Providers;

use RuntimeException;

/** Thrown when no API key is configured, or the provider call fails/times out - AiChatService always catches this and degrades to a safe canned reply (CLAUDE.md §16: a non-critical path failure must never break the chat flow). */
class AiProviderUnavailableException extends RuntimeException {}
