<?php

namespace App\Http\Controllers;

use App\Models\GithubAccount;
use App\Services\CloudflareConnectorService;
use App\Services\CloudflareService;
use App\Services\GithubService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index()
    {
        return view('settings.integrations', [
            'githubAccounts' => auth()->user()->githubAccounts()->orderBy('created_at', 'desc')->get(),
            'cloudflare' => auth()->user()->cloudflareIntegration,
        ]);
    }

    public function github(Request $request, GithubService $github)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $identity = $github->identity($data['token']);
            $account = $request->user()->githubAccounts()->create([
                'username' => $identity['login'],
                'token' => $data['token'],
                'name' => $data['name'] ?? $identity['login'],
            ]);

            return back()->with('success', 'GitHub terhubung sebagai '.$identity['login'].'.');
        } catch (RequestException|ConnectionException $exception) {
            report($exception);

            $msg = $exception instanceof RequestException ? $exception->response->json('message') ?? $exception->getMessage() : $exception->getMessage();

            return back()->withErrors(['github' => 'Token GitHub tidak valid atau API tidak dapat dihubungi: '.$msg]);
        }
    }

    public function disconnectGithub(Request $request, GithubAccount $githubAccount)
    {
        $this->authorize('delete', $githubAccount);

        // Check if any projects are using this github account
        $projectsUsingAccount = $githubAccount->projects()->count();

        if ($projectsUsingAccount > 0) {
            return back()->withErrors(['github' => 'Tidak dapat menghapus akun GitHub karena masih digunakan oleh '.$projectsUsingAccount.' project.']);
        }

        $username = $githubAccount->username;
        $githubAccount->delete();

        return back()->with('success', 'Koneksi GitHub ('.$username.') dihapus.');
    }

    public function cloudflare(Request $request, CloudflareService $cloudflare, CloudflareConnectorService $connector)
    {
        $data = $request->validate([
            'account_id' => ['required', 'regex:/^[a-f0-9]{32}$/'],
            'zone_id' => ['required', 'regex:/^[a-f0-9]{32}$/'],
            'tunnel_id' => ['required', 'uuid'],
            'zone_name' => ['required', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
            'api_token' => ['required', 'string', 'min:20', 'max:255'],
            'tunnel_token' => ['nullable', 'string', 'min:20', 'max:2048'],
        ]);
        $data['api_token'] = CloudflareService::normalizeToken($data['api_token']);
        if (isset($data['tunnel_token'])) {
            $data['tunnel_token'] = trim($data['tunnel_token']);
        }

        try {
            $cloudflare->verify($data);
            $integration = $request->user()->cloudflareIntegration()->updateOrCreate([], $data + ['verified_at' => now()]);
            if (! $integration->tunnel_token) {
                $integration->update(['tunnel_token' => $cloudflare->connectorToken($integration)]);
            }
            $connector->restart($integration);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare' => 'Cloudflare gagal diverifikasi: '.$this->cloudflareErrorMessage($exception)]);
        }

        return back()->with('success', 'Cloudflare '.$integration->zone_name.' dan Tunnel berhasil dihubungkan.');
    }

    private function cloudflareErrorMessage(\Throwable $exception): string
    {
        if (! $exception instanceof RequestException) {
            return $exception->getMessage();
        }

        $response = $exception->response;
        $messages = collect($response->json('errors', []))
            ->pluck('message')
            ->filter()
            ->implode(', ');

        if ($response->status() === 403 && str_contains(strtolower($messages), 'invalid access token')) {
            return 'API Token tidak valid. Paste token saja tanpa awalan "Bearer", jangan gunakan Global API Key, dan pastikan token belum expired.';
        }

        if ($response->status() === 403) {
            return 'API Token tidak punya permission yang cukup. Butuh Zone DNS Read/Edit dan Account Cloudflare Tunnel Read/Edit.';
        }

        return $messages !== '' ? $messages : $exception->getMessage();
    }

    public function disconnectCloudflare(Request $request, CloudflareConnectorService $connector)
    {
        $connector->disconnect();
        $request->user()->cloudflareIntegration()->delete();

        return back()->with('success', 'Cloudflare berhasil diputus. DNS yang sudah dibuat tetap dipertahankan.');
    }
}
