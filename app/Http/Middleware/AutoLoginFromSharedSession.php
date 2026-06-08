<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginFromSharedSession
{
    /**
     * The session cookies from other AMIS portals to check for SSO.
     */
    protected array $portalCookies = [
        'amis_admin_session',
        'amis_student_session',
        'amis_teacher_session',
        'amis_enrollment_session',
    ];

    /**
     * Handle an incoming request.
     * Auto-login the user if they have a valid session from another AMIS portal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Already logged in — nothing to do
        if (Auth::check()) {
            return $next($request);
        }

        foreach ($this->portalCookies as $cookieName) {
            // $request->cookie() returns the already-decrypted value
            // because Laravel's EncryptCookies middleware runs before us
            $sessionId = $request->cookie($cookieName);

            if (!$sessionId) {
                // Fallback: try raw cookie bag in case it's unencrypted
                $rawCookie = $request->cookies->get($cookieName);
                $sessionId = $rawCookie ? $this->decryptCookie($rawCookie) : null;
            }

            if (!$sessionId) {
                continue;
            }

            // Look up the session in the shared sessions table
            $session = DB::table('sessions')
                ->where('id', $sessionId)
                ->whereNotNull('user_id')
                ->first();

            if ($session && $session->user_id) {
                Auth::loginUsingId((int) $session->user_id, remember: false);

                Log::info('[AMIS SSO] Auto-logged in user via shared session', [
                    'user_id'     => $session->user_id,
                    'from_cookie' => $cookieName,
                ]);

                break;
            }
        }

        return $next($request);
    }

    /**
     * Decrypt a Laravel-encrypted session cookie.
     * Returns null if decryption fails.
     */
    protected function decryptCookie(string $rawCookie): ?string
    {
        try {
            // Laravel uses Crypt facade — decrypt() handles the JSON/serialization
            return decrypt($rawCookie, unserialize: false);
        } catch (\Throwable) {
            // Cookie may already be a plain session ID (when SESSION_ENCRYPT=false)
            // Try using it directly if it looks like a valid session ID (40 hex chars)
            if (preg_match('/^[a-zA-Z0-9]{20,128}$/', $rawCookie)) {
                return $rawCookie;
            }
            return null;
        }
    }
}
