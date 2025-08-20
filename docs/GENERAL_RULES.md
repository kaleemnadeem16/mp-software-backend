# 🚀 MP-Software Laravel Backend - General Development Rules

> **CRITICAL**: This file MUST be referenced at the beginning of every development session and chat conversation. No development work should begin without reviewing these rules.

## 📋 Table of Contents

- [Core Development Principles](#core-development-principles)
- [Technology Stack Rules](#technology-stack-rules)
- [Task Management & Logging](#task-management--logging)
- [Documentation Standards](#documentation-standards)
- [Code Quality Standards](#code-quality-standards)
- [Security Requirements](#security-requirements)
- [Performance Standards](#performance-standards)
- [Testing Requirements](#testing-requirements)
- [Git Workflow Rules](#git-workflow-rules)
- [Communication & Collaboration](#communication--collaboration)

---

## 🎯 Core Development Principles

### 1. **ALWAYS START WITH RULES**
- 🔴 **MANDATORY**: Reference this file at the beginning of every chat/session
- 🔴 **MANDATORY**: Review current task status in `docs/project-journal/`
- 🔴 **MANDATORY**: Update task logs after every iteration

### 2. **TASK MANAGEMENT FLOW**
```
1. Review GENERAL_RULES.md
2. Check current task in project-journal/current-tasks.md
3. Update task-tree.md with any deviations
4. Implement solution
5. Log iteration in iteration-log.md
6. Update documentation
7. Commit with proper message
```

### 3. **DEVIATION HANDLING**
- 🟡 **When a task leads to other prerequisites:**
  - Log the deviation in `task-tree.md`
  - Create sub-tasks for prerequisites
  - Maintain parent-child relationship
  - Never lose track of original goal

### 4. **DOCUMENTATION-FIRST APPROACH**
- All features must be documented BEFORE implementation
- All changes must update relevant documentation
- All iterations must be logged with context

---

## 🛠 Technology Stack Rules

### **Fixed Technology Stack** (NO CHANGES WITHOUT APPROVAL)
```yaml
Backend Framework: Laravel 12+ (Latest)
Database: PostgreSQL 17+ (Latest)
Authentication: Laravel Sanctum
Authorization: Spatie Laravel Permission (RBAC)
WebSockets: Laravel Reverb
Performance: Laravel Octane
Cache/Sessions: Redis
Excel Operations: Maatwebsite/Laravel-Excel
API Documentation: OpenAPI/Swagger
Testing: PHPUnit + Pest
Code Standards: PSR-12
Static Analysis: PHPStan Level 8
```

### **Development Environment**
- PHP 8.2+
- Composer for dependency management
- Node.js for frontend assets (if needed)
- PostgreSQL 17+ as primary database
- Redis for caching and sessions

### **Project Structure**
- Laravel backend in `backend/` folder
- Frontend in `frontend/` folder (separate project)
- Focus exclusively on backend development
- Documentation moves to `backend/docs/` after Laravel installation

---

## 📝 Task Management & Logging

### **Before Starting Any Task**
```markdown
□ Review GENERAL_RULES.md
□ Check current-tasks.md
□ Understand task context
□ Identify potential dependencies
□ Search for latest documentation if unfamiliar with features
□ Plan approach
```

### **Documentation Research Rule**
- **ALWAYS** search for latest documentation when implementing new features
- **NO ASSUMPTIONS** about API changes or deprecated methods
- **VERIFY** compatibility with Laravel 12+ and PostgreSQL 17+
- **CHECK** official docs for breaking changes
- **USE** web search tools when documentation access is limited

### **Task Logging Rules**

#### 1. **During Task Execution**
```markdown
□ Log any deviations immediately
□ Update task-tree.md with sub-tasks
□ Document decisions and reasoning
□ Track time and progress
```

#### 2. **After Task Completion**
```markdown
□ Update iteration-log.md
□ Mark task as complete
□ Update related documentation
□ Plan next iteration
□ Commit changes with proper message
```

### **Task Documentation Structure**
```
docs/project-journal/
├── current-tasks.md          # Active tasks and status
├── task-tree.md             # Task hierarchy and dependencies
├── iteration-log.md         # Detailed iteration history
├── decisions.md             # Architecture and design decisions
├── blockers.md              # Current blockers and solutions
└── retrospectives.md        # Learning and improvements
```

---

## 📚 Documentation Standards

### **Documentation Hierarchy**
```
docs/
├── GENERAL_RULES.md         # This file - ALWAYS reference first
├── README.md                # Project overview and navigation
├── implementation/          # Technical implementation guides
│   ├── 01-authentication-security.md
│   ├── 02-rbac.md
│   ├── 03-api-standards.md
│   ├── 04-database-standards.md
│   ├── 05-websockets.md
│   ├── 06-excel-operations.md
│   ├── 07-performance.md
│   ├── 08-code-standards.md
│   ├── 09-security-standards.md
│   └── 10-performance-optimization.md
├── project-journal/         # Development tracking and logs
│   ├── current-tasks.md
│   ├── task-tree.md
│   ├── iteration-log.md
│   ├── decisions.md
│   ├── blockers.md
│   └── retrospectives.md
├── api/                     # API documentation
│   ├── endpoints.md
│   ├── authentication.md
│   ├── responses.md
│   └── examples.md
├── deployment/              # Deployment and infrastructure
│   ├── environments.md
│   ├── server-setup.md
│   ├── database-setup.md
│   └── monitoring.md
└── assets/                  # Images, diagrams, etc.
    ├── architecture/
    ├── workflows/
    └── screenshots/
```

### **Documentation Update Rules**

#### 🔴 **MANDATORY Updates**
- After every feature implementation
- After every bug fix
- After every configuration change
- After every architectural decision

#### 🟡 **RECOMMENDED Updates**
- After every refactoring
- After every performance optimization
- After every security enhancement

#### **Documentation Quality Standards**
1. **Clear and Actionable**: Every doc must be implementable
2. **Up-to-date**: No outdated information
3. **Examples Included**: Code examples for complex concepts
4. **Cross-referenced**: Proper linking between related docs
5. **Version Controlled**: Track documentation changes

---

## ⚡ Code Quality Standards

### **Core Technology Components**
- Laravel 12+ (core backend framework - latest version)
- Laravel Sanctum (API token authentication)
- Spatie Laravel Permission (RBAC)
- Laravel Reverb (WebSockets)
- Maatwebsite/Laravel-Excel (Excel import/export)
- Laravel Octane (performance)
- PostgreSQL 17+ (primary database - latest version)
- Redis (caching, session, queue)

### **Code Standards (NON-NEGOTIABLE)**
- PSR-12 compliance (enforced by PHP CS Fixer)
- PHPStan Level 8 (no errors allowed)
- 100% type declarations
- Comprehensive PHPDoc comments
- SOLID principles adherence

### **File Organization Rules**
```php
// Controllers: Single responsibility, thin controllers
app/Http/Controllers/Api/V1/

// Services: Business logic
app/Services/{Domain}/

// Repositories: Data access layer
app/Repositories/{Entity}/

// Models: Eloquent models with relationships
app/Models/

// Requests: Form validation
app/Http/Requests/{Entity}/

// Resources: API response formatting
app/Http/Resources/{Entity}/

// Exceptions: Custom exceptions
app/Exceptions/{Domain}/
```

### **Route Organization Rules (MANDATORY)**
```php
// Central route file structure for scalability
routes/
├── api.php              # Main API routes file (delegating only)
├── api/                 # API route modules
│   ├── v1/
│   │   ├── auth.php     # Authentication routes
│   │   ├── rbac.php     # Role & permission routes
│   │   ├── users.php    # User management routes
│   │   ├── projects.php # Project routes
│   │   └── reports.php  # Report routes
│   └── v2/              # Future API versions
└── web.php              # Web routes (minimal for API-only)

// Route file naming: {feature}.php
// Max 50 routes per file
// Use route groups for middleware and prefixes
// Include route caching for production
```

### **Route File Structure Template**
```php
<?php
// routes/api/v1/feature.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\FeatureController;

// Group with middleware and prefix
Route::middleware(['auth:sanctum'])->prefix('feature')->group(function () {
    // RESTful routes
    Route::apiResource('items', FeatureController::class);
    
    // Custom routes
    Route::post('items/{item}/action', [FeatureController::class, 'action'])
        ->middleware('permission:feature.action');
});
```

### **Naming Conventions (STRICT)**
```php
// Classes: PascalCase
class ProjectManagementService {}

// Methods: camelCase with descriptive verbs
public function createProject() {}
public function updateProjectStatus() {}

// Variables: camelCase, descriptive
$activeProjects = [];
$userPermissions = [];

// Constants: SCREAMING_SNAKE_CASE
const MAX_PROJECTS_PER_USER = 10;

// Database: snake_case
projects, project_members, user_roles
```

---

## 🔐 Security Requirements

### **Authentication & Authorization**
- Laravel Sanctum for API authentication
- Spatie Laravel Permission for RBAC
- Rate limiting on all API endpoints
- Input validation on all requests
- SQL injection prevention (use Eloquent/Query Builder)

### **Data Protection**
- All sensitive data encrypted
- No hardcoded secrets in code
- Environment-based configuration
- Audit logging for sensitive operations
- GDPR compliance considerations

### **Security Headers**
```php
// Required headers for all API responses
'X-Content-Type-Options' => 'nosniff',
'X-Frame-Options' => 'DENY',
'X-XSS-Protection' => '1; mode=block',
'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
```

---

## 🚀 Performance Standards

### **Database Performance**
- All queries must use proper indexing
- N+1 query prevention (eager loading)
- Query optimization with `EXPLAIN ANALYZE`
- Connection pooling configuration
- Regular performance monitoring

### **Caching Strategy**
```php
// Cache layers (in order)
1. Redis (application cache)
2. Database query cache
3. HTTP response cache (for static content)
4. CDN (for assets)
```

### **Response Time Targets**
- API endpoints: < 200ms (95th percentile)
- Database queries: < 50ms average
- WebSocket connections: < 100ms latency
- File uploads: Progress tracking for > 1MB

---

## ✅ Testing Requirements

### **Test Coverage Standards**
- Unit tests: 90%+ coverage
- Feature tests: All API endpoints
- Integration tests: Critical workflows
- Performance tests: Load testing for key endpoints

### **Test Structure**
```php
tests/
├── Unit/           # Unit tests for services, repositories
├── Feature/        # API endpoint tests
├── Integration/    # Multi-component tests
└── Performance/    # Load and stress tests
```

### **Testing Rules**
- Write tests BEFORE implementation (TDD)
- All tests must pass before merging
- No mocking of Laravel framework components
- Use factories for test data generation

---

## 🌿 Git Workflow Rules

### **Branch Strategy**
```
main           # Production-ready code
develop        # Integration branch
feature/*      # Feature development
hotfix/*       # Critical fixes
release/*      # Release preparation
```

### **Commit Message Format**
```
type(scope): description

feat(auth): implement JWT token refresh mechanism
fix(api): resolve user permission validation bug
docs(rbac): update role assignment documentation
refactor(db): optimize project query performance
test(api): add integration tests for project endpoints
```

### **Pre-commit Requirements**
- PHP CS Fixer (code formatting)
- PHPStan analysis (no errors)
- All tests passing
- Documentation updated

---

## 🤝 Communication & Collaboration

### **Development Session Protocol**
1. **Start**: Reference GENERAL_RULES.md
2. **Plan**: Review current tasks and dependencies
3. **Implement**: Follow standards and document decisions
4. **Review**: Update logs and documentation
5. **Commit**: Proper commit messages and tags

### **Decision Documentation**
- All architectural decisions in `decisions.md`
- Reasoning behind technology choices
- Trade-offs and alternatives considered
- Impact assessment

### **Issue Reporting**
```markdown
## Issue Template
**Type**: Bug/Feature/Enhancement
**Priority**: Critical/High/Medium/Low
**Component**: Auth/API/Database/etc.
**Description**: Clear description
**Steps to Reproduce**: Numbered steps
**Expected Result**: What should happen
**Actual Result**: What actually happens
**Environment**: Development/Staging/Production
**Related Tasks**: Link to task-tree.md
```

---

## 🔄 Iteration and Improvement

### **After Every Iteration**
1. Update `iteration-log.md` with:
   - What was accomplished
   - What challenges were faced
   - What decisions were made
   - What's next

2. Update `task-tree.md` with:
   - Completed tasks
   - New dependencies discovered
   - Modified priorities

3. Review and update documentation:
   - Implementation guides
   - API documentation
   - Code comments

### **Weekly Reviews**
- Review `retrospectives.md`
- Assess progress against goals
- Identify process improvements
- Update project timeline

---

## 🚨 Critical Reminders

### **NEVER START WITHOUT:**
- [ ] Reading GENERAL_RULES.md
- [ ] Checking current-tasks.md
- [ ] Understanding the current context
- [ ] Planning the approach

### **NEVER FINISH WITHOUT:**
- [ ] Updating task logs
- [ ] Updating documentation
- [ ] Running tests
- [ ] Committing changes
- [ ] Planning next steps

### **ALWAYS REMEMBER:**
- Quality over speed
- Documentation is code
- Test everything
- Security is not optional
- Performance matters
- Maintainability is key

---

## 📞 Emergency Contacts

**For Critical Issues:**
- Database problems: Check `blockers.md`
- Security concerns: Follow security protocols
- Performance degradation: Review performance docs
- Task confusion: Refer to `task-tree.md`

---

**Last Updated**: December 2024
**Version**: 1.1.0 (Laravel 12+ / PostgreSQL 17+)
**Next Review**: Every 30 days or after major changes

---

> **Remember**: These rules exist to ensure quality, maintainability, and team collaboration. They are not suggestions - they are requirements for professional software development.