# Database Standards

## Overview

This comprehensive guide covers database design standards, migration best practices, and PostgreSQL-specific optimizations for Laravel applications. These standards ensure data integrity, performance, and maintainability while leveraging PostgreSQL's advanced features.

## Table of Contents

- [Database Design Principles](#database-design-principles)
- [PostgreSQL Configuration](#postgresql-configuration)
- [Migration Standards](#migration-standards)
- [Schema Design](#schema-design)
- [Indexing Strategies](#indexing-strategies)
- [Data Types](#data-types)
- [Constraints & Validation](#constraints--validation)
- [Relationships](#relationships)
- [Performance Optimization](#performance-optimization)
- [Backup & Recovery](#backup--recovery)
- [Database Seeding](#database-seeding)
- [Query Optimization](#query-optimization)

## Database Design Principles

### 1. Normalization Standards

```php
<?php

// Follow 3NF (Third Normal Form) principles
// ✅ Good: Normalized structure
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->foreignId('department_id')->constrained();
    $table->timestamps();
});

Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 10)->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});

// ❌ Avoid: Denormalized structure
Schema::create('users_bad', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('department_name'); // Denormalized
    $table->string('department_code'); // Denormalized
    $table->timestamps();
});
```

### 2. Naming Conventions

```php
<?php

// Table names: plural, snake_case
Schema::create('project_members', function (Blueprint $table) {
    // Primary key: 'id' (implicit)
    $table->id();
    
    // Foreign keys: {model}_id
    $table->foreignId('project_id')->constrained();
    $table->foreignId('user_id')->constrained();
    
    // Boolean fields: is_{condition} or has_{condition}
    $table->boolean('is_active')->default(true);
    $table->boolean('has_admin_access')->default(false);
    
    // Timestamps: {action}_at
    $table->timestamp('joined_at')->nullable();
    $table->timestamp('last_accessed_at')->nullable();
    
    // JSON fields: {purpose}_data
    $table->json('settings_data')->nullable();
    $table->json('metadata')->nullable();
    
    // Standard Laravel timestamps
    $table->timestamps();
    
    // Unique constraints: descriptive names
    $table->unique(['project_id', 'user_id'], 'project_members_unique');
    
    // Indexes: {table}_{columns}_index
    $table->index(['project_id', 'is_active'], 'project_members_project_active_idx');
});
```

## PostgreSQL Configuration

### 1. Database Configuration

```php
<?php

// config/database.php - PostgreSQL optimized configuration
return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::PGSQL_ATTR_DISABLE_PREPARES => false,
            ]) : [],
        ],

        // Read replica for scaling
        'pgsql_read' => [
            'driver' => 'pgsql',
            'read' => [
                'host' => [
                    env('DB_READ_HOST_1', '127.0.0.1'),
                    env('DB_READ_HOST_2', '127.0.0.1'),
                ],
            ],
            'write' => [
                'host' => [env('DB_WRITE_HOST', '127.0.0.1')],
            ],
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'require'),
        ],
    ],
];
```

### 2. Environment Configuration

```env
# PostgreSQL Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mp_software
DB_USERNAME=mp_user
DB_PASSWORD=secure_password_here
DB_SSLMODE=require

# Read Replicas (for scaling)
DB_READ_HOST_1=127.0.0.1
DB_READ_HOST_2=127.0.0.2
DB_WRITE_HOST=127.0.0.1

# Connection Pool Settings
DB_POOL_MIN=5
DB_POOL_MAX=20
```

### 3. PostgreSQL Extensions

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EnablePostgresqlExtensions extends Migration
{
    public function up()
    {
        // Enable UUID extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        
        // Enable full-text search
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');
        
        // Enable hstore for key-value storage
        DB::statement('CREATE EXTENSION IF NOT EXISTS "hstore"');
        
        // Enable PostGIS for geolocation features (if needed)
        // DB::statement('CREATE EXTENSION IF NOT EXISTS "postgis"');
        
        // Enable citext for case-insensitive text
        DB::statement('CREATE EXTENSION IF NOT EXISTS "citext"');
    }

    public function down()
    {
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
        DB::statement('DROP EXTENSION IF EXISTS "pg_trgm"');
        DB::statement('DROP EXTENSION IF EXISTS "hstore"');
        DB::statement('DROP EXTENSION IF EXISTS "citext"');
    }
}
```

## Migration Standards

### 1. Migration Structure

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Basic fields
            $table->string('title');
            $table->text('description');
            $table->string('code', 20)->unique();
            
            // Foreign keys with constraints
            $table->foreignId('owner_id')
                  ->constrained('users')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');
            
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained('departments')
                  ->onUpdate('cascade')
                  ->onDelete('set null');
            
            // Enums using PostgreSQL native enums
            $table->enum('status', ['draft', 'active', 'completed', 'archived'])
                  ->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                  ->default('medium');
            
            // Dates
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            
            // Numbers with proper precision
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->integer('estimated_hours')->nullable();
            
            // JSON for flexible data
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            
            // Boolean flags
            $table->boolean('is_public')->default(false);
            $table->boolean('is_archived')->default(false);
            
            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['status', 'priority']);
            $table->index(['owner_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('created_at');
            
            // Full-text search index
            $table->index(DB::raw('to_tsvector(\'english\', title || \' \' || description)'), 'projects_search_idx');
            
            // Partial index for active projects
            $table->index('created_at', 'projects_active_created_idx')
                  ->where('status', 'active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
}
```

### 2. PostgreSQL-Specific Features

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateAdvancedFeaturesTable extends Migration
{
    public function up()
    {
        Schema::create('advanced_features', function (Blueprint $table) {
            $table->id();
            
            // UUID column with automatic generation
            $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
            
            // Case-insensitive text
            $table->string('email')->nullable();
            
            // Array columns
            $table->json('tags'); // For storing arrays
            
            // JSONB for better performance
            $table->jsonb('document')->nullable();
            
            // Timestamp with timezone
            $table->timestampTz('event_time')->nullable();
            
            // IP address columns
            $table->ipAddress('client_ip')->nullable();
            $table->macAddress('device_mac')->nullable();
            
            $table->timestamps();
            
            // PostgreSQL specific indexes
            $table->index('uuid');
            $table->index(DB::raw('(document->\'status\')'), 'document_status_idx');
            $table->index(DB::raw('gin(tags)'), 'tags_gin_idx'); // GIN index for JSON
        });

        // Add check constraints
        DB::statement('ALTER TABLE advanced_features ADD CONSTRAINT email_format CHECK (email ~* \'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$\')');
        
        // Add comment to table
        DB::statement('COMMENT ON TABLE advanced_features IS \'Table demonstrating PostgreSQL advanced features\'');
    }

    public function down()
    {
        Schema::dropIfExists('advanced_features');
    }
}
```

### 3. Data Type Mapping

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DatabaseDataTypesExample extends Migration
{
    public function up()
    {
        Schema::create('data_types_example', function (Blueprint $table) {
            // Integer types
            $table->id(); // BIGSERIAL
            $table->integer('small_number'); // INTEGER
            $table->bigInteger('large_number'); // BIGINT
            $table->smallInteger('tiny_number'); // SMALLINT
            
            // String types
            $table->string('short_text', 255); // VARCHAR(255)
            $table->text('long_text'); // TEXT
            $table->char('fixed_length', 10); // CHAR(10)
            
            // Numeric types
            $table->decimal('price', 10, 2); // DECIMAL(10,2)
            $table->float('percentage', 5, 2); // REAL
            $table->double('precise_value'); // DOUBLE PRECISION
            
            // Date and time
            $table->date('event_date'); // DATE
            $table->time('event_time'); // TIME
            $table->dateTime('event_datetime'); // TIMESTAMP
            $table->timestamp('created_at'); // TIMESTAMP
            $table->timestampTz('created_at_tz'); // TIMESTAMP WITH TIME ZONE
            
            // Boolean
            $table->boolean('is_active'); // BOOLEAN
            
            // JSON
            $table->json('settings'); // JSON
            $table->jsonb('config'); // JSONB (PostgreSQL specific)
            
            // Binary
            $table->binary('file_data'); // BYTEA
            
            // UUID
            $table->uuid('identifier'); // UUID
            
            // Network addresses
            $table->ipAddress('ip_addr'); // INET
            $table->macAddress('mac_addr'); // MACADDR
            
            // Arrays (using JSON in Laravel)
            $table->json('string_array'); // Store as JSON array
            $table->json('integer_array'); // Store as JSON array
            
            // Full-text search
            $table->text('searchable_content');
            
            $table->timestamps();
        });

        // Add PostgreSQL-specific features
        DB::statement('CREATE INDEX data_types_search_idx ON data_types_example USING gin(to_tsvector(\'english\', searchable_content))');
    }

    public function down()
    {
        Schema::dropIfExists('data_types_example');
    }
}
```

## Schema Design

### 1. User Management Schema

```php
<?php

// Users table with PostgreSQL optimizations
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
    $table->string('name');
    $table->string('email')->unique();
    $table->string('username', 50)->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('phone', 20)->nullable();
    $table->date('date_of_birth')->nullable();
    
    // Employment details
    $table->foreignId('department_id')->nullable()->constrained();
    $table->string('employee_id', 20)->unique()->nullable();
    $table->date('hire_date')->nullable();
    $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern']);
    
    // Status and security
    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
    $table->boolean('is_verified')->default(false);
    $table->boolean('two_factor_enabled')->default(false);
    $table->text('two_factor_secret')->nullable();
    $table->json('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
    
    // Login tracking
    $table->timestamp('last_login_at')->nullable();
    $table->ipAddress('last_login_ip')->nullable();
    $table->integer('failed_login_attempts')->default(0);
    $table->timestamp('locked_until')->nullable();
    
    // Profile data
    $table->text('bio')->nullable();
    $table->json('preferences')->nullable();
    $table->json('settings')->nullable();
    
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['status', 'is_verified']);
    $table->index(['department_id', 'employment_type']);
    $table->index('last_login_at');
    $table->unique(['email', 'deleted_at']);
    $table->unique(['username', 'deleted_at']);
});
```

### 2. Project Management Schema

```php
<?php

// Projects with comprehensive tracking
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
    $table->string('title');
    $table->string('code', 20)->unique();
    $table->text('description');
    $table->text('objectives')->nullable();
    
    // Ownership and management
    $table->foreignId('owner_id')->constrained('users');
    $table->foreignId('manager_id')->nullable()->constrained('users');
    $table->foreignId('department_id')->nullable()->constrained();
    $table->foreignId('client_id')->nullable()->constrained();
    
    // Project details
    $table->enum('status', ['draft', 'planning', 'active', 'on_hold', 'completed', 'cancelled', 'archived'])->default('draft');
    $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
    $table->enum('type', ['internal', 'client', 'research', 'maintenance'])->default('internal');
    
    // Timeline
    $table->date('planned_start_date');
    $table->date('planned_end_date');
    $table->date('actual_start_date')->nullable();
    $table->date('actual_end_date')->nullable();
    
    // Budget and resources
    $table->decimal('budget', 12, 2)->nullable();
    $table->decimal('actual_cost', 12, 2)->nullable();
    $table->integer('estimated_hours')->nullable();
    $table->integer('actual_hours')->nullable();
    
    // Progress tracking
    $table->decimal('progress_percentage', 5, 2)->default(0);
    $table->integer('total_tasks')->default(0);
    $table->integer('completed_tasks')->default(0);
    
    // Metadata
    $table->json('custom_fields')->nullable();
    $table->json('settings')->nullable();
    $table->json('risk_factors')->nullable();
    
    // Flags
    $table->boolean('is_billable')->default(false);
    $table->boolean('is_public')->default(false);
    $table->boolean('requires_approval')->default(true);
    
    $table->timestamps();
    $table->softDeletes();
    
    // Performance indexes
    $table->index(['status', 'priority']);
    $table->index(['owner_id', 'status']);
    $table->index(['department_id', 'status']);
    $table->index(['planned_start_date', 'planned_end_date']);
    $table->index('progress_percentage');
    
    // Full-text search
    $table->index(DB::raw('to_tsvector(\'english\', title || \' \' || description)'), 'projects_fts_idx');
});

// Project members pivot table
Schema::create('project_members', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('role', ['member', 'lead', 'viewer', 'manager'])->default('member');
    $table->decimal('hourly_rate', 8, 2)->nullable();
    $table->integer('allocated_hours')->nullable();
    $table->boolean('can_edit')->default(false);
    $table->boolean('can_manage_members')->default(false);
    $table->timestamp('joined_at')->useCurrent();
    $table->timestamp('left_at')->nullable();
    $table->timestamps();
    
    $table->unique(['project_id', 'user_id']);
    $table->index(['user_id', 'role']);
});
```

### 3. Task Management Schema

```php
<?php

// Tasks with hierarchy support
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->default(DB::raw('uuid_generate_v4()'));
    $table->string('title');
    $table->text('description')->nullable();
    
    // Hierarchy
    $table->foreignId('project_id')->constrained()->onDelete('cascade');
    $table->foreignId('parent_id')->nullable()->constrained('tasks')->onDelete('cascade');
    $table->integer('sort_order')->default(0);
    
    // Assignment
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->foreignId('created_by')->constrained('users');
    
    // Status and priority
    $table->enum('status', ['todo', 'in_progress', 'review', 'testing', 'done', 'blocked'])->default('todo');
    $table->enum('priority', ['lowest', 'low', 'medium', 'high', 'highest'])->default('medium');
    $table->enum('type', ['task', 'bug', 'feature', 'improvement', 'research'])->default('task');
    
    // Time tracking
    $table->integer('estimated_hours')->nullable();
    $table->integer('actual_hours')->nullable();
    $table->date('due_date')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    
    // Progress
    $table->decimal('progress_percentage', 5, 2)->default(0);
    $table->text('completion_notes')->nullable();
    
    // Metadata
    $table->json('custom_fields')->nullable();
    $table->json('checklist')->nullable();
    $table->string('external_id')->nullable(); // For integrations
    
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['project_id', 'status']);
    $table->index(['assigned_to', 'status']);
    $table->index(['parent_id', 'sort_order']);
    $table->index('due_date');
    $table->index(['status', 'priority']);
    
    // Full-text search
    $table->index(DB::raw('to_tsvector(\'english\', title || \' \' || coalesce(description, \'\'))'), 'tasks_fts_idx');
});
```

## Indexing Strategies

### 1. Performance Indexes

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        // Composite indexes for common query patterns
        DB::statement('CREATE INDEX CONCURRENTLY users_active_department_idx ON users (department_id, status) WHERE status = \'active\'');
        
        DB::statement('CREATE INDEX CONCURRENTLY projects_owner_status_idx ON projects (owner_id, status, created_at)');
        
        DB::statement('CREATE INDEX CONCURRENTLY tasks_project_assignee_idx ON tasks (project_id, assigned_to, status)');
        
        // Partial indexes for filtering
        DB::statement('CREATE INDEX CONCURRENTLY projects_active_idx ON projects (created_at) WHERE status IN (\'active\', \'planning\')');
        
        DB::statement('CREATE INDEX CONCURRENTLY tasks_pending_idx ON tasks (due_date, assigned_to) WHERE status IN (\'todo\', \'in_progress\')');
        
        // Expression indexes
        DB::statement('CREATE INDEX CONCURRENTLY users_email_lower_idx ON users (lower(email))');
        
        // GIN indexes for JSON fields
        DB::statement('CREATE INDEX CONCURRENTLY projects_settings_gin_idx ON projects USING gin(settings)');
        
        DB::statement('CREATE INDEX CONCURRENTLY tasks_custom_fields_gin_idx ON tasks USING gin(custom_fields)');
        
        // Full-text search indexes
        DB::statement('CREATE INDEX CONCURRENTLY users_search_idx ON users USING gin(to_tsvector(\'english\', name || \' \' || coalesce(email, \'\')))');
        
        // Covering indexes (include non-key columns)
        DB::statement('CREATE INDEX CONCURRENTLY projects_status_covering_idx ON projects (status) INCLUDE (title, owner_id, created_at)');
    }

    public function down()
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_active_department_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS projects_owner_status_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS tasks_project_assignee_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS projects_active_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS tasks_pending_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_email_lower_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS projects_settings_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS tasks_custom_fields_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_search_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS projects_status_covering_idx');
    }
}
```

### 2. Index Monitoring

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseIndexService
{
    public function getIndexUsageStats(): array
    {
        return DB::select("
            SELECT 
                schemaname,
                tablename,
                indexname,
                idx_tup_read,
                idx_tup_fetch,
                pg_size_pretty(pg_relation_size(indexrelid)) as index_size
            FROM pg_stat_user_indexes 
            WHERE schemaname = 'public'
            ORDER BY idx_tup_read DESC
        ");
    }

    public function getUnusedIndexes(): array
    {
        return DB::select("
            SELECT 
                schemaname,
                tablename,
                indexname,
                pg_size_pretty(pg_relation_size(indexrelid)) as index_size
            FROM pg_stat_user_indexes 
            WHERE idx_tup_read = 0 
            AND idx_tup_fetch = 0
            AND schemaname = 'public'
            ORDER BY pg_relation_size(indexrelid) DESC
        ");
    }

    public function getMissingIndexSuggestions(): array
    {
        return DB::select("
            SELECT 
                query,
                calls,
                total_time,
                mean_time,
                rows
            FROM pg_stat_statements 
            WHERE query LIKE '%SELECT%'
            AND total_time > 1000  -- More than 1 second total
            ORDER BY total_time DESC
            LIMIT 10
        ");
    }

    public function analyzeTableStats(): array
    {
        return DB::select("
            SELECT 
                schemaname,
                tablename,
                n_tup_ins as inserts,
                n_tup_upd as updates,
                n_tup_del as deletes,
                n_tup_hot_upd as hot_updates,
                n_live_tup as live_tuples,
                n_dead_tup as dead_tuples,
                last_vacuum,
                last_autovacuum,
                last_analyze,
                last_autoanalyze
            FROM pg_stat_user_tables 
            WHERE schemaname = 'public'
            ORDER BY n_live_tup DESC
        ");
    }
}
```

## Constraints & Validation

### 1. Database-Level Constraints

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDatabaseConstraints extends Migration
{
    public function up()
    {
        // Check constraints for data validation
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_format CHECK (email ~* \'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$\')');
        
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_phone_format CHECK (phone IS NULL OR phone ~* \'^\+?[1-9]\d{1,14}$\')');
        
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_budget_positive CHECK (budget IS NULL OR budget >= 0)');
        
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_dates_logical CHECK (planned_end_date >= planned_start_date)');
        
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_progress_range CHECK (progress_percentage >= 0 AND progress_percentage <= 100)');
        
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_hours_positive CHECK (estimated_hours IS NULL OR estimated_hours > 0)');
        
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_progress_range CHECK (progress_percentage >= 0 AND progress_percentage <= 100)');
        
        // Exclusion constraints for business rules
        DB::statement('ALTER TABLE project_members ADD CONSTRAINT no_duplicate_active_membership EXCLUDE (project_id WITH =, user_id WITH =) WHERE (left_at IS NULL)');
        
        // Unique constraints with conditions
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique_active UNIQUE (email) WHERE status != \'inactive\'');
    }

    public function down()
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_format');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_phone_format');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_budget_positive');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_dates_logical');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_progress_range');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_hours_positive');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_progress_range');
        DB::statement('ALTER TABLE project_members DROP CONSTRAINT IF EXISTS no_duplicate_active_membership');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique_active');
    }
}
```

### 2. Database Functions and Triggers

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateDatabaseFunctions extends Migration
{
    public function up()
    {
        // Function to update project progress
        DB::statement("
            CREATE OR REPLACE FUNCTION update_project_progress()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE projects 
                SET 
                    total_tasks = (SELECT COUNT(*) FROM tasks WHERE project_id = NEW.project_id AND deleted_at IS NULL),
                    completed_tasks = (SELECT COUNT(*) FROM tasks WHERE project_id = NEW.project_id AND status = 'done' AND deleted_at IS NULL),
                    progress_percentage = CASE 
                        WHEN (SELECT COUNT(*) FROM tasks WHERE project_id = NEW.project_id AND deleted_at IS NULL) = 0 THEN 0
                        ELSE ROUND((SELECT COUNT(*) FROM tasks WHERE project_id = NEW.project_id AND status = 'done' AND deleted_at IS NULL)::decimal / 
                                  (SELECT COUNT(*) FROM tasks WHERE project_id = NEW.project_id AND deleted_at IS NULL) * 100, 2)
                    END
                WHERE id = NEW.project_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger to automatically update project progress
        DB::statement("
            CREATE TRIGGER trigger_update_project_progress
            AFTER INSERT OR UPDATE OR DELETE ON tasks
            FOR EACH ROW
            EXECUTE FUNCTION update_project_progress();
        ");

        // Function to log user activities
        DB::statement("
            CREATE OR REPLACE FUNCTION log_user_activity()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO user_activity_logs (user_id, action, table_name, record_id, old_values, new_values, created_at)
                VALUES (
                    COALESCE(NEW.updated_by, NEW.created_by, OLD.updated_by),
                    TG_OP,
                    TG_TABLE_NAME,
                    COALESCE(NEW.id, OLD.id),
                    CASE WHEN TG_OP = 'DELETE' THEN row_to_json(OLD) ELSE NULL END,
                    CASE WHEN TG_OP != 'DELETE' THEN row_to_json(NEW) ELSE NULL END,
                    NOW()
                );
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function for soft delete cleanup
        DB::statement("
            CREATE OR REPLACE FUNCTION cleanup_soft_deleted()
            RETURNS INTEGER AS $$
            DECLARE
                deleted_count INTEGER := 0;
            BEGIN
                -- Permanently delete records soft-deleted more than 1 year ago
                DELETE FROM tasks WHERE deleted_at < NOW() - INTERVAL '1 year';
                GET DIAGNOSTICS deleted_count = ROW_COUNT;
                
                DELETE FROM projects WHERE deleted_at < NOW() - INTERVAL '1 year';
                GET DIAGNOSTICS deleted_count = deleted_count + ROW_COUNT;
                
                RETURN deleted_count;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    public function down()
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_project_progress ON tasks');
        DB::statement('DROP FUNCTION IF EXISTS update_project_progress()');
        DB::statement('DROP FUNCTION IF EXISTS log_user_activity()');
        DB::statement('DROP FUNCTION IF EXISTS cleanup_soft_deleted()');
    }
}
```

---

**Next**: Continue with backup & recovery, seeding, and query optimization sections.