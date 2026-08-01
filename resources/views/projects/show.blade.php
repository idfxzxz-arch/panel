@extends('layouts.app', ['title' => $project->name])

@section('content')
<div class="page-head">
    <div class="page-head-left">
        <p class="eyebrow">Application Detail</p>
        <h1>{{ $project->name }}</h1>
        <p class="subtitle">{{ $project->repository ?: 'Official WordPress image' }} · {{ $project->branch }}</p>
    </div>
    <span class="badge badge-{{ $project->status }}">{{ $project->status }}</span>
</div>

{{-- Operations --}}
<div class="card">
    <div class="section-title">OPERATIONS</div>
    <div class="actions">
        <form method="post" action="{{ route('projects.deploy', $project) }}">
            @csrf
            <button class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                DEPLOY AGAIN
            </button>
        </form>
        @foreach(['start', 'stop', 'restart'] as $action)
        <form method="post" action="{{ route('projects.lifecycle', [$project, $action]) }}">
            @csrf
            <button class="btn {{ $action === 'stop' ? 'btn-danger' : 'btn-secondary' }}">
                @if($action === 'start')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                @elseif($action === 'stop')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                @endif
                {{ strtoupper($action) }}
            </button>
        </form>
        @endforeach
        <a class="btn btn-secondary" href="{{ route('projects.logs', $project) }}" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            SERVER LOGS
        </a>
        @if($project->primaryDomain)
        <a class="btn btn-secondary" href="https://{{ $project->primaryDomain->domain }}" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
            OPEN SITE ↗
        </a>
        @endif
        <form method="post" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Hapus project ini? Source lokal, DNS Cloudflare, dan Tunnel ingress juga akan dihapus.')">
            @csrf @method('delete')
            <button class="btn btn-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                DELETE
            </button>
        </form>
    </div>
</div>

{{-- Configuration & Webhook --}}
<div class="grid grid-2">
    <div class="card">
        <div class="section-title">CONFIGURATION</div>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Port</span>
                <span class="detail-value">{{ $project->port ?? '—' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Primary Domain</span>
                <span class="detail-value">{{ $project->primaryDomain?->domain ?? 'Not configured' }}</span>
            </div>
            <div class="detail-item" style="grid-column: 1/-1">
                <span class="detail-label">Workspace</span>
                <code>{{ $project->path() }}</code>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">GITHUB WEBHOOK</div>
        @if($project->webhook)
        <p class="muted" style="font-size:10px;margin-bottom:10px">Content type: application/json · event: push</p>
        <code style="word-break:break-all;display:block;margin-bottom:12px">{{ route('webhooks.github', $project->webhook->uuid) }}</code>
        @if(session('webhook_secret'))
        <div class="secret-reveal">
            <strong style="font-size:10px;color:var(--amber)">⚠ Secret (ditampilkan sekali)</strong>
            <code style="word-break:break-all;display:block;margin-top:4px">{{ session('webhook_secret') }}</code>
        </div>
        @else
        <p class="muted" style="font-size:10px">Secret tersimpan terenkripsi dan tidak ditampilkan kembali.</p>
        @endif
        <form method="post" action="{{ route('projects.webhook.rotate', $project) }}" style="margin-top:12px" onsubmit="return confirm('Secret lama akan langsung tidak berlaku. Lanjutkan?')">
            @csrf
            <button class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                ROTATE SECRET
            </button>
        </form>
        @else
        <p class="muted" style="font-size:10px">Webhook belum dikonfigurasi untuk project ini.</p>
        @endif
    </div>
</div>

{{-- Environment Variables --}}
<div class="card">
    <div class="section-title">ENVIRONMENT VARIABLES</div>
    <p class="muted" style="font-size:10px;margin-bottom:16px">Isi hingga 3 variable sekaligus. Value terenkripsi dan tidak ditampilkan kembali. Key yang sama akan diperbarui.</p>
    <form method="post" action="{{ route('projects.environment.store', $project) }}">
        @csrf
        @for($i = 0; $i < 3; $i++)
        <div class="env-variable-row">
            <div class="env-grid">
                <div class="form-group">
                    <label>Key {{ $i + 1 }}</label>
                    <input name="variables[{{ $i }}][key]" value="{{ old("variables.$i.key") }}" placeholder="{{ $i === 0 ? 'APP_NAME' : 'VARIABLE_NAME' }}">
                </div>
                <div class="form-group">
                    <label>Value {{ $i + 1 }}</label>
                    <input type="password" name="variables[{{ $i }}][value]" autocomplete="new-password">
                </div>
            </div>
            <label class="checkbox-label">
                <input style="display:inline;width:auto" type="checkbox" name="variables[{{ $i }}][is_build_time]" value="1" @checked(old("variables.$i.is_build_time"))>
                Build-time (mis. VITE_*)
            </label>
        </div>
        @endfor
        <button class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
            SAVE VARIABLES
        </button>
    </form>

    <div class="table-wrap" style="margin-top:20px">
        <table>
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Scope</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($project->environmentVariables as $variable)
                <tr>
                    <td><code>{{ $variable->key }}</code></td>
                    <td style="color:var(--muted2)">••••••••</td>
                    <td>
                        <span class="badge {{ $variable->is_build_time ? 'badge-pending' : 'badge-running' }}">
                            {{ $variable->is_build_time ? 'BUILD' : 'RUNTIME' }}
                        </span>
                    </td>
                    <td>
                        <form method="post" action="{{ route('projects.environment.destroy', [$project, $variable]) }}">
                            @csrf @method('delete')
                            <button class="btn btn-danger btn-sm">REMOVE</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="muted">Belum ada variable.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Backups --}}
