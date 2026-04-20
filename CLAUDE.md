# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

EMA Education Platform is a comprehensive web-based educational management system providing quiz/exam management, file distribution, user access control, and mobile app integration. The project is currently being refactored from legacy PHP files to a modern, structured architecture using PHP 8.0+ with PSR-4 autoloading.

## Implementation Progress

**Phase 1: Infrastructure & Foundation (Security)** (Week 1) - ✅ **COMPLETED**

### ✅ Completed Components (April 20, 2026)
- **1.1 Project Structure Setup**: All directories created with proper PSR-4 namespace structure
- **1.2 Core Configuration System**: Environment-based config with dotenv, centralized settings management
- **1.3 Database Layer**: Connection pooling, prepared statements, transaction management
- **1.4 Security Framework**: Complete security implementation including:
  - `src/utils/Logger.php` - Monolog-based logging with rotation and sensitive data filtering
  - `src/utils/Security.php` - CSRF, password hashing, input sanitization, XSS protection
  - `src/utils/Validator.php` - Comprehensive validation framework with 20+ rule types
  - `src/middleware/CorsMiddleware.php` - Configurable CORS handling
  - `src/middleware/RateLimitMiddleware.php` - Per-IP and per-user rate limiting
  - `src/middleware/AuthMiddleware.php` - Session-based authentication with role checking
  - `src/middleware/ValidationMiddleware.php` - Automatic input validation

### 🔄 Phase 2: Core Systems (Week 2) - IN PROGRESS
- 2.1 Authentication System - ✅ **COMPLETED**
- 2.2 User Management
- 2.3 Access Control System
- 2.4 File Management

#### ✅ Phase 2.1: Authentication System (April 20, 2026)
Complete session-based authentication implementation:

**Models Created:**
- `src/models/User.php` - User model with CRUD operations, password verification, email/phone existence checks

**Services Created:**
- `src/services/AuthService.php` - Authentication business logic including:
  - Login with attempt tracking and IP lockout (5 failed attempts = 15 min lockout)
  - User registration with validation
  - Logout with session cleanup
  - Password reset flow (request + completion)
  - Password change for authenticated users
  - Session timeout handling (2 days inactivity)
  - Login attempt tracking with file-based storage in `storage/login_attempts/`

**Controllers Created:**
- `src/controllers/AuthController.php` - HTTP endpoints for all authentication operations

**Updated Components:**
- `src/middleware/AuthMiddleware.php` - Session timeout updated to 2 days (172800 seconds)

**Features Implemented:**
- Secure password hashing (bcrypt cost 12)
- Session-based authentication with 2-day inactivity timeout
- Login attempt tracking with IP lockout
- Complete password reset flow
- Comprehensive input validation and rate limiting
- Full security logging

**API Endpoints:**
- `POST /api/auth/login` - User login (20 attempts/30min per IP)
- `POST /api/auth/register` - User registration
- `POST /api/auth/logout` - User logout
- `POST /api/auth/forgot-password` - Request password reset (10 requests/hour)
- `POST /api/auth/reset-password` - Complete password reset (15 attempts/hour)
- `POST /api/auth/change-password` - Change password (authenticated)
- `GET /api/auth/me` - Get current user info

### 📋 Phase 3: Content Management (Week 3)
- 3.1 Folder System
- 3.2 Quiz System
- 3.3 Notice System
- 3.4 Admin Features

### 📋 Phase 4: API Implementation (Week 4)
- 4.1 API Contract Preservation
- Legacy route compatibility

### 📋 Phase 5: Performance Optimization (Week 5)
- 5.1 Database Optimization
- 5.2 Caching Strategy
- 5.3 Resource Optimization
- 5.4 cPanel-Specific Optimizations

### 📋 Phase 6: Testing & Deployment (Week 6)
- 6.1 Testing
- 6.2 Deployment
- 6.3 Documentation

## Architecture

### Core Framework Components

The application uses a custom lightweight framework built around these core components:

