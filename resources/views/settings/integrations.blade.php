@extends('layouts.app', ['title' => 'Integrations'])

@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">Provider connections</p>
        <h1>GitHub & Cloudflare</h1>
        <p class="muted">Hubungkan provider sekali, lalu deployment berikutnya cukup memilih repository dan subdomain.</p>
    </div>
</div>

<div class="integration-grid">
    <section class="card">
        <div class="provider-head">
            <span class="provider github">GH</span>
            <div>
                <h2>GitHub</h2>
                <p>{{ $github ? 'Connected as @'.$github->username : 'Not connected' }}</p>
            </div>
            <span class="badge {{ $github ? 'succeeded' : 'stopped' }}">{{ $github ? 'CONNECTED' : 'OFFLINE' }}</span>
        </div>

        @if ($github)
            <p class="muted">Repository public dan private yang dapat diakses token akan muncul di New Deployment.</p>
            <form method="post" action="{{ route('integrations.github.destroy') }}" onsubmit="return confirm('Putuskan GitHub?')">
                @csrf
                @method('delete')
                <button class="danger">DISCONNECT</button>
            </form>
        @else
            <form method="post" action="{{ route('integrations.github') }}">
                @csrf
                <label>Fine-grained Personal Access Token
                    <input type="password" name="token" required autocomplete="new-password" placeholder="github_pat_...">
                </label>
                <button>CONNECT GITHUB</button>
            </form>
            <p class="hint">Buat fine-grained token dengan akses read-only Contents dan Metadata hanya untuk repository yang akan di-host.</p>
        @endif
    </section>

    <section class="card">
        <div class="provider-head">
            <span class="provider cloudflare">CF</span>
            <div>
                <h2>Cloudflare Zero Trust</h2>
                <p>{{ $cloudflare ? $cloudflare->zone_name : 'Not connected' }}</p>
            </div>
            <span class="badge {{ $cloudflare ? 'succeeded' : 'stopped' }}">{{ $cloudflare ? 'CONNECTED' : 'OFFLINE' }}</span>
        </div>

        @if ($cloudflare)
            <form method="post" action="{{ route('integrations.cloudflare.destroy') }}" class="disconnect-form" onsubmit="return confirm('Putuskan Cloudflare? Connector lokal akan dihentikan, tetapi DNS yang sudah ada tetap dipertahankan.')">
                @csrf
                @method('delete')
                <button class="danger">DISCONNECT CLOUDFLARE</button>
            </form>
        @endif

        <form method="post" action="{{ route('integrations.cloudflare') }}">
            @csrf
            <div class="form-grid">
                <label>Account ID<input name="account_id" value="{{ $cloudflare?->account_id }}" required maxlength="32"></label>
                <label>Zone ID<input name="zone_id" value="{{ $cloudflare?->zone_id }}" required maxlength="32"></label>
                <label>Tunnel UUID<input name="tunnel_id" value="{{ $cloudflare?->tunnel_id }}" required></label>
                <label>Base domain<input name="zone_name" value="{{ $cloudflare?->zone_name ?? 'idkxz.my.id' }}" required></label>
            </div>
            <label>API Token @if ($cloudflare)<span class="muted">(masukkan kembali untuk memperbarui)</span>@endif
                <input type="password" name="api_token" required autocomplete="new-password" placeholder="DNS Write + Cloudflare Tunnel Edit">
            </label>
            <label>Tunnel Token <span class="muted">(opsional jika cloudflared sudah berjalan)</span>
                <input type="password" name="tunnel_token" autocomplete="new-password">
            </label>
            <button>VERIFY & CONNECT CLOUDFLARE</button>
        </form>
        <p class="hint">API token membutuhkan Zone DNS Read/Write dan Account Cloudflare Tunnel Read/Edit. Tunnel harus bertipe remotely-managed.</p>
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
