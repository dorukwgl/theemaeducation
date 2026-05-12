<?php

/**
 * EMA Education Platform - Main Entry Point
 *
 * This file serves as the main entry point for all HTTP requests.
 * It initializes the application and handles routing.
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Load autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

// Load configuration
require_once ROOT_PATH . '/src/config/config.php';
require_once ROOT_PATH . '/src/config/database.php';
require_once ROOT_PATH . '/src/config/constants.php';

// Import core classes
use EMA\Core\App;
use EMA\Core\Router;
use EMA\Controllers\AuthController;
use EMA\Controllers\UserController;
use EMA\Controllers\FolderController;
use EMA\Controllers\FileController;
use EMA\Controllers\AdminController;
use EMA\Controllers\AccessController;
use EMA\Controllers\SystemController;
use EMA\Controllers\QuizController;
use EMA\Controllers\NoticeController;
use EMA\Controllers\CsrfController;
use EMA\Middleware\AuthMiddleware;
use EMA\Middleware\RateLimitMiddleware;
use EMA\Middleware\ValidationMiddleware;
use EMA\Middleware\CsrfMiddleware;

// Initialize application
$app = new App();
$router = $app->getRouter();

// API Routes (to be implemented)

// CSRF token route (public - no auth required)
$router->get('/api/csrf/token', [CsrfController::class, 'getToken']);

// Authentication routes
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
$router->post('/api/auth/change-password', [AuthController::class, 'changePassword'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

// User routes
$router->get('/api/users', [UserController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/users/{id}', [UserController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/users/{id}', [UserController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->delete('/api/users/{id}', [UserController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

// Folder routes
$router->get('/api/folders', [FolderController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/folders', [FolderController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/folders/{id}', [FolderController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/folders/{id}', [FolderController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->delete('/api/folders/{id}', [FolderController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

// File routes
// Public file routes (NO authentication required)
$router->get('/api/public/folders', [FolderController::class, 'publicIndex']);
$router->get('/api/public/folder/{id}/files', [FileController::class, 'publicFolderFiles']);
$router->get('/api/public/files', [FileController::class, 'publicIndex']);
$router->get('/api/public/files/{id}', [FileController::class, 'publicShow']);
$router->get('/api/public/files/{id}/download', [FileController::class, 'publicDownload']);

// Authenticated file routes (requires authentication)
$router->get('/api/files', [FileController::class, 'authenticatedIndex'], [AuthMiddleware::class]);
$router->get('/api/files/{id}', [FileController::class, 'show'], [AuthMiddleware::class]);
$router->get('/api/files/{id}/download', [FileController::class, 'download'], [AuthMiddleware::class]);
$router->delete('/api/files/{id}', [FileController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class]);

// Admin file management routes
$router->post('/api/files/upload', [FileController::class, 'upload'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/files/{id}', [FileController::class, 'update'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/files/{id}', [FileController::class, 'delete'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/files/{id}/status', [FileController::class, 'updateStatus'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/files/{id}/access-type', [FileController::class, 'updateAccessType'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);

// Folder file listing (authenticated)
$router->get('/api/folders/{id}/files', [FileController::class, 'folderFiles'], [AuthMiddleware::class]);

// Resource access (special handling for public access)
$router->get('/api/res/{path:.+}', [FileController::class, 'serveByPath']); // NO middleware - handle internally

// Admin routes
$router->post('/api/admin/grant', [AdminController::class, 'grant'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/revoke', [AdminController::class, 'revoke'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/approve-reset', [AdminController::class, 'approveReset'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/change-password', [AdminController::class, 'changePassword'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->get('/api/admin/dashboard', [AdminController::class, 'dashboard'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/user-activity', [AdminController::class, 'userActivity'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/system-health', [AdminController::class, 'systemHealth'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/audit-log', [AdminController::class, 'auditLog'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->post('/api/admin/bulk-operations', [AdminController::class, 'createBulkOperation'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->get('/api/admin/bulk-operations/{id}', [AdminController::class, 'bulkOperationStatus'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->delete('/api/admin/bulk-operations/{id}', [AdminController::class, 'cancelBulkOperation'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->get('/api/admin/analytics', [AdminController::class, 'systemAnalytics'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->post('/api/admin/health-check', [AdminController::class, 'runHealthCheck'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/admin/audit-log', [AdminController::class, 'clearAuditLog'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);

// Admin user grant inspection routes
$router->get('/api/admin/users/{userId}/files/granted', [FileController::class, 'userGrantedFiles'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/users/{userId}/files/not-granted', [FileController::class, 'userNotGrantedFiles'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/users/{userId}/quiz-sets/granted', [QuizController::class, 'userGrantedQuizSets'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/users/{userId}/quiz-sets/not-granted', [QuizController::class, 'userNotGrantedQuizSets'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);

// Access control routes
$router->post('/api/access/grant', [AccessController::class, 'grant'], [AuthMiddleware::class, CsrfMiddleware::class]);

// System routes
$router->post('/api/analytics/track-download', [SystemController::class, 'trackDownload'], [CsrfMiddleware::class]);

// Quiz routes
// Public quiz routes (NO authentication required)
$router->get('/api/public/folder/{id}/quiz-sets', [QuizController::class, 'publicFolderQuizSets']);
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
$router->post('/api/quiz-sets/{id}', [QuizController::class, 'update'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/quiz-sets/{id}', [QuizController::class, 'delete'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/quiz-sets/{id}/status', [QuizController::class, 'updateStatus'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/admin/quiz-sets/{id}/access-type', [QuizController::class, 'updateAccessType'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);

// Admin question management routes
$router->post('/api/quiz-sets/{id}/questions', [QuizController::class, 'createQuestion'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/quiz-sets/{id}/questions/{question_id}', [QuizController::class, 'updateQuestion'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/quiz-sets/{id}/questions/{question_id}', [QuizController::class, 'deleteQuestion'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);

// Notice routes — public view, admin manage
$router->get('/api/notices', [NoticeController::class, 'index']);
$router->post('/api/notices', [NoticeController::class, 'store'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->post('/api/notices/{id}', [NoticeController::class, 'update'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->delete('/api/notices/{id}', [NoticeController::class, 'delete'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN]), CsrfMiddleware::class]);
$router->get('/api/notices/attachments/{id}/download', [NoticeController::class, 'downloadAttachment'], [AuthMiddleware::class]);

// Run the application
$app->run();