- **Router** (`src/core/Router.php`): Handles route matching, parameter extraction, and middleware execution. Supports named routes and HTTP method-specific routing (GET, POST, PUT, DELETE, OPTIONS).
- **Request** (`src/core/Request.php`): Provides unified access to request data from query parameters, POST data, JSON body, and files. Includes IP sanitization, CSRF token validation, and header parsing.
- **Response** (`src/core/Response.php`): Handles HTTP responses with JSON formatting, file downloads, streaming, CORS headers, and various HTTP status codes.

### Configuration System

Configuration is managed through environment variables and a central Config class:

- **Config** (`src/config/config.php`): Loads environment variables using `vlucas/phpdotenv`, provides dot-notation access to nested config values. Includes app, database, security, CORS, rate limiting, upload, logging, cache, and session configurations.
- **Database** (`src/config/database.php`): Singleton connection manager using mysqli. Supports prepared statements, transactions, and connection pooling. Includes connection tracking and error logging.
- **Constants** (`src/config/constants.php`): Centralized constants for HTTP status codes, user roles, access types, database table names, file paths, error messages, and other application-wide values.

### Directory Structure

```
src/
├── config/         # Configuration classes
│   ├── config.php      # Centralized configuration (✅)
│   ├── database.php    # Database connection factory (✅)
│   └── constants.php   # Application constants (✅)
├── core/          # Framework components (Router, Request, Response)
│   ├── Router.php      # Request routing (✅)
│   ├── Request.php     # Request handling (✅)
│   └── Response.php    # Response formatting (✅)
├── middleware/     # Route middleware
│   ├── AuthMiddleware.php       # Authentication (✅)
│   ├── CorsMiddleware.php       # CORS handling (✅)
│   ├── RateLimitMiddleware.php  # Rate limiting (✅)
│   └── ValidationMiddleware.php # Input validation (✅)
├── controllers/    # Application controllers
│   ├── AuthController.php   # Authentication endpoints (✅)
│   ├── UserController.php   # User management (to be implemented)
│   ├── FolderController.php # Folder management (to be implemented)
│   ├── FileController.php   # File management (to be implemented)
│   ├── QuizController.php   # Quiz management (to be implemented)
│   ├── AdminController.php  # Admin operations (to be implemented)
│   ├── AccessController.php # Access control (to be implemented)
│   └── SystemController.php # System operations (to be implemented)
├── models/        # Data models
│   └── User.php          # User model (✅)
├── services/      # Business logic services
│   └── AuthService.php   # Authentication logic (✅)
└── utils/         # Utility classes
    ├── Logger.php      # Logging system (✅)
    ├── Security.php    # Security utilities (✅)
    └── Validator.php  # Input validation (✅)

storage/
├── rate_limits/   # Rate limiting data storage (✅)
└── login_attempts/ # Login attempt tracking (✅)

logs/              # Application logs (auto-created)
├── app.log        # Main application log
├── error.log      # Error log
└── access.log     # Security audit log
```

## Development Commands

### Dependency Management
```bash
composer install          # Install dependencies
composer update           # Update dependencies
composer dump-autoload    # Regenerate autoloader
```

### Testing
```bash
composer test             # Run PHPUnit tests
```

### Database
```bash
composer migrate          # Run database migrations
```

### Environment Setup
1. Copy `.env.example` to `.env`
2. Configure environment variables for your local setup
3. Set up database connection credentials
4. Run database migrations to create tables

## Key Architectural Decisions

