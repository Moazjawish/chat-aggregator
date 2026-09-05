<?php
namespace App\Http\Middleware;

use App\Services\FeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureMiddleware
{
    public function __construct(
        private FeatureService $featureService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $featureKey
    ): Response {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->featureService->has(
            $user,
            $featureKey
        )) {
            return response()->json([
                'message' =>
                    'This feature is not available in your subscription plan.',
                'feature' =>
                    $featureKey,
            ], 403);
        }

        return $next($request);
    }
}
