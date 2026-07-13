<?php

namespace App\Services;

use App\Models\CloudflareIntegration;
use App\Models\ProjectDomain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareService
{
    private const API = 'https://api.cloudflare.com/client/v4';

    public function verify(array $config): void
    {
        $client = $this->client($config['api_token']);
        $zone = $client->get(self::API.'/zones/'.$config['zone_id'])->throw()->json();
        if (! ($zone['success'] ?? false) || strtolower($zone['result']['name'] ?? '') !== strtolower($config['zone_name'])) {
            throw new RuntimeException('Zone Cloudflare tidak cocok dengan Zone ID.');
        }
        $tunnel = $client->get(self::API.'/accounts/'.$config['account_id'].'/cfd_tunnel/'.$config['tunnel_id'])->throw()->json();
        if (! ($tunnel['success'] ?? false)) {
            throw new RuntimeException('Cloudflare Tunnel tidak dapat diverifikasi.');
        }
    }

    public function connectorToken(CloudflareIntegration $integration): string
    {
        $response = $this->client($integration->api_token)
            ->get(self::API.'/accounts/'.$integration->account_id.'/cfd_tunnel/'.$integration->tunnel_id.'/token')
            ->throw()->json();
        $token = $response['result'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Cloudflare tidak mengembalikan connector token.');
        }

        return $token;
    }

    public function provision(ProjectDomain $domain, CloudflareIntegration $integration): void
    {
        $client = $this->client($integration->api_token);
        $existing = $client->get(self::API.'/zones/'.$integration->zone_id.'/dns_records', [
            'name' => $domain->domain, 'type' => 'CNAME',
        ])->throw()->json('result', []);
        $target = $integration->tunnel_id.'.cfargotunnel.com';
        if (isset($existing[0])) {
            $record = $existing[0];
            if (($record['content'] ?? '') !== $target || ! ($record['proxied'] ?? false)) {
                $record = $client->put(self::API.'/zones/'.$integration->zone_id.'/dns_records/'.$record['id'], [
                    'type' => 'CNAME', 'name' => $domain->domain, 'content' => $target,
                    'ttl' => 1, 'proxied' => true, 'comment' => 'Managed by Harbor Control',
                ])->throw()->json('result');
            }
        } else {
            $record = $client->post(self::API.'/zones/'.$integration->zone_id.'/dns_records', [
                'type' => 'CNAME', 'name' => $domain->domain, 'content' => $target,
                'ttl' => 1, 'proxied' => true, 'comment' => 'Managed by Harbor Control',
            ])->throw()->json('result');
        }
        if (! is_array($record) || empty($record['id'])) {
            throw new RuntimeException('Cloudflare tidak mengembalikan DNS record ID.');
        }
        $this->upsertIngress($client, $integration, $domain->domain);
        $domain->update(['cloudflare_record_id' => $record['id'], 'cloudflare_status' => 'active']);
    }

    public function deprovision(ProjectDomain $domain, CloudflareIntegration $integration): void
    {
        $client = $this->client($integration->api_token);
        if ($domain->cloudflare_record_id) {
            $response = $client->delete(self::API.'/zones/'.$integration->zone_id.'/dns_records/'.$domain->cloudflare_record_id);
            if (! $response->successful() && $response->status() !== 404) {
                $response->throw();
            }
        }
        $this->removeIngress($client, $integration, $domain->domain);
    }

    private function upsertIngress(PendingRequest $client, CloudflareIntegration $integration, string $hostname): void
    {
        $ingress = $this->ingress($client, $integration);
        $catchAll = collect($ingress)->first(fn ($rule) => ! isset($rule['hostname'])) ?? ['service' => 'http_status:404'];
        $rules = collect($ingress)->filter(fn ($rule) => isset($rule['hostname']) && $rule['hostname'] !== $hostname)->values()->all();
        $rules[] = ['hostname' => $hostname, 'service' => 'https://traefik:443', 'originRequest' => [
            'noTLSVerify' => true, 'httpHostHeader' => $hostname,
        ]];
        $rules[] = $catchAll;
        $this->putIngress($client, $integration, $rules);
    }

    private function removeIngress(PendingRequest $client, CloudflareIntegration $integration, string $hostname): void
    {
        $rules = collect($this->ingress($client, $integration))->reject(fn ($rule) => ($rule['hostname'] ?? null) === $hostname)->values()->all();
        if (! collect($rules)->contains(fn ($rule) => ! isset($rule['hostname']))) {
            $rules[] = ['service' => 'http_status:404'];
        }
        $this->putIngress($client, $integration, $rules);
    }

    private function ingress(PendingRequest $client, CloudflareIntegration $integration): array
    {
        return $client->get(self::API.'/accounts/'.$integration->account_id.'/cfd_tunnel/'.$integration->tunnel_id.'/configurations')
            ->throw()->json('result.config.ingress', [['service' => 'http_status:404']]);
    }

    private function putIngress(PendingRequest $client, CloudflareIntegration $integration, array $rules): void
    {
        $rules = collect($rules)->map(function (array $rule) {
            if (($rule['originRequest'] ?? null) === []) {
                unset($rule['originRequest']);
            }

            return $rule;
        })->values()->all();
        $client->put(self::API.'/accounts/'.$integration->account_id.'/cfd_tunnel/'.$integration->tunnel_id.'/configurations', [
            'config' => ['ingress' => $rules],
        ])->throw();
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->asJson()->timeout(20);
    }
}
