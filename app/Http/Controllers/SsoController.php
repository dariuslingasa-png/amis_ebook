<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    /**
     * Called by other AMIS portals to generate a one-time SSO token.
     * This endpoint is protected by a shared secret key.
     *
     * POST /sso/token
     * Headers: X-SSO-Secret: <shared_secret>
     * Body: { "user_id": 1 }
     */
    public function issueToken(Request $request)
    {
        // Validate the shared secret
        $secret = config('app.sso_secret');
        if (!$secret || $request->header('X-SSO-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        // Clean up expired tokens first
        DB::table('sso_tokens')->where('expires_at', '<', now())->delete();

        // Create a new one-time token (expires in 30 seconds)
        $token = Str::random(64);
        DB::table('sso_tokens')->insert([
            'token'         => $token,
            'user_id'       => $request->user_id,
            'source_portal' => $request->input('source', 'amis_admin'),
            'expires_at'    => now()->addSeconds(30),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['token' => $token]);
    }

    /**
     * The redirect target — amis_admin links here with ?sso_token=xxx
     * Validates the token, logs the user in, then redirects to the requested local path.
     *
     * GET /sso/login?sso_token=xxx&redirect=/admin/books
     */
    public function loginWithToken(Request $request)
    {
        $token = $request->query('sso_token');
        $redirectTo = $this->safeRedirectPath((string) $request->query('redirect', '/books'));

        if (!$token) {
            return redirect()->route('login')->withErrors(['sso' => 'Missing SSO token.']);
        }

        // Find valid, unused, unexpired token
        $ssoToken = DB::table('sso_tokens')
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$ssoToken) {
            Log::warning('[AMIS SSO] Invalid or expired token attempt', ['token_prefix' => substr($token, 0, 8)]);
            return redirect()->route('login')->withErrors(['sso' => 'Invalid or expired SSO link. Please try again.']);
        }

        // Mark token as used (one-time only)
        DB::table('sso_tokens')
            ->where('token', $token)
            ->update(['used_at' => now(), 'updated_at' => now()]);

        // Log the user in
        $user = User::find($ssoToken->user_id);
        if (!$user) {
            return redirect()->route('login')->withErrors(['sso' => 'User not found.']);
        }

        Auth::login($user, remember: false);

        Log::info('[AMIS SSO] User auto-logged in via SSO token', [
            'user_id'       => $user->id,
            'user_role'     => $user->role,
            'source_portal' => $ssoToken->source_portal,
        ]);

        $request->session()->forget('url.intended');

        return redirect()->to($redirectTo);
    }

    private function safeRedirectPath(string $redirectTo): string
    {
        if (! str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            return '/books';
        }

        return $redirectTo;
    }
}
