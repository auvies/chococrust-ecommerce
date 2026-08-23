<?php

namespace App\Providers;

use App\Auth\JwtCookieGuard;
use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderDispatched;
use App\Events\OrderReady;
use App\Events\PaymentSubmitted;
use App\Events\PaymentVerified;
use App\Events\RefundCompleted;
use App\Listeners\LogAgentEvent;
use App\Models\Category;
use App\Models\CodRecord;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable, short type strings in polymorphic columns (coupon_scopes,
        // seo_metadata, audit_logs) instead of leaking full class paths -
        // classes can be renamed/moved without a data migration. Not
        // enforced: audit_logs.auditable_type spans nearly every model in
        // the app, and a not-yet-mapped model must fall back to its FQCN
        // rather than hard-failing the request (enforceMorphMap() would
        // throw ClassMorphViolationException for anything missing here).
        Relation::morphMap([
            'category' => Category::class,
            'product' => Product::class,
            'customer' => Customer::class,
            'user' => User::class,
            'order' => Order::class,
            'payment' => Payment::class,
            'cod_record' => CodRecord::class,
            'delivery' => Delivery::class,
            'delivery_rule' => DeliveryRule::class,
            'review' => Review::class,
            'coupon' => Coupon::class,
            'system_setting' => SystemSetting::class,
        ]);

        $this->registerAuthGuard();
        $this->registerRateLimiters();
        $this->registerAgentEventListeners();
        $this->validateProductionSecrets();
    }

    /**
     * config/security.php's own docblock has always claimed this check
     * existed ("JWT_SECRET is required in staging/production - checked at
     * boot in AppServiceProvider"); it didn't (SECURITY_AUDIT.md finding
     * L2) - added as part of Phase 16 pre-deployment hardening, closing
     * the gap between that claim and reality before it matters. Compares
     * already-resolved config rather than calling env() directly, which
     * stays correct whether or not config:cache has run (Laravel 11+
     * disables env() outside config files once config is cached).
     */
    private function validateProductionSecrets(): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        if (config('security.jwt_secret') === config('app.key')) {
            throw new \RuntimeException(
                'JWT_SECRET must be set to a value distinct from APP_KEY outside local/testing (CLAUDE.md §6: distinct keys per secret) - refusing to boot with the fallback in a staging/production environment.'
            );
        }
    }

    /**
     * Phase 13's event architecture: every one of the 9 named lifecycle
     * events writes to `agent_event_logs` via the same single listener -
     * see LogAgentEvent's own docblock for why nothing else subscribes
     * yet.
     */
    private function registerAgentEventListeners(): void
    {
        foreach ([
            OrderCreated::class, PaymentSubmitted::class, PaymentVerified::class,
            OrderConfirmed::class, OrderReady::class, OrderDispatched::class,
            OrderDelivered::class, OrderCancelled::class, RefundCompleted::class,
        ] as $event) {
            Event::listen($event, LogAgentEvent::class);
        }
    }

    private function registerAuthGuard(): void
    {
        Auth::extend('jwt-cookie', fn ($app, $name, array $config) => new JwtCookieGuard(
            $app['request'],
            Auth::createUserProvider($config['provider']),
        ));
    }

    private function registerRateLimiters(): void
    {
        // CLAUDE.md §14: strict, per-email+IP throttling on auth endpoints
        // with backoff, distinct from the general API default below.
        RateLimiter::for('login', fn ($request) => Limit::perMinutes(
            config('security.login_throttle.decay_seconds') / 60,
            config('security.login_throttle.max_attempts'),
        )->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn ($request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('token-refresh', fn ($request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by(
            $request->user()?->id ?? $request->ip()
        ));

        // CLAUDE.md §14: the AI (chatbot/agent) endpoint is rate-limited
        // per user/session specifically, distinct from the general API
        // default - to control cost (§19), not just abuse.
        RateLimiter::for('ai-tools', fn ($request) => Limit::perMinute(20)->by($request->user()?->id ?? $request->ip()));

        // The customer-facing chatbot message endpoint - caps request
        // *volume* regardless of whether a given message ends up costing
        // an AI call or not. AiUsageLimiter is the separate, cost-specific
        // circuit breaker (per-conversation and global) on top of this.
        RateLimiter::for('chat', fn ($request) => Limit::perMinute(15)->by($request->user()?->id ?? $request->ip()));
    }
}
