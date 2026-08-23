<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown for any malformed, expired, or tampered token - JWT access tokens
 * and opaque refresh tokens alike. Callers only need to know "this token
 * doesn't authenticate", never the specific reason (which is logged
 * server-side, not leaked to the client - CLAUDE.md §16).
 */
class InvalidTokenException extends RuntimeException {}
