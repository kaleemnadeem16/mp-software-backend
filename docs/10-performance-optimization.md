# Performance & Optimization Standards

## Overview

This comprehensive guide covers performance optimization strategies for Laravel applications, from database query optimization to caching strategies and server-level performance tuning. Following these standards will ensure your application scales efficiently and provides optimal user experience.

## Table of Contents

- [Database Optimization](#database-optimization)
- [Eloquent Performance](#eloquent-performance)
- [Query Optimization](#query-optimization)
- [Caching Strategies](#caching-strategies)
- [Laravel Octane Integration](#laravel-octane-integration)
- [Frontend Performance](#frontend-performance)
- [File & Asset Optimization](#file--asset-optimization)
- [API Performance](#api-performance)
- [Memory Management](#memory-management)
- [Performance Monitoring](#performance-monitoring)
- [Load Testing](#load-testing)
- [Production Optimization](#production-optimization)

## Database Optimization

### 1. Database Indexing Strategy

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOptimizedUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('department')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Strategic indexes for performance
            $table->index(['status', 'created_at']); // For active users queries
            $table->index(['department', 'status']); // For department-based queries
            $table->index('last_login_at'); // For login analytics
            $table->index(['email_verified_at', 'status']); // For verified users
            
            // Composite index for complex queries
            $table->index(['status', 'department', 'created_at'], 'users_status_dept_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}

class CreateProjectsTable extends Migration
{
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('owner_id')->constrained('users');
            $table->enum('status', ['draft', 'active', 'completed', 'archived']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['status', 'priority']); // Most common filter combination
            $table->index(['owner_id', 'status']); // Owner's projects
            $table->index(['start_date', 'end_date']); // Date range queries
            $table->index('created_at'); // Sorting by creation date
            
            // Full-text search index for title and description
            $table->fullText(['title', 'description']);
        });
    }
}
```

### 2. Database Connection Optimization

```php
<?php

// config/database.php - Optimized database configuration
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                // Connection pooling and optimization
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'",
            ]) : [],
        ],

        // Read replica configuration for scaling
        'mysql_read' => [
            'driver' => 'mysql',
            'host' => env('DB_READ_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_READ_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('DB_READ_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ],
    ],

    // Redis configuration for caching and sessions
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'options' => [
                'parameters' => [
                    'tcp_keepalive' => 1,
                ],
            ],
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
        'session' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_SESSION_DB', '2'),
        ],
    ],
];
```

### 3. Database Query Analysis Service

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabasePerformanceService
{
    public static function analyzeSlowQueries(): array
    {
        // Enable slow query logging
        DB::statement("SET GLOBAL slow_query_log = 'ON'");
        DB::statement("SET GLOBAL long_query_time = 1"); // Queries > 1 second

        // Get current slow queries
        $slowQueries = DB::select("
            SELECT sql_text, exec_count, avg_timer_wait/1000000000 as avg_time_seconds
            FROM performance_schema.events_statements_summary_by_digest
            WHERE avg_timer_wait > 1000000000
            ORDER BY avg_timer_wait DESC
            LIMIT 10
        ");

        return $slowQueries;
    }

    public static function optimizeTable(string $tableName): array
    {
        $results = [];
        
        // Analyze table
        $analysis = DB::select("ANALYZE TABLE {$tableName}");
        $results['analysis'] = $analysis;

        // Check table status
        $status = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'");
        $results['status'] = $status;

        // Get index usage
        $indexes = DB::select("SHOW INDEX FROM {$tableName}");
        $results['indexes'] = $indexes;

        // Suggest optimizations
        $results['suggestions'] = self::generateOptimizationSuggestions($tableName, $status[0] ?? null);

        return $results;
    }

    private static function generateOptimizationSuggestions(string $tableName, $tableStatus): array
    {
        $suggestions = [];

        if ($tableStatus) {
            // Check for fragmentation
            $dataFree = $tableStatus->Data_free ?? 0;
            $dataLength = $tableStatus->Data_length ?? 1;
            
            if ($dataFree > 0 && ($dataFree / $dataLength) > 0.1) {
                $suggestions[] = "Consider running OPTIMIZE TABLE {$tableName} - {$dataFree} bytes fragmented";
            }

            // Check avg row length
            $avgRowLength = $tableStatus->Avg_row_length ?? 0;
            if ($avgRowLength > 1000) {
                $suggestions[] = "Consider normalizing {$tableName} - average row length is {$avgRowLength} bytes";
            }

            // Check for unused indexes
            $indexUsage = DB::select("
                SELECT DISTINCT s.INDEX_NAME
                FROM information_schema.STATISTICS s
                LEFT JOIN performance_schema.table_io_waits_summary_by_index_usage i
                    ON s.TABLE_SCHEMA = i.OBJECT_SCHEMA
                    AND s.TABLE_NAME = i.OBJECT_NAME
                    AND s.INDEX_NAME = i.INDEX_NAME
                WHERE s.TABLE_NAME = ? AND i.INDEX_NAME IS NULL
                AND s.INDEX_NAME != 'PRIMARY'
            ", [$tableName]);

            foreach ($indexUsage as $unused) {
                $suggestions[] = "Consider removing unused index: {$unused->INDEX_NAME}";
            }
        }

        return $suggestions;
    }
}
```

## Eloquent Performance

### 1. Optimized Model Design

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    protected $fillable = [
        'title', 'description', 'owner_id', 'status',
        'priority', 'start_date', 'end_date', 'budget'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    // Define which relationships to always load
    protected $with = ['owner:id,name,email'];

    // Optimize queries with scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeWithinDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    public function scopeWithTaskCounts(Builder $query): Builder
    {
        return $query->withCount(['tasks', 'completedTasks']);
    }

    // Efficient relationship definitions
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')
            ->select(['id', 'name', 'email']); // Only select needed columns
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function completedTasks(): HasMany
    {
        return $this->tasks()->where('status', 'completed');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    // Computed attributes for common calculations
    public function getProgressPercentageAttribute(): float
    {
        $totalTasks = $this->tasks_count ?? $this->tasks()->count();
        
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->completed_tasks_count ?? $this->completedTasks()->count();
        
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
    }

    // Efficient search functionality
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search) {
            $query->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('owner', function (Builder $query) use ($search) {
                      $query->where('name', 'LIKE', "%{$search}%");
                  });
        });
    }

    // Batch operations for performance
    public static function bulkUpdateStatus(array $projectIds, string $status): int
    {
        return static::whereIn('id', $projectIds)->update(['status' => $status]);
    }
}
```

### 2. Repository Pattern for Complex Queries

```php
<?php

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectRepository
{
    public function getActiveProjectsWithStats(): Collection
    {
        return Project::with(['owner:id,name'])
            ->withCount(['tasks', 'completedTasks'])
            ->active()
            ->get();
    }

    public function getPaginatedProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::with(['owner:id,name,email'])
            ->withCount(['tasks', 'completedTasks']);

        // Apply filters efficiently
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('end_date', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getProjectAnalytics(): array
    {
        // Use raw queries for complex analytics
        $stats = DB::select("
            SELECT 
                status,
                priority,
                COUNT(*) as count,
                AVG(DATEDIFF(COALESCE(end_date, CURDATE()), start_date)) as avg_duration_days,
                SUM(budget) as total_budget
            FROM projects 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            GROUP BY status, priority
            ORDER BY status, priority
        ");

        $monthlyStats = DB::select("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as projects_created,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as projects_completed
            FROM projects
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");

        return [
            'status_priority_breakdown' => $stats,
            'monthly_trends' => $monthlyStats,
        ];
    }

    public function getTopPerformers(): Collection
    {
        return DB::table('users')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(projects.id) as total_projects'),
                DB::raw('COUNT(CASE WHEN projects.status = "completed" THEN 1 END) as completed_projects'),
                DB::raw('AVG(DATEDIFF(COALESCE(projects.end_date, CURDATE()), projects.start_date)) as avg_project_duration')
            ])
            ->leftJoin('projects', 'users.id', '=', 'projects.owner_id')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->having('total_projects', '>', 0)
            ->orderByDesc('completed_projects')
            ->limit(10)
            ->get();
    }

    public function bulkCreateProjects(array $projectsData): bool
    {
        // Use chunk for large datasets
        $chunks = array_chunk($projectsData, 500);
        
        DB::beginTransaction();
        
        try {
            foreach ($chunks as $chunk) {
                Project::insert($chunk);
            }
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
```

### 3. Eager Loading Optimization

```php
<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OptimizedDataService
{
    public function getProjectsWithAllRelations(): Collection
    {
        return Project::with([
            'owner:id,name,email',
            'tasks:id,project_id,title,status,assigned_to',
            'tasks.assignee:id,name',
            'members:id,name,email',
        ])
        ->withCount([
            'tasks',
            'tasks as completed_tasks_count' => function ($query) {
                $query->where('status', 'completed');
            },
            'tasks as pending_tasks_count' => function ($query) {
                $query->where('status', 'pending');
            }
        ])
        ->get();
    }

    public function getUsersWithProjectStats(): Collection
    {
        return User::with([
            'projects:id,owner_id,title,status',
            'assignedTasks:id,assigned_to,title,status,project_id'
        ])
        ->withCount([
            'projects',
            'projects as active_projects_count' => function ($query) {
                $query->where('status', 'active');
            },
            'assignedTasks',
            'assignedTasks as completed_tasks_count' => function ($query) {
                $query->where('status', 'completed');
            }
        ])
        ->get();
    }

    // Lazy eager loading for conditional relationships
    public function getProjectsWithConditionalData(bool $includeTasks = false): Collection
    {
        $projects = Project::with('owner:id,name,email')->get();

        if ($includeTasks) {
            $projects->load([
                'tasks:id,project_id,title,status,assigned_to',
                'tasks.assignee:id,name'
            ]);
        }

        return $projects;
    }

    // Optimized nested relationships
    public function getProjectHierarchy(): Collection
    {
        return Project::with([
            'tasks' => function ($query) {
                $query->select('id', 'project_id', 'title', 'status', 'parent_id')
                      ->with('subtasks:id,parent_id,title,status');
            }
        ])
        ->whereNull('parent_project_id') // Only root projects
        ->get();
    }
}
```

## Query Optimization

### 1. Query Builder Optimization

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class OptimizedQueryService
{
    public function getProjectReports(): array
    {
        // Use select to limit columns
        $activeProjects = DB::table('projects')
            ->select(['id', 'title', 'status', 'created_at', 'budget'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        // Use joins instead of separate queries
        $projectsWithOwners = DB::table('projects')
            ->join('users', 'projects.owner_id', '=', 'users.id')
            ->select([
                'projects.id',
                'projects.title',
                'projects.status',
                'users.name as owner_name',
                'users.email as owner_email'
            ])
            ->where('projects.status', 'active')
            ->get();

        // Use subqueries for complex aggregations
        $projectsWithTaskCounts = DB::table('projects')
            ->select([
                'projects.*',
                DB::raw('(SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id) as total_tasks'),
                DB::raw('(SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.status = "completed") as completed_tasks')
            ])
            ->get();

        // Use window functions for rankings
        $topProjects = DB::select("
            SELECT 
                id,
                title,
                budget,
                RANK() OVER (ORDER BY budget DESC) as budget_rank,
                ROW_NUMBER() OVER (PARTITION BY status ORDER BY created_at DESC) as status_order
            FROM projects
            WHERE status IN ('active', 'completed')
        ");

        return [
            'active_projects' => $activeProjects,
            'projects_with_owners' => $projectsWithOwners,
            'projects_with_counts' => $projectsWithTaskCounts,
            'top_projects' => $topProjects,
        ];
    }

    public function getOptimizedUserMetrics(int $userId): array
    {
        // Single query to get all user metrics
        $metrics = DB::select("
            SELECT 
                u.id,
                u.name,
                COUNT(DISTINCT p.id) as total_projects,
                COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.id END) as completed_projects,
                COUNT(DISTINCT t.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks,
                COALESCE(SUM(p.budget), 0) as total_budget_managed,
                AVG(CASE WHEN p.status = 'completed' 
                    THEN DATEDIFF(p.end_date, p.start_date) 
                    END) as avg_project_duration
            FROM users u
            LEFT JOIN projects p ON u.id = p.owner_id
            LEFT JOIN tasks t ON u.id = t.assigned_to
            WHERE u.id = ?
            GROUP BY u.id, u.name
        ", [$userId]);

        return $metrics[0] ?? [];
    }

    // Pagination with cursor-based approach for large datasets
    public function getCursorPaginatedProjects(int $lastId = 0, int $limit = 20): array
    {
        $projects = DB::table('projects')
            ->select(['id', 'title', 'status', 'created_at', 'owner_id'])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($limit + 1) // Get one extra to check if there are more
            ->get();

        $hasMore = $projects->count() > $limit;
        
        if ($hasMore) {
            $projects = $projects->take($limit);
        }

        return [
            'data' => $projects,
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? $projects->last()->id : null,
        ];
    }

    // Batch operations for performance
    public function batchUpdateProjectStatus(array $projectIds, string $status): int
    {
        // Use chunks for large datasets
        $chunks = array_chunk($projectIds, 1000);
        $totalUpdated = 0;

        foreach ($chunks as $chunk) {
            $updated = DB::table('projects')
                ->whereIn('id', $chunk)
                ->update([
                    'status' => $status,
                    'updated_at' => now()
                ]);
            
            $totalUpdated += $updated;
        }

        return $totalUpdated;
    }
}
```

### 2. Advanced Query Techniques

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdvancedQueryService
{
    public function getComplexAnalytics(): array
    {
        // Common Table Expressions (CTE) for complex queries
        $projectAnalytics = DB::select("
            WITH project_metrics AS (
                SELECT 
                    p.id,
                    p.title,
                    p.status,
                    p.budget,
                    COUNT(t.id) as task_count,
                    COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_task_count,
                    AVG(t.estimated_hours) as avg_task_hours
                FROM projects p
                LEFT JOIN tasks t ON p.id = t.project_id
                WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                GROUP BY p.id, p.title, p.status, p.budget
            ),
            user_performance AS (
                SELECT 
                    u.id as user_id,
                    u.name,
                    COUNT(DISTINCT p.id) as projects_owned,
                    SUM(pm.completed_task_count) as total_completed_tasks,
                    AVG(pm.budget) as avg_project_budget
                FROM users u
                LEFT JOIN projects p ON u.id = p.owner_id
                LEFT JOIN project_metrics pm ON p.id = pm.id
                GROUP BY u.id, u.name
            )
            SELECT 
                pm.*,
                up.name as owner_name,
                up.projects_owned,
                up.total_completed_tasks,
                CASE 
                    WHEN pm.task_count > 0 
                    THEN (pm.completed_task_count / pm.task_count) * 100 
                    ELSE 0 
                END as completion_percentage
            FROM project_metrics pm
            LEFT JOIN projects p ON pm.id = p.id
            LEFT JOIN user_performance up ON p.owner_id = up.user_id
            ORDER BY completion_percentage DESC, pm.budget DESC
        ");

        return $projectAnalytics;
    }

    public function getTimeSeriesData(string $interval = 'month'): array
    {
        $intervalFormat = match($interval) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m'
        };

        $timeSeries = DB::select("
            SELECT 
                DATE_FORMAT(created_at, ?) as time_period,
                COUNT(*) as projects_created,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as projects_completed,
                SUM(budget) as total_budget,
                AVG(budget) as avg_budget
            FROM projects
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
            GROUP BY DATE_FORMAT(created_at, ?)
            ORDER BY time_period
        ", [$intervalFormat, $intervalFormat]);

        return $timeSeries;
    }

    // Optimized search with full-text search
    public function fullTextSearch(string $query, int $limit = 50): array
    {
        // Use full-text search for better performance on large text fields
        $projects = DB::select("
            SELECT 
                id,
                title,
                description,
                MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score
            FROM projects
            WHERE MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE)
            ORDER BY relevance_score DESC
            LIMIT ?
        ", [$query, $query, $limit]);

        return $projects;
    }

    // Hierarchical data with recursive CTE
    public function getProjectHierarchy(int $rootProjectId = null): array
    {
        $query = "
            WITH RECURSIVE project_tree AS (
                -- Base case: root projects
                SELECT 
                    id,
                    title,
                    parent_project_id,
                    0 as level,
                    CAST(id AS CHAR(1000)) as path
                FROM projects
                WHERE parent_project_id " . ($rootProjectId ? "= ?" : "IS NULL") . "
                
                UNION ALL
                
                -- Recursive case: child projects
                SELECT 
                    p.id,
                    p.title,
                    p.parent_project_id,
                    pt.level + 1,
                    CONCAT(pt.path, '->', p.id)
                FROM projects p
                INNER JOIN project_tree pt ON p.parent_project_id = pt.id
                WHERE pt.level < 5 -- Prevent infinite recursion
            )
            SELECT * FROM project_tree ORDER BY path
        ";

        $params = $rootProjectId ? [$rootProjectId] : [];
        
        return DB::select($query, $params);
    }

    // Optimized exists queries
    public function getUsersWithActiveProjects(): array
    {
        return DB::select("
            SELECT u.id, u.name, u.email
            FROM users u
            WHERE EXISTS (
                SELECT 1 
                FROM projects p 
                WHERE p.owner_id = u.id 
                AND p.status = 'active'
            )
            ORDER BY u.name
        ");
    }

    // Pivot queries for reporting
    public function getProjectStatusPivot(): array
    {
        return DB::select("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_count
            FROM projects
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
    }
}
```

## Caching Strategies

### 1. Comprehensive Caching Service

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CachingService
{
    // Cache configuration
    private const DEFAULT_TTL = 3600; // 1 hour
    private const LONG_TTL = 86400; // 24 hours
    private const SHORT_TTL = 300; // 5 minutes

    // Cache key prefixes
    private const USER_PREFIX = 'user:';
    private const PROJECT_PREFIX = 'project:';
    private const ANALYTICS_PREFIX = 'analytics:';
    private const SEARCH_PREFIX = 'search:';

    public function cacheUser(int $userId, Model $user): void
    {
        $key = self::USER_PREFIX . $userId;
        Cache::put($key, $user, self::DEFAULT_TTL);
        
        // Also cache by email for login lookups
        Cache::put(self::USER_PREFIX . 'email:' . $user->email, $user, self::DEFAULT_TTL);
    }

    public function getCachedUser(int $userId): ?Model
    {
        return Cache::get(self::USER_PREFIX . $userId);
    }

    public function getCachedUserByEmail(string $email): ?Model
    {
        return Cache::get(self::USER_PREFIX . 'email:' . $email);
    }

    public function invalidateUserCache(int $userId, string $email = null): void
    {
        Cache::forget(self::USER_PREFIX . $userId);
        
        if ($email) {
            Cache::forget(self::USER_PREFIX . 'email:' . $email);
        }
    }

    // Project caching with tags
    public function cacheProject(int $projectId, Model $project): void
    {
        $key = self::PROJECT_PREFIX . $projectId;
        Cache::tags(['projects', "project:{$projectId}"])->put($key, $project, self::DEFAULT_TTL);
    }

    public function getCachedProject(int $projectId): ?Model
    {
        return Cache::tags(['projects'])->get(self::PROJECT_PREFIX . $projectId);
    }

    public function invalidateProjectCache(int $projectId): void
    {
        Cache::tags(["project:{$projectId}"])->flush();
    }

    public function invalidateAllProjectsCache(): void
    {
        Cache::tags(['projects'])->flush();
    }

    // Analytics caching with different TTLs
    public function cacheAnalytics(string $type, array $data, int $ttl = null): void
    {
        $key = self::ANALYTICS_PREFIX . $type;
        $ttl = $ttl ?? self::LONG_TTL; // Analytics can be cached longer
        
        Cache::put($key, $data, $ttl);
    }

    public function getCachedAnalytics(string $type): ?array
    {
        return Cache::get(self::ANALYTICS_PREFIX . $type);
    }

    // Search result caching
    public function cacheSearchResults(string $query, array $results, int $ttl = null): void
    {
        $key = self::SEARCH_PREFIX . md5($query);
        $ttl = $ttl ?? self::SHORT_TTL; // Search results have shorter TTL
        
        Cache::put($key, $results, $ttl);
    }

    public function getCachedSearchResults(string $query): ?array
    {
        $key = self::SEARCH_PREFIX . md5($query);
        return Cache::get($key);
    }

    // Remember pattern for automatic caching
    public function rememberUserProjects(int $userId): Collection
    {
        return Cache::remember(
            "user:{$userId}:projects",
            self::DEFAULT_TTL,
            fn() => \App\Models\Project::where('owner_id', $userId)->with('tasks')->get()
        );
    }

    public function rememberProjectAnalytics(): array
    {
        return Cache::remember(
            self::ANALYTICS_PREFIX . 'overview',
            self::LONG_TTL,
            function () {
                return [
                    'total_projects' => \App\Models\Project::count(),
                    'active_projects' => \App\Models\Project::where('status', 'active')->count(),
                    'completed_projects' => \App\Models\Project::where('status', 'completed')->count(),
                    'total_budget' => \App\Models\Project::sum('budget'),
                ];
            }
        );
    }

    // Cache warming
    public function warmUserCache(int $userId): void
    {
        $user = \App\Models\User::with(['roles', 'permissions'])->find($userId);
        if ($user) {
            $this->cacheUser($userId, $user);
        }
    }

    public function warmProjectCache(int $projectId): void
    {
        $project = \App\Models\Project::with(['owner', 'tasks', 'members'])->find($projectId);
        if ($project) {
            $this->cacheProject($projectId, $project);
        }
    }

    // Bulk cache operations
    public function warmMultipleUsers(array $userIds): void
    {
        $users = \App\Models\User::with(['roles', 'permissions'])->whereIn('id', $userIds)->get();
        
        foreach ($users as $user) {
            $this->cacheUser($user->id, $user);
        }
    }

    // Cache statistics
    public function getCacheStats(): array
    {
        $redis = Redis::connection('cache');
        
        return [
            'memory_usage' => $redis->info('memory')['used_memory_human'] ?? 'N/A',
            'total_keys' => $redis->dbsize(),
            'hit_rate' => $this->calculateHitRate(),
        ];
    }

    private function calculateHitRate(): float
    {
        $redis = Redis::connection('cache');
        $stats = $redis->info('stats');
        
        $hits = $stats['keyspace_hits'] ?? 0;
        $misses = $stats['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
}
```

### 2. Model-Level Caching

```php
<?php

namespace App\Models;

use App\Services\CachingService;
use Illuminate\Database\Eloquent\Model;

class CachableProject extends Model
{
    protected $table = 'projects';
    
    protected static function booted()
    {
        // Automatically cache when created/updated
        static::saved(function ($project) {
            app(CachingService::class)->cacheProject($project->id, $project);
        });

        // Invalidate cache when deleted
        static::deleted(function ($project) {
            app(CachingService::class)->invalidateProjectCache($project->id);
        });
    }

    // Override find method to use cache
    public static function findCached(int $id): ?static
    {
        $cached = app(CachingService::class)->getCachedProject($id);
        
        if ($cached) {
            return $cached;
        }

        $project = static::find($id);
        
        if ($project) {
            app(CachingService::class)->cacheProject($id, $project);
        }

        return $project;
    }

    // Cache expensive computed properties
    public function getCachedTaskStatsAttribute(): array
    {
        return \Cache::remember(
            "project:{$this->id}:task_stats",
            300, // 5 minutes
            function () {
                return [
                    'total_tasks' => $this->tasks()->count(),
                    'completed_tasks' => $this->tasks()->where('status', 'completed')->count(),
                    'pending_tasks' => $this->tasks()->where('status', 'pending')->count(),
                    'in_progress_tasks' => $this->tasks()->where('status', 'in_progress')->count(),
                ];
            }
        );
    }

    public function getCachedProgressPercentageAttribute(): float
    {
        $stats = $this->cached_task_stats;
        
        if ($stats['total_tasks'] === 0) {
            return 0;
        }

        return round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 2);
    }
}
```

### 3. Redis-Based Advanced Caching

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisAdvancedCaching
{
    private Redis $redis;

    public function __construct()
    {
        $this->redis = Redis::connection('cache');
    }

    // Distributed locking for cache regeneration
    public function lockAndCache(string $key, callable $callback, int $ttl = 3600, int $lockTtl = 30): mixed
    {
        $lockKey = "lock:{$key}";
        
        // Try to acquire lock
        $lockAcquired = $this->redis->set($lockKey, 1, 'EX', $lockTtl, 'NX');
        
        if ($lockAcquired) {
            try {
                // Generate and cache data
                $data = $callback();
                $this->redis->setex($key, $ttl, serialize($data));
                return $data;
            } finally {
                // Always release lock
                $this->redis->del($lockKey);
            }
        } else {
            // If can't acquire lock, wait and try to get cached data
            sleep(1);
            $cached = $this->redis->get($key);
            
            if ($cached) {
                return unserialize($cached);
            }
            
            // If still no cached data, call callback without caching
            return $callback();
        }
    }

    // Sliding window rate limiting with cache
    public function slidingWindowRateLimit(string $key, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $pipeline = $this->redis->pipeline();
        
        // Remove old entries outside the window
        $pipeline->zremrangebyscore($key, '-inf', $now - $windowSeconds);
        
        // Count current entries
        $pipeline->zcard($key);
        
        // Add current request
        $pipeline->zadd($key, $now, $now . ':' . uniqid());
        
        // Set expiration
        $pipeline->expire($key, $windowSeconds);
        
        $results = $pipeline->exec();
        $currentCount = $results[1];
        
        return $currentCount < $limit;
    }

    // Pub/Sub for cache invalidation across multiple servers
    public function publishCacheInvalidation(string $pattern): void
    {
        $this->redis->publish('cache:invalidate', $pattern);
    }

    public function subscribeToCacheInvalidation(): void
    {
        $this->redis->subscribe(['cache:invalidate'], function ($message) {
            $pattern = $message;
            
            // Get all keys matching pattern
            $keys = $this->redis->keys($pattern);
            
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        });
    }

    // Bloom filter for cache miss reduction
    public function bloomFilterCheck(string $key, string $value): bool
    {
        $bloomKey = "bloom:{$key}";
        
        // Simple bloom filter implementation using multiple hash functions
        $hashes = [
            crc32($value) % 1000000,
            hash('fnv1a32', $value) % 1000000,
            hash('adler32', $value) % 1000000,
        ];

        foreach ($hashes as $hash) {
            if (!$this->redis->getbit($bloomKey, $hash)) {
                return false; // Definitely not in set
            }
        }

        return true; // Might be in set
    }

    public function bloomFilterAdd(string $key, string $value): void
    {
        $bloomKey = "bloom:{$key}";
        
        $hashes = [
            crc32($value) % 1000000,
            hash('fnv1a32', $value) % 1000000,
            hash('adler32', $value) % 1000000,
        ];

        foreach ($hashes as $hash) {
            $this->redis->setbit($bloomKey, $hash, 1);
        }
    }

    // Geospatial caching for location-based features
    public function cacheLocation(string $key, float $longitude, float $latitude, string $member): void
    {
        $this->redis->geoadd($key, $longitude, $latitude, $member);
    }

    public function getNearbyFromCache(string $key, float $longitude, float $latitude, float $radiusKm): array
    {
        return $this->redis->georadius($key, $longitude, $latitude, $radiusKm, 'km', 'WITHDIST', 'ASC');
    }
}
```

---

**Next**: I'll continue with the remaining sections including Laravel Octane integration, frontend performance, and production optimization strategies.