<div class="card">
    <div class="section-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>BACKUPS</span>
        <form method="post" action="{{ route('projects.backups.store', $project) }}" style="margin:0">
            @csrf
            <button class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="M12 5v14M5 12h14"/></svg>
                CREATE BACKUP
            </button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($project->backups->take(5) as $backup)
                <tr>
                    <td>{{ $backup->id }}</td>
                    <td><code>{{ $backup->filename }}</code></td>
                    <td>{{ format_bytes($backup->size) }}</td>
                    <td><span class="badge badge-{{ $backup->status }}">{{ $backup->status }}</span></td>
                    <td>{{ $backup->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('projects.backups.download', [$project, $backup]) }}" class="btn btn-secondary btn-sm">DOWNLOAD</a>
                            <form method="post" action="{{ route('projects.backups.restore', [$project, $backup]) }}" onsubmit="return confirm('Restore backup ini? Data project saat ini akan ditimpa.')">
                                @csrf
                                <button class="btn btn-secondary btn-sm">RESTORE</button>
                            </form>
                            <form method="post" action="{{ route('projects.backups.destroy', [$project, $backup]) }}" onsubmit="return confirm('Hapus backup ini?')">
                                @csrf @method('delete')
                                <button class="btn btn-danger btn-sm">DELETE</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="muted">Belum ada backup.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($project->backups->count() > 5)
    <div style="margin-top:15px;padding-top:15px;border-top:1px solid var(--line)">
        <a href="{{ route('projects.backups.index', $project) }}" class="btn btn-secondary btn-sm">VIEW ALL BACKUPS</a>
    </div>
    @endif
</div>

{{-- Deployment History --}}
<div class="card">
    <div class="section-title">DEPLOYMENT HISTORY</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Trigger</th>
                    <th>Status</th>
                    <th>Commit</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                @forelse($project->deployments as $d)
                <tr>
                    <td><a href="{{ route('deployments.show', $d) }}" style="color:var(--red);font-weight:600">#{{ $d->id }}</a></td>
                    <td>{{ strtoupper($d->trigger) }}</td>
                    <td><span class="badge badge-{{ $d->status }}">{{ $d->status }}</span></td>
                    <td><code>{{ $d->commit_sha ? substr($d->commit_sha, 0, 8) : '—' }}</code></td>
                    <td>{{ $d->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="muted">Belum ada deploy.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .detail-label {
        font-size: 9px;
        letter-spacing: 1px;
        color: var(--muted);
        text-transform: uppercase;
        font-weight: 700;
    }
    .detail-value {
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
    }
    .env-variable-row {
        padding: 0 0 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid var(--line);
    }
    .env-variable-row:last-of-type {
        margin-bottom: 20px;
    }
    .env-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .checkbox-label {
        font-weight: 400;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        font-size: 11px;
        color: var(--muted);
    }
    .secret-reveal {
        padding: 10px;
        border: 1px solid rgba(245,166,35,0.3);
        background: rgba(245,166,35,0.06);
        border-radius: var(--radius-sm);
        margin-bottom: 8px;
    }
    @media(max-width: 768px) {
        .detail-grid, .env-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection
