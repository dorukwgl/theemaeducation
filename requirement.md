# EMA Education Platform - Complete Software Requirements Specification

## Project Overview

**EMA Education Platform** is a comprehensive web-based educational management system that provides quiz/exam management, file distribution, user access control, and mobile app integration for educational institutions.

## Functional Requirements for Developers

### 1. User Management System
- **User Registration** (`register.php`)
  - Create user accounts with full name, email, phone, password
  - Profile image upload support
  - Email and phone uniqueness validation
  - Role-based access (user/admin)

- **Authentication System** (`login.php`)
  - Secure login with email/phone and password
  - Session management with security headers
  - Database connection with retry logic
  - CORS support for cross-origin requests

### 2. File Management System
- **File Upload** (`upload_file.php`)
  - Multi-format file support (documents, images, audio)
  - Folder-based organization
  - Access type control (all/logged_in users)
  - File metadata storage and retrieval

- **Folder Management** (`folders.php`, `folder_details_page.php`)
  - Hierarchical folder structure
  - Folder details with file listings
  - Icon support for visual organization
  - Access control per folder

### 3. Quiz/Examination System
- **Quiz Management** (`quiz_set_detail_page.php`, `quiz_set_detail_page_ui.php`)
  - Create and manage quiz sets
  - Question bank with multiple choice format
  - Multimedia question support (text, images, audio)
  - Answer options with file attachments
  - Rich text formatting for questions and answers

- **Question Types** (from `questions` table)
  - Multiple choice questions (A, B, C, D)
  - Text and image-based questions
  - Optional explanatory text
  - Word formatting (bold, underline) support

### 4. Access Control System
- **Permission Management** (`grant_file_access.php`, `check_access.php`)
  - Granular access control for files and quiz sets
  - User-specific and role-based permissions
  - Access count tracking and limits
  - Admin override capabilities

- **Access Types**:
  - `all_users`: Public access
  - `logged_in`: Registered users only
  - Individual user permissions
  - Admin-level access

### 5. Administrative Features
- **Admin Panel** (`give_admin_access.php`, `get_admins.php`)
  - Admin user management
  - Access permission grants/revokes
  - Bulk access operations
  - User activity monitoring

- **Content Management** (`notices.php`)
  - System announcements
  - File attachments support
  - CRUD operations for notices
  - Multi-file upload support

### 6. Mobile App Integration
- **App Distribution** (`index.php`)
  - Android APK download page
  - Download tracking analytics
  - Progressive Web App support
  - Service worker implementation

- **Analytics** (`track_download.php`)
  - Platform-specific download tracking
  - Retry mechanism for failed tracking
  - Error handling and logging

### 7. API Endpoints Architecture

#### Authentication APIs:
- `POST /login.php` - User authentication
- `POST /register.php` - User registration

#### Content Management APIs:
- `GET /folders.php` - List folders
- `GET /folder_details_page.php` - Folder contents
- `POST /upload_file.php` - File upload
- `GET /quiz_set_detail_page.php` - Quiz details

#### Access Control APIs:
- `POST /check_access.php` - Verify access permissions
- `POST /grant_file_access.php` - Grant/revoke access
- `GET /get_access_permissions` - List permissions
- `POST /update_activation` - Toggle content activation

#### Admin APIs:
- `POST /give_admin_access.php` - Admin management
- `GET /get_admins.php` - List admins
- `POST /approve_reset_request.php` - Password reset approval

#### System APIs:
- `GET /notices.php` - System notices
- `POST /track_download.php` - Download analytics

### 8. Technical Implementation Requirements

#### Database Requirements:
- MySQL 8.0+ with UTF8MB4 support
- InnoDB engine for transactional integrity
- Foreign key relationships for data consistency
- Indexing for performance optimization

#### Security Requirements:
- Input validation and sanitization
- SQL injection prevention with prepared statements
- CORS configuration for API security
- Session management with secure headers
- Password hashing for user authentication

