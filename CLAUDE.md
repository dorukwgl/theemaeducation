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

### 🔄 Phase 2: Core Systems (Week 2) - ✅ **COMPLETED**
- 2.1 Authentication System - ✅ **COMPLETED**
- 2.2 User Management - ✅ **COMPLETED**
- 2.3 Access Control System - ✅ **COMPLETED**
- 2.4 File Management - ✅ **COMPLETED**

### 🔄 Phase 3: Content Management (Week 3) - IN PROGRESS
- 3.1 Folder System - 📋 **PLANNING**
- 3.2 Quiz System
- 3.3 Notice System
- 3.4 Admin Features

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

#### ✅ Phase 2.2: User Management (April 20, 2026)
Complete user management system implementation:

**Models Extended:**
- `src/models/User.php` - Extended with user management methods:
  - `getAllUsers()` - User listing with pagination, search, role filtering, sorting
  - `deleteUserCascade()` - User deletion with cascade cleanup
  - `getAllAdmins()` - Admin user listing
  - `grantAdmin()` - Grant admin privileges
  - `revokeAdmin()` - Revoke admin privileges
  - `isAdmin()` - Check admin status
  - `getUserStats()` - User statistics for dashboard

**Controllers Created:**
- `src/controllers/UserController.php` - User management endpoints:
  - `index()` - List users with pagination (admin only)
  - `show()` - Get user profile (admin or own)
  - `update()` - Update user profile (admin or own)
  - `delete()` - Delete user with cascade (admin only)

- `src/controllers/AdminController.php` - Admin operations:
  - `index()` - List admin users (admin only)
  - `grant()` - Grant admin privileges (admin only)
  - `list()` - Alternative admin listing (admin only)
  - `approveReset()` - Approve password reset requests (admin only)

**Features Implemented:**
- User listing with pagination and search
- Profile management with role-based permissions
- Admin privilege management
- Cascade deletion for user accounts
- Password reset approval workflow
- Comprehensive security logging

**API Endpoints:**
- `GET /api/users` - List users (admin only)
- `GET /api/users/{id}` - Get user profile
- `PUT /api/users/{id}` - Update user profile
- `DELETE /api/users/{id}` - Delete user account
- `GET /api/admins` - List admin users
- `POST /api/admin/grant` - Grant admin privileges
- `GET /api/admin/list` - List all admin users
- `POST /api/admin/approve-reset` - Approve password reset

#### ✅ Phase 2.3: Access Control System (April 20, 2026)
Complete access control system implementation:

**Models Created:**
- `src/models/Access.php` - Access control database operations:
  - `checkAccess()` - Check if user has access to item
  - `grantAccess()` - Grant user access to item
  - `revokeAccess()` - Revoke user access from item
  - `incrementAccess()` - Increment access count with limit enforcement
  - `getPermissions()` - Get user permissions with filters
  - `grantAccessToAllUsers()` - Grant/revoke public access
  - `grantAccessToLoggedInUsers()` - Grant/revoke logged-in access
  - `getAllUsersAccess()` - List public access items
  - `getLoggedInUsersAccess()` - List logged-in access items
  - `getAccessStats()` - Get access statistics

**Services Created:**
- `src/services/AccessService.php` - Access control business logic:
  - `validateAccessRequest()` - Validate access requests
  - `checkAccess()` - Check access with caching layer
  - `grantAccessWithValidation()` - Grant access with validation
  - `revokeAccessWithValidation()` - Revoke access with validation
  - `incrementAccessWithCheck()` - Increment access with limit check
  - `bulkGrantAccess()` - Bulk grant access to multiple users
  - `getAccessReport()` - Generate detailed access reports
  - `cleanupExpiredAccess()` - Cleanup expired/inactive permissions

**Controllers Created:**
- `src/controllers/AccessController.php` - Access control endpoints:
  - `check()` - Check access permissions (authenticated users)
  - `increment()` - Increment access count (authenticated users)
  - `grant()` - Grant/revoke user access (admin only)
  - `permissions()` - List permissions (admin only)
  - `grantAllUsers()` - Grant/revoke public access (admin only)
  - `allUsers()` - List public access items (admin only)
  - `grantLoginUsers()` - Grant/revoke logged-in access (admin only)
  - `loginUsers()` - List logged-in access items (admin only)

**Features Implemented:**
- User access checking (admin bypass, public access, individual permissions)
- Access counting and limit enforcement
- Public access management (all users)
- Logged-in access management (authenticated users)
- Bulk access operations
- Access statistics and reporting
- Comprehensive security logging
- Admin-only access control enforcement

**API Endpoints:**
- `POST /api/access/check` - Check access permissions
- `POST /api/access/increment` - Increment access count
- `POST /api/access/grant` - Grant/revoke user access
- `GET /api/access/permissions` - List permissions
- `POST /api/access/all-users` - Grant/revoke public access
- `GET /api/access/all-users` - List public access items
- `POST /api/access/login-users` - Grant/revoke logged-in access
- `GET /api/access/login-users` - List logged-in access items

