@extends('layouts.app', ['title' => 'Command Center'])

@section('content')
<style>
    .delay-4 { animation-delay: 0.4s; }
    .delay-5 { animation-delay: 0.5s; }
    .delay-6 { animation-delay: 0.6s; }
    .delay-7 { animation-delay: 0.7s; }
    .delay-8 { animation-delay: 0.8s; }
    .delay-9 { animation-delay: 0.9s; }
    .delay-10 { animation-delay: 1.0s; }
    
    /* Interactive Card Hover Glow Overrides */
    .card-interactive {
        transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.3s ease, border-color 0.3s ease !important;
    }
    .card-interactive:hover {
        transform: translateY(-4px) scale(1.01) !important;
        box-shadow: 0 12px 30px -10px rgba(0,0,0,0.8), 0 0 24px rgba(240,56,71,0.08) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
    }
    
    /* Stats pulse animation */
    @keyframes subtlePulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(0.95); }
    }
    .stats-icon-pulse {
        animation: subtlePulse 3s ease-in-out infinite;
    }
    
    /* SVG Map Path Animation */
    .map-path-anim {
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: drawPath 3s ease forwards infinite;
    }
    @keyframes drawPath {
        to { stroke-dashoffset: 0; }
    }
</style>

<div class="page-head animate-fade-in delay-3">
    <div class="page-head-left">
        <p class="eyebrow">Infrastructure Overview</p>
        <h1>Command Center</h1>
        <p class="subtitle">Real-time project and deployment operations across all nodes</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('monitoring.index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M4 19V5M4 19h16M7 15l4-5 3 3 5-7"/></svg>
            View Monitoring
        </a>
        <a class="btn btn-primary" href="{{ route('projects.create') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M12 5v14M5 12h14"/></svg>
            Deploy Project
        </a>
    </div>
</div>

{{-- Stats Overview Row --}}
<div class="grid grid-4" style="margin-bottom:14px">
    <div class="card card-interactive animate-slide-up delay-4" onclick="window.location.href='{{ route('applications.index') }}'" style="cursor:pointer">
        <div style="display:flex;align-items:center;gap:12px">
            <span class="stats-icon-pulse" style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(240,56,71,0.12);border:1px solid rgba(240,56,71,0.25);color:var(--red);font-size:18px">◆</span>
            <div>
                <div style="font-size:24px;font-weight:800;line-height:1">{{ $stats['total'] }}</div>
                <div style="font-size:10px;color:var(--muted);font-weight:600">Total Projects</div>
            </div>
        </div>
    </div>
    <div class="card card-interactive animate-slide-up delay-5" onclick="window.location.href='{{ route('applications.index', ['status'=>'running']) }}'" style="cursor:pointer">
        <div style="display:flex;align-items:center;gap:12px">
            <span class="stats-icon-pulse" style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(66,211,146,0.12);border:1px solid rgba(66,211,146,0.25);color:var(--green);font-size:18px;animation-delay:0.2s">●</span>
            <div>
                <div style="font-size:24px;font-weight:800;line-height:1;color:var(--green)">{{ $stats['running'] }}</div>
                <div style="font-size:10px;color:var(--muted);font-weight:600">Active Services</div>
            </div>
        </div>
    </div>
    <div class="card card-interactive animate-slide-up delay-6" onclick="window.location.href='{{ route('applications.index', ['status'=>'failed']) }}'" style="cursor:pointer">
        <div style="display:flex;align-items:center;gap:12px">
            <span class="stats-icon-pulse" style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(245,166,35,0.12);border:1px solid rgba(245,166,35,0.25);color:var(--amber);font-size:18px;animation-delay:0.4s">▲</span>
            <div>
                <div style="font-size:24px;font-weight:800;line-height:1;color:var(--amber)">{{ $stats['failed'] }}</div>
                <div style="font-size:10px;color:var(--muted);font-weight:600">Failed Services</div>
            </div>
        </div>
    </div>
    <div class="card animate-slide-up delay-7">
        <div style="display:flex;align-items:center;gap:12px">
            <span class="stats-icon-pulse" style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(77,171,247,0.12);border:1px solid rgba(77,171,247,0.25);color:var(--blue);font-size:18px;animation-delay:0.6s">♥</span>
            <div>
                <div style="font-size:24px;font-weight:800;line-height:1;color:var(--blue)">{{ $stats['success_rate'] }}%</div>
                <div style="font-size:10px;color:var(--muted);font-weight:600">Health Score</div>
            </div>
        </div>
    </div>
</div>

{{-- Main Dashboard Grid --}}
<div class="grid" style="grid-template-columns:1.2fr 0.9fr 1.6fr;gap:14px;margin-bottom:14px">
    {{-- Platform Status Card --}}
    <div class="card card-interactive animate-slide-up delay-6" onclick="window.location.href='{{ route('monitoring.index') }}'" style="cursor:pointer;min-height:280px">
        <div class="card-title">
            <span>Platform Status</span>
            <span class="live">{{ $infra['available'] ? 'LIVE' : 'OFFLINE' }}</span>
        </div>
        <div style="display:grid;place-items:center;padding:20px 0">
            <div style="position:relative;width:160px;height:80px;overflow:hidden">
                <div style="position:absolute;width:140px;height:140px;left:10px;top:10px;border-radius:50%;background:conic-gradient(from 270deg,var(--red) calc({{ $stats['success_rate'] }}*1.8deg),#242b35 0 180deg,transparent 0);mask:radial-gradient(circle,#0000 57%,#000 59%)"></div>
                <div style="position:absolute;inset:38px 0 0;text-align:center">
                    <div style="font-size:38px;font-weight:800;line-height:1;color:#f04755;text-shadow:0 0 20px rgba(240,56,71,0.3)">{{ $stats['success_rate'] }}</div>
                    <div style="font-size:8px;letter-spacing:1.2px;color:var(--muted);text-transform:uppercase">Health Score</div>
                </div>
            </div>
        </div>
        <div style="height:50px">
            <svg viewBox="0 0 320 50" preserveAspectRatio="none" style="width:100%;height:100%">
                <polyline points="{{ $trendPoints }}" fill="none" stroke="#f03847" stroke-width="2" opacity="0.8" class="map-path-anim" />
                <text x="5" y="48" fill="#526174" font-size="7">{{ $series->first()['label'] ?? 'START' }}</text>
                <text x="280" y="48" fill="#526174" font-size="7">NOW</text>
            </svg>
        </div>
    </div>

    {{-- Quick Metrics Stack --}}
    <div style="display:grid;grid-template-rows:repeat(3,1fr);gap:10px">
        <div class="card card-interactive animate-slide-up delay-7" onclick="window.location.href='{{ route('applications.index') }}'" style="cursor:pointer;display:flex;align-items:center;gap:12px;padding:14px;margin:0">
            <span style="display:grid;place-items:center;width:32px;height:32px;border:1px solid rgba(240,56,71,0.3);background:rgba(240,56,71,0.08);color:var(--red);font-size:14px">◆</span>
            <div style="flex:1"><strong style="display:block;font-size:20px;line-height:1">{{ $stats['total'] }}</strong><small style="font-size:8px;color:var(--muted);letter-spacing:1px">ALL PROJECTS</small></div>
            <em style="font-style:normal;font-size:8px;color:var(--muted2)">NODES</em>
        </div>
        <div class="card card-interactive animate-slide-up delay-8" onclick="window.location.href='{{ route('applications.index', ['status'=>'running']) }}'" style="cursor:pointer;display:flex;align-items:center;gap:12px;padding:14px;margin:0">
            <span style="display:grid;place-items:center;width:32px;height:32px;border:1px solid rgba(66,211,146,0.3);background:rgba(66,211,146,0.08);color:var(--green);font-size:14px">●</span>
            <div style="flex:1"><strong style="display:block;font-size:20px;line-height:1;color:var(--green)">{{ $stats['running'] }}</strong><small style="font-size:8px;color:var(--muted);letter-spacing:1px">RUNNING</small></div>
            <em style="font-style:normal;font-size:8px;color:var(--muted2)">ACTIVE</em>
        </div>
        <div class="card card-interactive animate-slide-up delay-9" onclick="window.location.href='{{ route('applications.index', ['status'=>'failed']) }}'" style="cursor:pointer;display:flex;align-items:center;gap:12px;padding:14px;margin:0">
            <span style="display:grid;place-items:center;width:32px;height:32px;border:1px solid rgba(245,166,35,0.3);background:rgba(245,166,35,0.08);color:var(--amber);font-size:14px">▲</span>
            <div style="flex:1"><strong style="display:block;font-size:20px;line-height:1;color:var(--amber)">{{ $stats['failed'] }}</strong><small style="font-size:8px;color:var(--muted);letter-spacing:1px">FAILED</small></div>
            <em style="font-style:normal;font-size:8px;color:var(--muted2)">ALERT</em>
        </div>
    </div>

    {{-- Deployment Network Map --}}
    <div class="card card-interactive animate-slide-up delay-10" onclick="window.location.href='{{ route('domains.index') }}'" style="cursor:pointer;min-height:280px">
        <div class="card-title">
            <span>Deployment Network</span>
            <span class="muted">ROUTING</span>
        </div>
        <div style="width:100%;height:205px">
            <svg viewBox="0 0 400 200" style="width:100%;height:100%">
                <circle cx="200" cy="100" r="40" fill="none" stroke="#2c3645" stroke-dasharray="4 4" style="animation: spin 30s linear infinite; transform-origin: 200px 100px;" />
                <circle cx="200" cy="100" r="70" fill="none" stroke="#1c232d"/>
                <circle cx="200" cy="100" r="6" fill="var(--red)"/>
                <circle cx="200" cy="100" r="14" fill="#f0384722" class="stats-icon-pulse"/>
                <circle cx="160" cy="70" r="4" fill="var(--green)"/>
                <circle cx="240" cy="130" r="4" fill="var(--green)"/>
                <circle cx="140" cy="120" r="4" fill="var(--amber)"/>
                <circle cx="270" cy="80" r="4" fill="var(--blue)"/>
                <path class="map-path-anim" d="M200 100 L160 70 M200 100 L240 130 M200 100 L140 120 M200 100 L270 80" stroke="#2c3645" stroke-width="1"/>
            </svg>
        </div>
        <div style="display:flex;gap:17px;align-items:center;font-size:8px;color:#687689">
            <i style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#4c5867;margin-right:5px"></i> Edge Node 
            <i style="display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--red);box-shadow:0 0 7px var(--red);margin-right:5px"></i> Gateway 
            <strong style="margin-left:auto;color:var(--red)">{{ $stats['total'] }} ACTIVE ROUTES</strong>
        </div>
    </div>
</div>

{{-- Summary Grid --}}
<div class="grid grid-2" style="margin-bottom:14px">
    {{-- System Load --}}
    <div class="card animate-slide-up delay-7">
        <div class="card-title">
            <span>SYSTEM LOAD</span>
            <span class="muted">{{ $stats['total'] }} REGISTERED</span>
        </div>
        <div>
            <div style="display:grid;grid-template-columns:65px 1fr 25px;gap:10px;align-items:center;margin:17px 0;font-size:9px;color:#7f8c9d">
                <span>Running</span>
                <i style="height:4px;background:#202733"><b style="display:block;height:100%;width:{{ $stats['total'] ? ($stats['running']/$stats['total'])*100 : 0 }}%;background:var(--green);transition:width 1s ease-out"></b></i>
                <strong style="text-align:right;color:#c5cfda">{{ $stats['running'] }}</strong>
            </div>
            <div style="display:grid;grid-template-columns:65px 1fr 25px;gap:10px;align-items:center;margin:17px 0;font-size:9px;color:#7f8c9d">
                <span>Failed</span>
                <i style="height:4px;background:#202733"><b style="display:block;height:100%;width:{{ $stats['total'] ? ($stats['failed']/$stats['total'])*100 : 0 }}%;background:var(--red);transition:width 1s ease-out"></b></i>
                <strong style="text-align:right;color:#c5cfda">{{ $stats['failed'] }}</strong>
            </div>
            <div style="display:grid;grid-template-columns:65px 1fr 25px;gap:10px;align-items:center;margin:17px 0;font-size:9px;color:#7f8c9d">
                <span>Other</span>
                <i style="height:4px;background:#202733"><b style="display:block;height:100%;width:{{ $stats['total'] ? (($stats['total']-$stats['running']-$stats['failed'])/$stats['total'])*100 : 0 }}%;background:var(--blue);transition:width 1s ease-out"></b></i>
                <strong style="text-align:right;color:#c5cfda">{{ $stats['total']-$stats['running']-$stats['failed'] }}</strong>
            </div>
        </div>
    </div>

    {{-- Applications List --}}
    <div class="card animate-slide-up delay-8">
        <div class="card-title">
            <span>APPLICATIONS</span>
            <a href="{{ route('projects.create') }}">ADD NEW ＋</a>
        </div>
        <div>
            @forelse($projects->take(5) as $project)
            <a href="{{ route('projects.show', $project) }}" style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #1c232d;transition:background 0.2s ease;border-radius:4px" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                <span style="display:grid;place-items:center;width:27px;height:27px;background:#171f2b;border:1px solid #303a48;color:#f05b68;font-size:8px;border-radius:4px">{{ strtoupper(substr($project->name,0,2)) }}</span>
                <div style="flex:1">
                    <strong style="display:block;font-size:10px;color:var(--text)">{{ $project->name }}</strong>
                    <small style="display:block;font-size:8px;color:#647184">{{ $project->primaryDomain?->domain ?? 'No domain' }}</small>
                </div>
                <span class="badge badge-{{ $project->status }}">{{ $project->status }}</span>
            </a>
            @empty
            <div style="padding:45px 0;text-align:center;color:#566475">No applications deployed</div>
            @endforelse
        </div>
    </div>


</div>

{{-- Recent Deployments Table --}}
<div class="card animate-slide-up delay-10">
    <div class="card-title">
        <span>RECENT DEPLOYMENT ACTIVITY</span>
        <span class="live">STREAMING</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Application</th>
                    <th>Trigger</th>
                    <th>Commit</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deployments as $deployment)
                <tr style="transition:all 0.2s ease" onmouseover="this.style.background='rgba(255,255,255,0.02)';this.style.transform='translateX(4px)'" onmouseout="this.style.background='transparent';this.style.transform='translateX(0)'">
                    <td><a href="{{ route('deployments.show', $deployment) }}" style="color:var(--text);font-weight:600">{{ $deployment->project->name }}</a></td>
                    <td>{{ strtoupper($deployment->trigger) }}</td>
                    <td><code>{{ $deployment->commit_sha ? substr($deployment->commit_sha,0,8) : '--------' }}</code></td>
                    <td><span class="badge badge-{{ $deployment->status }}">{{ $deployment->status }}</span></td>
                    <td>{{ $deployment->created_at->format('d M Y · H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">No deployment activity recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.setTimeout(() => window.location.reload(), 30000);
});
</script>
@endsection