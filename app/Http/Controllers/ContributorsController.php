<?php

namespace App\Http\Controllers;

use App\Models\ManualContributor;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ContributorsController extends Controller
{
    private function fetchGithubUser(string $login): ?array
    {
        return Cache::remember("github_user_{$login}", 3600, function () use ($login) {
            $r = Http::withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("https://api.github.com/users/{$login}");

            return $r->ok() ? $r->json() : null;
        });
    }

    /**
     * Prime the per-user profile cache with a single concurrent batch so the
     * per-contributor fetchGithubUser() calls don't block on serial round-trips
     * on a cold cache. Uses the same cache key/TTL that fetchGithubUser() reads.
     */
    private function warmGithubUsers(array $logins): void
    {
        $toFetch = collect($logins)
            ->filter()
            ->unique()
            ->reject(fn ($login) => Cache::has("github_user_{$login}"))
            ->values();

        if ($toFetch->isEmpty()) {
            return;
        }

        $responses = Http::pool(fn (Pool $pool) => $toFetch
            ->map(fn ($login) => $pool->as($login)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get("https://api.github.com/users/{$login}"))
            ->all());

        foreach ($toFetch as $login) {
            $response = $responses[$login] ?? null;
            $profile = ($response instanceof Response && $response->ok()) ? $response->json() : null;
            Cache::put("github_user_{$login}", $profile, 3600);
        }
    }

    private function mapManual(ManualContributor $m): array
    {
        $profile = $m->github_username ? $this->fetchGithubUser($m->github_username) : null;

        return [
            'login' => $m->github_username,
            'display_name' => $m->display_name ?? $profile['name'] ?? $m->github_username ?? 'Unknown',
            'html_url' => $m->github_username ? "https://github.com/{$m->github_username}" : null,
            'contributions' => null,
            'note' => $m->note,
        ];
    }

    public function index()
    {
        // Cache only successful responses. A transient GitHub failure returns an
        // empty collection for this request but is not cached, so the next
        // request retries rather than serving an empty list for a full hour.
        $apiContributors = Cache::get('github_contributors');
        if ($apiContributors === null) {
            $response = Http::withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/repos/zjx-artcc/site-laravel/contributors', ['per_page' => 100]);

            if ($response->ok()) {
                $apiContributors = collect($response->json())->reject(fn ($c) => ($c['type'] ?? '') === 'Bot');
                Cache::put('github_contributors', $apiContributors, 3600);
            } else {
                $apiContributors = collect();
            }
        }

        $manualLogins = ManualContributor::pluck('github_username');
        $mainLogins = ManualContributor::where('section', 'main')->pluck('github_username');

        // Warm every profile lookup we're about to make in one concurrent batch.
        $this->warmGithubUsers(
            $apiContributors->pluck('login')
                ->merge(ManualContributor::whereNotNull('github_username')->pluck('github_username'))
                ->all()
        );

        $main = $apiContributors->map(function ($c) {
            $profile = $this->fetchGithubUser($c['login']);

            return [
                'login' => $c['login'],
                'display_name' => $profile['name'] ?? $c['login'],
                'html_url' => $c['html_url'],
                'contributions' => $c['contributions'],
                'note' => null,
            ];
        })->concat(
            ManualContributor::where('section', 'main')->get()->map(fn ($m) => $this->mapManual($m))
        )->reject(fn ($c) => $manualLogins->contains($c['login']) && ! $mainLogins->contains($c['login']));

        $fork = ManualContributor::where('section', 'fork')->get()->map(fn ($m) => $this->mapManual($m));
        $contributor = ManualContributor::where('section', 'contributor')->get()->map(fn ($m) => $this->mapManual($m));
        $beta = ManualContributor::where('section', 'beta')->get()->map(fn ($m) => $this->mapManual($m));

        return view('contributors.index', compact('main', 'fork', 'contributor', 'beta'));
    }
}
