<?php

namespace App\Http\Controllers;

use App\Jobs\DeployProject;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GithubWebhookController extends Controller
{
    public function __invoke(Request $request, string $uuid)
    {
        $hook = Webhook::where('uuid', $uuid)->where('active', true)->with('project')->firstOrFail();
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $hook->secret);
        abort_unless(hash_equals($expected, $signature), 401, 'Invalid signature');
        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json(['ok' => true]);
        }abort_unless($request->header('X-GitHub-Event') === 'push', 202);
        $delivery = (string) $request->header('X-GitHub-Delivery');
        if ($delivery === '' || ! Cache::add('github-delivery:'.$delivery, true, 86400)) {
            return response()->json(['accepted' => true, 'duplicate' => true], 202);
        }
        $payload = $request->json()->all();
        if (($payload['ref'] ?? '') !== 'refs/heads/'.$hook->project->branch) {
            return response()->json(['accepted' => false, 'reason' => 'branch ignored'], 202);
        }
        $d = $hook->project->deployments()->create(['trigger' => 'webhook', 'commit_sha' => substr((string) ($payload['after'] ?? ''), 0, 40)]);
        $hook->update(['last_received_at' => now()]);
        DeployProject::dispatch($d->id);

        return response()->json(['accepted' => true, 'deployment_id' => $d->id], 202);
    }
}
