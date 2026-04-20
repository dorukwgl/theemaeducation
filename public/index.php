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
use EMA\Controllers\QuizController;
use EMA\Controllers\AdminController;
use EMA\Controllers\AccessController;
use EMA\Controllers\SystemController;
use EMA\Middleware\AuthMiddleware;
use EMA\Middleware\RateLimitMiddleware;
use EMA\Middleware\ValidationMiddleware;

// Initialize application
$app = new App();
$router = $app->getRouter();

// API Routes (to be implemented)
// Authentication routes
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

// User routes
$router->get('/api/users', [UserController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/users/{id}', [UserController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/users/{id}', [UserController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/users/{id}', [UserController::class, 'delete'], [AuthMiddleware::class]);

// Folder routes
$router->get('/api/folders', [FolderController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/folders', [FolderController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/folders/{id}', [FolderController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/folders/{id}', [FolderController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/folders/{id}', [FolderController::class, 'delete'], [AuthMiddleware::class]);
$router->get('/api/folders/{id}/contents', [FolderController::class, 'contents'], [AuthMiddleware::class]);

// File routes
$router->post('/api/files/upload', [FileController::class, 'upload'], [AuthMiddleware::class]);
$router->get('/api/files/{id}', [FileController::class, 'show'], [AuthMiddleware::class]);
$router->delete('/api/files/{id}', [FileController::class, 'delete'], [AuthMiddleware::class]);
$router->get('/api/files/{id}/download', [FileController::class, 'download'], [AuthMiddleware::class]);

// Quiz routes
$router->get('/api/quiz-sets', [QuizController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/quiz-sets', [QuizController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/quiz-sets/{id}', [QuizController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/quiz-sets/{id}', [QuizController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/quiz-sets/{id}', [QuizController::class, 'delete'], [AuthMiddleware::class]);
$router->get('/api/quiz-sets/{id}/questions', [QuizController::class, 'questions'], [AuthMiddleware::class]);
$router->post('/api/quiz-sets/{id}/questions', [QuizController::class, 'storeQuestion'], [AuthMiddleware::class]);
$router->delete('/api/quiz-sets/{id}/questions/{question_id}', [QuizController::class, 'deleteQuestion'], [AuthMiddleware::class]);

// Admin routes
$router->get('/api/admins', [AdminController::class, 'index'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->post('/api/admin/grant', [AdminController::class, 'grant'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->get('/api/admin/list', [AdminController::class, 'list'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);
$router->post('/api/admin/approve-reset', [AdminController::class, 'approveReset'], [new AuthMiddleware([EMA\Config\Constants::ROLE_ADMIN])]);

// Access control routes
$router->post('/api/access/check', [AccessController::class, 'check']);
$router->post('/api/access/increment', [AccessController::class, 'increment'], [AuthMiddleware::class]);
$router->post('/api/access/grant', [AccessController::class, 'grant'], [AuthMiddleware::class]);
$router->get('/api/access/permissions', [AccessController::class, 'permissions'], [AuthMiddleware::class]);
$router->post('/api/access/all-users', [AccessController::class, 'grantAllUsers'], [AuthMiddleware::class]);
$router->get('/api/access/all-users', [AccessController::class, 'allUsers'], [AuthMiddleware::class]);
$router->post('/api/access/login-users', [AccessController::class, 'grantLoginUsers'], [AuthMiddleware::class]);
$router->get('/api/access/login-users', [AccessController::class, 'loginUsers'], [AuthMiddleware::class]);

// System routes
$router->get('/api/notices', [SystemController::class, 'notices']);
$router->post('/api/notices', [SystemController::class, 'storeNotice'], [AuthMiddleware::class]);
$router->delete('/api/notices/{id}', [SystemController::class, 'deleteNotice'], [AuthMiddleware::class]);
$router->post('/api/analytics/track-download', [SystemController::class, 'trackDownload']);
$router->post('/api/content/free-access', [SystemController::class, 'freeAccess'], [AuthMiddleware::class]);

// Run the application
$app->run();