### PSR-4 Autoloading
All classes follow PSR-4 autoloading with namespace `EMA\`. Classes are autoloaded from the `src/` directory. Example: `EMA\Config\Config` maps to `src/config/Config.php`.

### Request/Response Pattern
The framework uses a centralized Request and Response pattern. Controllers receive Request objects and should use Response methods for consistent API responses. All API responses use JSON format with `success`, `message`, and optional `data` or `errors` fields.

### Database Layer
Database access uses mysqli with prepared statements. The Database class provides static methods for connection management, prepared statements, and transactions. All database queries should use prepared statements to prevent SQL injection.

### Configuration Management
Environment variables are loaded via `vlucas/phpdotenv`. Required environment variables are validated on startup. Configuration values should be accessed via `Config::get('key.path')` with dot notation.

### File Upload Handling
File uploads are configured through environment variables (max size, allowed types, upload path). The system supports multiple file types (images, documents, audio, video). File paths are stored in the database while actual files are stored in the `uploads/` directory with organized subdirectories.

### Access Control System
The platform implements a complex access control system with:
- **Access types**: `all` (public), `logged_in` (authenticated users only)
- **User roles**: `user`, `admin`
- **Item types**: `file`, `quiz_set`
- **Granular permissions**: Individual user access, role-based access, admin overrides
- **Access tracking**: Limits on how many times content can be accessed

### Quiz System Architecture
The quiz system supports:
- **Multiple choice questions** (A, B, C, D options)
- **Multimedia content**: Text, images, audio for questions and answers
- **Word formatting**: JSON-based formatting for bold, underline text
- **Question types**: Reading, Listening
- **Quiz sets**: Hierarchical organization within folders

## Domain Model

### Core Entities
- **Users**: Authentication, profiles, roles (user/admin)
- **Folders**: Hierarchical organization of content with icons
- **Files**: Educational materials with access control and folder organization
- **Quiz Sets**: Collections of questions with access control
- **Questions**: Individual quiz items with multimedia content and multiple choice answers
- **Access Permissions**: Granular access control with usage tracking
- **Notices**: System announcements with file attachments
- **Admin Users**: Administrative user assignments

### Database Relationships
- Users → Admin Users (one-to-one)
- Folders → Files (one-to-many)
- Folders → Quiz Sets (one-to-many)
- Quiz Sets → Questions (one-to-many)
- Users → Access Permissions (one-to-many)
- Files/Quiz Sets → Access Permissions (one-to-many)

## API Design Patterns

### Authentication
- Session-based authentication with secure headers
- CSRF token protection for state-changing operations
- Admin-only endpoints for administrative functions

### Standard Response Format
```json
{
  "success": true|false,
  "message": "Human-readable message",
  "data": {}, // Optional, for successful responses
  "errors": [] // Optional, for error responses
}
```

### Error Handling
- Use appropriate HTTP status codes (401, 403, 404, 422, 500)
- Provide clear error messages via Response methods
- Log errors for debugging using the logging system

## Security Considerations

- **Input validation**: All user input must be validated and sanitized
- **SQL injection prevention**: Use prepared statements via Database::prepare()
- **CORS configuration**: Configure allowed origins, methods, and headers in environment
- **File upload security**: Validate file types, sizes, and scan for malicious content
- **Session management**: Use secure session configuration with appropriate lifetime
- **CSRF protection**: Validate CSRF tokens on state-changing operations

### Using Security Utilities

**Logger:**
```php
use EMA\Utils\Logger;

Logger::info('User action', ['user_id' => $userId]);
Logger::error('Database error', ['error' => $e->getMessage()]);
Logger::securityEvent('Failed login attempt', ['email' => $email]);
```

**Security:**
```php
use EMA\Utils\Security;

// CSRF tokens
$token = Security::generateCsrfToken();
$isValid = Security::verifyCsrfToken($token);

// Password handling
$hashed = Security::hashPassword($password);
$isValid = Security::verifyPassword($password, $hash);

// Input sanitization
$cleanEmail = Security::sanitizeEmail($email);
$cleanPhone = Security::sanitizePhone($phone);

// Security checks
$ip = Security::getRealIp();
$isSecure = Security::isSecureConnection();
```

**Validator:**
```php
use EMA\Utils\Validator;

$validator = Validator::make($data, [
    'email' => 'required|email',
    'password' => 'required|min:8',
    'name' => 'required|min:2|max:100',
]);

if (!$validator->validate()) {
    $errors = $validator->getErrors();
    // Handle validation errors
}
```

### Using Middleware

**Adding middleware to routes:**
```php
// All routes
$router->addMiddleware(CorsMiddleware::class);

// Per-route middleware
$router->get('/api/users', [UserController::class, 'index'], [
    AuthMiddleware::class,
    RateLimitMiddleware::class
]);

// With parameters
$router->post('/api/admin', [AdminController::class, 'create'], [
    new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])
]);

