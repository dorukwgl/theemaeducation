# Access Control Implementation Plan

## Executive Summary

This plan outlines the comprehensive implementation of enhanced access control for Files and Quiz Sets in the EMA Education Platform. The current implementation has partial access control but lacks several critical features including status management, private access types, and proper public route separation.

## Current State Analysis

### Database Schema Status

#### Files Table (`files`)
**Current Fields:**
- ✅ `access_type` - enum('all','logged_in') - PARTIALLY IMPLEMENTED
- ❌ `status` - MISSING (needs to be added)

**Issues:**
- Missing `status` field to control active/inactive state
- Missing `private` access_type option
- No proper status management

#### Quiz Sets Table (`quiz_sets`)
**Current Fields:**
- ✅ `access_type` - enum('all','logged_in') - PARTIALLY IMPLEMENTED  
- ⚠️ `is_published` - boolean (0/1) - NEEDS IMPROVEMENT

**Issues:**
- `is_published` is boolean but should be more explicit status field
- Missing `private` access_type option
- Status naming inconsistency (published vs active)

### Current Access Types
```php
// Current implementation
'all'        // Public access for everyone (including unauthenticated)
'logged_in'  // Access only for authenticated users
// MISSING: 'private' - Access only for users with explicit permissions
```

### Current Status Implementation
```php
// Files: NO STATUS FIELD
// Quiz Sets: is_published (boolean) - should be status enum
```

### Current Route Analysis

#### File Routes
```php
// Current routes - all require AuthMiddleware
POST   /api/files/upload              - Admin only
PUT    /api/files/{id}                - Admin only  
DELETE /api/files/{id}                - Admin only
GET    /api/files/{id}/download       - Authenticated
GET    /api/folders/{id}/files        - Authenticated
GET    /api/res/{path}                - Authenticated (resource access)
```

**Issues:**
- No public routes for `access_type = all` resources
- No admin routes to change status/access_type
- Resource access route doesn't properly handle public access

#### Quiz Set Routes  
```php
// Current routes - all require AuthMiddleware
GET    /api/quiz-sets                 - Authenticated
GET    /api/quiz-sets/{id}            - Authenticated
POST   /api/quiz-sets                 - Admin only
PUT    /api/quiz-sets/{id}            - Admin only
DELETE /api/quiz-sets/{id}            - Admin only
GET    /api/quiz-sets/{id}/questions  - Authenticated
POST   /api/quiz-sets/{id}/start      - Authenticated
POST   /api/quiz-sets/{id}/submit     - Authenticated
GET    /api/quiz-sets/{id}/statistics - Authenticated
POST   /api/quiz-sets/batch-check     - Authenticated
```

**Issues:**
- No public routes for `access_type = all` resources
- No admin routes to change status/access_type
- All routes require authentication, even for public resources

### Current Access Control Logic

#### Access Model (`src/models/Access.php`)
**Current Implementation:**
```php
public static function checkAccess(int $userId, int $itemId, string $itemType): bool
{
    // Admin always has access
    if (User::isAdminById($userId)) return true;
    
    // Check access_type from database
    $accessType = getItemAccessType($itemId, $itemType);
    
    // Public access
    if ($accessType === 'all') return true;
    
    // Logged-in access  
    if ($accessType === 'logged_in') {
        return User::findById($userId) !== null;
    }
    
    // Check individual permissions via access_permissions table
    // This handles what should be 'private' access
    return checkIndividualPermissions($userId, $itemId, $itemType);
}
```

**Issues:**
- Logic is partially correct but access_type enum doesn't include 'private'
- No status checking in access control
- Doesn't differentiate between authenticated and unauthenticated users properly

## Required Changes

### 1. Database Schema Updates

#### Files Table Modifications
```sql
-- Add status field
ALTER TABLE files 
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' 
AFTER access_type;

-- Modify access_type enum to include 'private'
ALTER TABLE files 
MODIFY COLUMN access_type ENUM('all', 'logged_in', 'private') 
DEFAULT 'logged_in';

-- Add indexes for performance
CREATE INDEX idx_files_status ON files(status);
CREATE INDEX idx_files_access_status ON files(access_type, status);
```

