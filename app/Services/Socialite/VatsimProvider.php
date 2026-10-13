<?php

namespace App\Services\Socialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class VatsimProvider extends AbstractProvider
{
    protected $scopes = ['full_name', 'email', 'vatsim_details'];

    protected $scopeSeparator = ' ';

    protected $BASE_URL;

    public function __construct(Request $request, $clientId, $clientSecret, $redirectUrl, $guzzle)
    {
        $this->BASE_URL = config('app.vatsim_auth_url');

        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
    }

    public function getAuthUrl($string): string
    {
        return $this->buildAuthUrlFromBase($this->BASE_URL.'/oauth/authorize', $string);
    }

    protected function getTokenUrl(): string
    {
        return $this->BASE_URL.'/oauth/token';
    }

    protected function getUserByToken($token): mixed
    {
        $url = $this->BASE_URL.'/api/user';

        $response = $this->getHttpClient()->get($url, [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        if (! $user) {
            throw new AuthorizationException('Unable to authenticate user by token.');
        }

        $user = $user['data']; // vatsim stored user data in an array called data

        // This does not update the database, that is done in the VatsimOauthController using this object.
        $newUser = (new User);
        $newUser->map([
            'cid' => $user['cid'],
            'first_name' => $user['personal']['name_first'],
            'last_name' => $user['personal']['name_last'],
            'email' => $user['personal']['email'],
            'rating' => $user['vatsim']['rating']['id'],
            'division' => $user['vatsim']['division']['id'] ?? null,
            // VATUSA controllers often have a null VATSIM subdivision; guard the access.
            // Note: `facility` is not persisted at login (see VatsimOauthController) — the
            // VATUSA roster sync owns it.
            'facility' => $user['vatsim']['subdivision']['id'] ?? null,
        ]);

        return $newUser;
    }
}