#### ✅ Phase 2.4: File Management System (April 20, 2026)
Complete file and folder management system implementation:

**Models Created:**
- `src/models/Folder.php` - Folder model with CRUD operations:
  - `getAllFolders()` - List all folders with file counts
  - `findById()` - Find folder by ID
  - `create()` - Create new folder with icon upload
  - `update()` - Update folder with icon replacement
  - `delete()` - Delete folder with cascade cleanup
  - `getFolderContents()` - Get files in folder

- `src/models/File.php` - File model with access control integration:
  - `findById()` - Find file by ID with folder details
  - `create()` - Create file record
  - `update()` - Update file metadata
  - `delete()` - Delete file with cascade cleanup
  - `checkFileAccess()` - Check file access with Access model integration
  - `getFileStats()` - Get file access statistics
  - `getFilesByFolder()` - Get files by folder with user filtering

**Controllers Created:**
- `src/controllers/FolderController.php` - Folder management endpoints:
  - `index()` - List all folders (authenticated users)
  - `store()` - Create new folder (admin only)
  - `show()` - Get folder details (authenticated users)
  - `update()` - Update folder (admin only)
  - `delete()` - Delete folder (admin only)
  - `contents()` - Get folder contents (authenticated users)

- `src/controllers/FileController.php` - File management endpoints:
  - `upload()` - Upload file with security validation (admin only)
  - `show()` - Get file details with access info (authenticated users)
  - `delete()` - Delete file (admin only)
  - `download()` - Download file with access tracking (authenticated users)

**Features Implemented:**
- Folder CRUD operations with icon management
- File upload with comprehensive security (MIME type, size, extension validation)
- File download with access control and tracking
- Secure filename generation and file storage
- Cascade deletion (files, icons, access permissions)
- Access control integration for all file operations
- Public and logged-in access types support
- Individual user permissions via Access model
- Admin bypass for all operations
- CSRF protection on all state-changing operations
- Comprehensive security logging
- File path validation (directory traversal prevention)
- Proper HTTP headers for downloads

**File Upload Security:**
- Multi-layer validation (MIME type, extension, size)
- Server-side MIME type validation (not just extension)
- File extension whitelisting (images, documents, audio, video)
- File size limit enforcement (10MB default)
- Secure filename generation (timestamp + random string)
- Directory traversal prevention
- Proper file permissions (0644)
- File content scanning framework

**Access Control Integration:**
- Public access ('all') - No authentication required
- Logged-in access ('logged_in') - Requires authentication
- Individual user permissions - Via Access model integration
- Access counting and limit enforcement
- Admin bypass for all operations
- Access tracking on downloads

**API Endpoints:**
- `GET /api/folders` - List all folders
- `POST /api/folders` - Create new folder
- `GET /api/folders/{id}` - Get folder details
- `PUT /api/folders/{id}` - Update folder
- `DELETE /api/folders/{id}` - Delete folder
- `GET /api/folders/{id}/contents` - Get folder contents
- `POST /api/files/upload` - Upload file
- `GET /api/files/{id}` - Get file details
- `DELETE /api/files/{id}` - Delete file
- `GET /api/files/{id}/download` - Download file

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
│   ├── UserController.php   # User management (✅)
│   ├── AdminController.php  # Admin operations (✅)
│   ├── AccessController.php # Access control (✅)
│   ├── FolderController.php # Folder management (✅)
│   ├── FileController.php   # File management (✅)
│   ├── QuizController.php   # Quiz management (to be implemented)
│   └── SystemController.php # System operations (to be implemented)
├── models/        # Data models
│   ├── User.php          # User model (✅)
│   ├── Access.php        # Access model (✅)
│   ├── Folder.php        # Folder model (✅)
│   └── File.php          # File model (✅)
├── services/      # Business logic services
│   ├── AuthService.php   # Authentication logic (✅)
│   └── AccessService.php # Access control logic (✅)
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
All classes follow PSR-4 autoloading with namespace `EMA\`. Classes are autoloaded from `src/` directory. Example: `EMA\Config\Config` maps to `src/config/Config.php`.

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
- **Permission types**: User-specific, public access, logged-in access

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
  "data": {}, // optional, for successful responses
  "errors": [] // optional, for error responses
}
```

### Error Handling
- Use appropriate HTTP status codes (200, 201, 400, 401, 403, 404, 422, 500)
- Provide clear error messages via Response methods
- Log errors for debugging using logging system

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

Phase 2.1 implements a complete session-based authentication system with following components:

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

## User Management System

Phase 2.2 implements a complete user management system with following components:

**User Model Extensions (`src/models/User.php`):**
```php
use EMA\Models\User;

// Get all users with pagination
$users = User::getAllUsers($page = 1, $perPage = 20, $search = null, $role = null, $sortBy = 'created_at', $sortOrder = 'DESC');

// Delete user with cascade cleanup
$result = User::deleteUserCascade($userId);

// Get all admin users
$admins = User::getAllAdmins();

// Grant admin privileges
$result = User::grantAdmin($userId, 'admin@example.com');

// Revoke admin privileges
$result = User::revokeAdmin($userId);

// Check if user is admin
$isAdmin = User::isAdmin($userId);

// Get user statistics
$stats = User::getUserStats();
```