#### Quiz Sets Table Modifications
```sql
-- Add proper status field (keep is_published for backward compatibility)
ALTER TABLE quiz_sets 
ADD COLUMN status ENUM('published', 'draft', 'archived') DEFAULT 'draft' 
AFTER is_published;

-- Modify access_type enum to include 'private'
ALTER TABLE quiz_sets 
MODIFY COLUMN access_type ENUM('all', 'logged_in', 'private') 
DEFAULT 'logged_in';

-- Add indexes for performance
CREATE INDEX idx_quiz_sets_status ON quiz_sets(status);
CREATE INDEX idx_quiz_sets_access_status ON quiz_sets(access_type, status);

-- Migrate existing is_published values to status
UPDATE quiz_sets SET status = 'published' WHERE is_published = 1;
UPDATE quiz_sets SET status = 'draft' WHERE is_published = 0;
```

### 2. Constants Updates

#### Update `src/config/constants.php`
```php
// Access Types
public const ACCESS_ALL = 'all';
public const ACCESS_LOGGED_IN = 'logged_in';
public const ACCESS_PRIVATE = 'private';  // NEW

// Item Status
public const STATUS_ACTIVE = 'active';       // For files
public const STATUS_INACTIVE = 'inactive';   // For files
public const STATUS_PUBLISHED = 'published'; // For quiz sets
public const STATUS_DRAFT = 'draft';         // For quiz sets
public const STATUS_ARCHIVED = 'archived';   // For quiz sets
```

### 3. Model Updates

#### File Model (`src/models/File.php`)

**Required Changes:**
1. Update `findById()` to include status field
2. Update `create()` to validate status field
3. Update `update()` to handle status changes
4. Update `checkFileAccess()` to consider status
5. Update `getFilesByFolderPaginated()` to filter by status
6. Add new methods for status and access_type management

**New Methods Needed:**
```php
// Status management
public static function updateStatus(int $fileId, string $status): bool
public static function updateAccessType(int $fileId, string $accessType): bool

// Public access methods
public static function getPublicFiles(int $page, int $perPage, ?int $folderId = null): array
public static function getLoggedInFiles(int $userId, int $page, int $perPage, ?int $folderId = null): array
```

**Modified Methods:**
```php
// Update access checking logic
public static function checkFileAccess(int $userId, int $fileId): bool
{
    // Check if file exists and is active
    $file = self::findById($fileId);
    if (!$file || $file['status'] !== 'active') {
        return false;
    }
    
    // Admin always has access
    if (User::isAdminById($userId)) {
        return true;
    }
    
    // Public access (all) - including unauthenticated users
    if ($file['access_type'] === 'all') {
        return true;
    }
    
    // Logged-in access
    if ($file['access_type'] === 'logged_in') {
        return $userId !== null; // User must be authenticated
    }
    
    // Private access - check individual permissions
    if ($file['access_type'] === 'private') {
        if ($userId === null) return false;
        return Access::checkAccess($userId, $fileId, 'file');
    }
    
    return false;
}
```

#### Quiz Set Model (`src/models/QuizSet.php`)

**Required Changes:**
1. Update `findById()` to include status field
2. Update `create()` to validate status field  
3. Update `update()` to handle status changes
4. Update `checkQuizSetAccess()` to consider status
5. Update `getAllQuizSets()` to filter by status
6. Add new methods for status and access_type management

**New Methods Needed:**
```php
// Status management
public static function updateStatus(int $quizSetId, string $status): bool
public static function updateAccessType(int $quizSetId, string $accessType): bool

// Public access methods
public static function getPublicQuizSets(int $page, int $perPage, ?int $folderId = null): array
public static function getLoggedInQuizSets(int $userId, int $page, int $perPage, ?int $folderId = null): array
```

**Modified Methods:**
```php
// Update access checking logic
public static function checkQuizSetAccess(int $userId, int $quizSetId): bool
{
    // Check if quiz set exists and is published
    $quizSet = self::findById($quizSetId);
    if (!$quizSet || $quizSet['status'] !== 'published') {
        return false;
    }
    
    // Admin always has access
    if (User::isAdminById($userId)) {
        return true;
    }
    
    // Public access (all) - including unauthenticated users
    if ($quizSet['access_type'] === 'all') {
        return true;
    }
    
    // Logged-in access
    if ($quizSet['access_type'] === 'logged_in') {
        return $userId !== null; // User must be authenticated
    }
    
    // Private access - check individual permissions
    if ($quizSet['access_type'] === 'private') {
        if ($userId === null) return false;
        return Access::checkAccess($userId, $quizSetId, 'quiz_set');
    }
    
    return false;
}
```

#### Access Model (`src/models/Access.php`)

**Required Changes:**
1. Update `checkAccess()` to handle 'private' access_type explicitly
2. Remove redundant access_type checking (moved to File/QuizSet models)
3. Focus only on individual permission checking

### 4. Controller Updates

#### File Controller (`src/controllers/FileController.php`)

