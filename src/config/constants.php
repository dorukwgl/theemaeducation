<?php

namespace EMA\Config;

class Constants
{
    // HTTP Status Codes
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_SERVICE_UNAVAILABLE = 503;

    // User Roles
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';

    // Access Types
    public const ACCESS_ALL = 'all';
    public const ACCESS_LOGGED_IN = 'logged_in';

    // Item Types
    public const ITEM_TYPE_FILE = 'file';
    public const ITEM_TYPE_QUIZ_SET = 'quiz_set';

    // Question Types
    public const QUESTION_TYPE_READING = 'Reading';
    public const QUESTION_TYPE_LISTENING = 'Listening';

    // Answer Choices
    public const ANSWER_A = 'A';
    public const ANSWER_B = 'B';
    public const ANSWER_C = 'C';
    public const ANSWER_D = 'D';

    // Access Permission Values
    public const ACCESS_UNLIMITED = -1;
    public const ACCESS_NO_LIMIT = 0;

    // File Upload
    public const UPLOAD_MAX_SIZE_DEFAULT = 10485760; // 10MB
    public const UPLOAD_CHUNK_SIZE = 8192; // 8KB

    // Cache Keys
    public const CACHE_KEY_PREFIX = 'ema_';
    public const CACHE_TTL_DEFAULT = 3600; // 1 hour

    // Session Keys
    public const SESSION_USER_ID = 'user_id';
    public const SESSION_USER_EMAIL = 'user_email';
    public const SESSION_USER_ROLE = 'user_role';
    public const SESSION_USER_NAME = 'user_name';
    public const SESSION_USER_IMAGE = 'user_image';
    public const SESSION_CSRF_TOKEN = 'csrf_token';
    public const SESSION_LAST_ACTIVITY = 'last_activity';

    // Rate Limiting
    public const RATE_LIMIT_MAX_REQUESTS_DEFAULT = 100;
    public const RATE_LIMIT_WINDOW_DEFAULT = 60; // seconds

    // Password Requirements
    public const PASSWORD_MIN_LENGTH = 8;
    public const PASSWORD_MAX_LENGTH = 128;

    // Pagination
    public const PAGINATION_DEFAULT_PAGE = 1;
    public const PAGINATION_DEFAULT_PER_PAGE = 20;
    public const PAGINATION_MAX_PER_PAGE = 100;

    // File Validation
    public const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const ALLOWED_DOCUMENT_TYPES = ['application/pdf'];
    public const ALLOWED_AUDIO_TYPES = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac', 'audio/ogg'];
    public const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    // Logging Levels
    public const LOG_LEVEL_DEBUG = 'debug';
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';
    public const LOG_LEVEL_CRITICAL = 'critical';

    // Time Formats
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const DATE_FORMAT = 'Y-m-d';
    public const TIME_FORMAT = 'H:i:s';

    // API Response
    public const RESPONSE_SUCCESS = true;
    public const RESPONSE_ERROR = false;

    // Security
    public const CSRF_TOKEN_NAME = 'csrf_token';
    public const CSRF_HEADER_NAME = 'X-CSRF-Token';
    public const AUTH_HEADER_NAME = 'Authorization';
    public const AUTH_TOKEN_PREFIX = 'Bearer ';

    // File Paths
    public const PATH_UPLOADS = 'uploads';
    public const PATH_FILES = 'uploads/files';
    public const PATH_ICONS = 'uploads/icons';
    public const PATH_QUESTIONS = 'uploads/questions';
    public const PATH_NOTICES = 'uploads/notices';

    // Database Tables
    public const TABLE_USERS = 'users';
    public const TABLE_ADMIN_USERS = 'admin_users';
    public const TABLE_FOLDERS = 'folders';
    public const TABLE_FILES = 'files';
    public const TABLE_QUIZ_SETS = 'quiz_sets';
    public const TABLE_QUESTIONS = 'questions';
    public const TABLE_ACCESS_PERMISSIONS = 'access_permissions';
    public const TABLE_ITEM_ACTIVATION_STATUS = 'item_activation_status';
    public const TABLE_NOTICES = 'notices';
    public const TABLE_PASSWORD_RESET_REQUESTS = 'password_reset_requests';
    public const TABLE_ACCESS_TO_ALL_USERS = 'access_to_all_users';
    public const TABLE_GIVE_ACCESS_TO_ALL_USERS = 'give_access_to_all_users';
    public const TABLE_GIVE_ACCESS_TO_LOGIN_USERS = 'give_access_to_login_users';

    // Error Messages
    public const ERROR_UNAUTHORIZED = 'Unauthorized access';
    public const ERROR_FORBIDDEN = 'Access forbidden';
    public const ERROR_NOT_FOUND = 'Resource not found';
    public const ERROR_VALIDATION = 'Validation failed';
    public const ERROR_DATABASE = 'Database error';
    public const ERROR_SERVER = 'Internal server error';
    public const ERROR_RATE_LIMIT = 'Too many requests';
    public const ERROR_INVALID_TOKEN = 'Invalid token';
    public const ERROR_EXPIRED_TOKEN = 'Token expired';

    // Success Messages
    public const SUCCESS_CREATED = 'Resource created successfully';
    public const SUCCESS_UPDATED = 'Resource updated successfully';
    public const SUCCESS_DELETED = 'Resource deleted successfully';
    public const SUCCESS_LOGGED_IN = 'Login successful';
    public const SUCCESS_LOGGED_OUT = 'Logout successful';

    // Time Intervals (in seconds)
    public const MINUTE = 60;
    public const HOUR = 3600;
    public const DAY = 86400;
    public const WEEK = 604800;
    public const MONTH = 2592000;
    public const YEAR = 31536000;
}