// With validation
$router->post('/api/register', [AuthController::class, 'register'], [
    new ValidationMiddleware([
        'email' => 'required|email',
        'password' => 'required|min:8|password',
        'name' => 'required|min:2|max:100',
        'phone' => 'required|phone'
    ])
]);
```

**AuthMiddleware static helpers:**
```php
// Get current user
$user = AuthMiddleware::getCurrentUser();
$userId = AuthMiddleware::getCurrentUserId();

// Check roles
if (AuthMiddleware::isAdmin()) {
    // Admin-only logic
}

// Require specific roles
AuthMiddleware::requireRole(['admin', 'moderator']);
AuthMiddleware::requireAdmin();
```

## Authentication System

Phase 2.1 implements a complete session-based authentication system with the following components:

**User Model (`src/models/User.php`):**
```php
use EMA\Models\User;

// Find user by email
$user = User::findByEmail('user@example.com');

// Find user by ID
$user = User::findById(1);

// Create new user
$user = User::create([
    'full_name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '1234567890',
    'password' => 'securePassword123',
    'role' => 'user'
]);

// Update user
User::update($userId, ['full_name' => 'Jane Doe', 'phone' => '9876543210']);

// Check existence
User::isEmailExists('user@example.com');
User::isPhoneExists('1234567890');

// Password verification
User::verifyPassword($plainPassword, $hashedPassword);

// Update login/logout status
User::updateLoginTime($userId);
User::updateLogoutTime($userId);
```

**AuthService (`src/services/AuthService.php`):**
```php
use EMA\Services\AuthService;

$authService = new AuthService();

// Login
$result = $authService->login([
    'email' => 'user@example.com',
    'password' => 'password123'
]);

// Register
$result = $authService->register([
    'full_name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '1234567890',
    'password' => 'securePassword123'
]);

// Logout
$authService->logout();

// Check authentication
$authService->isAuthenticated();
$authService->isAdmin();
$authService->hasRole('admin');

// Get current user
$user = $authService->getCurrentUser();

// Require authentication/authorization
$authService->requireAuth();
$authService->requireRole('admin');

// Check session timeout (2 days inactivity)
$authService->checkSessionTimeout();

// Password reset
$result = $authService->requestPasswordReset('user@example.com');
$result = $authService->resetPassword($resetId, $token, $newPassword);

// Change password (authenticated)
$result = $authService->changePassword($userId, $currentPassword, $newPassword);
```

**AuthController (`src/controllers/AuthController.php`):**
All authentication endpoints are handled automatically by the router. The controller handles request/response formatting and error handling.

**Session Management:**
- 2-day inactivity timeout (172800 seconds)
- Session regeneration on login
- Secure session configuration (HTTP-only cookies, SameSite: lax)
- Last activity tracking and automatic cleanup

**Login Attempt Tracking:**
- IP-based attempt tracking in `storage/login_attempts/`
- 5 failed attempts = 15-minute lockout
- Automatic cleanup of old attempts (1 hour)
- Failed attempt logging

**Rate Limiting:**
- Login: 20 attempts per 30 minutes per IP
- Password reset request: 10 requests per hour per IP
- Password reset completion: 15 attempts per hour per IP
- Uses existing RateLimitMiddleware

**Security Features:**
- Bcrypt password hashing (cost 12)
- Comprehensive input validation
- CSRF token protection on all state-changing operations
- Session hijacking protection
- Brute force protection via IP lockout
- Comprehensive security logging

## Legacy Code Migration

The project is transitioning from legacy PHP files (currently deleted in git) to the new architecture. Legacy API endpoints should be gradually migrated to:
1. Controllers in `src/controllers/`
2. Models in `src/models/`
3. Services in `src/services/` for business logic
4. Middleware in `src/middleware/` for cross-cutting concerns

When implementing new features, use the new architecture rather than the legacy patterns.

## Testing

PHPUnit is configured for testing. Test files should be placed in the `tests/` directory following PSR-4 autoloading with namespace `EMA\Tests\`. The project includes database fixtures in the SQL file for testing purposes.