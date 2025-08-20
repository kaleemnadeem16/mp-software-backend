# Performance & Scalability

## Overview

This comprehensive guide covers performance optimization strategies and scalability solutions for Laravel applications, with focus on Laravel Octane integration, horizontal scaling, and advanced performance techniques. These standards ensure your application can handle high traffic loads and scale efficiently.

## Table of Contents

- [Laravel Octane Integration](#laravel-octane-integration)
- [Application Performance](#application-performance)
- [Horizontal Scaling](#horizontal-scaling)
- [Load Balancing](#load-balancing)
- [Queue Management](#queue-management)
- [Session Management](#session-management)
- [File Storage Optimization](#file-storage-optimization)
- [API Performance](#api-performance)
- [Real-time Performance](#real-time-performance)
- [Monitoring & Metrics](#monitoring--metrics)
- [Performance Testing](#performance-testing)
- [Production Optimization](#production-optimization)

## Laravel Octane Integration

### 1. Installation & Setup

```bash
# Install Laravel Octane
composer require laravel/octane

# Install Swoole (recommended for production)
pecl install swoole

# Or install RoadRunner
composer require spiral/roadrunner-cli spiral/roadrunner-http

# Publish Octane configuration
php artisan octane:install

# Start Octane server
php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=4
```

### 2. Octane Configuration

```php
<?php

// config/octane.php
return [
    'server' => env('OCTANE_SERVER', 'swoole'),
    
    'https' => env('OCTANE_HTTPS', false),

    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeMoved::class,
        ],

        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
        ],

        RequestHandled::class => [
            FlushTemporarycontainerInstances::class,
            DisconnectFromDatabases::class,
            CollectGarbage::class,
        ],

        RequestTerminated::class => [
            FlushArrayCache::class,
            FlushAuthenticationState::class,
            FlushBroadcastingState::class,
            FlushBusState::class,
            FlushCacheState::class,
            FlushConfigurationState::class,
            FlushCookieState::class,
            FlushDatabaseState::class,
            FlushEventState::class,
            FlushFilesystemState::class,
            FlushHashState::class,
            FlushLogState::class,
            FlushMailState::class,
            FlushNotificationState::class,
            FlushPasswordState::class,
            FlushQueueState::class,
            FlushRedisState::class,
            FlushRequestState::class,
            FlushRouteState::class,
            FlushSessionState::class,
            FlushTranslationState::class,
            FlushUrlState::class,
            FlushValidationState::class,
            FlushViewState::class,
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [
            //
        ],

        TickReceived::class => [
            ...Octane::prepareApplicationForNextTick(),
        ],

        TickTerminated::class => [
            //
        ],
    ],

    'warm' => [
        ...Octane::defaultServicesToWarm(),
        // Add your services to warm up
        'cache',
        'config',
        'router',
        'view',
    ],

    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],

    'tables' => [
        'users:1000',
        'projects:500',
        'tasks:2000',
    ],

    'swoole' => [
        'options' => [
            'log_file' => storage_path('logs/swoole_http.log'),
            'package_max_length' => 10 * 1024 * 1024, // 10MB
            'buffer_output_size' => 10 * 1024 * 1024, // 10MB
            'socket_buffer_size' => 128 * 1024 * 1024, // 128MB
            'max_request' => 1000,
            'max_conn' => 1024,
            'reactor_num' => swoole_cpu_num(),
            'worker_num' => swoole_cpu_num() * 2,
            'task_worker_num' => swoole_cpu_num(),
            'task_enable_coroutine' => true,
            'task_max_request' => 1000,
            'enable_static_handler' => true,
            'document_root' => public_path(),
            'enable_coroutine' => true,
            'max_coroutine' => 100000,
            'open_tcp_nodelay' => true,
            'max_wait_time' => 60,
            'reload_async' => true,
            'enable_reuse_port' => true,
        ],
    ],

    'roadrunner' => [
        'config_path' => base_path('.rr.yaml'),
    ],
];
```

### 3. Octane-Optimized Service Provider

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Facades\Octane;

class OctaneServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register Octane-specific services
    }

    public function boot()
    {
        // Warm up services for better performance
        Octane::tick('warmup-cache', function () {
            $this->warmupApplicationCache();
        })->seconds(300); // Every 5 minutes

        // Clean up memory periodically
        Octane::tick('memory-cleanup', function () {
            $this->performMemoryCleanup();
        })->seconds(600); // Every 10 minutes

        // Monitor performance metrics
        Octane::tick('performance-metrics', function () {
            $this->collectPerformanceMetrics();
        })->seconds(60); // Every minute
    }

    protected function warmupApplicationCache(): void
    {
        // Warm up frequently accessed data
        \Cache::remember('system_settings', 3600, function () {
            return \DB::table('settings')->pluck('value', 'key');
        });

        \Cache::remember('active_users_count', 300, function () {
            return \App\Models\User::where('status', 'active')->count();
        });

        \Cache::remember('project_statistics', 600, function () {
            return [
                'total' => \App\Models\Project::count(),
                'active' => \App\Models\Project::where('status', 'active')->count(),
                'completed' => \App\Models\Project::where('status', 'completed')->count(),
            ];
        });
    }

    protected function performMemoryCleanup(): void
    {
        // Clear expired cache entries
        \Cache::flush();
        
        // Force garbage collection
        gc_collect_cycles();
        
        // Clear query log if enabled
        \DB::flushQueryLog();
    }

    protected function collectPerformanceMetrics(): void
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_usage' => sys_getloadavg()[0] ?? 0,
            'active_connections' => $this->getActiveConnections(),
        ];

        // Store metrics for monitoring
        \Cache::put('performance_metrics', $metrics, 120);
        
        // Log high resource usage
        if ($metrics['memory_usage'] > 512 * 1024 * 1024) { // 512MB
            \Log::warning('High memory usage detected', $metrics);
        }
    }

    protected function getActiveConnections(): int
    {
        // This would be implementation-specific based on your setup
        return 0;
    }
}
```

### 4. Octane-Safe Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OctaneSafeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Clear any previous request state
        $this->clearPreviousState();
        
        $response = $next($request);
        
        // Clean up after request
        $this->cleanupAfterRequest();
        
        return $response;
    }

    protected function clearPreviousState(): void
    {
        // Clear any global state that might persist between requests
        app()->forgetInstance('custom.service');
        
        // Reset singleton services that might hold state
        app()->forgetInstance('file.manager');
        
        // Clear any static variables in your classes
        \App\Services\StatefulService::resetState();
    }

    protected function cleanupAfterRequest(): void
    {
        // Close any open file handles
        $this->closeFileHandles();
        
        // Clear temporary data
        $this->clearTemporaryData();
        
        // Reset database connections if needed
        $this->resetDatabaseConnections();
    }

    protected function closeFileHandles(): void
    {
        // Close any file handles that might be open
        // This prevents file descriptor leaks
    }

    protected function clearTemporaryData(): void
    {
        // Clear any temporary data stored in static variables
        // or application containers
    }

    protected function resetDatabaseConnections(): void
    {
        // Disconnect from databases to prevent connection leaks
        \DB::disconnect();
    }
}
```

### 5. Memory-Safe Services

```php
<?php

namespace App\Services;

class OctaneSafeProjectService
{
    // Avoid storing state in properties
    // Use method parameters and return values instead

    public function getProjectStatistics(array $filters = []): array
    {
        // Use local variables instead of instance properties
        $cacheKey = 'project_stats_' . md5(serialize($filters));
        
        return \Cache::remember($cacheKey, 300, function () use ($filters) {
            $query = \App\Models\Project::query();
            
            // Apply filters
            foreach ($filters as $key => $value) {
                if ($key === 'status' && $value) {
                    $query->where('status', $value);
                }
                if ($key === 'department_id' && $value) {
                    $query->where('department_id', $value);
                }
            }
            
            return [
                'total' => $query->count(),
                'by_status' => $query->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    public function createProject(array $data, $user): \App\Models\Project
    {
        // Use database transactions to ensure consistency
        return \DB::transaction(function () use ($data, $user) {
            $project = \App\Models\Project::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'owner_id' => $user->id,
                'status' => $data['status'] ?? 'draft',
                'priority' => $data['priority'] ?? 'medium',
                // ... other fields
            ]);

            // Clear related caches
            \Cache::forget('project_stats_*');
            \Cache::tags(['projects'])->flush();

            // Dispatch events without storing references
            event(new \App\Events\ProjectCreated($project));

            return $project;
        });
    }

    // Avoid this pattern in Octane:
    // protected $cachedProjects = []; // This persists between requests!
    
    // Use this pattern instead:
    public function getCachedProjects(int $userId): \Illuminate\Support\Collection
    {
        $cacheKey = "user_{$userId}_projects";
        
        return \Cache::remember($cacheKey, 600, function () use ($userId) {
            return \App\Models\Project::where('owner_id', $userId)
                ->with(['tasks', 'members'])
                ->get();
        });
    }
}
```

## Application Performance

### 1. Response Time Optimization

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = $endMemory - $startMemory;
        
        // Add performance headers
        $response->headers->set('X-Response-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsage));
        
        // Log slow requests
        if ($executionTime > 1000) { // Slower than 1 second
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'memory_usage' => $memoryUsage,
                'user_id' => $request->user()?->id,
            ]);
        }
        
        // Store metrics for analysis
        $this->storePerformanceMetrics($request, $executionTime, $memoryUsage);
        
        return $response;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function storePerformanceMetrics(Request $request, float $executionTime, int $memoryUsage): void
    {
        // Store in cache for real-time monitoring
        $metrics = [
            'timestamp' => now()->timestamp,
            'url' => $request->path(),
            'method' => $request->method(),
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'status_code' => http_response_code(),
        ];
        
        // Use Redis for real-time metrics
        \Redis::lpush('performance_metrics', json_encode($metrics));
        \Redis::ltrim('performance_metrics', 0, 999); // Keep last 1000 entries
        \Redis::expire('performance_metrics', 3600); // Expire after 1 hour
    }
}
```

### 2. Database Connection Optimization

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseOptimizationService
{
    public function optimizeConnections(): void
    {
        // Configure connection pooling
        config([
            'database.connections.pgsql.options' => [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        ]);
    }

    public function setupReadWriteSeparation(): void
    {
        // Configure read/write splitting
        config([
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
                'read' => [
                    'host' => [
                        env('DB_READ_HOST_1'),
                        env('DB_READ_HOST_2'),
                        env('DB_READ_HOST_3'),
                    ],
                ],
                'write' => [
                    'host' => [env('DB_WRITE_HOST')],
                ],
                'sticky' => true, // Keep writes on write connection
                // ... other config
            ]
        ]);
    }

    public function optimizeQueryPerformance(): void
    {
        // Enable query caching
        DB::enableQueryLog();
        
        // Set up query result caching
        \Cache::extend('query_cache', function ($app) {
            return \Cache::repository(new \Illuminate\Cache\TaggedCache(
                \Cache::store('redis'),
                new \Illuminate\Cache\ArrayCacheStore()
            ));
        });
    }

    public function monitorSlowQueries(): void
    {
        DB::listen(function ($query) {
            if ($query->time > 1000) { // Queries slower than 1 second
                \Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                ]);
                
                // Store for analysis
                \Redis::lpush('slow_queries', json_encode([
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                    'timestamp' => now()->timestamp,
                ]));
            }
        });
    }
}
```

### 3. Memory Optimization

```php
<?php

namespace App\Services;

class MemoryOptimizationService
{
    public function optimizeForLargeDatasets(): void
    {
        // Increase memory limit for data processing
        ini_set('memory_limit', '1G');
        
        // Use chunking for large queries
        $this->processLargeDataset();
    }

    protected function processLargeDataset(): void
    {
        \App\Models\Project::chunk(1000, function ($projects) {
            foreach ($projects as $project) {
                // Process each project
                $this->processProject($project);
            }
            
            // Free memory after each chunk
            gc_collect_cycles();
        });
    }

    protected function processProject($project): void
    {
        // Process project data
        // Avoid storing large objects in memory
    }

    public function optimizeImageProcessing(): void
    {
        // Use efficient image processing
        if (extension_loaded('imagick')) {
            $this->useImagickOptimizations();
        } else {
            $this->useGDOptimizations();
        }
    }

    protected function useImagickOptimizations(): void
    {
        // Configure Imagick for memory efficiency
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024); // 256MB
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);    // 512MB
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_DISK, 1024 * 1024 * 1024);  // 1GB
    }

    protected function useGDOptimizations(): void
    {
        // Configure GD for memory efficiency
        ini_set('gd.jpeg_ignore_warning', 1);
    }

    public function clearUnusedMemory(): void
    {
        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Clear realpath cache
        clearstatcache();
        
        // Force garbage collection
        gc_collect_cycles();
        
        // Clear internal Laravel caches
        app('cache')->flush();
        app('config')->set('cache.stores.array.store', []);
    }
}
```

## Horizontal Scaling

### 1. Load Balancer Configuration

```nginx
# nginx.conf - Load balancer configuration
upstream laravel_backend {
    least_conn;
    server 10.0.1.10:8000 weight=3 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:8000 weight=3 max_fails=3 fail_timeout=30s;
    server 10.0.1.12:8000 weight=2 max_fails=3 fail_timeout=30s;
    server 10.0.1.13:8000 weight=2 max_fails=3 fail_timeout=30s backup;
    
    keepalive 32;
}

upstream websocket_backend {
    ip_hash; # Sticky sessions for WebSocket connections
    server 10.0.1.10:8080;
    server 10.0.1.11:8080;
    server 10.0.1.12:8080;
}

server {
    listen 80;
    listen 443 ssl http2;
    server_name api.mpsoft.com;

    # SSL configuration
    ssl_certificate /etc/ssl/certs/mpsoft.com.crt;
    ssl_certificate_key /etc/ssl/private/mpsoft.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req zone=api burst=20 nodelay;

    # Main application
    location / {
        proxy_pass http://laravel_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Buffer settings
        proxy_buffering on;
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
    }

    # WebSocket connections
    location /ws {
        proxy_pass http://websocket_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # WebSocket specific timeouts
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }

    # Static file serving
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        proxy_pass http://laravel_backend;
    }

    # Health check endpoint
    location /health {
        proxy_pass http://laravel_backend/health;
        access_log off;
    }
}
```

### 2. Session Management for Scaling

```php
<?php

// config/session.php - Redis-based session configuration
return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'session'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE', 'session'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',
];

// Redis session configuration
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'session' => [
        'host' => env('REDIS_SESSION_HOST', '127.0.0.1'),
        'password' => env('REDIS_SESSION_PASSWORD'),
        'port' => env('REDIS_SESSION_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '2'),
        'prefix' => env('REDIS_SESSION_PREFIX', 'session:'),
        'serializer' => 'php',
        'compression' => 'gzip',
    ],
];
```

### 3. Distributed Caching Strategy

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class DistributedCacheService
{
    protected array $cacheNodes;

    public function __construct()
    {
        $this->cacheNodes = [
            'primary' => 'redis-primary',
            'replica1' => 'redis-replica1',
            'replica2' => 'redis-replica2',
        ];
    }

    public function distributedGet(string $key, callable $callback = null, int $ttl = 3600)
    {
        // Try primary first
        $value = $this->getFromNode('primary', $key);
        
        if ($value !== null) {
            return $value;
        }

        // Try replicas
        foreach (['replica1', 'replica2'] as $replica) {
            $value = $this->getFromNode($replica, $key);
            if ($value !== null) {
                // Replicate back to primary
                $this->setToNode('primary', $key, $value, $ttl);
                return $value;
            }
        }

        // Generate new value if callback provided
        if ($callback) {
            $value = $callback();
            $this->distributedSet($key, $value, $ttl);
            return $value;
        }

        return null;
    }

    public function distributedSet(string $key, $value, int $ttl = 3600): bool
    {
        $success = true;

        // Set to all nodes
        foreach ($this->cacheNodes as $node) {
            try {
                $this->setToNode($node, $key, $value, $ttl);
            } catch (\Exception $e) {
                \Log::warning("Failed to set cache on node {$node}", [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                $success = false;
            }
        }

        return $success;
    }

    public function distributedForget(string $key): bool
    {
        $success = true;

        foreach ($this->cacheNodes as $node) {
            try {
                $this->forgetFromNode($node, $key);
            } catch (\Exception $e) {
                \Log::warning("Failed to delete cache on node {$node}", [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                $success = false;
            }
        }

        return $success;
    }

    protected function getFromNode(string $node, string $key)
    {
        try {
            return Redis::connection($node)->get($key);
        } catch (\Exception $e) {
            \Log::warning("Failed to get cache from node {$node}", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function setToNode(string $node, string $key, $value, int $ttl): void
    {
        Redis::connection($node)->setex($key, $ttl, serialize($value));
    }

    protected function forgetFromNode(string $node, string $key): void
    {
        Redis::connection($node)->del($key);
    }

    public function getNodeHealth(): array
    {
        $health = [];

        foreach ($this->cacheNodes as $name => $node) {
            try {
                $start = microtime(true);
                Redis::connection($node)->ping();
                $responseTime = (microtime(true) - $start) * 1000;

                $health[$name] = [
                    'status' => 'healthy',
                    'response_time' => round($responseTime, 2),
                    'memory_usage' => Redis::connection($node)->info('memory')['used_memory_human'] ?? 'unknown',
                ];
            } catch (\Exception $e) {
                $health[$name] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $health;
    }
}
```

### 4. Auto-Scaling Configuration

```yaml
# docker-compose.yml for auto-scaling
version: '3.8'

services:
  app:
    image: mpsoft/laravel-app:latest
    deploy:
      replicas: 3
      update_config:
        parallelism: 1
        delay: 10s
        order: start-first
        failure_action: rollback
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
      resources:
        limits:
          cpus: '1.0'
          memory: 1G
        reservations:
          cpus: '0.5'
          memory: 512M
    environment:
      - APP_ENV=production
      - DB_CONNECTION=pgsql
      - CACHE_DRIVER=redis
      - SESSION_DRIVER=redis
      - QUEUE_CONNECTION=redis
    networks:
      - app-network
    depends_on:
      - postgres
      - redis

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '0.5'
          memory: 256M
    networks:
      - app-network
    depends_on:
      - app

  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: mpsoft
      POSTGRES_USER: mpsoft
      POSTGRES_PASSWORD: secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
        reservations:
          cpus: '1.0'
          memory: 1G
    networks:
      - app-network

  redis:
    image: redis:7-alpine
    deploy:
      replicas: 1
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
    networks:
      - app-network

volumes:
  postgres_data:

networks:
  app-network:
    driver: overlay
    attachable: true
```

## Queue Management

### 1. High-Performance Queue Configuration

```php
<?php

// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

        'high_priority' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => 'high',
            'retry_after' => 60,
            'block_for' => null,
        ],

        'low_priority' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => 'low',
            'retry_after' => 300,
            'block_for' => null,
        ],

        'batch' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => 'batch',
            'retry_after' => 3600,
            'block_for' => null,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
```

### 2. Queue Worker Optimization

```bash
#!/bin/bash
# queue-worker.sh - Optimized queue worker script

# Set environment variables
export MEMORY_LIMIT=512
export TIMEOUT=60
export SLEEP=3
export MAX_JOBS=1000
export MAX_TIME=3600

# Function to start worker
start_worker() {
    local queue=$1
    local workers=${2:-1}
    
    for ((i=1; i<=workers; i++)); do
        php artisan queue:work redis \
            --queue=$queue \
            --memory=$MEMORY_LIMIT \
            --timeout=$TIMEOUT \
            --sleep=$SLEEP \
            --max-jobs=$MAX_JOBS \
            --max-time=$MAX_TIME \
            --tries=3 \
            --backoff=10,30,60 \
            --daemon &
        
        echo "Started worker $i for queue: $queue (PID: $!)"
    done
}

# Start workers for different queues
start_worker "high" 4      # 4 workers for high priority
start_worker "default" 8   # 8 workers for default queue
start_worker "low" 2       # 2 workers for low priority
start_worker "batch" 2     # 2 workers for batch processing

# Function to monitor workers
monitor_workers() {
    while true; do
        # Check worker processes
        worker_count=$(pgrep -f "queue:work" | wc -l)
        echo "$(date): $worker_count queue workers running"
        
        # Restart if no workers
        if [ $worker_count -eq 0 ]; then
            echo "$(date): No workers running, restarting..."
            exec $0
        fi
        
        # Check memory usage
        memory_usage=$(free | grep Mem | awk '{printf "%.2f", $3/$2 * 100.0}')
        echo "$(date): Memory usage: $memory_usage%"
        
        sleep 30
    done
}

# Start monitoring
monitor_workers
```

### 3. Queue Job Optimization

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;

class OptimizedDataProcessing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 2;
    public $backoff = [10, 30, 60]; // Exponential backoff
    public $timeout = 300; // 5 minutes

    protected $dataId;
    protected $options;

    public function __construct(int $dataId, array $options = [])
    {
        $this->dataId = $dataId;
        $this->options = $options;
        
        // Set queue based on priority
        if ($options['priority'] === 'high') {
            $this->onQueue('high');
        } elseif ($options['priority'] === 'low') {
            $this->onQueue('low');
        }
    }

    public function middleware(): array
    {
        return [
            // Prevent overlapping jobs for the same data
            new WithoutOverlapping($this->dataId),
            
            // Rate limit processing
            new RateLimited('data-processing')->allow(10)->everyMinute(),
        ];
    }

    public function handle(): void
    {
        // Load data efficiently
        $data = $this->loadData();
        
        if (!$data) {
            $this->fail('Data not found');
            return;
        }

        try {
            // Process in chunks to manage memory
            $this->processInChunks($data);
            
            // Clean up after processing
            $this->cleanup();
            
        } catch (\Exception $e) {
            \Log::error('Data processing failed', [
                'data_id' => $this->dataId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    protected function loadData()
    {
        // Use select to limit memory usage
        return \App\Models\DataModel::select([
            'id', 'name', 'content', 'status'
        ])->find($this->dataId);
    }

    protected function processInChunks($data): void
    {
        $chunkSize = $this->options['chunk_size'] ?? 1000;
        
        // Process related records in chunks
        $data->relatedRecords()->chunk($chunkSize, function ($records) {
            foreach ($records as $record) {
                $this->processRecord($record);
            }
            
            // Force garbage collection after each chunk
            gc_collect_cycles();
        });
    }

    protected function processRecord($record): void
    {
        // Actual processing logic
        $record->update(['processed_at' => now()]);
    }

    protected function cleanup(): void
    {
        // Clear any temporary files or cache
        \Cache::forget("processing_{$this->dataId}");
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failed job
        \Log::error('Job failed permanently', [
            'data_id' => $this->dataId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
        
        // Notify administrators
        \Notification::route('mail', config('app.admin_email'))
            ->notify(new \App\Notifications\JobFailedNotification($this, $exception));
    }
}
```

---

**Next**: Continue with API performance, monitoring & metrics, and production optimization sections.