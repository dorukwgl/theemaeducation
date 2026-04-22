# EMA Education Platform - API Documentation

**Complete API documentation for all endpoints with parameters, responses, and usage examples.**

## Table of Contents

- [Authentication](#authentication)
- [Users](#users)
- [Folders](#folders)
- [Files](#files)
- [Quizzes](#quizzes)
- [Notices](#notices)
- [Access Control](#access-control)
- [Admin Dashboard](#admin-dashboard)

---

## Authentication

### POST /api/auth/login
User login with attempt tracking and IP lockout.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "user@example.com",
      "phone": "1234567890",
      "role": "user",
      "image": null,
      "created_at": "2026-04-20 10:00:00",
      "updated_at": "2026-04-20 10:00:00",
      "last_login_at": null
    }
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**Rate Limiting:** 20 attempts per 30 minutes per IP

### POST /api/auth/register
User registration with validation.

**Request Body:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "1234567890",
  "password": "securePassword123"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 2,
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "1234567890",
      "role": "user",
      "image": null,
      "created_at": "2026-04-21 09:30:00",
      "updated_at": "2026-04-21 09:30:00",
      "last_login_at": null
    }
  }
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Invalid email format",
    "password": "Password must be at least 8 characters"
  }
}
```

### POST /api/auth/logout
User logout with session cleanup.

**Headers:** Requires authentication session

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

### POST /api/auth/forgot-password
Request password reset.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password reset email sent"
}
```

**Rate Limiting:** 10 requests per hour per IP

### POST /api/auth/reset-password
Complete password reset with token validation.

**Request Body:**
```json
{
  "reset_id": 123,
  "token": "abc123def456",
  "new_password": "newSecurePassword456"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password reset successful"
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Invalid or expired token"
}
```

### POST /api/auth/change-password
Change password for authenticated user.

**Request Body:**
```json
{
  "current_password": "oldPassword123",
  "new_password": "newPassword456"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

### GET /api/auth/me
Get current authenticated user information.

**Headers:** Requires authentication session

**Success Response (200):**
```json
{
  "success": true,
  "message": "User data retrieved successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "user@example.com",
      "role": "user"
    }
  }
}
```

---

## Users

### GET /api/users
List all users with pagination, search, role filtering.

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20, max: 100)
- `search` (optional): Search query string
- `role` (optional): Filter by role ('user', 'admin')
- `sort_by` (optional): Sort field (default: 'created_at')
- `sort_order` (optional): Sort order ('ASC', 'DESC', default: 'DESC')

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": {
    "users": [...],
    "total": 150,
    "page": 1,
    "per_page": 20,
    "total_pages": 8
  }
}
```

### GET /api/users/{id}
Get user profile by ID.

**URL Parameters:**
- `id` (required): User ID

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "user@example.com",
      "phone": "1234567890",
      "role": "user",
      "image": null,
      "created_at": "2026-04-20 10:00:00"
    }
  }
}
```

### PUT /api/users/{id}
Update user profile.

**URL Parameters:**
- `id` (required): User ID

**Request Body:**
```json
{
  "full_name": "John Updated",
  "phone": "9876543210",
  "image": "base64_image_data"
}
```

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Updated",
      "email": "user@example.com",
      "phone": "9876543210",
      "role": "user",
      "image": null
    }
  }
}
```

### DELETE /api/users/{id}
Delete user account with cascade cleanup.

**URL Parameters:**
- `id` (required): User ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

---

## Folders

### GET /api/folders
List all folders with file counts.

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Folders retrieved successfully",
  "data": {
    "folders": [
      {
        "id": 1,
        "name": "Mathematics",
        "icon_path": "/uploads/folder_icons/icon1.png",
        "created_at": "2026-04-20 10:00:00",
        "updated_at": "2026-04-20 10:00:00",
        "file_count": 15
      }
    ],
    "total": 10
  }
}
```

### POST /api/folders
Create new folder with icon upload.

**Request Body:**
```json
{
  "name": "New Folder",
  "icon": "base64_image_data"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Folder created successfully",
  "data": {
    "folder": {
      "id": 11,
      "name": "New Folder",
      "icon_path": "/uploads/folder_icons/icon11.png",
      "created_at": "2026-04-21 10:00:00"
    }
  }
}
```

### GET /api/folders/{id}
Get folder details with optional contents.

**URL Parameters:**
- `id` (required): Folder ID
- `include_contents` (optional): Include folder contents (default: false)

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Folder retrieved successfully",
  "data": {
    "folder": {
      "id": 1,
      "name": "Mathematics",
      "icon_path": "/uploads/folder_icons/icon1.png",
      "file_count": 15,
      "contents": [
        {
          "id": 1,
          "name": "lesson1.pdf",
          "file_type": "application/pdf",
          "file_size": 1024000
        }
      ]
    },
    "access_info": {
      "has_access": true,
      "access_type": "logged_in",
      "can_write": false
    }
  }
}
```

