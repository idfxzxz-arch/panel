@extends('layouts.app', ['title' => 'Integrations'])

@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">External Services</p>
        <h1>Integrations</h1>
        <p class="muted">Koneksikan panel dengan layanan pihak ketiga.</p>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-title">GITHUB ACCOUNTS</div>
        <div style="display:flex;gap:15px;margin-bottom:20px">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:40px;height:40px"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
            <div>
                <strong style="display:block;font-size:14px;margin-bottom:4px">GitHub Token</strong>
                <p class="muted" style="margin:0">Akses private repository untuk deployment. Anda dapat menambahkan beberapa akun.</p>
            </div>
        </div>

        @if($githubAccounts->isNotEmpty())
            <div style="margin-bottom: 20px;">
                @foreach($githubAccounts as $account)
                <div class="status-box" style="margin-bottom: 10px;">
                    <div>
                        <strong style="color:#fff">{{ $account->name ?? $account->username }} (@{{ $account->username }})</strong>
                        <p class="muted" style="margin:0">Token valid. {{ $account->projects()->count() }} project(s) menggunakan akun ini.</p>
                    </div>
                    <form method="post" action="{{ route('integrations.github.destroy', $account) }}">
                        @csrf @method('delete')
                        <button class="btn btn-danger" type="submit">DISCONNECT</button>
                    </form>
                </div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('integrations.github') }}">
            @csrf
            <label>Personal Access Token (Classic)
                <input name="token" required placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
            </label>
            <label>Account Name (Optional)
                <input name="name" placeholder="My GitHub Account">
                <p class="muted" style="font-size: 11px; margin-top: 4px;">Nama untuk identifikasi akun (contoh: "Work", "Personal")</p>
            </label>
            <button class="btn btn-primary" style="margin-top:14px">ADD GITHUB ACCOUNT</button>
        </form>
    </section>

    <section class="card">
        <div class="card-title">CLOUDFLARE ACCOUNT</div>
        <div style="display:flex;gap:15px;margin-bottom:20px">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:40px;height:40px;color:#f5a33b"><path d="M22.84 15.68a6.38 6.38 0 0 0-4.38-5.32 6.63 6.63 0 0 0-9.88-5.37 5.86 5.86 0 0 0-7.39 5.37A5.93 5.93 0 0 0 0 16.27a5.86 5.86 0 0 0 5.86 5.86h11.23a6.83 6.83 0 0 0 5.75-6.45z"/></svg>
            <div>
                <strong style="display:block;font-size:14px;margin-bottom:4px">Cloudflare API Token</strong>
                <p class="muted" style="margin:0">Otomatisasi DNS dan Cloudflare Tunnel.</p>
            </div>
        </div>
        @if($cloudflare)
            <div class="status-box">
                <div>
                    <strong style="color:#fff">Connected to {{ $cloudflare->zone_name }}</strong>
                    <p class="muted" style="margin:0">Token valid.</p>
                </div>
                <form method="post" action="{{ route('integrations.cloudflare.destroy') }}">
                    @csrf @method('delete')
                    <button class="btn btn-danger">DISCONNECT</button>
                </form>
            </div>
        @else
            <form method="post" action="{{ route('integrations.cloudflare') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label>Account ID
                            <input name="account_id" required>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Zone ID
                            <input name="zone_id" required>
                        </label>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Zone Name (Domain)
                            <input name="zone_name" required placeholder="example.com">
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Tunnel ID
                            <input name="tunnel_id" required>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>API Token
                        <input type="password" name="api_token" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Tunnel Token <span class="muted">(opsional jika cloudflared sudah berjalan)</span>
                        <input type="password" name="tunnel_token" autocomplete="new-password">
                    </label>
                </div>
                <button class="btn btn-primary" style="margin-top:10px">VERIFY & CONNECT CLOUDFLARE</button>
            </form>
            <p class="hint">API token membutuhkan Zone DNS Read/Write dan Account Cloudflare Tunnel Read/Edit. Tunnel harus bertipe remotely-managed.</p>
        @endif
    </section>
</div>

<section class="card flow">
    <div><strong>1</strong><span>CONNECT PROVIDERS</span></div><i>→</i>
    <div><strong>2</strong><span>SELECT REPOSITORY</span></div><i>→</i>
    <div><strong>3</strong><span>CHOOSE SUBDOMAIN</span></div><i>→</i>
    <div><strong>4</strong><span>AUTO DNS + TUNNEL</span></div>
</section>

<style>
    .integration-grid{display:grid;grid-template-columns:1fr 1.4fr;gap:14px}.provider-head{display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--line);padding-bottom:15px;margin-bottom:16px}.provider-head>div{flex:1}.provider-head h2{margin:0;font-size:15px}.provider-head p{margin:3px 0 0;color:var(--muted);font-size:10px}.provider{display:grid;place-items:center;width:38px;height:38px;border:1px solid var(--line2);font-weight:800}.provider.github{background:#1d222b}.provider.cloudflare{background:#371d0b;color:#f5a33b;border-color:#71401e}.disconnect-form{margin-bottom:16px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}.hint{font-size:9px;color:#637185;margin-top:16px}.flow{display:flex;align-items:center;justify-content:center;gap:25px;margin-top:14px}.flow div{display:flex;align-items:center;gap:9px}.flow strong{display:grid;place-items:center;width:26px;height:26px;border:1px solid #9f2c39;color:var(--red)}.flow span{font-size:8px;letter-spacing:1px}.flow i{color:#4a5665}@media(max-width:850px){.integration-grid{grid-template-columns:1fr}.flow{align-items:flex-start;flex-direction:column}.flow i{transform:rotate(90deg);margin-left:10px}}@media(max-width:600px){.form-grid{grid-template-columns:1fr}}
</style>
@endsection