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
        $this->ensureTraefik();
        try {
            $this->runner->capture(['docker', 'rm', '-f', 'harbor-cloudflared'], base_path());
        } catch (\Throwable) {
            // Container does not exist yet.
        }
        $this->runner->capture([
            'docker', 'run', '-d', '--name', 'harbor-cloudflared', '--network', config('hosting.proxy_network'),
            '--restart', 'unless-stopped', '-e', 'TUNNEL_TOKEN', 'cloudflare/cloudflared:latest',
            'tunnel', '--no-autoupdate', 'run',
        ], base_path(), ['TUNNEL_TOKEN' => $integration->tunnel_token], 180);
    }

    public function disconnect(): void
    {
        try {
            $this->runner->capture(['docker', 'rm', '-f', 'harbor-cloudflared'], base_path());
        } catch (\Throwable) {
            // The connector may already have been removed outside the panel.
        }
    }

    private function ensureTraefik(): void
    {
        try {
            $configuration = json_decode($this->runner->capture(['docker', 'inspect', '--format', '{{json .Config}}', 'traefik'], base_path()), true, 512, JSON_THROW_ON_ERROR);
            if (($configuration['Image'] ?? null) === 'traefik:v3.7.1') {
                $this->runner->capture(['docker', 'start', 'traefik'], base_path());

                return;
            }
            $this->runner->capture(['docker', 'rm', '-f', 'traefik'], base_path());
        } catch (\Throwable) {
            // Create the managed edge proxy below.
        }
        $this->runner->capture(['docker', 'volume', 'create', 'harbor_letsencrypt'], base_path());
        $this->runner->capture([
            'docker', 'run', '-d', '--name', 'traefik', '--network', config('hosting.proxy_network'),
            '--restart', 'unless-stopped', '-v', '/var/run/docker.sock:/var/run/docker.sock:ro',
            '-v', 'harbor_letsencrypt:/letsencrypt', 'traefik:v3.7.1',
            '--providers.docker=true', '--providers.docker.exposedbydefault=false',
            '--entrypoints.web.address=:80', '--entrypoints.websecure.address=:443',
            '--certificatesresolvers.letsencrypt.acme.email=admin@idkxz.my.id',
            '--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json',
            '--certificatesresolvers.letsencrypt.acme.tlschallenge=true',
        ], base_path(), [], 180);
    }
}
