<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\EnvironmentVariableController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Controllers\InfrastructureController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ProjectActionController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});
Route::post('/webhooks/github/{uuid}', GithubWebhookController::class)->middleware('throttle:120,1')->name('webhooks.github');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/applications', [ProjectController::class, 'applications'])->name('applications.index');
    Route::resource('projects', ProjectController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('/projects/{project}/deploy', [ProjectActionController::class, 'deploy'])->name('projects.deploy');
    Route::post('/projects/{project}/{action}', [ProjectActionController::class, 'lifecycle'])->whereIn('action', ['start', 'stop', 'restart'])->name('projects.lifecycle');
    Route::get('/projects/{project}/container-logs', [ProjectActionController::class, 'logs'])->name('projects.logs');
    Route::post('/projects/{project}/webhook/rotate', [ProjectActionController::class, 'rotateWebhook'])->name('projects.webhook.rotate');
    Route::post('/projects/{project}/environment', [EnvironmentVariableController::class, 'store'])->name('projects.environment.store');
    Route::delete('/projects/{project}/environment/{variable}', [EnvironmentVariableController::class, 'destroy'])->name('projects.environment.destroy');
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
    Route::get('/infrastructure/domains', [DomainController::class, 'index'])->name('domains.index');
    Route::post('/infrastructure/domains', [DomainController::class, 'store'])->name('domains.store');
    Route::delete('/infrastructure/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
    Route::get('/infrastructure/containers', [InfrastructureController::class, 'containers'])->name('containers.index');
    Route::get('/infrastructure/monitoring', [InfrastructureController::class, 'monitoring'])->name('monitoring.index');
    Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/settings/integrations/github', [IntegrationController::class, 'github'])->name('integrations.github');
    Route::delete('/settings/integrations/github', [IntegrationController::class, 'disconnectGithub'])->name('integrations.github.destroy');
    Route::post('/settings/integrations/cloudflare', [IntegrationController::class, 'cloudflare'])->name('integrations.cloudflare');
    Route::delete('/settings/integrations/cloudflare', [IntegrationController::class, 'disconnectCloudflare'])->name('integrations.cloudflare.destroy');
});
