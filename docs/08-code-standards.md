# Code Standards & Best Practices

## Overview

This comprehensive guide establishes coding standards, best practices, and architectural patterns for Laravel backend development. These standards ensure code quality, maintainability, readability, and team collaboration while following PSR-12 guidelines and Laravel conventions.

## Table of Contents

- [PSR-12 Code Style](#psr-12-code-style)
- [File Organization](#file-organization)
- [Naming Conventions](#naming-conventions)
- [Class Structure](#class-structure)
- [Method Design](#method-design)
- [Error Handling](#error-handling)
- [Documentation Standards](#documentation-standards)
- [Testing Standards](#testing-standards)
- [Security Guidelines](#security-guidelines)
- [Performance Considerations](#performance-considerations)
- [Code Review Process](#code-review-process)
- [Tools & Automation](#tools--automation)

## PSR-12 Code Style

### 1. Basic Formatting Rules

```php
<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Project;
use App\Models\User;
use App\Exceptions\ProjectNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing project operations and business logic.
 */
final class ProjectManagementService
{
    private const MAX_PROJECTS_PER_USER = 10;
    private const DEFAULT_PROJECT_STATUS = 'draft';

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Create a new project with validation and business rules.
     *
     * @param array<string, mixed> $data Project data
     * @param User $user User creating the project
     * @return Project Created project instance
     * @throws ProjectCreationException When project creation fails
     */
    public function createProject(array $data, User $user): Project
    {
        $this->validateProjectCreation($data, $user);

        return DB::transaction(function () use ($data, $user) {
            $project = $this->projectRepository->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'owner_id' => $user->id,
                'status' => self::DEFAULT_PROJECT_STATUS,
                'priority' => $data['priority'] ?? 'medium',
                'budget' => $data['budget'] ?? null,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_end_date' => $data['planned_end_date'] ?? null,
            ]);

            $this->notificationService->sendProjectCreatedNotification($project, $user);

            Log::info('Project created successfully', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'title' => $project->title,
            ]);

            return $project;
        });
    }

    /**
     * Validate project creation rules and constraints.
     *
     * @param array<string, mixed> $data
     * @param User $user
     * @throws ProjectCreationException
     */
    private function validateProjectCreation(array $data, User $user): void
    {
        // Check user project limit
        $userProjectCount = $this->projectRepository->countByOwner($user);
        
        if ($userProjectCount >= self::MAX_PROJECTS_PER_USER) {
            throw new ProjectCreationException(
                "User has reached maximum project limit of " . self::MAX_PROJECTS_PER_USER
            );
        }

        // Validate date logic
        if (
            isset($data['planned_start_date'], $data['planned_end_date'])
            && $data['planned_end_date'] < $data['planned_start_date']
        ) {
            throw new ProjectCreationException('End date cannot be before start date');
        }

        // Validate budget
        if (isset($data['budget']) && $data['budget'] < 0) {
            throw new ProjectCreationException('Budget cannot be negative');
        }
    }
}
```

### 2. Method Formatting Standards

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectCollection;
use App\Services\Project\ProjectManagementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends ApiController
{
    use ApiResponse;

    public function __construct(
        private readonly ProjectManagementService $projectService
    ) {
    }

    /**
     * Display a paginated list of projects.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $projects = $this->projectService->getPaginatedProjects($filters);

        return $this->success(
            data: new ProjectCollection($projects),
            message: 'Projects retrieved successfully'
        );
    }

    /**
     * Store a newly created project.
     */
    public function store(CreateProjectRequest $request): JsonResponse
    {
        try {
            $project = $this->projectService->createProject(
                data: $request->validated(),
                user: $request->user()
            );

            return $this->created(
                data: new ProjectResource($project),
                message: 'Project created successfully'
            );
        } catch (ProjectCreationException $e) {
            return $this->badRequest(
                message: $e->getMessage()
            );
        }
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return $this->success(
            data: new ProjectResource($project->load(['owner', 'members', 'tasks'])),
            message: 'Project retrieved successfully'
        );
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $updatedProject = $this->projectService->updateProject(
                project: $project,
                data: $request->validated(),
                user: $request->user()
            );

            return $this->success(
                data: new ProjectResource($updatedProject),
                message: 'Project updated successfully'
            );
        } catch (ProjectUpdateException $e) {
            return $this->badRequest(
                message: $e->getMessage()
            );
        }
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->deleteProject($project);

        return $this->success(
            message: 'Project deleted successfully'
        );
    }

    /**
     * Validate request filters for project listing.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'status' => 'sometimes|string|in:draft,active,completed,archived',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'owner_id' => 'sometimes|integer|exists:users,id',
            'department_id' => 'sometimes|integer|exists:departments,id',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:title,created_at,updated_at,priority',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
    }
}
```

## File Organization

### 1. Directory Structure

```
app/
├── Console/
│   ├── Commands/
│   └── Kernel.php
├── Exceptions/
│   ├── Business/
│   │   ├── ProjectCreationException.php
│   │   └── TaskAssignmentException.php
│   ├── Handler.php
│   └── CustomException.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── V1/
│   │   │   │   ├── ProjectController.php
│   │   │   │   └── TaskController.php
│   │   │   └── ApiController.php
│   │   └── Controller.php
│   ├── Middleware/
│   ├── Requests/
│   │   ├── Project/
│   │   │   ├── CreateProjectRequest.php
│   │   │   └── UpdateProjectRequest.php
│   │   └── BaseRequest.php
│   └── Resources/
│       ├── Project/
│       │   ├── ProjectResource.php
│       │   └── ProjectCollection.php
│       └── BaseResource.php
├── Models/
│   ├── Project.php
│   ├── Task.php
│   └── User.php
├── Repositories/
│   ├── Contracts/
│   │   ├── ProjectRepositoryInterface.php
│   │   └── BaseRepositoryInterface.php
│   ├── Eloquent/
│   │   ├── ProjectRepository.php
│   │   └── BaseRepository.php
│   └── RepositoryServiceProvider.php
├── Services/
│   ├── Project/
│   │   ├── ProjectManagementService.php
│   │   ├── ProjectAnalyticsService.php
│   │   └── ProjectNotificationService.php
│   ├── Auth/
│   │   ├── AuthenticationService.php
│   │   └── AuthorizationService.php
│   └── BaseService.php
└── Traits/
    ├── ApiResponse.php
    ├── HasUuid.php
    └── Auditable.php
```

### 2. Namespace Organization

```php
<?php

// Domain-specific organization
namespace App\Services\Project;
namespace App\Services\User;
namespace App\Services\Auth;

// Feature-specific organization
namespace App\Http\Controllers\Api\V1;
namespace App\Http\Resources\Project;
namespace App\Http\Requests\Project;

// Repository pattern organization
namespace App\Repositories\Contracts;
namespace App\Repositories\Eloquent;

// Exception organization
namespace App\Exceptions\Business;
namespace App\Exceptions\Infrastructure;
```

## Naming Conventions

### 1. Class Naming

```php
<?php

// Controllers - PascalCase with descriptive suffix
class ProjectController extends Controller {}
class ProjectApiController extends ApiController {}
class ProjectManagementController extends Controller {}

// Services - PascalCase with Service suffix
class ProjectManagementService {}
class UserAuthenticationService {}
class EmailNotificationService {}

// Models - PascalCase, singular noun
class Project extends Model {}
class User extends Model {}
class ProjectMember extends Model {}

// Requests - PascalCase with Request suffix
class CreateProjectRequest extends FormRequest {}
class UpdateUserProfileRequest extends FormRequest {}

// Resources - PascalCase with Resource suffix
class ProjectResource extends JsonResource {}
class UserCollection extends ResourceCollection {}

// Repositories - PascalCase with Repository suffix
class ProjectRepository extends BaseRepository {}
class EloquentProjectRepository implements ProjectRepositoryInterface {}

// Exceptions - PascalCase with Exception suffix
class ProjectNotFoundException extends Exception {}
class InvalidProjectStatusException extends BusinessException {}

// Jobs - PascalCase, descriptive action
class ProcessProjectData implements ShouldQueue {}
class SendProjectNotification implements ShouldQueue {}

// Events - PascalCase, past tense
class ProjectCreated {}
class UserRegistered {}
class TaskCompleted {}

// Listeners - PascalCase, descriptive action
class SendProjectCreatedNotification {}
class UpdateProjectStatistics {}
```

### 2. Method and Variable Naming

```php
<?php

class ProjectService
{
    // Methods - camelCase, descriptive verbs
    public function createProject(array $data): Project {}
    public function updateProjectStatus(Project $project, string $status): bool {}
    public function calculateProjectProgress(Project $project): float {}
    public function assignUserToProject(User $user, Project $project): void {}
    
    // Boolean methods - start with 'is', 'has', 'can', 'should'
    public function isProjectActive(Project $project): bool {}
    public function hasProjectPermission(User $user, Project $project): bool {}
    public function canUserEditProject(User $user, Project $project): bool {}
    public function shouldNotifyUser(User $user, string $event): bool {}
    
    // Private methods - camelCase with descriptive names
    private function validateProjectData(array $data): void {}
    private function calculateDuration(Carbon $start, Carbon $end): int {}
    
    // Variables - camelCase, descriptive
    private readonly ProjectRepository $projectRepository;
    private array $validationRules = [];
    private int $maxProjectsPerUser = 10;
    
    public function processProjects(): void
    {
        $activeProjects = $this->getActiveProjects();
        $completedTasksCount = $this->countCompletedTasks();
        $projectStatistics = $this->calculateStatistics();
        
        foreach ($activeProjects as $currentProject) {
            $projectOwner = $currentProject->owner;
            $estimatedCompletionDate = $this->calculateEstimatedCompletion($currentProject);
        }
    }
}
```

### 3. Constants and Configuration

```php
<?php

class ProjectConstants
{
    // Constants - SCREAMING_SNAKE_CASE
    public const MAX_PROJECTS_PER_USER = 10;
    public const DEFAULT_PROJECT_STATUS = 'draft';
    public const PROJECT_STATUS_ACTIVE = 'active';
    public const PROJECT_STATUS_COMPLETED = 'completed';
    
    // Array constants
    public const VALID_PROJECT_STATUSES = [
        'draft',
        'planning',
        'active',
        'on_hold',
        'completed',
        'cancelled',
        'archived',
    ];
    
    public const PRIORITY_LEVELS = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'urgent' => 4,
    ];
}

// Configuration keys - snake_case
// config/project.php
return [
    'max_projects_per_user' => 10,
    'default_status' => 'draft',
    'auto_assign_owner' => true,
    'notification_settings' => [
        'send_creation_email' => true,
        'send_status_updates' => true,
    ],
];
```

## Class Structure

### 1. Property and Method Order

```php
<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectManagementService
{
    // 1. Constants (public first, then private)
    public const MAX_PROJECTS_PER_USER = 10;
    private const CACHE_TTL = 3600;
    
    // 2. Properties (public, protected, private)
    public readonly string $serviceName;
    protected array $validationRules;
    private readonly ProjectRepositoryInterface $projectRepository;
    
    // 3. Constructor
    public function __construct(
        ProjectRepositoryInterface $projectRepository,
        NotificationService $notificationService
    ) {
        $this->projectRepository = $projectRepository;
        $this->serviceName = 'Project Management Service';
        $this->validationRules = $this->getValidationRules();
    }
    
    // 4. Public methods (grouped by functionality)
    // Project creation methods
    public function createProject(array $data, User $user): Project {}
    public function validateProjectCreation(array $data, User $user): void {}
    
    // Project retrieval methods
    public function getProject(int $id): Project {}
    public function getProjectsByUser(User $user): Collection {}
    public function getPaginatedProjects(array $filters): LengthAwarePaginator {}
    
    // Project update methods
    public function updateProject(Project $project, array $data): Project {}
    public function updateProjectStatus(Project $project, string $status): bool {}
    
    // Project deletion methods
    public function deleteProject(Project $project): bool {}
    public function softDeleteProject(Project $project): bool {}
    
    // 5. Protected methods
    protected function validateBusinessRules(array $data, User $user): void {}
    protected function prepareProjectData(array $data): array {}
    
    // 6. Private methods (grouped by functionality)
    private function getValidationRules(): array {}
    private function calculateProjectProgress(Project $project): float {}
    private function notifyStakeholders(Project $project, string $event): void {}
    
    // 7. Static methods (if any)
    public static function getDefaultProjectData(): array {}
}
```

### 2. Interface Definition

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    /**
     * Create a new project.
     *
     * @param array<string, mixed> $data
     * @return Project
     */
    public function create(array $data): Project;
    
    /**
     * Find project by ID.
     *
     * @param int $id
     * @return Project|null
     */
    public function find(int $id): ?Project;
    
    /**
     * Find project by ID or fail.
     *
     * @param int $id
     * @return Project
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Project;
    
    /**
     * Update existing project.
     *
     * @param Project $project
     * @param array<string, mixed> $data
     * @return Project
     */
    public function update(Project $project, array $data): Project;
    
    /**
     * Delete project.
     *
     * @param Project $project
     * @return bool
     */
    public function delete(Project $project): bool;
    
    /**
     * Get projects by owner.
     *
     * @param User $user
     * @return Collection<int, Project>
     */
    public function getByOwner(User $user): Collection;
    
    /**
     * Get paginated projects with filters.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    
    /**
     * Count projects by owner.
     *
     * @param User $user
     * @return int
     */
    public function countByOwner(User $user): int;
}
```

### 3. Abstract Base Classes

```php
<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class ApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, ApiResponse;
    
    /**
     * Items per page for pagination.
     */
    protected const DEFAULT_PER_PAGE = 15;
    protected const MAX_PER_PAGE = 100;
    
    /**
     * Get validated pagination parameters.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, int>
     */
    protected function getPaginationParams($request): array
    {
        return $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);
    }
    
    /**
     * Get validated sort parameters.
     *
     * @param \Illuminate\Http\Request $request
     * @param array<string> $allowedColumns
     * @return array<string, string>
     */
    protected function getSortParams($request, array $allowedColumns): array
    {
        return $request->validate([
            'sort_by' => 'sometimes|string|in:' . implode(',', $allowedColumns),
            'sort_order' => 'sometimes|string|in:asc,desc',
        ]);
    }
}
```

## Method Design

### 1. Method Responsibility

```php
<?php

// ✅ Good - Single responsibility
class ProjectService
{
    public function createProject(array $data, User $user): Project
    {
        $this->validateProjectData($data);
        $this->checkUserPermissions($user);
        
        return $this->projectRepository->create($data);
    }
    
    public function validateProjectData(array $data): void
    {
        $validator = Validator::make($data, $this->getValidationRules());
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
    
    public function checkUserPermissions(User $user): void
    {
        if (!$user->can('create-projects')) {
            throw new UnauthorizedException('User cannot create projects');
        }
    }
}

// ❌ Bad - Multiple responsibilities
class ProjectService
{
    public function createProjectAndSendEmails(array $data, User $user): Project
    {
        // Validation
        $validator = Validator::make($data, [...]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        // Permission check
        if (!$user->can('create-projects')) {
            throw new UnauthorizedException();
        }
        
        // Project creation
        $project = Project::create($data);
        
        // Email sending
        Mail::to($user)->send(new ProjectCreatedMail($project));
        Mail::to($project->members)->send(new ProjectNotificationMail($project));
        
        // Slack notification
        Http::post('slack-webhook', [...]);
        
        return $project;
    }
}
```

### 2. Parameter Design

```php
<?php

// ✅ Good - Typed parameters with clear names
class ProjectService
{
    public function assignUserToProject(
        User $user,
        Project $project,
        string $role = 'member',
        ?Carbon $startDate = null
    ): ProjectMember {
        return ProjectMember::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role,
            'joined_at' => $startDate ?? now(),
        ]);
    }
    
    public function updateProjectStatus(
        Project $project,
        ProjectStatus $status,
        User $updatedBy,
        string $reason = ''
    ): bool {
        return $project->update([
            'status' => $status->value,
            'status_updated_by' => $updatedBy->id,
            'status_updated_at' => now(),
            'status_change_reason' => $reason,
        ]);
    }
}

// ✅ Good - Value objects for complex parameters
class CreateProjectData
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ProjectStatus $status,
        public readonly ProjectPriority $priority,
        public readonly ?Money $budget = null,
        public readonly ?Carbon $startDate = null,
        public readonly ?Carbon $endDate = null,
    ) {
    }
    
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'budget' => $this->budget?->getAmount(),
            'start_date' => $this->startDate?->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
        ];
    }
}

class ProjectService
{
    public function createProject(CreateProjectData $data, User $owner): Project
    {
        return Project::create([
            ...$data->toArray(),
            'owner_id' => $owner->id,
        ]);
    }
}
```

### 3. Return Types

```php
<?php

// ✅ Good - Explicit return types
class ProjectAnalyticsService
{
    public function getProjectStatistics(int $projectId): ProjectStatistics
    {
        $project = Project::findOrFail($projectId);
        
        return new ProjectStatistics(
            totalTasks: $project->tasks()->count(),
            completedTasks: $project->tasks()->where('status', 'completed')->count(),
            overdueTasks: $project->tasks()->where('due_date', '<', now())->count(),
            progressPercentage: $this->calculateProgress($project),
        );
    }
    
    /**
     * @return Collection<int, Project>
     */
    public function getActiveProjects(): Collection
    {
        return Project::where('status', 'active')
            ->with(['owner', 'members'])
            ->get();
    }
    
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        return [
            'projects' => $this->getUserProjects($user),
            'tasks' => $this->getUserTasks($user),
            'statistics' => $this->getUserStatistics($user),
        ];
    }
}

// Value object for complex return types
readonly class ProjectStatistics
{
    public function __construct(
        public int $totalTasks,
        public int $completedTasks,
        public int $overdueTasks,
        public float $progressPercentage,
    ) {
    }
    
    public function getCompletionRate(): float
    {
        return $this->totalTasks > 0 
            ? ($this->completedTasks / $this->totalTasks) * 100 
            : 0;
    }
    
    public function hasOverdueTasks(): bool
    {
        return $this->overdueTasks > 0;
    }
}
```

## Error Handling

### 1. Custom Exception Hierarchy

```php
<?php

namespace App\Exceptions;

use Exception;

abstract class BusinessException extends Exception
{
    protected string $errorCode;
    protected array $context = [];
    
    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode ?: static::getDefaultErrorCode();
        $this->context = $context;
    }
    
    abstract protected static function getDefaultErrorCode(): string;
    
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
}

// Project-specific exceptions
namespace App\Exceptions\Project;

class ProjectNotFoundException extends BusinessException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'PROJECT_NOT_FOUND';
    }
}

class ProjectCreationException extends BusinessException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'PROJECT_CREATION_FAILED';
    }
}