### PUT /api/folders/{id}
Update folder with icon replacement.

**URL Parameters:**
- `id` (required): Folder ID

**Request Body:**
```json
{
  "name": "Updated Folder Name",
  "icon": "new_base64_image_data"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Folder updated successfully",
  "data": {
    "folder": {
      "id": 1,
      "name": "Updated Folder Name",
      "icon_path": "/uploads/folder_icons/icon_updated.png",
      "updated_at": "2026-04-21 10:00:00"
    }
  }
}
```

### DELETE /api/folders/{id}
Delete folder with cascade cleanup.

**URL Parameters:**
- `id` (required): Folder ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Folder deleted successfully"
}
```

---

## Files

### POST /api/files/upload
Upload file with security validation.

**Request Body (multipart/form-data):**
```
file: [file]
folder_id: 1
```

**Headers:** Requires authentication (admin only)

**Success Response (201):**
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": {
    "file": {
      "id": 1,
      "name": "document.pdf",
      "file_type": "application/pdf",
      "file_size": 1024000,
      "folder_id": 1,
      "file_path": "/uploads/files/2026/04/21/document.pdf",
      "uploaded_at": "2026-04-21 10:00:00",
      "access_count": 0
    }
  }
}
```

### GET /api/files/{id}
Get file details with embedded access control.

**URL Parameters:**
- `id` (required): File ID

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "File retrieved successfully",
  "data": {
    "file": {
      "id": 1,
      "name": "document.pdf",
      "file_type": "application/pdf",
      "file_size": 1024000,
      "folder_id": 1,
      "folder_name": "Mathematics"
    },
    "access_info": {
      "has_access": true,
      "access_type": "individual_permission",
      "remaining_accesses": 5,
      "can_download": true
    }
  }
}
```

### DELETE /api/files/{id}
Delete file with cascade cleanup.

**URL Parameters:**
- `id` (required): File ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "File deleted successfully"
}
```

### GET /api/files/{id}/download
Download file with access tracking.

**URL Parameters:**
- `id` (required): File ID

**Headers:** Requires authentication

**Success Response (200):**
- Returns file directly with proper headers

**Error Response (403):**
```json
{
  "success": false,
  "message": "No access to download this file"
}
```

---

## Quizzes

