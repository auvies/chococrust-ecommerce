/**
 * Minimal structured logger foundation. Wraps console so call sites don't
 * depend on a specific logging backend — swapping in a provider (e.g.
 * Sentry, Axiom) later only means changing this file.
 *
 * Never log secrets, tokens, or raw customer PII (CLAUDE.md §4).
 */

type LogLevel = "debug" | "info" | "warn" | "error";

type LogContext = Record<string, unknown>;

function log(level: LogLevel, message: string, context?: LogContext): void {
  const entry = {
    level,
    message,
    time: new Date().toISOString(),
    ...(context ? { context } : {}),
  };

  const method = level === "debug" ? "log" : level;
  console[method](JSON.stringify(entry));
}

export const logger = {
  debug: (message: string, context?: LogContext) => log("debug", message, context),
  info: (message: string, context?: LogContext) => log("info", message, context),
  warn: (message: string, context?: LogContext) => log("warn", message, context),
  error: (message: string, context?: LogContext) => log("error", message, context),
};
