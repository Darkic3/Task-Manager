<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\FeatureAvailability;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Return 404 for beta-excluded modules, including direct/API requests. */
class BetaFeatureUnavailable
{
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $feature ??= app(FeatureAvailability::class)->featureForRequest($request);

        if ($feature === null || app(FeatureAvailability::class)->isEnabled($feature, $request->user())) {
            return $next($request);
        }

        abort(404);
    }
}