### GET /api/quiz-sets
List quiz sets with pagination and filtering.

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)
- `folder_id` (optional): Filter by folder ID
- `published_only` (optional): Only published quiz sets (default: true)

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz sets retrieved successfully",
  "data": {
    "quiz_sets": [
      {
        "id": 1,
        "folder_id": 1,
        "name": "Math Quiz 1",
        "icon_path": "/uploads/quiz_icons/icon1.png",
        "question_count": 10,
        "duration_minutes": 30,
        "passing_score": 70,
        "is_published": true,
        "created_at": "2026-04-20 10:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "per_page": 20
  }
}
```

### GET /api/quiz-sets/{id}
Get quiz set details with optional questions and statistics.

**URL Parameters:**
- `id` (required): Quiz set ID
- `include_questions` (optional): Include questions (default: false)
- `include_stats` (optional): Include statistics (default: false)

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz set retrieved successfully",
  "data": {
    "quiz_set": {
      "id": 1,
      "name": "Math Quiz 1",
      "description": "Basic mathematics quiz",
      "question_count": 10,
      "duration_minutes": 30,
      "passing_score": 70,
      "is_published": true,
      "created_at": "2026-04-20 10:00:00"
    },
    "questions": [...],
    "stats": {
      "total_attempts": 150,
      "average_score": 75.5,
      "completion_rate": 85.3
    },
    "access_info": {
      "has_access": true,
      "access_type": "logged_in"
    }
  }
}
```

### POST /api/quiz-sets
Create new quiz set (admin only).

**Request Body:**
```json
{
  "folder_id": 1,
  "name": "New Math Quiz",
  "description": "Advanced mathematics quiz",
  "duration_minutes": 45,
  "passing_score": 80,
  "icon": "base64_image_data"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Quiz set created successfully",
  "data": {
    "quiz_set": {
      "id": 6,
      "name": "New Math Quiz",
      "folder_id": 1,
      "question_count": 0,
      "duration_minutes": 45,
      "passing_score": 80,
      "is_published": false,
      "created_at": "2026-04-21 10:00:00"
    }
  }
}
```

### PUT /api/quiz-sets/{id}
Update quiz set (admin only).

**URL Parameters:**
- `id` (required): Quiz set ID

**Request Body:**
```json
{
  "name": "Updated Quiz Name",
  "description": "Updated description",
  "duration_minutes": 60,
  "passing_score": 85,
  "is_published": true,
  "icon": "new_base64_image_data"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz set updated successfully",
  "data": {
    "quiz_set": {
      "id": 1,
      "name": "Updated Quiz Name",
      "description": "Updated description",
      "duration_minutes": 60,
      "passing_score": 85,
      "is_published": true,
      "updated_at": "2026-04-21 10:00:00"
    }
  }
}
```

### DELETE /api/quiz-sets/{id}
Delete quiz set with cascade cleanup (admin only).

**URL Parameters:**
- `id` (required): Quiz set ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz set deleted successfully"
}
```

### GET /api/quiz-sets/{id}/questions
Get quiz set questions with pagination.

**URL Parameters:**
- `id` (required): Quiz set ID
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)
- `include_files` (optional): Include file URLs (default: false)

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Questions retrieved successfully",
  "data": {
    "questions": [
      {
        "id": 1,
        "question_text": "What is 2 + 2?",
        "correct_answer": "A",
        "choice_A_text": "3",
        "choice_B_text": "4",
        "choice_C_text": "5",
        "choice_D_text": "6",
        "question_type": "reading",
        "question_file_path": "/uploads/questions/q1_audio.mp3"
      }
    ],
    "total": 10,
    "page": 1,
    "per_page": 20
  }
}
```

### POST /api/quiz-sets/{id}/questions
Create question (admin only).

**URL Parameters:**
- `id` (required): Quiz set ID

**Request Body:**
```json
{
  "question_text": "What is 3 x 3?",
  "correct_answer": "A",
  "choice_A_text": "9",
  "choice_B_text": "6",
  "choice_C_text": "8",
  "choice_D_text": "7",
  "question_type": "reading",
  "word_formatting": "{\"bold\": [\"3\"]}",
  "question_file": "base64_audio_data"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Question created successfully",
  "data": {
    "question": {
      "id": 11,
      "question_text": "What is 3 x 3?",
      "correct_answer": "A",
      "question_type": "reading",
      "created_at": "2026-04-21 10:00:00"
    }
  }
}
```

### PUT /api/quiz-sets/{id}/questions/{question_id}
Update question (admin only).

**URL Parameters:**
- `id` (required): Quiz set ID
- `question_id` (required): Question ID

**Request Body:** Same as create question

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Question updated successfully",
  "data": {
    "question": {
      "id": 1,
      "question_text": "Updated question text",
      "updated_at": "2026-04-21 10:00:00"
    }
  }
}
```

### DELETE /api/quiz-sets/{id}/questions/{question_id}
Delete question with backup (admin only).

**URL Parameters:**
- `id` (required): Quiz set ID
- `question_id` (required): Question ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Question deleted successfully",
  "data": {
    "backup_id": 45,
    "message": "Question backed up to backup table"
  }
}
```

### POST /api/quiz-sets/{id}/start
Start quiz attempt.

**URL Parameters:**
- `id` (required): Quiz set ID

**Request Body:**
```json
{
  "question_count": 5
}
```

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz attempt started",
  "data": {
    "attempt": {
      "id": 25,
      "quiz_set_id": 1,
      "attempt_number": 1,
      "started_at": "2026-04-21 10:00:00"
    },
    "questions": [...]
  }
}
```

### POST /api/quiz-sets/{id}/submit
Submit quiz answers.

**URL Parameters:**
- `id` (required): Quiz set ID

**Request Body:**
```json
{
  "attempt_id": 25,
  "answers": [
    {
      "question_id": 1,
      "answer": "A",
      "time_spent_seconds": 45
    },
    {
      "question_id": 2,
      "answer": "A",
      "time_spent_seconds": 30
    }
  ]
}
```

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz submitted successfully",
  "data": {
    "result": {
      "score": 80,
      "total_questions": 2,
      "correct_answers": 2,
      "percentage": 100,
      "time_spent_seconds": 75
    },
    "attempt": {
      "id": 25,
      "completed_at": "2026-04-21 10:01:15"
    }
  }
}
```