**UserController (`src/controllers/UserController.php`):**
```php
// List users (admin only)
GET /api/users?page=1&per_page=20&search=john&role=user&sort_by=created_at&sort_order=DESC

// Get user profile
GET /api/users/{id}

// Update user profile
PUT /api/users/{id}
Body: { full_name, phone, image } (user) or { full_name, email, phone, image, role } (admin)

// Delete user (admin only)
DELETE /api/users/{id}
```

**AdminController (`src/controllers/AdminController.php`):**
```php
// List admin users (admin only)
GET /api/admins

// Grant admin privileges (admin only)
POST /api/admin/grant
Body: { user_id, email (optional) }

// List admin users (alternative format) (admin only)
GET /api/admin/list

// Approve password reset (admin only)
POST /api/admin/approve-reset
Body: { reset_id, action: 'approve'|'reject' }
```

## Access Control System

Phase 2.3 implements a complete access control system with following components:

**Access Model (`src/models/Access.php`):**
```php
use EMA\Models\Access;

// Check if user has access
$hasAccess = Access::checkAccess($userId, $itemId, $itemType);

// Grant user access
$result = Access::grantAccess($userId, $itemId, $itemType, $accessTimes = 0);

// Revoke user access
$result = Access::revokeAccess($userId, $itemId, $itemType);

// Increment access count
$result = Access::incrementAccess($userId, $itemId, $itemType);

// Get user permissions
$permissions = Access::getPermissions($userId, $itemType);

// Grant public access
$result = Access::grantAccessToAllUsers($itemId, $itemType, $grant = true);

// Grant logged-in access
$result = Access::grantAccessToLoggedInUsers($itemId, $itemType, $grant = true);

// Get public access items
$items = Access::getAllUsersAccess($itemType);

// Get logged-in access items
$items = Access::getLoggedInUsersAccess($itemType);

// Get access statistics
$stats = Access::getAccessStats($itemId, $itemType);
```

**AccessService (`src/services/AccessService.php`):**
```php
use EMA\Services\AccessService;

$accessService = new AccessService();

// Validate access request
$validation = $accessService->validateAccessRequest($data);

// Check access with caching
$hasAccess = $accessService->checkAccess($userId, $itemId, $itemType);

// Grant access with validation
$result = $accessService->grantAccessWithValidation($data);

// Revoke access with validation
$result = $accessService->revokeAccessWithValidation($userId, $itemId, $itemType);

// Increment access with limit check
$result = $accessService->incrementAccessWithCheck($userId, $itemId, $itemType);

// Bulk grant access
$result = $accessService->bulkGrantAccess($userIds, $itemId, $itemType, $accessTimes = 0);

// Get access report
$report = $accessService->getAccessReport($itemId, $itemType);

// Cleanup expired access
$result = $accessService->cleanupExpiredAccess();
```

**AccessController (`src/controllers/AccessController.php`):**
```php
// Check access permissions (authenticated users)
POST /api/access/check
Body: { item_id, item_type: 'file'|'quiz_set' }

// Increment access count (authenticated users)
POST /api/access/increment
Body: { item_id, item_type: 'file'|'quiz_set' }

// Grant/revoke user access (admin only)
POST /api/access/grant
Body: { user_id, item_id, item_type, access_times, action: 'grant'|'revoke' }

// List permissions (admin only)
GET /api/access/permissions?user_id=123&item_type=file

// Grant/revoke public access (admin only)
POST /api/access/all-users
Body: { item_id, item_type: 'file'|'quiz_set', grant: true|false }

// List public access items (admin only)
GET /api/access/all-users?item_type=file

// Grant/revoke logged-in access (admin only)
POST /api/access/login-users
Body: { item_id, item_type: 'file'|'quiz_set', grant: true|false }

// List logged-in access items (admin only)
GET /api/access/login-users?item_type=file
```

**Access Control Features:**
- User access checking (admin bypass, public access, individual permissions)
- Access counting and limit enforcement
- Public access management (all users)
- Logged-in access management (authenticated users)
- Bulk access operations
- Access statistics and reporting
- Admin-only access control enforcement
- Comprehensive security logging

## Legacy Code Migration

The project is transitioning from legacy PHP files (currently deleted in git) to a new architecture. Legacy API endpoints should be gradually migrated to:
1. Controllers in `src/controllers/`
2. Models in `src/models/`
3. Services in `src/services/` for business logic
4. Middleware in `src/middleware/` for cross-cutting concerns

When implementing new features, use the new architecture rather than legacy patterns.

## Testing

PHPUnit is configured for testing. Test files should be placed in `tests/` directory following PSR-4 autoloading with namespace `EMA\Tests\`. The project includes database fixtures in SQL file for testing purposes.
