<?php

namespace App\Http\Controllers;

use App\Services\CloudflareConnectorService;
use App\Services\CloudflareService;
use App\Services\GithubService;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index()
    {
        return view('settings.integrations', [
            'github' => auth()->user()->githubAccount,
            'cloudflare' => auth()->user()->cloudflareIntegration,
        ]);
    }

    public function github(Request $request, GithubService $github)
    {
        $data = $request->validate(['token' => ['required', 'string', 'min:20', 'max:255']]);
        try {
            $identity = $github->identity($data['token']);
            $request->user()->githubAccount()->updateOrCreate([], [
                'username' => $identity['login'], 'token' => $data['token'],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['github' => 'Token GitHub tidak valid atau API tidak dapat dihubungi.']);
        }

        return back()->with('success', 'GitHub terhubung sebagai '.$identity['login'].'.');
    }

    public function disconnectGithub(Request $request)
    {
        $request->user()->githubAccount()->delete();

        return back()->with('success', 'Koneksi GitHub dihapus.');
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
        try {
            $cloudflare->verify($data);
            $integration = $request->user()->cloudflareIntegration()->updateOrCreate([], $data + ['verified_at' => now()]);
            if (! $integration->tunnel_token) {
                $integration->update(['tunnel_token' => $cloudflare->connectorToken($integration)]);
            }
            $connector->restart($integration);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['cloudflare' => 'Cloudflare gagal diverifikasi: '.$exception->getMessage()]);
        }

        return back()->with('success', 'Cloudflare '.$integration->zone_name.' dan Tunnel berhasil dihubungkan.');
    }

    public function disconnectCloudflare(Request $request, CloudflareConnectorService $connector)
    {
        $connector->disconnect();
        $request->user()->cloudflareIntegration()->delete();

        return back()->with('success', 'Cloudflare berhasil diputus. DNS yang sudah dibuat tetap dipertahankan.');
    }
}
