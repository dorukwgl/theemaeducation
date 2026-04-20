# EMA Education Platform - Complete Rewrite Plan

## Context

The current EMA Education Platform codebase has critical issues that require a complete rewrite:

**Security Vulnerabilities:**
- Hardcoded admin credentials in multiple files
- Open CORS (`Access-Control-Allow-Origin: *`) allowing any origin
- No CSRF protection, rate limiting, or brute force protection
- Mixed authentication approaches (hardcoded + database)
- Insecure session management
- File upload security vulnerabilities
- No XSS protection framework
- Inconsistent SQL injection protection

**Performance Issues:**
- Multiple database connections per request
- No connection pooling or query optimization
- No caching mechanism
- Inefficient resource usage (critical for cPanel hosting)
- No pagination for large datasets

**Code Quality Issues:**
- No separation of concerns (business logic mixed with presentation)
- Mixed coding styles (mysqli vs PDO)
- Duplicate code across files
- Inconsistent response formats
- No proper error handling framework
- No input validation framework
- No proper logging system

**Architecture Issues:**
- No MVC pattern or proper routing
- No middleware system
- No authentication/authorization framework
- Hardcoded configuration values
- No proper dependency management

The objective is to create a production-ready, secure, and optimized system while preserving all existing API contracts for backward compatibility.

## Implementation Approach

### Phase 1: Infrastructure & Foundation (Week 1)

**1.1 Project Structure Setup**
```
/public/
  index.php              # Main entry point
  .htaccess              # Apache configuration
/src/
  /config/
    config.php           # Centralized configuration
    database.php         # Database connection factory
    constants.php        # Application constants
  /core/
    Router.php           # Request routing
    Request.php          # Request handling
    Response.php         # Response formatting
    App.php              # Application bootstrap
  /middleware/
    AuthMiddleware.php   # Authentication
    CorsMiddleware.php   # CORS handling
    RateLimitMiddleware.php # Rate limiting
    ValidationMiddleware.php # Input validation
  /controllers/
    AuthController.php   # Authentication endpoints
    UserController.php   # User management
    FolderController.php # Folder management
    FileController.php   # File management
    QuizController.php   # Quiz management
    AdminController.php  # Admin operations
    AccessController.php # Access control
    SystemController.php # System operations
  /models/
    User.php             # User model
    Folder.php           # Folder model
    File.php             # File model
    QuizSet.php          # Quiz set model
    Question.php         # Question model
    AccessPermission.php # Access permissions
    Notice.php           # Notice model
  /services/
    AuthService.php      # Authentication logic
    FileService.php      # File operations
    AccessService.php    # Access control logic
    UploadService.php    # File upload handling
    CacheService.php     # Caching layer
  /utils/
    Validator.php        # Input validation
    Sanitizer.php        # Input sanitization
    Logger.php           # Logging utility
    Security.php         # Security utilities
/uploads/
  /files/               # User uploaded files
  /icons/               # Icon files
  /questions/           # Question media
  /notices/             # Notice attachments
/logs/
  /app/                 # Application logs
  /error/               # Error logs
  /access/              # Access logs
.env                   # Environment variables
.env.example           # Environment template
composer.json          # Dependencies
```

**1.2 Core Configuration System**
- Environment-based configuration (.env files)
- Secure database connection pooling
- Centralized error handling
- Logging configuration
- Security settings (CORS, rate limits, etc.)

**1.3 Database Layer**
- Single connection factory with connection pooling
- Prepared statement wrapper for consistent SQL protection
- Query builder for complex queries
- Transaction management
- Database migration system

**1.4 Security Framework**
- Input validation and sanitization
- XSS protection
- CSRF token generation and validation
- Rate limiting (per IP and per user)
- Brute force protection
- Secure session management
- Password hashing with bcrypt
- Secure file upload handling

### Phase 2: Core Systems (Week 2)

**2.1 Authentication System**
- Secure password hashing (bcrypt)
- Session management with secure cookies
- JWT token support for API
- Login attempt tracking
- Password reset flow
- Session timeout handling

**2.2 User Management**
- User registration with validation
- Profile management
- Role-based access control (user/admin)
- User listing and search
- User deletion with cleanup

**2.3 Access Control System**
- Permission checking middleware
- Access counter and limits
- Activation status management
- Guest vs registered user access
- Admin override capabilities

