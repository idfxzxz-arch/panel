@extends('layouts.app',['title'=>'New Deployment'])
@section('content')
<div class="page-head"><div><p class="eyebrow">Deployment pipeline</p><h1>New Deployment</h1><p class="muted">Pilih repository, runtime, dan domain. Harbor menangani clone, build, routing, DNS, Tunnel, dan SSL.</p></div><a class="btn btn-secondary" href="{{ route('integrations.index') }}">⚙ INTEGRATIONS</a></div>
@if($githubError)<div class="flash error">{{ $githubError }}</div>@endif
<div class="steps"><span class="active"><b>1</b> SOURCE</span><i></i><span><b>2</b> CONFIGURE</span><i></i><span><b>3</b> DEPLOY</span></div>
<form class="card deployment-form" method="post" action="{{ route('projects.store') }}">@csrf
    <div class="section-title">SOURCE PROVIDER</div>

    @if($githubAccounts->isNotEmpty())
    <div class="github-account-selector" style="margin-bottom: 15px;">
        <label>GitHub Account
            <select id="github_account_id" name="github_account_id">
                <option value="">Select GitHub Account</option>
                @foreach($githubAccounts as $account)
                <option value="{{ $account->id }}" data-username="{{ $account->username }}" data-name="{{ $account->name ?? $account->username }}" @selected(($selectedGithubAccount?->id ?? null) == $account->id)>
                    {{ $account->name ?? $account->username }} (@{{ $account->username }})
                </option>
                @endforeach
            </select>
        </label>
    </div>
    @endif

    @if($selectedGithubAccount && $repositories->isNotEmpty())
    <label>GitHub Repository<select id="repository" name="repository" required><option value="">SELECT A REPOSITORY</option>@foreach($repositories as $repo)<option value="{{ $repo['clone_url'] }}" data-name="{{ basename($repo['full_name']) }}" data-branch="{{ $repo['default_branch'] }}" @selected(old('repository')===$repo['clone_url'])>{{ $repo['private'] ? '🔒' : '◉' }} {{ $repo['full_name'] }}</option>@endforeach</select></label>
    <div class="connected">● Connected as {{ '@'.$selectedGithubAccount->username }} · {{ $repositories->count() }} repositories available</div>
    @elseif($githubAccounts->isNotEmpty())
    <div class="connect-empty"><strong>Repository tidak dapat dimuat</strong><p>Pilih akun GitHub terlebih dahulu atau perbarui token di Integrations.</p></div>
    <label>Repository URL (fallback)<input id="repository" type="url" name="repository" value="{{ old('repository') }}" required placeholder="https://github.com/owner/repository.git"></label>
    @else
    <div class="connect-empty"><strong>GitHub belum terhubung</strong><p>Hubungkan fine-grained token agar repository dapat dipilih langsung.</p><a class="btn btn-primary" href="{{ route('integrations.index') }}">CONNECT GITHUB</a></div>
    <label>Repository URL (fallback)<input id="repository" type="url" name="repository" value="{{ old('repository') }}" required placeholder="https://github.com/owner/repository.git"></label>
    @endif

    <div class="section-title">APPLICATION</div>
    <div class="form-grid"><label>Project name<input id="name" name="name" value="{{ old('name') }}" required placeholder="My application"></label><label>Project slug<input id="slug" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9-]+" placeholder="my-application"></label><label>Branch<input id="branch" name="branch" value="{{ old('branch','main') }}" required></label></div>
    <div class="section-title">EDGE & DOMAIN</div>
    @if($cloudflare)
    <div class="domain-mode"><label><input type="radio" name="domain_mode" value="subdomain" @checked(old('domain_mode','subdomain') !== 'custom')> Subdomain Cloudflare</label><label><input type="radio" name="domain_mode" value="custom" @checked(old('domain_mode') === 'custom')> Domain sendiri</label></div>
    <label id="subdomain-field">Subdomain<div class="domain-input"><input name="subdomain" value="{{ old('subdomain') }}" placeholder="app"><span>.{{ $cloudflare->zone_name }}</span></div></label>
    <label id="custom-domain-field">Full domain<input name="domain" value="{{ old('domain') }}" placeholder="www.domainkamu.com"></label>
    <div class="edge-ready"><span>✓ CLOUDFLARE CONNECTED</span><small>Subdomain {{ $cloudflare->zone_name }} dibuatkan DNS otomatis. Domain sendiri dibuatkan routing Tunnel; arahkan DNS domain ke tunnel/edge server.</small></div>
    @else
    <label>Full domain<input name="domain" value="{{ old('domain') }}" required placeholder="app.idkxz.my.id"></label><div class="edge-warning">Cloudflare belum terhubung. Domain disimpan, tetapi DNS tidak dibuat otomatis. <a href="{{ route('integrations.index') }}">Connect now</a></div>
    @endif
    <div class="deploy-submit"><div><strong>Ready to deploy</strong><small>Deployment berjalan di background queue dan log tersedia real-time.</small></div><button class="btn btn-primary">DEPLOY APPLICATION →</button></div>
