<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', 'unique:projects,slug'],
            'type' => ['required', Rule::in(['static', 'laravel', 'vite', 'wordpress'])],
            'repository' => ['required', 'url:http,https', 'max:500', 'regex:#^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$#'],
            'branch' => ['required', 'max:200', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', 'not_regex:/\.\./'],
            'domain' => ['required', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', 'unique:project_domains,domain'],
            'subdomain' => ['nullable', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $integration = $this->user()?->cloudflareIntegration;
        $subdomain = strtolower(trim((string) $this->subdomain));
        $this->merge([
            'slug' => strtolower((string) $this->slug),
            'subdomain' => $subdomain,
            'domain' => $integration && $subdomain !== '' ? $subdomain.'.'.$integration->zone_name : strtolower(rtrim((string) $this->domain, '.')),
            'repository' => rtrim((string) $this->repository, '/'),
        ]);
    }
}