### GET /api/quiz-sets/{id}/statistics
Get quiz statistics (admin/owner only).

**URL Parameters:**
- `id` (required): Quiz set ID
- `timeframe` (optional): 'day', 'week', 'month', 'all' (default: 'all')

**Headers:** Requires authentication (admin or quiz owner only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Quiz statistics retrieved successfully",
  "data": {
    "statistics": {
      "total_attempts": 150,
      "average_score": 75.5,
      "completion_rate": 85.3,
      "average_time_spent": 1800,
      "performance_distribution": {
        "excellent": 30,
        "good": 40,
        "average": 25,
        "poor": 5
      }
    },
    "analytics": {
      "attempt_frequency": [
        {"date": "2026-04-20", "count": 25},
        {"date": "2026-04-21", "count": 30}
      ],
      "score_trends": [
        {"date": "2026-04-20", "avg_score": 70},
        {"date": "2026-04-21", "avg_score": 78}
      ]
    }
  }
}
```

### POST /api/quiz-sets/batch-check
Batch quiz set access check.

**Request Body:**
```json
{
  "quiz_set_ids": [1, 2, 3, 4, 5]
}
```

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Batch access check completed",
  "data": {
    "results": [
      {"quiz_set_id": 1, "has_access": true},
      {"quiz_set_id": 2, "has_access": true},
      {"quiz_set_id": 3, "has_access": false}
    ],
    "summary": {
      "total": 3,
      "accessible": 2,
      "inaccessible": 1
    }
  }
}
```

---

## Notices

### GET /api/notices
Get all active system notices (public access).

**Success Response (200):**
```json
{
  "success": true,
  "message": "System notices retrieved successfully",
  "data": {
    "notices": [
      {
        "id": 1,
        "title": "System Maintenance Scheduled",
        "content": "System will be down for maintenance on Sunday from 2:00 AM to 4:00 AM UTC.",
        "notice_type": "info",
        "priority": "high",
        "expires_at": "2026-04-22 04:00:00",
        "created_at": "2026-04-20 10:00:00"
      }
    ],
    "total": 1
  }
}
```

### POST /api/notices
Create system notice (admin only).

**Request Body:**
```json
{
  "title": "New System Notice",
  "content": "Notice content here",
  "notice_type": "info",
  "priority": "medium",
  "expires_at": "2026-05-01 00:00:00"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (201):**
```json
{
  "success": true,
  "message": "System notice created successfully",
  "data": {
    "notice": {
      "id": 2,
      "title": "New System Notice",
      "notice_type": "info",
      "priority": "medium",
      "created_at": "2026-04-21 10:00:00"
    }
  }
}
```

### GET /api/notices/{id}
Get notice details.

**URL Parameters:**
- `id` (required): Notice ID

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notice retrieved successfully",
  "data": {
    "notice": {
      "id": 1,
      "title": "System Maintenance Scheduled",
      "content": "Full notice content",
      "notice_type": "info",
      "priority": "high",
      "created_at": "2026-04-20 10:00:00",
      "view_count": 1250,
      "dismissal_count": 85
    }
  }
}
```

### PUT /api/notices/{id}
Update system notice (admin only).

**URL Parameters:**
- `id` (required): Notice ID

**Request Body:**
```json
{
  "title": "Updated Notice Title",
  "content": "Updated notice content",
  "priority": "high",
  "is_active": true
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notice updated successfully"
}
```

### DELETE /api/notices/{id}
Delete system notice (admin only).

**URL Parameters:**
- `id` (required): Notice ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notice deleted successfully"
}
```

### POST /api/notices/{id}/upload-attachment
Upload notice attachment (admin only).

**URL Parameters:**
- `id` (required): Notice ID

**Request Body (multipart/form-data):**
```
attachment: [file]
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Attachment uploaded successfully",
  "data": {
    "attachment": {
      "id": 5,
      "file_name": "document.pdf",
      "file_path": "/uploads/notice_attachments/doc.pdf",
      "file_size": 1024000,
      "uploaded_at": "2026-04-21 10:00:00"
    }
  }
}
```

### DELETE /api/notices/{id}/attachment
Delete notice attachment (admin only).

**URL Parameters:**
- `id` (required): Notice ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Attachment deleted successfully"
}
```