**New Methods Needed:**
```php
// Public routes (no authentication required)
public function publicIndex(): void           // GET /api/public/files
public function publicShow(int $id): void     // GET /api/public/files/{id}

// Authenticated routes
public function authenticatedIndex(): void    // GET /api/files (logged_in users)

// Admin routes for status/access_type management
public function updateStatus(int $id): void        // PUT /api/admin/files/{id}/status
public function updateAccessType(int $id): void    // PUT /api/admin/files/{id}/access-type
```

**Modified Methods:**
```php
// Update existing methods to use new access control
public function show(int $id): void
public function download(int $id): void  
public function folderFiles(int $folderId): void
```

#### Quiz Controller (`src/controllers/QuizController.php`)

**New Methods Needed:**
```php
// Public routes (no authentication required)
public function publicIndex(): void              // GET /api/public/quiz-sets
public function publicShow(int $id): void        // GET /api/public/quiz-sets/{id}
public function publicQuestions(int $id): void   // GET /api/public/quiz-sets/{id}/questions

// Authenticated routes
public function authenticatedIndex(): void       // GET /api/quiz-sets (logged_in users)

// Admin routes for status/access_type management  
public function updateStatus(int $id): void           // PUT /api/admin/quiz-sets/{id}/status
public function updateAccessType(int $id): void       // PUT /api/admin/quiz-sets/{id}/access-type
```

**Modified Methods:**
```php
// Update existing methods to use new access control
public function index(): void
public function show(int $id): void
public function questions(int $id): void
public function startAttempt(int $id): void
```

### 5. Route Definitions

#### New File Routes (`public/index.php`)
```php
// Public file routes (NO authentication required)
$router->get('/api/public/files', [FileController::class, 'publicIndex']);
$router->get('/api/public/files/{id}', [FileController::class, 'publicShow']);
$router->get('/api/public/files/{id}/download', [FileController::class, 'publicDownload']);

// Authenticated file routes (requires authentication)
$router->get('/api/files', [FileController::class, 'authenticatedIndex'], [AuthMiddleware::class]);
$router->get('/api/files/{id}', [FileController::class, 'show'], [AuthMiddleware::class]);
$router->get('/api/files/{id}/download', [FileController::class, 'download'], [AuthMiddleware::class]);

// Admin file management routes
$router->post('/api/files/upload', [FileController::class, 'upload'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/files/{id}', [FileController::class, 'update'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/files/{id}', [FileController::class, 'delete'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/admin/files/{id}/status', [FileController::class, 'updateStatus'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/admin/files/{id}/access-type', [FileController::class, 'updateAccessType'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);

// Folder file listing (authenticated)
$router->get('/api/folders/{id}/files', [FileController::class, 'folderFiles'], [AuthMiddleware::class]);

// Resource access (special handling for public access)
$router->get('/api/res/{path:.+}', [FileController::class, 'serveByPath']); // NO middleware - handle internally
```

#### New Quiz Set Routes (`public/index.php`)
```php
// Public quiz routes (NO authentication required)
$router->get('/api/public/quiz-sets', [QuizController::class, 'publicIndex']);
$router->get('/api/public/quiz-sets/{id}', [QuizController::class, 'publicShow']);
$router->get('/api/public/quiz-sets/{id}/questions', [QuizController::class, 'publicQuestions']);

// Authenticated quiz routes (requires authentication)
$router->get('/api/quiz-sets', [QuizController::class, 'authenticatedIndex'], [AuthMiddleware::class]);
$router->get('/api/quiz-sets/{id}', [QuizController::class, 'show'], [AuthMiddleware::class]);
$router->get('/api/quiz-sets/{id}/questions', [QuizController::class, 'questions'], [AuthMiddleware::class]);
$router->post('/api/quiz-sets/{id}/start', [QuizController::class, 'startAttempt'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/quiz-sets/{id}/submit', [QuizController::class, 'submitAttempt'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/quiz-sets/{id}/statistics', [QuizController::class, 'statistics'], [AuthMiddleware::class]);
$router->post('/api/quiz-sets/batch-check', [QuizController::class, 'batchCheck'], [AuthMiddleware::class, CsrfMiddleware::class]);

// Admin quiz management routes
$router->post('/api/quiz-sets', [QuizController::class, 'store'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/quiz-sets/{id}', [QuizController::class, 'update'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/quiz-sets/{id}', [QuizController::class, 'delete'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/admin/quiz-sets/{id}/status', [QuizController::class, 'updateStatus'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/admin/quiz-sets/{id}/access-type', [QuizController::class, 'updateAccessType'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);

// Admin question management routes
$router->post('/api/quiz-sets/{id}/questions', [QuizController::class, 'createQuestion'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->put('/api/quiz-sets/{id}/questions/{question_id}', [QuizController::class, 'updateQuestion'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/quiz-sets/{id}/questions/{question_id}', [QuizController::class, 'deleteQuestion'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
```