class InvalidProjectStatusException extends BusinessException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'INVALID_PROJECT_STATUS';
    }
}

class ProjectPermissionException extends BusinessException
{
    protected static function getDefaultErrorCode(): string
    {
        return 'PROJECT_PERMISSION_DENIED';
    }
}
```

### 2. Exception Handling in Services

```php
<?php

namespace App\Services\Project;

use App\Exceptions\Project\ProjectCreationException;
use App\Exceptions\Project\ProjectNotFoundException;
use App\Exceptions\Project\InvalidProjectStatusException;

class ProjectManagementService
{
    public function createProject(array $data, User $user): Project
    {
        try {
            $this->validateProjectCreation($data, $user);
            
            return DB::transaction(function () use ($data, $user) {
                $project = $this->projectRepository->create([
                    ...$data,
                    'owner_id' => $user->id,
                ]);
                
                $this->notificationService->sendProjectCreatedNotification($project);
                
                return $project;
            });
            
        } catch (ValidationException $e) {
            throw new ProjectCreationException(
                message: 'Project validation failed',
                context: ['validation_errors' => $e->errors()]
            );
        } catch (QueryException $e) {
            Log::error('Database error during project creation', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            throw new ProjectCreationException(
                message: 'Failed to create project due to database error',
                context: ['user_id' => $user->id]
            );
        }
    }
    
    public function updateProjectStatus(Project $project, string $status, User $user): Project
    {
        if (!in_array($status, Project::VALID_STATUSES)) {
            throw new InvalidProjectStatusException(
                message: "Invalid project status: {$status}",
                context: [
                    'provided_status' => $status,
                    'valid_statuses' => Project::VALID_STATUSES,
                ]
            );
        }
        
        $previousStatus = $project->status;
        
        if (!$this->canTransitionStatus($previousStatus, $status)) {
            throw new InvalidProjectStatusException(
                message: "Cannot transition from {$previousStatus} to {$status}",
                context: [
                    'current_status' => $previousStatus,
                    'requested_status' => $status,
                    'project_id' => $project->id,
                ]
            );
        }
        
        try {
            $project->update([
                'status' => $status,
                'status_updated_by' => $user->id,
                'status_updated_at' => now(),
            ]);
            
            return $project->fresh();
            
        } catch (QueryException $e) {
            Log::error('Failed to update project status', [
                'project_id' => $project->id,
                'status' => $status,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            throw new ProjectCreationException(
                message: 'Failed to update project status',
                context: [
                    'project_id' => $project->id,
                    'status' => $status,
                ]
            );
        }
    }
    
    private function canTransitionStatus(string $from, string $to): bool
    {
        $validTransitions = [
            'draft' => ['planning', 'active', 'cancelled'],
            'planning' => ['active', 'on_hold', 'cancelled'],
            'active' => ['on_hold', 'completed', 'cancelled'],
            'on_hold' => ['active', 'cancelled'],
            'completed' => ['archived'],
            'cancelled' => ['archived'],
            'archived' => [],
        ];
        
        return in_array($to, $validTransitions[$from] ?? []);
    }
}
```

### 3. Global Exception Handler

```php
<?php

namespace App\Exceptions;

use App\Exceptions\BusinessException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Custom reporting logic
            if ($e instanceof BusinessException) {
                Log::warning('Business exception occurred', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                    'context' => $e->getContext(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
        
        $this->renderable(function (BusinessException $e, Request $request) {
            if ($request->expectsJson()) {
                return $this->renderBusinessException($e);
            }
        });
        
        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return $this->renderValidationException($e);
            }
        });
    }
    
    private function renderBusinessException(BusinessException $exception): JsonResponse
    {
        $status = $this->getHttpStatusForBusinessException($exception);
        
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'error_code' => $exception->getErrorCode(),
            'context' => $exception->getContext(),
            'timestamp' => now()->toISOString(),
        ], $status);
    }
    
    private function renderValidationException(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'error_code' => 'VALIDATION_FAILED',
            'errors' => $exception->errors(),
            'timestamp' => now()->toISOString(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    
    private function getHttpStatusForBusinessException(BusinessException $exception): int
    {
        return match ($exception->getErrorCode()) {
            'PROJECT_NOT_FOUND', 'USER_NOT_FOUND' => Response::HTTP_NOT_FOUND,
            'PROJECT_PERMISSION_DENIED', 'UNAUTHORIZED_ACCESS' => Response::HTTP_FORBIDDEN,
            'INVALID_PROJECT_STATUS', 'INVALID_INPUT' => Response::HTTP_BAD_REQUEST,
            'PROJECT_CREATION_FAILED', 'UPDATE_FAILED' => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
```

## Documentation Standards

### 1. PHPDoc Standards

```php
<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\User;
use App\Exceptions\Project\ProjectCreationException;
use Illuminate\Support\Collection;

/**
 * Service for managing project operations and business logic.
 * 
 * This service handles all project-related operations including creation,
 * updates, status management, and user assignments. It implements business
 * rules and validation logic while maintaining separation of concerns.
 * 
 * @package App\Services\Project
 * @author Development Team <dev@company.com>
 * @version 1.0.0
 * @since 2024-01-01
 */
final class ProjectManagementService
{
    /**
     * Maximum number of projects a user can own.
     */
    private const MAX_PROJECTS_PER_USER = 10;

    /**
     * Create a new project with comprehensive validation.
     * 
     * This method validates the project data, checks user permissions and limits,
     * creates the project record, and triggers necessary notifications. All
     * operations are wrapped in a database transaction for consistency.
     * 
     * @param array<string, mixed> $data Project creation data
     * @param User $user User creating the project
     * @return Project Newly created project instance
     * 
     * @throws ProjectCreationException When validation fails or business rules are violated
     * @throws \Illuminate\Database\QueryException When database operation fails
     * 
     * @example
     * ```php
     * $projectData = [
     *     'title' => 'New Website Project',
     *     'description' => 'Complete redesign of company website',
     *     'priority' => 'high',
     *     'budget' => 50000,
     *     'planned_start_date' => '2024-02-01',
     *     'planned_end_date' => '2024-06-30',
     * ];
     * 
     * $project = $projectService->createProject($projectData, $user);
     * ```
     * 
     * @see ProjectCreationException For exception details
     * @see User::can() For permission checking
     * @link https://docs.company.com/projects/creation Documentation
     */
    public function createProject(array $data, User $user): Project
    {
        // Implementation...
    }

    /**
     * Retrieve projects for a specific user with optional filters.
     * 
     * @param User $user The user whose projects to retrieve
     * @param array<string, mixed> $filters Optional filters (status, priority, etc.)
     * @param bool $includeArchived Whether to include archived projects
     * @return Collection<int, Project> Collection of user's projects
     * 
     * @since 1.0.0
     */
    public function getUserProjects(
        User $user, 
        array $filters = [], 
        bool $includeArchived = false
    ): Collection {
        // Implementation...
    }

    /**
     * Calculate project completion percentage based on tasks.
     * 
     * @param Project $project Project to calculate progress for
     * @return float Completion percentage (0.0 to 100.0)
     * 
     * @internal This method is used internally for progress calculations
     */
    private function calculateProjectProgress(Project $project): float
    {
        // Implementation...
    }
}
```

### 2. README Documentation

```markdown
# Project Management Service

## Overview

The Project Management Service handles all project-related operations including creation, updates, status management, and user assignments. It implements comprehensive business rules and validation logic.

## Features

- ✅ Project creation with validation
- ✅ Status management with transition rules
- ✅ User assignment and permission checking
- ✅ Progress tracking and analytics
- ✅ Notification integration
- ✅ Audit logging

## Installation

```bash
composer require company/project-management
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=project-config
```

Configure your `.env` file:

```env
PROJECT_MAX_PER_USER=10
PROJECT_DEFAULT_STATUS=draft
PROJECT_NOTIFICATIONS_ENABLED=true
```

## Usage

### Basic Usage

```php
use App\Services\Project\ProjectManagementService;

$projectService = app(ProjectManagementService::class);

// Create a new project
$project = $projectService->createProject([
    'title' => 'Website Redesign',
    'description' => 'Complete company website redesign',
    'priority' => 'high',
], $user);

// Update project status
$projectService->updateProjectStatus($project, 'active', $user);
```

### Advanced Usage

```php
// Get user projects with filters
$projects = $projectService->getUserProjects($user, [
    'status' => 'active',
    'priority' => 'high',
]);

// Calculate project statistics
$stats = $projectService->getProjectStatistics($project->id);
```

## API Reference

### Methods

| Method | Description | Parameters | Returns |
|--------|-------------|------------|---------|
| `createProject()` | Create new project | `array $data, User $user` | `Project` |
| `updateProject()` | Update existing project | `Project $project, array $data` | `Project` |
| `getUserProjects()` | Get user projects | `User $user, array $filters` | `Collection` |

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ProjectManagementTest.php

# Run with coverage
php artisan test --coverage
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request

## License

This package is licensed under the MIT License.
```

### 3. API Documentation

```php
<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @OA\Info(
 *     title="Project Management API",
 *     version="1.0.0",
 *     description="API for managing projects, tasks, and user assignments",
 *     @OA\Contact(
 *         email="api@company.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="https://api.company.com/v1",
 *     description="Production server"
 * )
 * 
 * @OA\Server(
 *     url="https://staging-api.company.com/v1",
 *     description="Staging server"
 * )
 */
class ProjectController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/projects",
     *     summary="Get paginated list of projects",
     *     description="Retrieve a paginated list of projects with optional filtering and sorting",
     *     operationId="getProjects",
     *     tags={"Projects"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by project status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "active", "completed", "archived"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projects retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Projects retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Project")
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Implementation...
    }

    /**
     * @OA\Post(
     *     path="/projects",
     *     summary="Create a new project",
     *     description="Create a new project with the provided data",
     *     operationId="createProject",
     *     tags={"Projects"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateProjectRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Project")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function store(CreateProjectRequest $request): JsonResponse
    {
        // Implementation...
    }
}

/**
 * @OA\Schema(
 *     schema="Project",
 *     type="object",
 *     description="Project model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Website Redesign"),
 *     @OA\Property(property="description", type="string", example="Complete redesign of company website"),
 *     @OA\Property(property="status", type="string", enum={"draft", "active", "completed", "archived"}),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}),
 *     @OA\Property(property="budget", type="number", format="float", example=50000.00),
 *     @OA\Property(property="progress_percentage", type="number", format="float", example=65.5),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="owner", ref="#/components/schemas/User")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CreateProjectRequest",
 *     type="object",
 *     required={"title", "description"},
 *     @OA\Property(property="title", type="string", maxLength=255, example="Website Redesign"),
 *     @OA\Property(property="description", type="string", example="Complete redesign of company website"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}, default="medium"),
 *     @OA\Property(property="budget", type="number", format="float", minimum=0, example=50000.00),
 *     @OA\Property(property="planned_start_date", type="string", format="date", example="2024-02-01"),
 *     @OA\Property(property="planned_end_date", type="string", format="date", example="2024-06-30")
 * )
 */
```

## Testing Standards

### 1. Test Structure

```php
<?php

namespace Tests\Feature\Services\Project;

use App\Models\Project;
use App\Models\User;
use App\Services\Project\ProjectManagementService;
use App\Exceptions\Project\ProjectCreationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectManagementServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    private ProjectManagementService $projectService;
    private User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->projectService = app(ProjectManagementService::class);
        $this->user = User::factory()->create();
    }
    
    /** @test */
    public function it_creates_project_successfully(): void
    {
        // Arrange
        $projectData = [
            'title' => 'Test Project',
            'description' => 'Test project description',
            'priority' => 'medium',
            'budget' => 10000,
        ];
        
        // Act
        $project = $this->projectService->createProject($projectData, $this->user);
        
        // Assert
        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals($projectData['title'], $project->title);
        $this->assertEquals($projectData['description'], $project->description);
        $this->assertEquals($this->user->id, $project->owner_id);
        $this->assertDatabaseHas('projects', [
            'title' => $projectData['title'],
            'owner_id' => $this->user->id,
        ]);
    }
    
    /** @test */
    public function it_throws_exception_when_user_exceeds_project_limit(): void
    {
        // Arrange
        Project::factory()->count(10)->create(['owner_id' => $this->user->id]);
        
        $projectData = [
            'title' => 'Excess Project',
            'description' => 'This should fail',
        ];
        
        // Act & Assert
        $this->expectException(ProjectCreationException::class);
        $this->expectExceptionMessage('User has reached maximum project limit');
        
        $this->projectService->createProject($projectData, $this->user);
    }
    
    /** @test */
    public function it_validates_project_dates(): void
    {
        // Arrange
        $projectData = [
            'title' => 'Test Project',
            'description' => 'Test description',
            'planned_start_date' => '2024-06-30',
            'planned_end_date' => '2024-02-01', // Invalid: end before start
        ];
        
        // Act & Assert
        $this->expectException(ProjectCreationException::class);
        $this->expectExceptionMessage('End date cannot be before start date');
        
        $this->projectService->createProject($projectData, $this->user);
    }
    
    /**
     * @test
     * @dataProvider projectStatusTransitionProvider
     */
    public function it_handles_status_transitions_correctly(
        string $fromStatus,
        string $toStatus,
        bool $shouldSucceed
    ): void {
        // Arrange
        $project = Project::factory()->create([
            'status' => $fromStatus,
            'owner_id' => $this->user->id,
        ]);
        
        // Act & Assert
        if ($shouldSucceed) {
            $result = $this->projectService->updateProjectStatus($project, $toStatus, $this->user);
            $this->assertTrue($result);
            $this->assertEquals($toStatus, $project->fresh()->status);
        } else {
            $this->expectException(InvalidProjectStatusException::class);
            $this->projectService->updateProjectStatus($project, $toStatus, $this->user);
        }
    }
    
    public static function projectStatusTransitionProvider(): array
    {
        return [
            'draft to active - valid' => ['draft', 'active', true],
            'draft to completed - invalid' => ['draft', 'completed', false],
            'active to completed - valid' => ['active', 'completed', true],
            'completed to active - invalid' => ['completed', 'active', false],
            'completed to archived - valid' => ['completed', 'archived', true],
        ];
    }
    
    /** @test */
    public function it_calculates_project_progress_correctly(): void
    {
        // Arrange
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        
        // Create tasks - 3 completed out of 5 total (60% completion)
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        Task::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);
        
        // Act
        $progress = $this->projectService->calculateProjectProgress($project);
        
        // Assert
        $this->assertEquals(60.0, $progress);
    }
    
    /** @test */
    public function it_filters_user_projects_correctly(): void
    {
        // Arrange
        $activeProject = Project::factory()->create([
            'owner_id' => $this->user->id,
            'status' => 'active',
            'priority' => 'high',
        ]);
        
        $completedProject = Project::factory()->create([
            'owner_id' => $this->user->id,
            'status' => 'completed',
            'priority' => 'medium',
        ]);
        
        // Different user's project (should not be included)
        Project::factory()->create([
            'status' => 'active',
            'priority' => 'high',
        ]);
        
        // Act
        $activeProjects = $this->projectService->getUserProjects($this->user, [
            'status' => 'active',
        ]);
        
        $highPriorityProjects = $this->projectService->getUserProjects($this->user, [
            'priority' => 'high',
        ]);
        
        // Assert
        $this->assertCount(1, $activeProjects);
        $this->assertTrue($activeProjects->contains($activeProject));
        $this->assertFalse($activeProjects->contains($completedProject));
        
        $this->assertCount(1, $highPriorityProjects);
        $this->assertTrue($highPriorityProjects->contains($activeProject));
    }
}
```

### 2. API Testing

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;
    
    private User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }
    
    /** @test */
    public function it_returns_paginated_projects(): void
    {
        // Arrange
        Project::factory()->count(25)->create(['owner_id' => $this->user->id]);
        
        // Act
        $response = $this->getJson('/api/v1/projects?per_page=10');
        
        // Assert
        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Projects retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'status',
                            'priority',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);
        
        $this->assertEquals(10, count($response->json('data.data')));
        $this->assertEquals(25, $response->json('data.total'));
    }
    
    /** @test */
    public function it_creates_project_successfully(): void
    {
        // Arrange
        $projectData = [
            'title' => 'New API Project',
            'description' => 'Created via API',
            'priority' => 'high',
            'budget' => 15000,
        ];
        
        // Act
        $response = $this->postJson('/api/v1/projects', $projectData);
        
        // Assert
        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => [
                    'title' => $projectData['title'],
                    'description' => $projectData['description'],
                    'priority' => $projectData['priority'],
                ],
            ]);
        
        $this->assertDatabaseHas('projects', [
            'title' => $projectData['title'],
            'owner_id' => $this->user->id,
        ]);
    }
    
    /** @test */
    public function it_validates_required_fields(): void
    {
        // Arrange
        $invalidData = [
            'description' => 'Missing title',
        ];
        
        // Act
        $response = $this->postJson('/api/v1/projects', $invalidData);
        
        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }
    
    /** @test */
    public function it_filters_projects_by_status(): void
    {
        // Arrange
        Project::factory()->count(5)->create([
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
        Project::factory()->count(3)->create([
            'owner_id' => $this->user->id,
            'status' => 'completed',
        ]);
        
        // Act
        $response = $this->getJson('/api/v1/projects?status=active');
        
        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(5, 'data.data');
        
        foreach ($response->json('data.data') as $project) {
            $this->assertEquals('active', $project['status']);
        }
    }
    
    /** @test */
    public function it_requires_authentication(): void
    {
        // Arrange
        Sanctum::actingAs(null); // Clear authentication
        
        // Act
        $response = $this->getJson('/api/v1/projects');
        
        // Assert
        $response->assertUnauthorized();
    }
    
    /** @test */
    public function it_shows_project_with_relationships(): void
    {
        // Arrange
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        Task::factory()->count(3)->create(['project_id' => $project->id]);
        
        // Act
        $response = $this->getJson("/api/v1/projects/{$project->id}");
        
        // Assert
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'owner' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'tasks' => [
                        '*' => [
                            'id',
                            'title',
                            'status',
                        ],
                    ],
                ],
            ]);
    }
}
```

### 3. Performance Testing

```php
<?php

namespace Tests\Performance;

use App\Models\Project;
use App\Models\User;
use App\Services\Project\ProjectManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPerformanceTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function it_handles_large_datasets_efficiently(): void
    {
        // Arrange
        $users = User::factory()->count(100)->create();
        $projects = Project::factory()->count(1000)->create();
        
        $service = app(ProjectManagementService::class);
        
        // Act & Assert - Should complete within reasonable time
        $startTime = microtime(true);
        
        $result = $service->getPaginatedProjects([
            'per_page' => 50,
            'sort_by' => 'created_at',
        ]);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within 1 second
        $this->assertLessThan(1.0, $executionTime);
        $this->assertEquals(50, $result->count());
    }
    
    /** @test */
    public function it_optimizes_database_queries(): void
    {
        // Arrange
        Project::factory()->count(20)->create();
        
        // Act & Assert - Track database queries
        $initialQueryCount = count(\DB::getQueryLog());
        \DB::enableQueryLog();
        
        $service = app(ProjectManagementService::class);
        $projects = $service->getProjectsWithOwners();
        
        $queries = \DB::getQueryLog();
        $queryCount = count($queries) - $initialQueryCount;
        
        // Should use eager loading to minimize queries
        $this->assertLessThanOrEqual(2, $queryCount); // 1 for projects, 1 for users
        $this->assertCount(20, $projects);
    }
}
```

## Security Guidelines

### 1. Authorization Policies

```php
<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine if the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['projects.view', 'projects.manage']);
    }
    
    /**
     * Determine if the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->owner_id
            || $project->members()->where('user_id', $user->id)->exists()
            || $user->hasPermission('projects.view-all');
    }
    
    /**
     * Determine if the user can create projects.
     */
    public function create(User $user): Response
    {
        if (!$user->hasPermission('projects.create')) {
            return Response::deny('You do not have permission to create projects.');
        }
        
        $projectCount = Project::where('owner_id', $user->id)->count();
        
        if ($projectCount >= config('project.max_projects_per_user')) {
            return Response::deny('You have reached the maximum number of projects allowed.');
        }
        
        return Response::allow();
    }
    
    /**
     * Determine if the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->id === $project->owner_id
            || ($project->members()->where('user_id', $user->id)->exists() 
                && $user->hasPermission('projects.edit-assigned'));
    }
    
    /**
     * Determine if the user can delete the project.
     */
    public function delete(User $user, Project $project): Response
    {
        if ($user->id !== $project->owner_id && !$user->hasPermission('projects.delete-any')) {
            return Response::deny('Only project owners can delete projects.');
        }
        
        if ($project->status === 'active' && $project->tasks()->count() > 0) {
            return Response::deny('Cannot delete active projects with tasks.');
        }
        
        return Response::allow();
    }
}
```

### 2. Input Validation

```php
<?php

namespace App\Http\Requests\Project;

use App\Rules\ValidProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }
    
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_]+$/', // Only alphanumeric, spaces, hyphens, underscores
            ],
            'description' => [
                'required',
                'string',
                'max:5000',
            ],
            'status' => [
                'sometimes',
                'string',
                new ValidProjectStatus(),
                Rule::in(['draft', 'planning']), // Only allow specific statuses for creation
            ],
            'priority' => [
                'sometimes',
                'string',
                Rule::in(['low', 'medium', 'high', 'urgent']),
            ],
            'budget' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:9999999.99',
            ],
            'planned_start_date' => [
                'sometimes',
                'date',
                'after_or_equal:today',
            ],
            'planned_end_date' => [
                'sometimes',
                'date',
                'after:planned_start_date',
            ],
            'department_id' => [
                'sometimes',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) {
                    $query->where('active', true);
                }),
            ],
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.regex' => 'The title may only contain letters, numbers, spaces, hyphens, and underscores.',
            'planned_end_date.after' => 'The end date must be after the start date.',
            'budget.max' => 'The budget cannot exceed $9,999,999.99.',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        // Sanitize input
        $this->merge([
            'title' => strip_tags($this->title),
            'description' => strip_tags($this->description),
        ]);
    }
}
```

### 3. Data Sanitization

```php
<?php

namespace App\Services\Security;

class DataSanitizer
{
    /**
     * Sanitize user input to prevent XSS and injection attacks.
     *
     * @param mixed $input
     * @return mixed
     */
    public static function sanitize($input)
    {
        if (is_string($input)) {
            return self::sanitizeString($input);
        }
        
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        
        return $input;
    }
    
    /**
     * Sanitize string input.
     */
    private static function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Remove or encode potentially dangerous characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        // Remove script tags
        $input = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $input);
        
        // Remove dangerous attributes
        $input = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $input);
        
        return trim($input);
    }
    
    /**
     * Sanitize SQL input to prevent injection.
     */
    public static function sanitizeSql(string $input): string
    {
        // Remove SQL comment markers
        $input = preg_replace('/--.*$/m', '', $input);
        $input = preg_replace('/\/\*.*?\*\//s', '', $input);
        
        // Remove dangerous SQL keywords
        $dangerous = ['DROP', 'DELETE', 'INSERT', 'UPDATE', 'EXEC', 'UNION', 'CREATE'];
        $pattern = '/\b(' . implode('|', $dangerous) . ')\b/i';
        $input = preg_replace($pattern, '', $input);
        
        return $input;
    }
}
```

## Performance Considerations

### 1. Database Optimization

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    /**
     * Get projects with optimized queries.
     */
    public function getPaginatedWithOptimization(array $filters = []): LengthAwarePaginator
    {
        return Project::query()
            ->select([
                'id',
                'title',
                'description',
                'status',
                'priority',
                'owner_id',
                'created_at',
                'updated_at'
            ])
            ->with([
                'owner:id,name,email', // Only load required fields
                'members' => function ($query) {
                    $query->select('user_id', 'project_id', 'role')
                          ->with('user:id,name,email');
                }
            ])
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->when($filters['priority'] ?? null, function (Builder $query, string $priority) {
                $query->where('priority', $priority);
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery->where('title', 'ILIKE', "%{$search}%")
                            ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
    
    /**
     * Get project statistics efficiently.
     */
    public function getStatistics(int $userId): array
    {
        return Project::where('owner_id', $userId)
            ->selectRaw('
                COUNT(*) as total_projects,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_projects,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_projects,
                AVG(CASE WHEN status = ? THEN 100 ELSE progress_percentage END) as avg_progress
            ', ['active', 'completed', 'completed'])
            ->first()
            ->toArray();
    }
    
    /**
     * Bulk update project statuses.
     */
    public function bulkUpdateStatus(array $projectIds, string $status): int
    {
        return Project::whereIn('id', $projectIds)
            ->update([
                'status' => $status,
                'status_updated_at' => now(),
            ]);
    }
}
```

### 2. Caching Strategy

```php
<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class ProjectCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const USER_PROJECTS_KEY = 'user_projects:';
    private const PROJECT_STATS_KEY = 'project_stats:';
    
    /**
     * Get user projects with caching.
     */
    public function getUserProjects(User $user, array $filters = []): Collection
    {
        $cacheKey = self::USER_PROJECTS_KEY . $user->id . ':' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return Project::where('owner_id', $user->id)
                ->when($filters['status'] ?? null, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->with(['owner', 'members'])
                ->get();
        });
    }
    
    /**
     * Get project statistics with caching.
     */
    public function getProjectStatistics(int $projectId): array
    {
        $cacheKey = self::PROJECT_STATS_KEY . $projectId;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($projectId) {
            $project = Project::with(['tasks'])->findOrFail($projectId);
            
            return [
                'total_tasks' => $project->tasks->count(),
                'completed_tasks' => $project->tasks->where('status', 'completed')->count(),
                'progress_percentage' => $this->calculateProgress($project),
                'estimated_completion' => $this->estimateCompletion($project),
            ];
        });
    }
    
    /**
     * Clear user-related caches.
     */
    public function clearUserCache(int $userId): void
    {
        $pattern = self::USER_PROJECTS_KEY . $userId . ':*';
        $keys = Cache::getRedis()->keys($pattern);
        
        if (!empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }
    
    /**
     * Clear project-related caches.
     */
    public function clearProjectCache(int $projectId): void
    {
        Cache::forget(self::PROJECT_STATS_KEY . $projectId);
        
        // Clear related user caches
        $project = Project::find($projectId);
        if ($project) {
            $this->clearUserCache($project->owner_id);
            
            foreach ($project->members as $member) {
                $this->clearUserCache($member->user_id);
            }
        }
    }
}
```

### 3. Query Optimization

```php
<?php

namespace App\Services\Performance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class QueryOptimizer
{
    /**
     * Add database indices for better performance.
     */
    public static function addIndices(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_projects_owner_status ON projects(owner_id, status)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_projects_created_at ON projects(created_at DESC)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_projects_priority_status ON projects(priority, status)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_project_members_user_project ON project_members(user_id, project_id)');
    }
    
    /**
     * Optimize query for better performance.
     */
    public static function optimizeProjectQuery(Builder $query): Builder
    {
        return $query
            ->select([
                'projects.id',
                'projects.title',
                'projects.status',
                'projects.priority',
                'projects.created_at',
                'users.name as owner_name',
                'users.email as owner_email'
            ])
            ->join('users', 'projects.owner_id', '=', 'users.id')
            ->where('projects.deleted_at', null)
            ->orderBy('projects.created_at', 'desc');
    }
    
    /**
     * Use raw queries for complex aggregations.
     */
    public static function getProjectStatisticsRaw(int $userId): array
    {
        $result = DB::selectOne("
            SELECT 
                COUNT(*) as total_projects,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_projects,
                ROUND(AVG(CASE WHEN status = 'completed' THEN 100 ELSE progress_percentage END), 2) as avg_progress,
                COUNT(CASE WHEN created_at >= NOW() - INTERVAL '30 days' THEN 1 END) as recent_projects
            FROM projects 
            WHERE owner_id = ? AND deleted_at IS NULL
        ", [$userId]);
        
        return (array) $result;
    }
}
```

## Code Review Process

### 1. Pre-commit Hooks

```bash
#!/bin/sh
# .git/hooks/pre-commit

echo "Running pre-commit checks..."

# Run PHP CS Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff
if [ $? != 0 ]; then
    echo "❌ PHP CS Fixer found issues. Please fix them before committing."
    exit 1
fi

# Run PHPStan
vendor/bin/phpstan analyse
if [ $? != 0 ]; then
    echo "❌ PHPStan found issues. Please fix them before committing."
    exit 1
fi

# Run tests
php artisan test
if [ $? != 0 ]; then
    echo "❌ Tests failed. Please fix them before committing."
    exit 1
fi

echo "✅ All checks passed!"
exit 0
```

### 2. Code Review Checklist

```markdown
# Code Review Checklist

## General Code Quality
- [ ] Code follows PSR-12 standards
- [ ] Variable and method names are descriptive
- [ ] No hardcoded values (use constants/config)
- [ ] Proper error handling implemented
- [ ] No commented-out code
- [ ] Code is DRY (Don't Repeat Yourself)

## Security
- [ ] Input validation implemented
- [ ] SQL injection protection in place
- [ ] XSS protection implemented
- [ ] Authorization checks present
- [ ] Sensitive data not logged
- [ ] Rate limiting considered

## Performance
- [ ] Database queries optimized
- [ ] N+1 queries avoided
- [ ] Appropriate caching implemented
- [ ] Large datasets paginated
- [ ] Eager loading used where beneficial

## Testing
- [ ] Unit tests cover new functionality
- [ ] Integration tests for API endpoints
- [ ] Edge cases tested
- [ ] Test coverage maintained/improved
- [ ] Tests are readable and maintainable

## Documentation
- [ ] PHPDoc comments added
- [ ] README updated if needed
- [ ] API documentation updated
- [ ] Changelog updated
- [ ] Migration scripts documented

## Laravel Best Practices
- [ ] Service classes used for business logic
- [ ] Repository pattern followed
- [ ] Form requests used for validation
- [ ] Policies used for authorization
- [ ] Jobs used for background tasks
- [ ] Events/Listeners for decoupling
```

## Tools & Automation

### 1. PHP CS Fixer Configuration

```php
<?php
// .php-cs-fixer.php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP80Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => false,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
    ])
    ->setFinder($finder);
```

### 2. PHPStan Configuration

```neon
# phpstan.neon
parameters:
    level: 8
    paths:
        - app/
        - database/
        - routes/
        - tests/
    
    excludePaths:
        - app/Console/Kernel.php
        - app/Exceptions/Handler.php
        - app/Http/Kernel.php
        - database/migrations/*
    
    ignoreErrors:
        - '#Call to an undefined method Illuminate\\Database\\Eloquent\\Builder::.*#'
        - '#Call to an undefined method Illuminate\\Database\\Query\\Builder::.*#'
    
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
    
    bootstrapFiles:
        - vendor/autoload.php
    
    symfony:
        container_xml_path: '%rootDir%/../../../bootstrap/cache/container.xml'
```

### 3. GitHub Actions Workflow

```yaml
# .github/workflows/laravel.yml
name: Laravel CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: testing
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432
      
      redis:
        image: redis:7
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 6379:6379

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_pgsql, redis
        coverage: xdebug

    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-

    - name: Install Dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Create Database
      run: |
        mkdir -p database
        touch database/database.sqlite

    - name: Execute tests (Unit and Feature tests) via PHPUnit
      env:
        DB_CONNECTION: pgsql
        DB_HOST: localhost
        DB_PORT: 5432
        DB_DATABASE: testing
        DB_USERNAME: postgres
        DB_PASSWORD: postgres
        REDIS_HOST: localhost
        REDIS_PORT: 6379
      run: vendor/bin/phpunit --coverage-clover=coverage.xml

    - name: Run PHP CS Fixer
      run: vendor/bin/php-cs-fixer fix --dry-run --diff

    - name: Run PHPStan
      run: vendor/bin/phpstan analyse

    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        fail_ci_if_error: true
```

### 4. IDE Configuration

```json
// .vscode/settings.json
{
    "php.suggest.basic": false,
    "php.validate.executablePath": "/usr/bin/php",
    "php.format.rules.arrayInitializersAlignKeyValuePairs": true,
    "php.format.rules.arrayInitializersWrapItems": "auto",
    
    "phpcs.enable": true,
    "phpcs.standard": "PSR12",
    "phpcs.executablePath": "./vendor/bin/phpcs",
    
    "phpcsfixer.executablePath": "./vendor/bin/php-cs-fixer",
    "phpcsfixer.onsave": true,
    
    "intelephense.stubs": [
        "apache",
        "bcmath",
        "bz2",
        "calendar",
        "com_dotnet",
        "Core",
        "ctype",
        "curl",
        "date",
        "dba",
        "dom",
        "enchant",
        "exif",
        "FFI",
        "fileinfo",
        "filter",
        "fpm",
        "ftp",
        "gd",
        "gettext",
        "gmp",
        "hash",
        "iconv",
        "imap",
        "intl",
        "json",
        "ldap",
        "libxml",
        "mbstring",
        "meta",
        "mysqli",
        "oci8",
        "odbc",
        "openssl",
        "pcntl",
        "pcre",
        "PDO",
        "pdo_ibm",
        "pdo_mysql",
        "pdo_pgsql",
        "pdo_sqlite",
        "pgsql",
        "Phar",
        "posix",
        "pspell",
        "readline",
        "Reflection",
        "session",
        "shmop",
        "SimpleXML",
        "snmp",
        "soap",
        "sockets",
        "sodium",
        "SPL",
        "sqlite3",
        "standard",
        "superglobals",
        "sysvmsg",
        "sysvsem",
        "sysvshm",
        "tidy",
        "tokenizer",
        "xml",
        "xmlreader",
        "xmlrpc",
        "xmlwriter",
        "xsl",
        "Zend OPcache",
        "zip",
        "zlib"
    ],
    
    "editor.formatOnSave": true,
    "editor.codeActionsOnSave": {
        "source.fixAll": true
    },
    
    "[php]": {
        "editor.defaultFormatter": "junstyle.php-cs-fixer",
        "editor.formatOnSave": true
    }
}
```

---

This comprehensive code standards document provides detailed guidelines for maintaining high-quality, secure, and performant Laravel backend code. Following these standards ensures consistency across the development team and facilitates easier maintenance and collaboration.