**2.4 File Management**
- Secure file upload with type validation
- File size limits
- Virus scanning integration
- File storage organization
- File deletion with cleanup
- Icon management

### Phase 3: Content Management (Week 3)

**3.1 Folder System**
- Folder CRUD operations
- Folder hierarchy support
- Icon upload and management
- Folder listing with metadata

**3.2 Quiz System**
- Quiz set creation and management
- Question management (text, images, audio)
- Multiple choice support
- Question formatting (bold, underline)
- Quiz set deletion with cleanup

**3.3 Notice System**
- Notice creation and updates
- File attachment support
- Notice listing
- Notice deletion

**3.4 Admin Features**
- Admin user management
- Access permission grants
- Bulk operations
- System monitoring
- User activity tracking

### Phase 4: API Implementation (Week 4)

**4.1 API Contract Preservation**

All existing API endpoints will be preserved with exact request/response formats:

**Authentication APIs:**
- `POST /login.php` → `POST /api/auth/login`
- `POST /register.php` → `POST /api/auth/register`

**User Management APIs:**
- `GET /register.php` → `GET /api/users`
- `DELETE /register.php` → `DELETE /api/users`

**Content Management APIs:**
- `GET /folders.php` → `GET /api/folders`
- `POST /folders.php` → `POST /api/folders`
- `GET /folder_details_page.php` → `GET /api/folders/{id}/contents`
- `POST /folder_details_page.php` → `POST /api/folders/{id}/contents`
- `POST /upload_file.php` → `POST /api/files/upload`
- `GET /quiz_set_detail_page.php` → `GET /api/quiz-sets/{id}`
- `POST /quiz_set_detail_page.php` → `POST /api/quiz-sets/{id}/questions`
- `DELETE /quiz_set_detail_page.php` → `DELETE /api/quiz-sets/{id}/questions/{question_id}`

**Access Control APIs:**
- `POST /check_access.php` → `POST /api/access/check`
- `POST /increment_access.php` → `POST /api/access/increment`
- `POST /grant_file_access.php` → `POST /api/access/grant`
- `GET /grant_file_access.php` → `GET /api/access/permissions`
- `POST /give_access_to_all_users.php` → `POST /api/access/all-users`
- `GET /give_access_to_all_users.php` → `GET /api/access/all-users`
- `POST /give_access_to_login_users.php` → `POST /api/access/login-users`
- `GET /give_access_to_login_users.php` → `GET /api/access/login-users`

**Admin APIs:**
- `POST /give_admin_access.php` → `POST /api/admin/grant`
- `GET /give_admin_access.php` → `GET /api/admin/list`
- `GET /get_admins.php` → `GET /api/admins`
- `POST /approve_reset_request.php` → `POST /api/admin/approve-reset`

**System APIs:**
- `GET /notices.php` → `GET /api/notices`
- `POST /notices.php` → `POST /api/notices`
- `DELETE /notices.php` → `DELETE /api/notices/{id}`
- `POST /track_download.php` → `POST /api/analytics/track-download`
- `POST /free_files_quiz_sets.php` → `POST /api/content/free-access`

**Legacy Route Compatibility:**
- All old routes will redirect to new routes with same response format
- This ensures backward compatibility during transition

### Phase 5: Performance Optimization (Week 5)

**5.1 Database Optimization**
- Query optimization with proper indexing
- Query result caching
- Database connection pooling
- Lazy loading for large datasets
- Pagination implementation

**5.2 Caching Strategy**
- File-based caching for frequent queries
- Session caching
- Response caching for static content
- Cache invalidation strategy

**5.3 Resource Optimization**
- Memory-efficient file handling
- Streaming for large file uploads/downloads
- Image optimization and compression
- CSS/JS minification
- Gzip compression

**5.4 cPanel-Specific Optimizations**
- Shared hosting resource limits awareness
- Efficient memory usage patterns
- Optimized for limited CPU resources
- Proper cleanup of resources
- Background job scheduling

### Phase 6: Testing & Deployment (Week 6)

**6.1 Testing**
- Unit tests for core functions
- Integration tests for API endpoints
- Security testing (OWASP guidelines)
- Performance testing
- Load testing

**6.2 Deployment**
- Environment setup (development, staging, production)
- Database migration execution
- File system setup
- SSL configuration
- Backup procedures
- Monitoring setup