#### File Storage Requirements:
- Organized upload directory structure
- File type validation and security scanning
- Storage path management in database
- Icon support for visual content organization

#### Performance Requirements:
- Database connection pooling
- Efficient query optimization
- File compression for large uploads
- Caching for frequently accessed content

#### Mobile Integration Requirements:
- Responsive web design
- Progressive Web App capabilities
- Service worker for offline functionality
- Cross-platform compatibility

## Database Schema Details

### Users Table Structure
```sql
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `phone` varchar(10) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role` varchar(20) DEFAULT 'user',
  `is_logged_in` tinyint(1) DEFAULT '0'
);
```

### Files Table Structure
```sql
CREATE TABLE `files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `access_type` enum('all','logged_in') NOT NULL DEFAULT 'logged_in'
);
```

### Quiz Sets Table Structure
```sql
CREATE TABLE `quiz_sets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folder_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `access_type` enum('all','logged_in') NOT NULL DEFAULT 'logged_in'
);
```

### Questions Table Structure
```sql
CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_set_id` int NOT NULL,
  `question` text NOT NULL,
  `optional_text` text,
  `question_file` mediumblob,
  `question_file_type` varchar(100) DEFAULT NULL,
  `question_file_mime` varchar(100) DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `choice_A_text` text,
  `choice_A_file` mediumblob,
  `choice_A_file_type` varchar(100) DEFAULT NULL,
  `choice_A_file_mime` varchar(100) DEFAULT NULL,
  `choice_B_text` text,
  `choice_B_file` mediumblob,
  `choice_B_file_type` varchar(100) DEFAULT NULL,
  `choice_B_file_mime` varchar(100) DEFAULT NULL,
  `choice_C_text` text,
  `choice_C_file` mediumblob,
  `choice_C_file_type` varchar(100) DEFAULT NULL,
  `choice_C_file_mime` varchar(100) DEFAULT NULL,
  `choice_D_text` text,
  `choice_D_file` mediumblob,
  `choice_D_file_type` varchar(100) DEFAULT NULL,
  `choice_D_file_mime` varchar(100) DEFAULT NULL,
  `question_type` varchar(20) NOT NULL,
  `question_word_formatting` json NOT NULL,
  `optional_word_formatting` json NOT NULL,
  `choice_A_word_formatting` json NOT NULL,
  `choice_B_word_formatting` json NOT NULL,
  `choice_C_word_formatting` json NOT NULL,
  `choice_D_word_formatting` json NOT NULL
);
```

### Access Permissions Table Structure
```sql
CREATE TABLE `access_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `item_id` int NOT NULL,
  `item_type` enum('file','quiz_set') NOT NULL,
  `access_times` int NOT NULL DEFAULT '0',
  `times_accessed` int NOT NULL DEFAULT '0',
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
);
```

## Implementation Priority

### Phase 1: Core Infrastructure
1. Database setup and configuration
2. User authentication system
3. Basic file management
4. Session management and security

### Phase 2: Content Management
1. Folder structure implementation
2. File upload and organization
3. Basic quiz creation
4. Access control foundation

### Phase 3: Advanced Features
1. Complete quiz system with multimedia
2. Advanced access controls
3. Admin panel functionality
4. Mobile app integration

### Phase 4: Enhancement & Optimization
1. Performance optimization
2. Advanced analytics
3. Mobile app features
4. System notifications

## Security Considerations

- Implement proper input validation for all user inputs
- Use prepared statements for all database queries
- Implement rate limiting for API endpoints
- Secure file upload with type validation
- Implement proper session management
- Use HTTPS for all communications
- Regular security audits and updates

## Deployment Requirements

- PHP 8.0+ with required extensions
- MySQL 8.0+ database server
- Web server (Apache/Nginx) with PHP support
- SSL certificate for HTTPS
- File storage with appropriate permissions
- Backup and recovery procedures

This specification provides complete guidance for developers to recreate the entire EMA Education Platform from scratch.
