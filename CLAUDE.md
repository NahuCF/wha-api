# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WHA-API is a multi-tenant WhatsApp Business API management platform built with Laravel 11. It provides SaaS capabilities for managing WhatsApp message templates, contacts, broadcasts, and integrations with Meta's WhatsApp Business API.

## Key Architecture

### Multi-Tenancy
- **Stancl/Tenancy v3.8**: Each tenant has isolated database and storage
- **Tenant Middleware**: Automatically initializes tenant context from domain/subdomain
- **Central vs Tenant**: Central DB manages tenants; tenant DBs contain isolated business data
- **Tenant Creation**: Async via queue jobs (see `app/Jobs/CreateTenant.php`)

### API Structure
- **Resource-based REST API**: All responses use Laravel Resources (`app/Http/Resources/`)
- **Authentication**: Laravel Passport OAuth2 with bearer tokens
- **Public Routes**: `routes/api/public.php` - registration, login, reference data
- **Tenant Routes**: `routes/api/tenant.php` - authenticated tenant operations

### Service Layer Pattern
Business logic is encapsulated in service classes under `app/Services/`:
- `TemplateService`: WhatsApp template management
- `ImportService`: Contact import processing
- `UserService`: User management with soft deletes

## Development Commands

```bash
# Start all development services (server, queue, logs, vite)
composer dev

# Run tests
vendor/bin/pest                    # Preferred test runner
php artisan test                    # Alternative

# Code quality
vendor/bin/pint                     # Fix code style
vendor/bin/pint --test             # Check style without fixing

# Database operations
php artisan migrate                 # Run central migrations
php artisan tenants:migrate        # Run tenant migrations
php artisan db:seed                # Seed reference data

# Queue processing (if not using composer dev)
php artisan queue:listen --tries=1
php artisan horizon                # Production queue monitoring

# Generate OAuth keys (first time setup)
php artisan passport:keys
php artisan passport:client --personal

# Tenant operations
php artisan tenants:list
php artisan tenants:run [command]  # Run command for all tenants
```

## Testing Approach

```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Feature/TenantTest.php

# Run with coverage
vendor/bin/pest --coverage

# Run specific test method
vendor/bin/pest --filter "test_example_method"
```

## Key Dependencies & Patterns

### Request Validation
- Form requests in `app/Http/Requests/` handle validation
- Custom validation rules in `app/Rules/`

### Job Queue System
- Tenant creation: `app/Jobs/CreateTenant.php`
- Contact imports: `app/Jobs/ImportJob.php`
- Email notifications: `app/Jobs/SendEmail.php`
- Monitor with Horizon in production

### Permissions System
- Spatie Laravel Permission package
- Roles and permissions seeded per tenant
- Check with `$user->hasPermissionTo('permission.name')`

### Excel Processing
- Spatie Simple Excel for imports/exports
- Async processing via queue jobs
- Custom field mapping for contact imports

## Database Structure

### Central Database
- `tenants`: Tenant records with domains
- `countries`, `currencies`, `timezones`, `industries`: Shared reference data
- `oauth_*`: Passport authentication tables

### Tenant Database
- `users`: Tenant users with soft deletes
- `templates`: WhatsApp message templates
- `contacts`: Customer records with custom fields
- `groups`, `broadcasts`: Campaign management
- `roles`, `permissions`: RBAC per tenant

## Environment Configuration

Critical environment variables:
```bash
# Multi-tenancy
TENANCY_DATABASE_MANAGER_MYSQL=tenant  # Tenant DB connection name

# Queue processing
QUEUE_CONNECTION=redis                  # Use Redis for production
HORIZON_PREFIX=horizon:                 # Horizon key prefix

# WhatsApp Business API (per tenant config)
# Stored in tenant settings, not env
```

## Common Development Tasks

### Adding New API Endpoint
1. Add route in `routes/api/tenant.php` or `routes/api/public.php`
2. Create controller in `app/Http/Controllers/Api/`
3. Create form request in `app/Http/Requests/`
4. Create resource in `app/Http/Resources/`
5. Add service logic in `app/Services/` if complex

### Working with Tenants
```php
// In tenant context (automatic via middleware)
$user = auth()->user();  // Gets tenant user

// Switch tenant context manually
tenancy()->initialize($tenant);

// Run for all tenants
tenancy()->runForMultiple($tenants, function ($tenant) {
    // Code runs in tenant context
});
```

### WhatsApp Template Management
- Templates stored with categories and languages
- Status tracking: pending, approved, rejected
- Versioning through soft deletes and updates
- Meta Business API integration for submission

## Security Considerations

- API rate limiting configured in `RouteServiceProvider`
- Tenant isolation enforced at database level
- Passport tokens with expiration
- Soft deletes for audit trail
- Permission-based access control