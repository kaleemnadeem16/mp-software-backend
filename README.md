# MP-Software Backend

> **Laravel 12+ API Backend with PostgreSQL 17+**

## 🚀 Quick Start

```bash
# Start the development server
php artisan serve

# Start the WebSocket server (Reverb)
php artisan reverb:start

# Run tests
php artisan test

# Run static analysis
vendor/bin/phpstan analyse

# Fix code formatting
vendor/bin/php-cs-fixer fix
```

## 📚 Documentation

All project documentation is located in the `docs/` folder:
- **[General Rules](docs/GENERAL_RULES.md)** - Must read before development
- **[Current Tasks](docs/project-journal/current-tasks.md)** - Active development tasks
- **[Iteration Log](docs/project-journal/iteration-log.md)** - Development history

## 🛠 Technology Stack

- **Framework**: Laravel 12.25.0
- **Database**: PostgreSQL 17.6
- **Authentication**: Laravel Sanctum 4.2.0
- **Authorization**: Spatie Laravel Permission 6.21.0
- **WebSockets**: Laravel Reverb 1.5.1
- **Excel Operations**: Maatwebsite Excel 3.1.66
- **Testing**: Pest 3.8.3
- **Static Analysis**: PHPStan 2.1.22 + Larastan 3.6.0

## 🗄 Database

- **Database Name**: `mp_software`
- **Connection**: PostgreSQL 17 on localhost:5432
- **Tables**: User authentication, permissions, roles, and more

## 🔧 Development Commands

```bash
# Database
php artisan migrate
php artisan migrate:refresh --seed

# Broadcasting
php artisan reverb:start

# Testing
php artisan test
php artisan test --filter=ExampleTest

# Code Quality
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run

# Laravel Utilities
php artisan route:list
php artisan config:clear
php artisan cache:clear
```

## 📋 Next Development Steps

1. Set up API routes and authentication middleware
2. Implement RBAC with roles and permissions
3. Create base API controllers
4. Add authentication endpoints (login, register, logout)
5. Implement comprehensive testing suite

---

**Last Updated**: December 2024  
**Laravel Version**: 12.25.0  
**Status**: Ready for API development

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
