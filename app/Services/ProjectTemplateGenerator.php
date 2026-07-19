<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

class ProjectTemplateGenerator
{
    public function generate(Project $project): void
    {
        $path = $project->path();
        File::ensureDirectoryExists($path);
        $this->ensureRuntimeDefaults($project);
        File::put($path.'/Dockerfile', $this->dockerfile($project->type));
        File::put($path.'/compose.yaml', $this->compose($project));
        $ignore = ".git\nnode_modules\nvendor\nstorage/logs/*\n".($project->type === 'vite' ? '' : ".env\n");
        File::put($path.'/.dockerignore', $ignore);
        if ($project->type === 'laravel') {
            File::put($path.'/docker-nginx.conf', $this->laravelNginx());
        }
        if ($project->type === 'vite') {
            File::put($path.'/docker-nginx.conf', $this->viteNginx());
        }
        $this->writeEnv($project);
    }

    public function writeEnv(Project $project): void
    {
        $lines = $project->environmentVariables()->get()->map(function ($variable) {
            $value = str_replace(['\\', "\n", "\r", '"'], ['\\\\', '\\n', '', '\\"'], $variable->value);

            return $variable->key.'="'.$value.'"';
        });
        File::put($project->path().'/.env', $lines->implode("\n").($lines->isNotEmpty() ? "\n" : ''));
        @chmod($project->path().'/.env', 0600);
    }

    private function ensureRuntimeDefaults(Project $project): void
    {
        if ($project->type === 'laravel') {
            $defaults = [
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => 'https://'.$project->primaryDomain->domain,
                'LOG_CHANNEL' => 'stderr',
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            ];
            foreach ($defaults as $key => $value) {
                $project->environmentVariables()->firstOrCreate(['key' => $key], ['value' => $value]);
            }
            $project->unsetRelation('environmentVariables');

            return;
        }

        if ($project->type !== 'wordpress') {
            return;
        }

        $dbPassword = $project->environmentVariables()->where('key', 'WORDPRESS_DB_PASSWORD')->first()?->value ?? bin2hex(random_bytes(18));
        $rootPassword = $project->environmentVariables()->where('key', 'MYSQL_ROOT_PASSWORD')->first()?->value ?? bin2hex(random_bytes(18));
        $defaults = [
            'WORDPRESS_DB_HOST' => 'db:3306',
            'WORDPRESS_DB_NAME' => 'wordpress',
            'WORDPRESS_DB_USER' => 'wordpress',
            'WORDPRESS_DB_PASSWORD' => $dbPassword,
            'WORDPRESS_TABLE_PREFIX' => 'wp_',
            'MYSQL_DATABASE' => 'wordpress',
            'MYSQL_USER' => 'wordpress',
            'MYSQL_PASSWORD' => $dbPassword,
            'MYSQL_ROOT_PASSWORD' => $rootPassword,
        ];
        foreach ($defaults as $key => $value) {
            $project->environmentVariables()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
        $project->unsetRelation('environmentVariables');
    }

    private function dockerfile(string $type): string
    {
        return match ($type) {
            'static' => <<<'DOCKER'
FROM nginxinc/nginx-unprivileged:1.27-alpine
COPY --chown=nginx:nginx . /usr/share/nginx/html
RUN rm -f /usr/share/nginx/html/compose.yaml /usr/share/nginx/html/Dockerfile /usr/share/nginx/html/.env
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]
DOCKER,
            'vite' => <<<'DOCKER'
FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build
FROM nginxinc/nginx-unprivileged:1.27-alpine
COPY docker-nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]
DOCKER,
            'laravel' => <<<'DOCKER'
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize
FROM php:8.3-fpm-alpine AS runtime
RUN apk add --no-cache icu-libs libzip libpng oniguruma && apk add --no-cache --virtual .build-deps icu-dev libzip-dev libpng-dev oniguruma-dev $PHPIZE_DEPS && docker-php-ext-install bcmath intl mbstring opcache pdo_mysql zip && apk del .build-deps
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app .
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

FROM nginxinc/nginx-unprivileged:1.27-alpine AS web
COPY docker-nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]
DOCKER,
            'wordpress' => <<<'DOCKER'
FROM wordpress:php8.3-apache
COPY --chown=www-data:www-data . /usr/src/wordpress
EXPOSE 80
DOCKER,
        }."\n";
    }

    private function compose(Project $p): string
    {
        $slug = $p->slug;
        $domains = $p->relationLoaded('domains') ? $p->domains : $p->domains()->get();
        $domainRule = $domains->map(fn ($domain) => 'Host(`'.$domain->domain.'`)')->implode(' || ');
        $network = config('hosting.proxy_network');
        $resolver = config('hosting.acme_resolver');
        $servicePort = $p->type === 'wordpress' ? 80 : 8080;
        $labels = <<<YAML
      - "traefik.enable=true"
      - "traefik.http.routers.{$slug}.rule={$domainRule}"
      - "traefik.http.routers.{$slug}.entrypoints=websecure"
      - "traefik.http.routers.{$slug}.tls=true"
      - "traefik.http.routers.{$slug}.tls.certresolver={$resolver}"
      - "traefik.http.services.{$slug}.loadbalancer.server.port={$servicePort}"
      - "traefik.docker.network={$network}"
YAML;
        if ($p->type === 'laravel') {
            return <<<YAML
name: {$slug}
services:
  app:
    build:
      context: .
      target: runtime
    restart: unless-stopped
    env_file: .env
    volumes:
      - uploads:/var/www/html/storage/app/public
    networks: [internal]
  web:
    build:
      context: .
      target: web
    restart: unless-stopped
    volumes:
      - uploads:/var/www/html/public/storage:ro
    depends_on: [app]
    networks: [internal, proxy]
    labels:
{$labels}
networks:
  internal: { internal: true }
  proxy: { external: true, name: {$network} }
volumes:
  uploads:
YAML;
        }

        if ($p->type === 'wordpress') {
            return <<<YAML
name: {$slug}
services:
  web:
    build: .
    restart: unless-stopped
    env_file: .env
    volumes:
      - wordpress_data:/var/www/html
    depends_on: [db]
    networks: [internal, proxy]
    labels:
{$labels}
  db:
    image: mysql:8.4
    restart: unless-stopped
    env_file: .env
    volumes:
      - db_data:/var/lib/mysql
    networks: [internal]
networks:
  internal: { internal: true }
  proxy: { external: true, name: {$network} }
volumes:
  wordpress_data:
  db_data:
YAML;
        }

        return <<<YAML
name: {$slug}
services:
  web:
    build: .
    restart: unless-stopped
    networks: [internal, proxy]
    labels:
{$labels}
networks:
  internal: { internal: true }
  proxy: { external: true, name: {$network} }
YAML;
    }

    private function laravelNginx(): string
    {
        return <<<'NGINX'
server {
    listen 8080; server_name _; root /var/www/html/public; index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass app:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
    location ~ /\. { deny all; }
}
NGINX;
    }

    private function viteNginx(): string
    {
        return <<<'NGINX'
server {
    listen 8080; server_name _; root /usr/share/nginx/html; index index.html;
    location / { try_files $uri $uri/ /index.html; }
}
NGINX;
    }
}