### 6. Access Control Logic Implementation

#### Unauthenticated User Access
```
Can access: 
- Files with access_type = 'all' AND status = 'active'
- Quiz sets with access_type = 'all' AND status = 'published'

Cannot access:
- Files with access_type = 'logged_in' or 'private'
- Quiz sets with access_type = 'logged_in' or 'private'
- Any inactive/draft resources
```

#### Authenticated User Access  
```
Can access:
- Files with access_type = 'all' AND status = 'active'
- Files with access_type = 'logged_in' AND status = 'active'
- Files with access_type = 'private' AND status = 'active' AND has explicit permission
- Quiz sets with access_type = 'all' AND status = 'published'
- Quiz sets with access_type = 'logged_in' AND status = 'published'
- Quiz sets with access_type = 'private' AND status = 'published' AND has explicit permission

Cannot access:
- Any inactive/draft resources (unless admin)
- Private resources without explicit permission (unless admin)
```

#### Admin Access
```
Can access:
- ALL resources regardless of access_type, status, or permissions
- Can modify status and access_type of any resource
- Full CRUD operations on all resources
```

## Implementation Steps

### Phase 1: Database Schema Updates ✅ COMPLETED
1. ✅ Create migration file for database changes
2. ✅ Add status field to files table
3. ✅ Update access_type enum for files table
4. ✅ Add status field to quiz_sets table
5. ✅ Update access_type enum for quiz_sets table
6. ✅ Create necessary indexes
7. ✅ Migrate existing data
8. ✅ Test migration on development environment

**Phase 1 Status:** ✅ **COMPLETED** (2025-04-30)
**Details:** All database schema changes successfully applied. See `database/phase1_completion_summary.md` for full details.

### Phase 2: Constants and Configuration ✅ COMPLETED
1. ✅ Update `src/config/constants.php` with new access types and statuses
2. ✅ Update any configuration files that reference access types
3. ✅ Test constant values

**Phase 2 Status:** ✅ **COMPLETED** (2025-04-30)
**Details:** All configuration constants successfully added and tested. See `phase2_completion_summary.md` for full details.

### Phase 3: Model Updates - File Model ✅ COMPLETED
1. ✅ Update `File::findById()` to include status field
2. ✅ Update `File::create()` to validate status
3. ✅ Update `File::update()` to handle status changes
4. ✅ Update `File::checkFileAccess()` with new logic
5. ✅ Update `File::getFilesByFolderPaginated()` with status filtering
6. ✅ Add `File::updateStatus()` method
7. ✅ Add `File::updateAccessType()` method
8. ✅ Add `File::getPublicFiles()` method
9. ✅ Add `File::getLoggedInFiles()` method
10. ✅ Update `File::delete()` to handle new fields

**Phase 3 Status:** ✅ **COMPLETED** (2025-04-30)
**Details:** All File model updates successfully implemented with 100% test success rate. See `phase3_completion_summary.md` for full details.

### Phase 4: Model Updates - Quiz Set Model ✅ COMPLETED
1. ✅ Update `QuizSet::findById()` to include status field
2. ✅ Update `QuizSet::create()` to validate status
3. ✅ Update `QuizSet::update()` to handle status changes
4. ✅ Update `QuizSet::checkQuizSetAccess()` with new logic
5. ✅ Update `QuizSet::getAllQuizSets()` with status filtering
6. ✅ Add `QuizSet::updateStatus()` method
7. ✅ Add `QuizSet::updateAccessType()` method
8. ✅ Add `QuizSet::getPublicQuizSets()` method
9. ✅ Add `QuizSet::getLoggedInQuizSets()` method
10. ✅ Update `QuizSet::delete()` to handle new fields

**Phase 4 Status:** ✅ **COMPLETED** (2025-04-30)
**Details:** All QuizSet model updates successfully implemented with 100% test success rate.

### Phase 5: Model Updates - Access Model
1. Refactor `Access::checkAccess()` to focus on permissions only
2. Update any methods that depend on access_type checking
3. Ensure backward compatibility with existing permission system

### Phase 6: Controller Updates - File Controller
1. Add `FileController::publicIndex()` method
2. Add `FileController::publicShow()` method
3. Add `FileController::publicDownload()` method
4. Add `FileController::authenticatedIndex()` method
5. Add `FileController::updateStatus()` method
6. Add `FileController::updateAccessType()` method
7. Update `FileController::show()` method
8. Update `FileController::download()` method
9. Update `FileController::folderFiles()` method
10. Update `FileController::serveByPath()` method

