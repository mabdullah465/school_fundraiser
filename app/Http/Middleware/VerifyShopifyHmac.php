<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies X-Shopify-Hmac-Sha256 against the RAW request body.
 * Never re-encode the JSON: a single changed byte invalidates the signature.
 */
class VerifyShopifyHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('shopify.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('SHOPIFY_WEBHOOK_SECRET is not configured; rejecting webhook.');

            return response()->json(['message' => 'Webhook receiver not configured.'], 503);
        }

        $given = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if ($given === '' || ! hash_equals($expected, $given)) {
            Log::warning('Shopify webhook rejected: bad HMAC', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
