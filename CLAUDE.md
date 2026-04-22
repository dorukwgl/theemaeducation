# EMA Education Platform - Development Guide

**Claude Code Development Guide for this repository.**

## Project Overview

EMA Education Platform is a comprehensive web-based educational management system providing quiz/exam management, file distribution, user access control, and mobile app integration. The project uses PHP 8.0+ with PSR-4 autoloading on a custom lightweight framework.

## Current Status

**Completed Phases:**
- ✅ Phase 1: Infrastructure & Foundation (Security)
- ✅ Phase 2: Core Systems (Authentication, User Management, Access Control, File Management)
- ✅ Phase 3: Content Management (API Optimization, Quiz System, Notice System, Admin Features)
- ⏳ Phase 4-6: Future phases

## Architecture

**Framework Components:**
- **Router** (`src/core/Router.php`): Route matching, parameter extraction, middleware execution
- **Request** (`src/core/Request.php`): Unified access to query parameters, POST data, JSON, files
- **Response** (`src/core/Response.php`): JSON responses, file downloads, HTTP status codes

**Configuration:**
- **Config** (`src/config/config.php`): Environment variables, dot-notation config access
- **Database** (`src/config/database.php`): mysqli connection factory, prepared statements, transactions
- **Constants** (`src/config/constants.php`): HTTP codes, user roles, access types, table names

**Middleware:**
- **AuthMiddleware**: Session-based authentication, role checking, user context
- **CorsMiddleware**: Configurable CORS handling
- **RateLimitMiddleware**: Per-IP and per-user rate limiting
- **ValidationMiddleware**: Automatic input validation

## Key Patterns

### Authentication & Authorization
- Session-based authentication with 2-day inactivity timeout
- Admin-only endpoints use `new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])`
- Regular auth endpoints use `AuthMiddleware::class`
- Always validate CSRF tokens on state-changing operations

### Database Operations
- Use `Database::prepare()` for all queries to prevent SQL injection
- Use `Database::query()` for simple queries without parameters
- Always use parameter binding: `$stmt->bind_param()`
- Use transactions for multi-step operations: `Database::beginTransaction()`, `commit()`, `rollback()`

### Error Handling
- Use `try-catch` blocks for database and file operations
- Log errors with `Logger::error()` including context
- Use appropriate HTTP status codes: 200, 201, 400, 401, 403, 404, 422, 500
- Return consistent response format via `Response` methods

### Security Best Practices
- **Input Validation**: Always validate user input with `Validator::make()`
- **CSRF Protection**: Generate and verify tokens via `Security::generateCsrfToken()`, `verifyCsrfToken()`
- **Password Security**: Use `Security::hashPassword()`, `verifyPassword()`
- **Input Sanitization**: Use `Security` class for email, phone, etc.
- **Logging**: Use `Logger::securityEvent()` for security-relevant actions
- **File Uploads**: Validate MIME types, sizes, extensions; use secure filename generation

## Response Format

**Success Response:**
```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": []
}
```

**Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": "Error message"
  }
}
```

## File Organization

```
src/
├── config/         # Configuration classes (config.php, database.php, constants.php)
├── core/          # Framework components (Router.php, Request.php, Response.php)
├── middleware/     # Route middleware (Auth, CORS, RateLimit, Validation)
├── controllers/    # HTTP handlers for API endpoints
├── models/         # Data access layer
├── services/       # Business logic layer
└── utils/          # Utility classes (Logger, Security, Validator)

public/
└── index.php         # Main entry point, route definitions

database/
└── migrations/       # Database migration files

logs/              # Application logs (auto-created)
storage/           # Runtime storage (rate limits, login attempts)
uploads/           # User uploaded files
```

## Development Commands

```bash
# Install dependencies
composer install

# Run database migrations
mysql -u username -p database_name < database/migrations/2025_04_21_phase_3_4_admin_features.sql

# Run tests
composer test

# Regenerate autoloader
composer dump-autoload

# Check PHP syntax
php -l path/to/file.php
```

## Quick Reference

**Controller Pattern:**
```php
class ExampleController {
    private Request $request;
    private Response $response;

    public function __construct() {
        $this->request = new Request();
        $this->response = new Response();
    }

    public function exampleMethod(): void {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Your logic here
            $data = ['result' => 'value'];

            $this->response->success('Success message', $data);
        } catch (\Exception $e) {
            Logger::error('Operation failed', [
                'error' => $e->getMessage()
            ]);
            $this->response->error('Error message', 500);
        }
    }
}
```

**Model Pattern:**
```php
class Example {
    public static function findByExample(int $id): ?array {
        $query = "SELECT * FROM table WHERE id = ?";
        $stmt = Database::prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }
}
```

**Service Pattern:**
```php
class ExampleService {
    public function processExample(array $data): array {
        // Validation
        $validation = Validator::make($data, [
            'field' => 'required|rule'
        ]);

        if (!$validation->validate()) {
            return ['success' => false, 'errors' => $validation->getErrors()];
        }

        // Business logic
        return ['success' => true, 'data' => $result];
    }
}
```

## Important Notes

- All database queries use prepared statements for SQL injection prevention
- Session timeout is 2 days (172800 seconds)
- CSRF tokens required on all POST/PUT/DELETE operations
- File uploads are validated for MIME type, size, and extension
- Rate limiting is applied to sensitive endpoints
- All admin actions are logged for audit purposes
- Use `EMA\Config\Constants::ROLE_ADMIN` for admin role references

## graphify

This project has a graphify knowledge graph at graphify-out/.

**Rules:**
- Before answering architecture or codebase questions, read graphify-out/GRAPH_REPORT.md for god nodes and community structure
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)