**6.3 Documentation**
- API documentation
- Deployment guide
- Configuration guide
- Troubleshooting guide

## Critical Files to be Created

### Configuration Files
- `/public/.htaccess` - Apache routing and security
- `.env` - Environment configuration
- `.env.example` - Configuration template
- `composer.json` - PHP dependencies

### Core Files
- `/public/index.php` - Application entry point
- `/src/config/config.php` - Centralized configuration
- `/src/config/database.php` - Database connection factory
- `/src/core/Router.php` - Request routing
- `/src/core/App.php` - Application bootstrap
- `/src/utils/Logger.php` - Logging system

### Security Files
- `/src/middleware/AuthMiddleware.php` - Authentication
- `/src/middleware/CorsMiddleware.php` - CORS handling
- `/src/middleware/RateLimitMiddleware.php` - Rate limiting
- `/src/utils/Validator.php` - Input validation
- `/src/utils/Security.php` - Security utilities

### Controller Files
- `/src/controllers/AuthController.php` - Authentication endpoints
- `/src/controllers/UserController.php` - User management
- `/src/controllers/FolderController.php` - Folder management
- `/src/controllers/FileController.php` - File management
- `/src/controllers/QuizController.php` - Quiz management
- `/src/controllers/AdminController.php` - Admin operations
- `/src/controllers/AccessController.php` - Access control
- `/src/controllers/SystemController.php` - System operations

### Model Files
- `/src/models/User.php` - User model
- `/src/models/Folder.php` - Folder model
- `/src/models/File.php` - File model
- `/src/models/QuizSet.php` - Quiz set model
- `/src/models/Question.php` - Question model
- `/src/models/AccessPermission.php` - Access permissions
- `/src/models/Notice.php` - Notice model

### Service Files
- `/src/services/AuthService.php` - Authentication logic
- `/src/services/FileService.php` - File operations
- `/src/services/AccessService.php` - Access control logic
- `/src/services/UploadService.php` - File upload handling
- `/src/services/CacheService.php` - Caching layer

## Files to be Deleted

All current PHP files will be deleted after successful migration:
- `index.php` (download page)
- `track_download.php`
- `sw.js`
- `login.php`
- `register.php`
- `upload_file.php`
- `config.php`
- `db.php`
- `GlobalConfigs.php`
- `folder_details_page.php`
- `folders.php`
- `free_files_quiz_sets.php`
- `get_admins.php`
- `give_access_to_all_users.php`
- `give_access_to_login_users.php`
- `give_admin_access.php`
- `increment_access.php`
- `notices.php`
- `quiz_set_detail_page.php`
- `quiz_set_detail_page_ui.php`
- `check_access.php`
- `grant_file_access.php`
- `approve_reset_request.php`
- `info.php`
- `migrate.php`
- `test.php`
- `.env` (will be replaced with new format)
- `.user.ini`
- `php.ini` (cPanel managed)
- `mssnorg_ema.sql` (will be replaced with migration files)

## Security Improvements

### Authentication & Authorization
- Secure password hashing (bcrypt with cost factor 12)
- Session management with HTTP-only, secure cookies
- JWT token support for API authentication
- Role-based access control (RBAC)
- Login attempt tracking and locking
- Password strength requirements
- Secure password reset flow

### Input Validation & Sanitization
- Comprehensive input validation framework
- XSS protection for all user inputs
- SQL injection protection via prepared statements
- CSRF token generation and validation
- File upload validation (type, size, content)
- URL validation and sanitization

### Rate Limiting & Protection
- Per-IP rate limiting for all endpoints
- Per-user rate limiting for authenticated users
- Brute force protection for login
- DDoS protection measures
- Request throttling

### Session Security
- Secure session configuration
- Session timeout management
- Session fixation prevention
- Secure cookie attributes
- Session regeneration on privilege escalation

### File Security
- Secure file upload handling
- File type validation (MIME type checking)
- File size limits
- Virus scanning integration
- Secure file storage
- File access permissions

### CORS & Headers
- Configurable CORS policies
- Security headers (CSP, X-Frame-Options, etc.)
- Proper HTTP status codes
- Secure error messages (no sensitive data)

## Performance Optimizations for cPanel

### Database Optimization
- Single connection factory with pooling
- Query optimization with proper indexing
- Prepared statement caching
- Lazy loading for large datasets
- Pagination for all list endpoints
- Query result caching

