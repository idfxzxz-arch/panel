<?php

namespace App\Services;

use App\Models\GithubAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GithubService
{
    private const API = 'https://api.github.com';

    public function identity(string $token): array
    {
        return Http::withToken($token)->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15)->get(self::API.'/user')->throw()->json();
    }

    public function repositories(GithubAccount $account): Collection
    {
        return collect(Http::withToken($account->token)->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(20)->get(self::API.'/user/repos', [
                'per_page' => 100, 'sort' => 'updated', 'affiliation' => 'owner,collaborator,organization_member',
            ])->throw()->json())->map(fn ($repo) => [
                'id' => $repo['id'], 'full_name' => $repo['full_name'], 'clone_url' => $repo['clone_url'],
                'default_branch' => $repo['default_branch'] ?: 'main', 'private' => (bool) $repo['private'],
            ]);
    }
}
