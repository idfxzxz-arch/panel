@extends('layouts.app', ['title' => 'Deployment #' . $deployment->id])

@section('content')
<div class="page-head">
    <div class="page-head-left">
        <p class="eyebrow">
            <a href="{{ route('projects.show', $deployment->project) }}" style="color:var(--red)">← {{ $deployment->project->name }}</a>
        </p>
        <h1>Deployment #{{ $deployment->id }}</h1>
        <p class="subtitle">
            <span class="badge badge-{{ $deployment->status }}">{{ $deployment->status }}</span>
            · {{ strtoupper($deployment->trigger) }}
            · {{ $deployment->commit_sha ?? 'commit belum diketahui' }}
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('projects.show', $deployment->project) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            BACK TO PROJECT
        </a>
    </div>
</div>

{{-- Deployment Info --}}
<div class="grid grid-3" style="margin-bottom:14px">
    <div class="card">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(240,56,71,0.12);border:1px solid rgba(240,56,71,0.25);color:var(--red);font-size:16px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </span>
            <div>
                <div style="font-size:9px;color:var(--muted);letter-spacing:1px;font-weight:700">TRIGGER</div>
                <div style="font-size:16px;font-weight:700;margin-top:2px">{{ strtoupper($deployment->trigger) }}</div>
            </div>
        </div>
    </div>
    <div class="card">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(77,171,247,0.12);border:1px solid rgba(77,171,247,0.25);color:var(--blue);font-size:16px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </span>
            <div>
                <div style="font-size:9px;color:var(--muted);letter-spacing:1px;font-weight:700">STARTED</div>
                <div style="font-size:14px;font-weight:600;margin-top:2px">{{ $deployment->started_at?->format('d M Y H:i:s') ?? 'Queued' }}</div>
            </div>
        </div>
    </div>
    <div class="card">
        <div style="display:flex;align-items:center;gap:12px">
            <span style="display:grid;place-items:center;width:40px;height:40px;border-radius:var(--radius);background:rgba(66,211,146,0.12);border:1px solid rgba(66,211,146,0.25);color:var(--green);font-size:16px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
            </span>
            <div>
                <div style="font-size:9px;color:var(--muted);letter-spacing:1px;font-weight:700">DURATION</div>
                <div style="font-size:14px;font-weight:600;margin-top:2px">
                    @if($deployment->started_at && $deployment->finished_at)
                        {{ $deployment->started_at->diffForHumans($deployment->finished_at, true) }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Deployment Log --}}
<div class="card">
    <div class="card-title">
        <span>DEPLOYMENT LOG</span>
        <span class="live">● LIVE OUTPUT</span>
    </div>
    <pre class="deploy-log">@forelse($deployment->logs as $log)<span class="log-line"><span class="log-time">[{{ $log->created_at->format('H:i:s') }}]</span> <span class="log-level log-level-{{ strtolower($log->level) }}">[{{ strtoupper($log->level) }}]</span> <span class="log-step">[{{ $log->step }}]</span>
{{ $log->message }}
</span>@empty<span class="log-empty">Deployment masih menunggu worker queue...</span>@endforelse</pre>
</div>

<style>
    .deploy-log {
        max-height: 600px;
        counter-reset: log-line;
    }
    .log-line {
        display: block;
        padding: 2px 0;
        border-bottom: 1px solid rgba(255,255,255,0.02);
    }
    .log-line:hover {
        background: rgba(255,255,255,0.02);
    }
    .log-time {
        color: var(--muted2);
    }
    .log-level {
        font-weight: 700;
    }
    .log-level-info { color: var(--blue); }
    .log-level-warning { color: var(--amber); }
    .log-level-error { color: var(--red); }
    .log-level-debug { color: var(--muted); }
    .log-step {
        color: var(--purple);
    }
    .log-empty {
        display: block;
        padding: 20px 0;
        text-align: center;
        color: var(--muted2);
        font-style: italic;
    }
</style>
@endsection