### Phase 7: Controller Updates - Quiz Controller
1. Add `QuizController::publicIndex()` method
2. Add `QuizController::publicShow()` method
3. Add `QuizController::publicQuestions()` method
4. Add `QuizController::authenticatedIndex()` method
5. Add `QuizController::updateStatus()` method
6. Add `QuizController::updateAccessType()` method
7. Update `QuizController::index()` method
8. Update `QuizController::show()` method
9. Update `QuizController::questions()` method
10. Update `QuizController::startAttempt()` method

### Phase 8: Route Updates
1. Update file routes in `public/index.php`
2. Update quiz set routes in `public/index.php`
3. Test route registration
4. Verify middleware configuration

### Phase 9: Documentation and Deployment
1. Update API documentation
2. Create migration guide
3. Update user documentation
4. Deploy to staging environment
5. Final testing on staging
6. Deploy to production
7. Monitor for issues

## Security Considerations

### Access Control Validation
- Always validate access_type and status values against allowed enums
- Never trust client-provided access control information
- Implement server-side access checking for all operations
- Log all access denials for security monitoring

### SQL Injection Prevention
- Use prepared statements for all database queries
- Validate and sanitize all user inputs
- Implement parameter binding for dynamic queries

### Authorization Checks
- Perform authorization checks before data access
- Implement principle of least privilege
- Use role-based access control consistently
- Audit admin actions

### Data Integrity
- Use database transactions for multi-step operations
- Implement proper error handling and rollback
- Validate referential integrity
- Maintain data consistency across status changes

## Performance Considerations

### Database Optimization
- Create composite indexes on (access_type, status) columns
- Optimize queries for pagination and filtering
- Use query caching where appropriate
- Monitor slow queries and optimize

### Caching Strategy
- Cache public resource listings
- Implement cache invalidation on status/access_type changes
- Use appropriate cache TTL values
- Monitor cache hit rates

### Query Optimization
- Avoid N+1 query problems
- Use JOINs instead of multiple queries
- Implement proper pagination
- Use database-specific optimizations

## Backward Compatibility

### Migration Strategy
- Maintain existing `is_published` field for quiz sets
- Keep existing access_permissions functionality
- Support legacy API endpoints during transition period
- Provide deprecation warnings for old endpoints

### Data Migration
- Migrate existing `is_published` values to new status field
- Update existing access_type values to new enum
- Preserve existing user permissions
- Create data backup before migration

### API Compatibility
- Maintain existing response formats
- Add new fields without breaking existing clients
- Provide versioned API if needed
- Document breaking changes clearly

## Rollback Plan

### Database Rollback
- Create rollback migration scripts
- Test rollback procedures
- Maintain data backups
- Document rollback steps

### Code Rollback
- Use version control for easy rollback
- Maintain feature branches
- Test rollback procedures
- Communicate rollback to stakeholders

## Monitoring and Maintenance

### Key Metrics
- Access denial rates
- Response times for different access types
- Error rates by user type
- Resource usage patterns

### Alerts
- High access denial rates
- Unusual access patterns
- Performance degradation
- Security violations

### Maintenance Tasks
- Regular review of access logs
- Performance optimization
- Security updates
- Feature enhancements

## Success Criteria

### Functional Requirements
- ✅ Unauthenticated users can access public active resources
- ✅ Authenticated users can access public and logged-in active resources  
- ✅ Users with permissions can access private active resources
- ✅ Admins can access and manage all resources
- ✅ Status changes properly control resource visibility
- ✅ Access type changes properly control resource access

### Non-Functional Requirements
- ✅ No security vulnerabilities in access control
- ✅ Proper error logging only on catched exceptions, no unnecessary logging in other parts.

### User Experience
- ✅ Clear error messages for access denials
- ✅ Intuitive API design
- ✅ Consistent behavior across resource types
- ✅ Proper documentation and examples

## Conclusion

This implementation plan provides a comprehensive approach to enhancing the access control system for Files and Quiz Sets in the EMA Education Platform. The phased approach ensures systematic implementation while maintaining system stability and backward compatibility.

The new access control system will provide:
- Enhanced security through proper access type classification
- Better resource management through status controls  
- Improved user experience with appropriate public/private content separation
- Flexible admin controls for content management
- Scalable architecture for future enhancements

Following this plan will result in a robust, secure, and user-friendly access control system that meets the requirements of the EMA Education Platform.