### Memory Management
- Efficient file handling (streaming for large files)
- Memory cleanup after requests
- Optimized data structures
- Minimal object creation
- Resource pooling

### Caching Strategy
- File-based caching for frequent queries
- Session caching
- Response caching for static content
- Cache invalidation strategy
- Configurable cache TTL

### Resource Optimization
- Gzip compression for responses
- Image optimization and compression
- CSS/JS minification
- Efficient file uploads (chunking)
- Background job processing

### cPanel-Specific Considerations
- Shared hosting resource limits
- CPU usage optimization
- Memory limit awareness
- Disk space management
- Proper error logging
- Graceful degradation under load

## Testing & Verification

### Unit Testing
- Test all core functions
- Test validation logic
- Test security functions
- Test database operations

### Integration Testing
- Test all API endpoints
- Test authentication flow
- Test file upload/download
- Test access control
- Test admin operations

### Security Testing
- OWASP Top 10 vulnerabilities
- SQL injection testing
- XSS testing
- CSRF testing
- Authentication bypass testing
- File upload security testing

### Performance Testing
- Load testing with concurrent users
- Memory usage monitoring
- Response time testing
- Database query performance
- File upload/download performance

### End-to-End Testing
- Complete user flows
- Admin workflows
- File management flows
- Quiz creation and access
- Access control scenarios

## Migration Strategy

### Phase 1: Setup
1. Create new project structure
2. Set up database connections
3. Implement core systems
4. Create configuration system

### Phase 2: Data Migration
1. Export existing data
2. Transform data to new format
3. Import to new database structure
4. Verify data integrity

### Phase 3: API Implementation
1. Implement new API endpoints
2. Create legacy route redirects
3. Test backward compatibility
4. Update frontend if needed

### Phase 4: Testing
1. Run comprehensive tests
2. Security audit
3. Performance testing
4. User acceptance testing

### Phase 5: Deployment
1. Backup existing system
2. Deploy new system
3. Monitor performance
4. Address any issues

### Phase 6: Cleanup
1. Remove old files
2. Clean up database
3. Update documentation
4. Final verification

## Rollback Plan

If issues arise during deployment:
1. Restore from backup
2. Revert DNS changes (if any)
3. Restore old files
4. Verify system functionality
5. Investigate and fix issues
6. Plan redeployment

## Success Criteria

### Functional Requirements
- All existing API contracts preserved
- All features working as expected
- No data loss during migration
- Backward compatibility maintained

### Security Requirements
- No OWASP Top 10 vulnerabilities
- Proper authentication and authorization
- Secure file handling
- Rate limiting implemented
- CSRF protection enabled

### Performance Requirements
- Response time under 200ms for most endpoints
- Memory usage under 64MB per request
- CPU usage optimized for shared hosting
- Proper caching implemented
- Efficient database queries

### Code Quality Requirements
- Follow PSR-12 coding standards
- Proper documentation
- Comprehensive error handling
- Consistent code style
- No code duplication

### Deployment Requirements
- Smooth deployment process
- Minimal downtime
- Proper monitoring
- Backup procedures in place
- Rollback plan tested

## Estimated Timeline

- Week 1: Infrastructure & Foundation
- Week 2: Core Systems
- Week 3: Content Management
- Week 4: API Implementation
- Week 5: Performance Optimization
- Week 6: Testing & Deployment

Total: 6 weeks for complete rewrite and deployment

## Dependencies

### PHP Packages (via Composer)
- `vlucas/phpdotenv` - Environment configuration
- `monolog/monolog` - Logging
- `firebase/php-jwt` - JWT authentication
- `guzzlehttp/guzzle` - HTTP client
- `ramsey/uuid` - UUID generation
- `intervention/image` - Image manipulation

### PHP Extensions Required
- mysqli
- pdo_mysql
- json
- mbstring
- gd
- fileinfo
- openssl
- session

## Notes

- All database credentials must be moved to environment variables
- Hardcoded admin credentials must be removed
- CORS policy must be configurable
- All file uploads must be validated and secured
- Session management must be secure
- Error messages must not expose sensitive information
- Logging must be comprehensive but not log sensitive data
- All user inputs must be validated and sanitized
- SQL queries must use prepared statements
- Rate limiting must be implemented for all endpoints
- CSRF protection must be enabled for all state-changing operations
