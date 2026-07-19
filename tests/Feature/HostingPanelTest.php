<?php

namespace Tests\Feature;

use App\Jobs\DeployProject;
use App\Models\CloudflareIntegration;
use App\Models\Project;
use App\Models\User;
use App\Services\CloudflareConnectorService;
use App\Services\CloudflareService;
use App\Services\ProcessRunner;
use App\Services\ProjectTemplateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class HostingPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/projects')->assertRedirect('/login');
    }

    public function test_admin_can_create_a_valid_project_and_queue_deployment(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projects', [
            'name' => 'Example', 'slug' => 'example', 'type' => 'static',
            'repository' => 'https://github.com/example/site.git', 'branch' => 'main',
            'domain' => 'site.example.com',
        ]);

        $project = Project::firstOrFail();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('project_domains', ['domain' => 'site.example.com']);
        $this->assertNotSame($project->webhook->getRawOriginal('secret'), $project->webhook->secret);
        Queue::assertPushed(DeployProject::class);
        $this->get('/projects')->assertOk()->assertSee('Hosting Command Center')->assertSee('Example');
        $this->get('/applications')->assertOk()->assertSee('Workload inventory')->assertSee('Example');
        $this->get('/infrastructure/domains')->assertOk()->assertSee('site.example.com');
        $this->get('/settings/integrations')->assertOk()->assertSee('GitHub & Cloudflare', false);
        $this->get('/projects/create')->assertOk()->assertSee('New Deployment');
        $this->get(route('projects.show', $project))->assertOk()->assertSee('DELETE');
        $this->post('/infrastructure/domains', ['project_id' => $project->id, 'domain' => 'www.example.com'])->assertRedirect();
        $this->assertDatabaseHas('project_domains', ['domain' => 'www.example.com', 'is_primary' => false]);
        $oldSecret = $project->webhook->secret;
        $this->post(route('projects.webhook.rotate', $project))->assertRedirect()->assertSessionHas('webhook_secret');
        $this->assertNotSame($oldSecret, $project->webhook->fresh()->secret);

        $runner = Mockery::mock(ProcessRunner::class);
        $runner->shouldReceive('capture')->twice()->andReturnUsing(fn (array $command) => $command[1] === 'info'
            ? json_encode(['Containers' => 2, 'ContainersRunning' => 1, 'Images' => 3, 'ServerVersion' => 'test'])
            : '');
        $this->app->instance(ProcessRunner::class, $runner);
        $this->get('/infrastructure/containers')->assertOk()->assertSee('Container Operations');
        $this->get('/infrastructure/monitoring')->assertOk()->assertSee('ONLINE')->assertSee('test');
    }

    public function test_command_injection_shaped_input_is_rejected(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name' => 'Bad', 'slug' => 'bad;rm-rf', 'type' => 'static',
            'repository' => 'https://evil.example/repo.git', 'branch' => '--upload-pack=evil',
            'domain' => 'bad host',
        ])->assertSessionHasErrors(['slug', 'repository', 'branch', 'domain']);

        $this->assertDatabaseCount('projects', 0);
        Queue::assertNothingPushed();
    }

    public function test_admin_can_save_three_environment_variables_at_once(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id, 'name' => 'Example', 'slug' => 'example', 'type' => 'static',
            'repository' => 'https://github.com/example/site.git', 'branch' => 'main',
        ]);

        $this->actingAs($user)->post(route('projects.environment.store', $project), [
            'variables' => [
                ['key' => 'API_URL', 'value' => 'https://api.example.com'],
                ['key' => 'API_TOKEN', 'value' => 'secret-token'],
                ['key' => 'VITE_APP_NAME', 'value' => 'Example', 'is_build_time' => '1'],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('environment_variables', 3);
        $this->assertTrue($project->environmentVariables()->where('key', 'VITE_APP_NAME')->firstOrFail()->is_build_time);
    }

    public function test_empty_environment_variable_rows_are_ignored(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id, 'name' => 'Example', 'slug' => 'example', 'type' => 'static',
            'repository' => 'https://github.com/example/site.git', 'branch' => 'main',
        ]);

        $this->actingAs($user)->post(route('projects.environment.store', $project), [
            'variables' => [
                ['key' => 'APP_NAME', 'value' => 'Example'],
                ['key' => null, 'value' => null],
                ['key' => null, 'value' => null],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('environment_variables', 1);
    }

    public function test_github_webhook_requires_a_valid_signature_and_branch(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Example', 'slug' => 'example', 'type' => 'static', 'repository' => 'https://github.com/example/site.git', 'branch' => 'main']);
        $hook = $project->webhook()->create(['uuid' => '550e8400-e29b-41d4-a716-446655440000', 'secret' => 'super-secret']);
        $payload = json_encode(['ref' => 'refs/heads/main', 'after' => str_repeat('a', 40)], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.github', $hook->uuid), [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'push', 'HTTP_X_GITHUB_DELIVERY' => 'delivery-1',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'wrong'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertUnauthorized();

        $this->call('POST', route('webhooks.github', $hook->uuid), [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'push', 'HTTP_X_GITHUB_DELIVERY' => 'delivery-2',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'super-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertAccepted();
        Queue::assertPushed(DeployProject::class);
    }

    public function test_laravel_template_uses_isolated_networks_and_non_root_targets(): void
    {
        $root = storage_path('framework/testing-hosting-apps');
        File::deleteDirectory($root);
        config(['hosting.apps_path' => $root, 'hosting.proxy_network' => 'hosting_proxy']);
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id, 'name' => 'Laravel', 'slug' => 'laravel-app',
            'type' => 'laravel', 'repository' => 'https://github.com/example/app.git', 'branch' => 'main',
        ]);
        $project->domains()->create(['domain' => 'laravel.example.com']);
        $project->load('primaryDomain');

        app(ProjectTemplateGenerator::class)->generate($project);

        $dockerfile = File::get($root.'/laravel-app/Dockerfile');
        $compose = File::get($root.'/laravel-app/compose.yaml');
        $this->assertStringContainsString('AS runtime', $dockerfile);
        $this->assertStringContainsString('USER www-data', $dockerfile);
        $this->assertStringContainsString('internal: { internal: true }', $compose);
        $this->assertStringContainsString('traefik.http.routers.laravel-app.rule=Host(`laravel.example.com`)', $compose);
        $this->assertStringContainsString('APP_KEY="base64:', File::get($root.'/laravel-app/.env'));
        File::deleteDirectory($root);
    }

    public function test_wordpress_template_includes_mysql_and_persistent_volumes(): void
    {
        $root = storage_path('framework/testing-hosting-apps');
        File::deleteDirectory($root);
        config(['hosting.apps_path' => $root, 'hosting.proxy_network' => 'hosting_proxy']);
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id, 'name' => 'WordPress', 'slug' => 'wp-site',
            'type' => 'wordpress', 'repository' => 'https://github.com/example/wp.git', 'branch' => 'main',
        ]);
        $project->domains()->create(['domain' => 'wp.example.com']);

        app(ProjectTemplateGenerator::class)->generate($project);

        $dockerfile = File::get($root.'/wp-site/Dockerfile');
        $compose = File::get($root.'/wp-site/compose.yaml');
        $env = File::get($root.'/wp-site/.env');
        $this->assertStringContainsString('FROM wordpress:php8.3-apache', $dockerfile);
        $this->assertStringContainsString('image: mysql:8.4', $compose);
        $this->assertStringContainsString('wordpress_data:/var/www/html', $compose);
        $this->assertStringContainsString('db_data:/var/lib/mysql', $compose);
        $this->assertStringContainsString('traefik.http.services.wp-site.loadbalancer.server.port=80', $compose);
        $this->assertStringContainsString('WORDPRESS_DB_HOST="db:3306"', $env);
        $this->assertStringContainsString('MYSQL_DATABASE="wordpress"', $env);
        File::deleteDirectory($root);
    }

    public function test_cloudflare_provisioning_creates_dns_and_tunnel_ingress(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/*/dns_records?*' => Http::response(['success' => true, 'result' => []]),
            'api.cloudflare.com/client/v4/zones/*/dns_records' => Http::response(['success' => true, 'result' => ['id' => str_repeat('d', 32)]]),
            'api.cloudflare.com/client/v4/accounts/*/cfd_tunnel/*/configurations' => Http::sequence()
                ->push(['success' => true, 'result' => ['config' => ['ingress' => [['hostname' => 'existing.idkxz.my.id', 'service' => 'http://localhost:8080', 'originRequest' => []], ['service' => 'http_status:404']]]]])
                ->push(['success' => true, 'result' => ['config' => []]]),
        ]);
        $user = User::factory()->create();
        $integration = CloudflareIntegration::create([
            'user_id' => $user->id, 'account_id' => str_repeat('a', 32), 'zone_id' => str_repeat('b', 32),
            'tunnel_id' => '550e8400-e29b-41d4-a716-446655440000', 'zone_name' => 'idkxz.my.id', 'api_token' => 'secret-cloudflare-token',
        ]);
        $project = Project::create(['user_id' => $user->id, 'name' => 'App', 'slug' => 'app', 'type' => 'static', 'repository' => 'https://github.com/example/app.git', 'branch' => 'main']);
        $domain = $project->domains()->create(['domain' => 'app.idkxz.my.id']);

        app(CloudflareService::class)->provision($domain, $integration);

        $this->assertDatabaseHas('project_domains', ['id' => $domain->id, 'cloudflare_record_id' => str_repeat('d', 32), 'cloudflare_status' => 'active']);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/configurations')
            && data_get($request->data(), 'config.ingress.1.hostname') === 'app.idkxz.my.id'
            && data_get($request->data(), 'config.ingress.1.service') === 'https://traefik:443'
            && ! array_key_exists('originRequest', data_get($request->data(), 'config.ingress.0')));
    }

    public function test_cloudflare_deprovisioning_deletes_dns_and_tunnel_ingress(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/*/dns_records/*' => Http::response(['success' => true, 'result' => ['id' => str_repeat('d', 32)]]),
            'api.cloudflare.com/client/v4/accounts/*/cfd_tunnel/*/configurations' => Http::sequence()
                ->push(['success' => true, 'result' => ['config' => ['ingress' => [['hostname' => 'app.idkxz.my.id', 'service' => 'https://traefik:443'], ['service' => 'http_status:404']]]]])
                ->push(['success' => true, 'result' => ['config' => []]]),
        ]);
        $user = User::factory()->create();
        $integration = CloudflareIntegration::create([
            'user_id' => $user->id, 'account_id' => str_repeat('a', 32), 'zone_id' => str_repeat('b', 32),
            'tunnel_id' => '550e8400-e29b-41d4-a716-446655440000', 'zone_name' => 'idkxz.my.id', 'api_token' => 'secret-cloudflare-token',
        ]);
        $project = Project::create(['user_id' => $user->id, 'name' => 'App', 'slug' => 'app', 'type' => 'static', 'repository' => 'https://github.com/example/app.git', 'branch' => 'main']);
        $domain = $project->domains()->create(['domain' => 'app.idkxz.my.id', 'cloudflare_record_id' => str_repeat('d', 32), 'cloudflare_status' => 'active']);

        app(CloudflareService::class)->deprovision($domain, $integration);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/dns_records/'.str_repeat('d', 32)));
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/configurations')
            && collect(data_get($request->data(), 'config.ingress'))->doesntContain(fn ($rule) => ($rule['hostname'] ?? null) === 'app.idkxz.my.id'));
    }

    public function test_admin_can_disconnect_cloudflare_without_deleting_remote_dns(): void
    {
        $user = User::factory()->create();
        CloudflareIntegration::create([
            'user_id' => $user->id,
            'account_id' => str_repeat('a', 32),
            'zone_id' => str_repeat('b', 32),
            'tunnel_id' => '550e8400-e29b-41d4-a716-446655440000',
            'zone_name' => 'idkxz.my.id',
            'api_token' => 'secret-cloudflare-token',
        ]);
        $connector = Mockery::mock(CloudflareConnectorService::class);
        $connector->shouldReceive('disconnect')->once();
        $this->app->instance(CloudflareConnectorService::class, $connector);

        $this->actingAs($user)
            ->delete(route('integrations.cloudflare.destroy'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cloudflare_integrations', 0);
        Http::assertNothingSent();
    }
}