### POST /api/notices/{id}/view
Track notice view (authenticated users).

**URL Parameters:**
- `id` (required): Notice ID

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notice view tracked"
}
```

### POST /api/notices/{id}/dismiss
Dismiss notice for user (authenticated users).

**URL Parameters:**
- `id` (required): Notice ID

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notice dismissed",
  "data": {
    "dismissed": true,
    "dismissed_at": "2026-04-21 10:00:00"
  }
}
```

### GET /api/notices/dismissed
Get user's dismissed notices.

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Dismissed notices retrieved",
  "data": {
    "notices": [...],
    "total": 5
  }
}
```

### GET /api/notices/{id}/statistics
Get notice statistics (admin/owner only).

**URL Parameters:**
- `id` (required): Notice ID

**Headers:** Requires authentication (admin or owner only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notice statistics retrieved",
  "data": {
    "statistics": {
      "view_count": 1250,
      "dismissal_count": 85,
      "engagement_rate": 93.2,
      "unique_viewers": 450,
      "peak_view_time": "2026-04-20 15:00:00"
    }
  }
}
```

### POST /api/notices/bulk-update-status
Bulk update notice status (admin only).

**Request Body:**
```json
{
  "notice_ids": [1, 2, 3],
  "is_active": false
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Bulk status update completed",
  "data": {
    "updated_count": 3,
    "failed_count": 0
  }
}
```

---

## Access Control

### POST /api/access/batch-check
Batch access check for multiple items.

**Request Body:**
```json
{
  "items": [
    {"item_id": 1, "item_type": "file"},
    {"item_id": 2, "item_type": "file"},
    {"item_id": 5, "item_type": "quiz_set"}
  ]
}
```

**Headers:** Requires authentication

**Success Response (200):**
```json
{
  "success": true,
  "message": "Batch access check completed",
  "data": {
    "results": [
      {"item_id": 1, "item_type": "file", "has_access": true, "access_type": "individual_permission"},
      {"item_id": 2, "item_type": "file", "has_access": false, "access_type": "private"},
      {"item_id": 5, "item_type": "quiz_set", "has_access": true, "access_type": "logged_in"}
    ],
    "summary": {
      "total": 3,
      "accessible": 2,
      "inaccessible": 1
    }
  }
}
```

### POST /api/access/grant
Grant/revoke user access (admin only).

**Request Body:**
```json
{
  "user_id": 15,
  "item_id": 1,
  "item_type": "file",
  "access_times": 5,
  "action": "grant"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Access granted successfully"
}
```

### GET /api/access/permissions
List user permissions (admin only).

**Query Parameters:**
- `user_id` (optional): Filter by user ID
- `item_type` (optional): Filter by item type ('file', 'quiz_set')

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Permissions retrieved successfully",
  "data": {
    "permissions": [
      {
        "id": 1,
        "user_id": 15,
        "item_id": 1,
        "item_type": "file",
        "access_times": 5,
        "granted_at": "2026-04-20 10:00:00"
      }
    ],
    "total": 25
  }
}
```

### POST /api/access/all-users
Grant/revoke public access (admin only).

**Request Body:**
```json
{
  "item_id": 1,
  "item_type": "file",
  "grant": true
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Public access granted successfully"
}
```

### GET /api/access/all-users
List items with public access (admin only).

**Query Parameters:**
- `item_type` (optional): Filter by item type

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Public access items retrieved",
  "data": {
    "items": [
      {"id": 1, "item_type": "file", "name": "document.pdf"},
      {"id": 5, "item_type": "quiz_set", "name": "Math Quiz 1"}
    ],
    "total": 2
  }
}
```

### POST /api/access/login-users
Grant/revoke logged-in access (admin only).

**Request Body:**
```json
{
  "item_id": 1,
  "item_type": "file",
  "grant": true
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logged-in access granted successfully"
}
```

### GET /api/access/login-users
List items with logged-in access (admin only).

