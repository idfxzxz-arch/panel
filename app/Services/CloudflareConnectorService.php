<?php

namespace App\Services;

use App\Models\CloudflareIntegration;

class CloudflareConnectorService
{
    public function __construct(private ProcessRunner $runner) {}

    public function restart(CloudflareIntegration $integration): void
    {
        if (! $integration->tunnel_token) {
            return;
        }

        $this->disconnect();

        // Run natively
        $this->runner->capture([
            'bash', '-c', 'nohup cloudflared tunnel --no-autoupdate run > /dev/null 2>&1 &'
        ], base_path(), ['TUNNEL_TOKEN' => $integration->tunnel_token]);
    }

    public function disconnect(): void
    {
        try {
            $this->runner->capture(['pkill', '-f', 'cloudflared tunnel'], base_path());
        } catch (\Throwable) {
            // Process may not exist
        }
    }
}