</form>
<style>.steps{display:flex;align-items:center;max-width:560px;margin:20px 0}.steps span{display:flex;align-items:center;gap:7px;color:#536173;font-size:9px;letter-spacing:1px}.steps span.active{color:#fff}.steps b{display:grid;place-items:center;width:23px;height:23px;border:1px solid #394453}.steps .active b{border-color:var(--red);color:var(--red)}.steps i{height:1px;background:#29313d;flex:1;margin:0 12px}.deployment-form{max-width:920px}.section-title{font-size:9px;letter-spacing:1.4px;color:var(--red);padding:8px 0 12px;margin:10px 0 16px;border-bottom:1px solid var(--line)}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.connected{font-size:9px;color:var(--green);margin:-7px 0 18px}.connect-empty{padding:20px;border:1px dashed #394453;text-align:center;margin-bottom:15px}.connect-empty p{color:var(--muted)}.runtime-note{display:none;margin:0 0 18px;padding:11px;border:1px solid #27563f;background:#0d2119;color:#8fd9b6;font-size:10px}.runtime-note.active{display:block}.domain-mode{display:flex;gap:8px;margin:0 0 13px}.domain-mode label{display:flex;align-items:center;gap:7px;padding:9px 11px;border:1px solid var(--line2);background:#090d14}.domain-mode input{width:auto;margin:0}.domain-input{display:flex;margin:6px 0 14px}.domain-input input{margin:0;border-radius:3px 0 0 3px}.domain-input span{display:flex;align-items:center;padding:0 13px;background:#171e28;border:1px solid var(--line2);border-left:0;color:#95a2b3;white-space:nowrap}.edge-ready,.edge-warning{display:flex;justify-content:space-between;gap:12px;padding:11px;border:1px solid #245f48;background:#0d281e;color:var(--green);font-size:9px}.edge-ready small{color:#729b89;text-align:right}.edge-warning{border-color:#705628;background:#2b2110;color:var(--amber)}.deploy-submit{display:flex;justify-content:space-between;align-items:center;margin-top:25px;padding-top:18px;border-top:1px solid var(--line)}.deploy-submit strong,.deploy-submit small{display:block}.deploy-submit small{color:var(--muted);font-size:9px;margin-top:3px}.github-account-selector label{display:block;margin-bottom:8px}.github-account-selector select{width:100%;padding:8px;border:1px solid var(--line2);background:#090d14;color:#fff;border-radius:3px}@media(max-width:650px){.form-grid{grid-template-columns:1fr}.domain-mode,.deploy-submit,.edge-ready{align-items:stretch;flex-direction:column;gap:12px}.edge-ready small{text-align:left}}
</style>
<script>document.addEventListener('DOMContentLoaded',()=>{
    const repo=document.getElementById('repository');
    const subdomainField=document.getElementById('subdomain-field');
    const customDomainField=document.getElementById('custom-domain-field');
    const githubAccountSelect = document.getElementById('github_account_id');

    const syncDomain=()=>{
        const mode=document.querySelector('input[name="domain_mode"]:checked')?.value||'subdomain';
        const subInput=subdomainField?.querySelector('input');
        const domainInput=customDomainField?.querySelector('input');
        if(subdomainField)subdomainField.style.display=mode==='subdomain'?'block':'none';
        if(customDomainField)customDomainField.style.display=mode==='custom'?'block':'none';
        if(subInput)subInput.required=mode==='subdomain';
        if(domainInput)domainInput.required=mode==='custom';
    };

    // Load repositories when GitHub account is changed
    const loadRepositories = async (accountId) => {
        if (!accountId) return;

        const connectedDiv = document.querySelector('.connected');
        const repoSelect = document.getElementById('repository');

        if (connectedDiv) {
            const accountOption = githubAccountSelect?.selectedOptions[0];
            if (accountOption) {
                const username = accountOption.dataset.username;
                connectedDiv.innerHTML = `● Loading repositories for @${username}...`;
            }
        }

        try {
            const response = await fetch('/projects/create/repositories/' + accountId);
            const data = await response.json();

            if (data.error) {
                if (connectedDiv) connectedDiv.innerHTML = `● Error: ${data.error}`;
                return;
            }

            // Update repository select
            if (repoSelect && repoSelect.tagName === 'SELECT') {
                let options = '<option value="">SELECT A REPOSITORY</option>';
                data.repositories.forEach(repo => {
                    const isPrivate = repo.private ? '🔒' : '◉';
                    options += `<option value="${repo.clone_url}" data-name="${basename(repo.full_name)}" data-branch="${repo.default_branch}">${isPrivate} ${repo.full_name}</option>`;
                });
                repoSelect.innerHTML = options;

                if (connectedDiv) {
                    connectedDiv.innerHTML = `● Connected as @${data.username} · ${data.repositories.length} repositories available`;
                }
            }
        } catch (error) {
            if (connectedDiv) connectedDiv.innerHTML = `● Error loading repositories`;
            console.error('Error loading repositories:', error);
        }
    };

    // Helper function to get basename
    function basename(path) {
        return path.split('/').pop();
    }

    if(githubAccountSelect){
        githubAccountSelect.addEventListener('change', function() {
            const accountId = this.value;
            loadRepositories(accountId);
        });
    }

    if(repo&&repo.tagName==='SELECT'){
        repo.addEventListener('change',()=>{
            const option=repo.selectedOptions[0];
            const raw=option.dataset.name||'';
            if(!raw)return;
            document.getElementById('name').value=raw.replace(/[-_]/g,' ');
            document.getElementById('slug').value=raw.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
            document.getElementById('branch').value=option.dataset.branch||'main';
        });
    }

    document.querySelectorAll('input[name="domain_mode"]').forEach(input=>input.addEventListener('change',syncDomain));
    syncDomain();
});
</script>
@endsection