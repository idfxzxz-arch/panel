@extends('layouts.app', ['title' => "Backups for {$project->name}"])

@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">Backups</p>
        <h1>{{ $project->name }}</h1>
        <p class="muted">{{ $project->repository ?: 'Official WordPress image' }} · {{ $project->branch }}</p>
    </div>
    <span class="badge {{ $project->status }}">{{ $project->status }}</span>
</div>

<div class="card">
    <div class="section-title">BACKUP OPERATIONS</div>
    <div class="actions">
        <form method="post" action="{{ route('projects.backups.store', $project) }}">
            @csrf
            <button class="btn btn-primary">CREATE NEW BACKUP</button>
        </form>
        <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">BACK TO PROJECT</a>
    </div>
</div>

<div class="card">
    <div class="section-title">BACKUP LIST</div>
    <div style="overflow:auto">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $backup)
                <tr>
                    <td>{{ $backup->id }}</td>
                    <td>{{ $backup->filename }}</td>
                    <td>{{ format_bytes($backup->size) }}</td>
                    <td><span class="badge {{ $backup->status }}">{{ $backup->status }}</span></td>
                    <td>{{ $backup->createdBy?->email ?? 'System' }}</td>
                    <td>{{ $backup->created_at->diffForHumans() }}</td>
                    <td>{{ $backup->expires_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="backup-actions">
                        <a href="{{ route('projects.backups.download', [$project, $backup]) }}" class="btn btn-secondary btn-sm">DOWNLOAD</a>
                        <form method="post" action="{{ route('projects.backups.restore', [$project, $backup]) }}" onsubmit="return confirm('Restore backup ini? Data project saat ini akan ditimpa.')">
                            @csrf
                            <button class="btn btn-secondary btn-sm">RESTORE</button>
                        </form>
                        <form method="post" action="{{ route('projects.backups.destroy', [$project, $backup]) }}" onsubmit="return confirm('Hapus backup ini?')">
                            @csrf @method('delete')
                            <button class="btn btn-danger btn-sm">DELETE</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">Belum ada backup.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($backups->hasPages())
    <div class="pagination">
        {{ $backups->links() }}
    </div>
    @endif
</div>

<style>
.section-title {
    font-size: 9px;
    letter-spacing: 1.3px;
    color: #a8b3c1;
    margin-bottom: 15px;
}
.backup-actions {
    display: flex;
    gap: 8px;
}
.backup-actions form {
    margin: 0;
}
.backup-actions .btn.small {
    padding: 4px 8px;
    font-size: 12px;
}
.pagination {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(168, 179, 193, 0.18);
}
</style>
@endsection