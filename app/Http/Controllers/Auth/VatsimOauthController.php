<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class VatsimOauthController extends Controller
{
    public function redirect()
    {
        // Preserve whatever page the user was on before leaving for VATSIM Connect, unless
        // the auth middleware already captured the actual protected route they were after.
        // Never let an auth route, or an off-site URL, become the stored destination.
        if (! session()->has('url.intended')) {
            $previous = url()->previous();
            $parsed = parse_url($previous);
            $path = $parsed['path'] ?? '';
            $sameHost = ! isset($parsed['host']) || $parsed['host'] === request()->getHost();

            if ($sameHost && ! str_contains($path, '/auth/') && ! str_contains($path, '/login')) {
                session(['url.intended' => $previous]);
            }
        }

        return Socialite::driver('vatsim')->redirect();
    }

    public function callback()
    {
        $user = Socialite::driver('vatsim')->user();

        $existing = User::find($user->cid);

        // NOTE: `facility` (the ARTCC) is intentionally NOT written here. It is owned
        // by the VATUSA roster sync (SyncRoster / User::updateFromVatusa), which is the
        // authoritative source. VATSIM's subdivision is frequently null for VATUSA
        // controllers, so updating facility at login previously blanked/clobbered it and
        // made rostered users render as visitors (issue #59).
        //
        // VATSIM Connect has no concept of VATUSA's name-privacy flag, so it always
        // reports the real last name. Only the roster sync knows about that flag, so
        // keep a last name it has already redacted to the CID rather than overwriting
        // it here — otherwise every login would undo the redaction until the next sync
        // fixes it back. `last_name` must still be present in every upsert call because
        // it is a NOT NULL column with no default.
        $isRedacted = $existing && $existing->last_name === (string) $existing->id;

        User::upsert([
            'id' => $user->cid,
            'first_name' => $user->first_name,
            'last_name' => $isRedacted ? $existing->last_name : $user->last_name,
            'email' => $user->email,
            'division' => $user->division,
            'facility' => $user->facility ?? $existing?->facility,
            'rating' => $user->rating,
        ], 'id');

        Auth::login(User::find($user->cid));

        return redirect()->intended(route('home'));
    }

    public function logout()
    {
        Auth::logout();
        session()->forget('url.intended');

        return redirect()->route('home');
    }
}