**Query Parameters:**
- `item_type` (optional): Filter by item type

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logged-in access items retrieved",
  "data": {
    "items": [
      {"id": 3, "item_type": "file", "name": "lesson.pdf"},
      {"id": 8, "item_type": "quiz_set", "name": "Science Quiz"}
    ],
    "total": 2
  }
}
```

---

## Admin Dashboard

### GET /api/admin/dashboard
Get comprehensive admin dashboard data.

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Admin dashboard data retrieved successfully",
  "data": {
    "overview": {
      "users": {
        "total": 250,
        "active_24h": 45,
        "new_today": 5,
        "new_week": 25,
        "new_month": 120,
        "admins": 5
      },
      "files": {
        "total": 1500,
        "total_downloads": 12500,
        "total_storage_mb": 875.5
      },
      "quizzes": {
        "total": 25,
        "total_attempts": 1500,
        "completion_rate": 85.3
      },
      "health": {
        "overall_score": 92,
        "overall_status": "healthy",
        "database": {
          "metric_type": "database",
          "metric_value": 35,
          "metric_unit": "ms",
          "status": "healthy"
        },
        "disk": {
          "metric_type": "disk",
          "metric_value": 65.2,
          "metric_unit": "%",
          "status": "warning"
        }
      },
      "recent_activity": [
        {"action": "login", "count": 125},
        {"action": "file_download", "count": 89},
        {"action": "quiz_attempt", "count": 67}
      ]
    },
    "alerts": [
      {
        "type": "disk",
        "severity": "warning",
        "message": "Disk usage is 65.2%. Consider cleaning up old files."
      }
    ],
    "generated_at": "2026-04-21 10:15:00"
  }
}
```

### GET /api/admin/user-activity
Get user activity statistics with time-based analysis.

**Query Parameters:**
- `timeframe` (optional): 'day', 'week', 'month', 'all' (default: 'all')

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "User activity statistics retrieved successfully",
  "data": {
    "activities": [
      {"action": "login", "count": 125, "unique_users": 45, "active_days": 15},
      {"action": "file_download", "count": 89, "unique_users": 32, "active_days": 12}
    ],
    "peak_hours": [
      {"hour": 9, "count": 45},
      {"hour": 14, "count": 38},
      {"hour": 15, "count": 32}
    ],
    "growth_trends": [
      {"date": "2026-04-20", "new_users": 8},
      {"date": "2026-04-21", "new_users": 5}
    ],
    "timeframe": "all"
  }
}
```

### GET /api/admin/system-health
Get system health monitoring data.

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "System health data retrieved successfully",
  "data": {
    "database": {
      "metric_type": "database",
      "metric_value": 35,
      "metric_unit": "ms",
      "status": "healthy",
      "details": "{\"connection_status\":\"connected\",\"active_connections\":45,\"query_time\":35}"
    },
    "disk": {
      "metric_type": "disk",
      "metric_value": 65.2,
      "metric_unit": "%",
      "status": "warning",
      "details": "{\"total_space_gb\":1250,\"free_space_gb\":435,\"used_space_gb\":815,\"usage_percentage\":65.2}"
    },
    "memory": {
      "metric_type": "memory",
      "metric_value": 72.5,
      "metric_unit": "%",
      "status": "healthy",
      "details": "{\"memory_usage_mb\":512,\"memory_limit\":\"256M\",\"memory_limit_mb\":256,\"usage_percentage\":72.5}"
    },
    "cpu": {
      "metric_type": "cpu",
      "metric_value": 0.8,
      "metric_unit": "load",
      "status": "healthy",
      "details": "{\"load_1min\":0.8,\"load_5min\":0.6,\"load_15min\":0.4}"
    },
    "api_performance": {
      "metric_type": "api_performance",
      "metric_value": 95,
      "metric_unit": "ms",
      "status": "healthy",
      "details": "{\"response_time\":95,\"test_query\":\"SELECT COUNT(*) FROM users\"}"
    },
    "error_rate": {
      "metric_type": "error_rate",
      "metric_value": 0.8,
      "metric_unit": "%",
      "status": "healthy",
      "details": "{\"error_count_last_hour\":12,\"error_rate\":0.8}"
    },
    "history": [
      {"metric_type": "database", "status": "healthy", "value": 35, "recorded_at": "2026-04-21 10:00:00"},
      {"metric_type": "database", "status": "healthy", "value": 38, "recorded_at": "2026-04-21 09:45:00"}
    ],
    "recommendations": [
      {
        "type": "disk",
        "priority": "high",
        "action": "Consider cleaning up old files or upgrading storage.",
        "target": "disk_space"
      }
    ]
  }
}
```

### GET /api/admin/audit-log
Get audit log with advanced filtering and pagination.

