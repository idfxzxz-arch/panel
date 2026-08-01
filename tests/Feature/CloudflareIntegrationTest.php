<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CloudflareConnectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CloudflareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloudflare_api_token_accepts_bearer_prefix(): void
    {
        $this->app->instance(CloudflareConnectorService::class, Mockery::mock(CloudflareConnectorService::class, function ($mock) {
            $mock->shouldReceive('restart')->once();
        }));

        Http::fake(function ($request) {
            $this->assertSame('Bearer clean-token-1234567890', $request->header('Authorization')[0] ?? null);

            if (str_contains($request->url(), '/zones/')) {
                return Http::response([
                    'success' => true,
                    'result' => ['name' => 'example.com'],
                ]);
            }

            if (str_ends_with($request->url(), '/token')) {
                return Http::response([
                    'success' => true,
                    'result' => 'connector-token',
                ]);
            }

            return Http::response([
                'success' => true,
                'result' => ['id' => '11111111-1111-4111-8111-111111111111'],
            ]);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('integrations.cloudflare'), [
            'account_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'zone_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'tunnel_id' => '11111111-1111-4111-8111-111111111111',
            'zone_name' => 'example.com',
            'api_token' => 'Bearer clean-token-1234567890',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('clean-token-1234567890', $user->fresh()->cloudflareIntegration->api_token);
    }

    public function test_invalid_cloudflare_token_returns_helpful_message(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'errors' => [
                    ['code' => 9109, 'message' => 'Invalid access token'],
                ],
                'messages' => [],
                'result' => null,
            ], 403),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('integrations.index'))->post(route('integrations.cloudflare'), [
            'account_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'zone_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'tunnel_id' => '11111111-1111-4111-8111-111111111111',
            'zone_name' => 'example.com',
            'api_token' => 'bad-token-value-1234567890',
        ]);

        $response->assertRedirect(route('integrations.index'));
        $response->assertSessionHasErrors([
            'cloudflare' => 'Cloudflare gagal diverifikasi: API Token tidak valid. Paste token saja tanpa awalan "Bearer", jangan gunakan Global API Key, dan pastikan token belum expired.',
        ]);
    }
}