**Query Parameters:**
- `user_id` (optional): Filter by user ID
- `action` (optional): Filter by action type
- `entity_type` (optional): Filter by entity type
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 50, max: 100)

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Audit log entries retrieved successfully",
  "data": {
    "logs": [
      {
        "id": 1,
        "user_id": 1,
        "action": "user_update",
        "entity_type": "users",
        "entity_id": 25,
        "old_values": {"role":"user"},
        "new_values": {"role":"admin"},
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0",
        "user_name": "John Doe",
        "user_email": "john@example.com",
        "created_at": "2026-04-21 09:30:00"
      }
    ],
    "total": 1250,
    "page": 1,
    "per_page": 50,
    "total_pages": 25
  }
}
```

### POST /api/admin/bulk-operations
Create bulk operation (admin only).

**Request Body:**
```json
{
  "operation_type": "bulk_delete",
  "target_type": "files",
  "target_ids": [1, 2, 3, 4, 5]
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Bulk operation created successfully",
  "data": {
    "operation_id": 15,
    "operation_type": "bulk_delete",
    "target_type": "files",
    "total_items": 5
  }
}
```

### GET /api/admin/bulk-operations/{id}
Get bulk operation status.

**URL Parameters:**
- `id` (required): Bulk operation ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Bulk operation status retrieved successfully",
  "data": {
    "operation": {
      "id": 15,
      "operation_type": "bulk_delete",
      "target_type": "files",
      "status": "processing",
      "total_items": 5,
      "processed_items": 3,
      "failed_items": 0,
      "progress": 60,
      "started_at": "2026-04-21 10:00:00",
      "admin_name": "Admin User",
      "admin_email": "admin@example.com"
    },
    "results": {
      "total_items": 5,
      "processed_items": 3,
      "failed_items": 0,
      "errors": []
    }
  }
}
```

### DELETE /api/admin/bulk-operations/{id}
Cancel bulk operation (admin only).

**URL Parameters:**
- `id` (required): Bulk operation ID

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Bulk operation cancelled successfully"
}
```

### GET /api/admin/analytics
Get comprehensive system analytics.

**Query Parameters:**
- `timeframe` (optional): 'day', 'week', 'month', 'all' (default: 'all')

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "System analytics retrieved successfully",
  "data": {
    "user_engagement": {
      "active_users": 45,
      "total_activities": 1250,
      "unique_users": 45,
      "active_days": 15,
      "engagement_rate": 18,
      "total_users": 250
    },
    "content_performance": {
      "files": {
        "total_files": 1500,
        "total_downloads": 12500,
        "avg_downloads_per_file": 8.3
      },
      "quizzes": {
        "total_quizzes": 25,
        "total_attempts": 1500,
        "avg_score": 75.5
      }
    },
    "system_performance": {
      "avg_response_time": 95,
      "avg_query_time": 38,
      "status": "healthy"
    },
    "security_events": {
      "summary": {
        "total_events": 125,
        "affected_users": 8,
        "event_types": 12
      },
      "recent_events": [
        {"user_id": 1, "action": "user_update", "entity_type": "users", "created_at": "2026-04-21 09:30:00"},
        {"user_id": 2, "action": "bulk_delete", "entity_type": "files", "created_at": "2026-04-21 08:15:00"}
      ]
    },
    "charts": {
      "daily_activity": [
        {"date": "2026-04-20", "count": 125},
        {"date": "2026-04-21", "count": 145}
      ],
      "user_growth": [
        {"date": "2026-04-20", "new_users": 8},
        {"date": "2026-04-21", "new_users": 5}
      ]
    },
    "insights": [
      {
        "type": "engagement",
        "severity": "warning",
        "message": "User engagement rate is low (18%). Consider improving content and user experience."
      },
      {
        "type": "content",
        "severity": "info",
        "message": "Average quiz score is 75.5%. Review quiz difficulty and content quality."
      }
    ],
    "timeframe": "all"
  }
}
```

### POST /api/admin/health-check
Run comprehensive system health check (admin only).

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Health check completed successfully",
  "data": {
    "health_check": {
      "health_checks": {
        "database": {
          "metric_type": "database",
          "metric_value": 38,
          "metric_unit": "ms",
          "status": "healthy"
        },
        "disk": {
          "metric_type": "disk",
          "metric_value": 65.2,
          "metric_unit": "%",
          "status": "warning"
        }
      },
      "overall_score": 88,
      "overall_status": "warning"
    },
    "issues": [
      {
        "type": "disk",
        "severity": "warning",
        "metric_value": 65.2,
        "metric_unit": "%",
        "details": {
          "total_space_gb": 1250,
          "free_space_gb": 435,
          "used_space_gb": 815,
          "usage_percentage": 65.2
        }
      }
    ],
    "recommendations": [
      {
        "priority": "high",
        "action": "Clean up old files and consider upgrading storage capacity.",
        "target": "disk_space"
      },
      {
        "priority": "medium",
        "action": "Monitor CPU usage and consider optimizing resource-intensive operations.",
        "target": "cpu_usage"
      }
    ]
  }
}
```

### DELETE /api/admin/audit-log
Clear audit log entries (admin only).

**Query Parameters:**
- `older_than` (optional): Delete entries older than specified days (default: 90, minimum: 30)

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Audit log cleaned successfully",
  "data": {
    "deleted_count": 250,
    "older_than_days": 90
  }
}
```

---

## Admin Operations

### POST /api/admin/grant
Grant admin privileges (admin only).

**Request Body:**
```json
{
  "user_id": 25,
  "email": "user@example.com"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Admin privileges granted successfully",
  "data": {
    "user": {
      "id": 25,
      "full_name": "New Admin User",
      "email": "user@example.com",
      "role": "admin"
    }
  }
}
```

### POST /api/admin/approve-reset
Approve or reject password reset requests (admin only).

**Request Body:**
```json
{
  "reset_id": 123,
  "action": "approve"
}
```

**Headers:** Requires authentication (admin only)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password reset approved successfully"
}
```

---

## Error Response Codes

- `200` - Success
- `201` - Resource created
- `400` - Bad request / validation error
- `401` - Unauthorized / authentication required
- `403` - Forbidden / insufficient permissions
- `404` - Resource not found
- `422` - Validation error
- `500` - Internal server error

## Authentication

All endpoints except `/api/auth/login`, `/api/auth/register`, `/api/auth/forgot-password`, `/api/auth/reset-password`, and public notice endpoints require authentication via session.

**Admin Endpoints:** Require admin role and use `new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])`

**CSRF Protection:** All POST/PUT/DELETE operations require valid CSRF token in request headers or body.

**Rate Limiting:** Applied to sensitive operations like login, password reset, and admin operations.

## Usage Examples

**Login Flow:**
```bash
# Step 1: Login
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'

# Step 2: Use session token for authenticated requests
curl -X GET http://localhost/api/auth/me \
  -H "Cookie: PHPSESSID=your_session_id"
```

**Admin Dashboard Monitoring:**
```bash
# Get system health
curl -X GET http://localhost/api/admin/system-health \
  -H "Cookie: PHPSESSID=admin_session_id"

# Run health check
curl -X POST http://localhost/api/admin/health-check \
  -H "Cookie: PHPSESSID=admin_session_id" \
  -H "Content-Type: application/json"
```

**Quiz Taking Flow:**
```bash
# Start quiz attempt
curl -X POST http://localhost/api/quiz-sets/1/start \
  -H "Cookie: PHPSESSID=user_session_id" \
  -H "Content-Type: application/json" \
  -d '{"question_count": 5}'

# Submit quiz answers
curl -X POST http://localhost/api/quiz-sets/1/submit \
  -H "Cookie: PHPSESSID=user_session_id" \
  -H "Content-Type: application/json" \
  -d '{
    "attempt_id": 25,
    "answers": [
      {"question_id": 1, "answer": "A", "time_spent_seconds": 45},
      {"question_id": 2, "answer": "A", "time_spent_seconds": 30}
    ]
  }'
```

**File Upload:**
```bash
# Upload file
curl -X POST http://localhost/api/files/upload \
  -H "Cookie: PHPSESSID=admin_session_id" \
  -F "file=@/path/to/document.pdf" \
  -F "folder_id=1"
```

---

## Notes

- **Session Management**: Sessions expire after 2 days of inactivity (172800 seconds)
- **CSRF Tokens**: Generated by `Security::generateCsrfToken()` and verified by `Security::verifyCsrfToken()`
- **File Uploads**: Maximum file size is 10MB by default, configurable via environment variables
- **Rate Limits**: Applied per IP and per user for sensitive operations
- **Pagination**: Most list endpoints support pagination with `page` and `per_page` parameters
- **Query Parameters**: Optional filtering parameters should be used to optimize data retrieval
- **Error Handling**: Always check HTTP status codes and error messages for proper client handling
- **Security**: All sensitive operations are logged via `Logger::securityEvent()` for audit purposes