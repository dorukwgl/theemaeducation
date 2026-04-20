<?php
include "GlobalConfigs.php";

// Start the session
session_start();

// Disable HTML error reporting and ensure clean JSON output // Update with actual path
error_reporting(E_ALL);

// Custom error handler to log errors
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    return true;
});

// Database connection
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASSWORD;
$database = DATABASE_NAME;

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If this is an API request, return JSON error
    if ($isApiRequest) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "error" => "Database connection failed",
        ]);
    } else {
        // For regular page load, show error page
        die("<h1>Database connection failed</h1><p>Please try again later.</p>");
    }
    error_log("Database connection failed: " . $e->getMessage());
    exit();
}

// Check if this is an API request (ends with .json or has a specific header)
$isApiRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
                (isset($_GET['_format']) && $_GET['_format'] === 'json');

// Handle different request methods and API endpoints
if ($isApiRequest) {
    // Set JSON headers for API responses
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Content-Type: application/json");
    
    // Get request method
    $method = $_SERVER["REQUEST_METHOD"];
    
    // Handle preflight OPTIONS request
    if ($method === "OPTIONS") {
        http_response_code(200);
        exit();
    }
    
    // Get request data
    $data = [];
    if ($method === "GET") {
        $data = $_GET;
    } else {
        // For POST/PUT/DELETE, get JSON from input
        $json_input = file_get_contents("php://input");
        if (!empty($json_input)) {
            $data = json_decode($json_input, true) ?? [];
        }
        // Also include any form data
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
    }
    
    // Route API requests
    $action = $data['action'] ?? '';
    
    switch (true) {
        case $method === 'GET' && !empty($data['quiz_set_id']):
            getQuestions($pdo, $data);
            break;
            
        case $method === 'POST' && $action === 'delete' && !empty($data['id']):
            deleteQuestion($pdo, $data['id']);
            break;
            
        case $method === 'POST' && !empty($data['quiz_set_id']):
            try {
                addOrEditQuestion($pdo, $data);
            } catch (Exception $e) {
                error_log("Error adding/editing question: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "error" => "Failed to process question",
                    "error_details" => $e->getMessage()
                ]);
            }
            break;
            
        case $method === 'DELETE' && !empty($data['id']):
            if (isset($data['action']) && $data['action'] === 'delete_quiz_set') {
                deleteQuizSet($pdo, $data['id']);
            } else {
                deleteQuestion($pdo, $data['id']);
            }
            break;
            
        case !empty($_FILES):
            handleFileUploadOnly();
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Invalid request"
            ]);
    }
    
    exit(); // Stop execution after API response
}

// If we get here, it's a regular page load - render the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Set Details</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --text-color: #5a5c69;
        }
        
        body {
            background-color: var(--secondary-color);
            color: var(--text-color);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        
        .question-card {
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
        }
        
        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .file-upload-area {
            border: 2px dashed #d1d3e2;
            border-radius: 0.35rem;
            padding: 2rem;
            text-align: center;
            background-color: #f8f9fc;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background-color: #f0f3ff;
        }
        
        .file-upload-area i {
            font-size: 2.5rem;
            color: #b7b9cc;
            margin-bottom: 1rem;
        }
        
        .action-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar d-md-block">
                <div class="p-4">
                    <h4 class="mb-4">Quiz Manager</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item mb-2">
                            <a href="#" class="nav-link text-white">
                                <i class="fas fa-home me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="#" class="nav-link text-white active">
                                <i class="fas fa-question-circle me-2"></i> Quiz Sets
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a href="#" class="nav-link text-white">
                                <i class="fas fa-chart-bar me-2"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item mb-4">
                            <button class="btn btn-sm btn-outline-light w-100" id="createNewQuizSetBtn">
                                <i class="fas fa-plus-circle me-2"></i> Create New Quiz Set
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2" id="quizSetTitle">Quiz Set Title</h1>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-secondary me-2" id="editQuizSetBtn">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-outline-danger" id="deleteQuizSetBtn" data-bs-toggle="modal" data-bs-target="#deleteQuizSetModal">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Quiz Info Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Quiz Set Information</span>
                        <button class="btn btn-sm btn-outline-primary" id="editInfoBtn">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Created:</strong> <span id="createdDate">-</span></p>
                                <p><strong>Last Modified:</strong> <span id="modifiedDate">-</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Questions:</strong> <span id="totalQuestions">0</span></p>
                                <p><strong>Status:</strong> <span class="badge bg-success" id="statusBadge">Active</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Questions Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Questions</span>
                        <button class="btn btn-sm btn-primary" id="addQuestionBtn">
                            <i class="fas fa-plus me-1"></i> Add Question
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="questionsList">
                            <!-- Questions will be loaded here dynamically -->
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                                <p>Loading questions...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Media Files Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        Media Files
                    </div>
                    <div class="card-body">
                        <!-- File Upload Area -->
                        <div class="file-upload-area mb-4" id="fileUploadArea">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h5>Drag & Drop files here</h5>
                            <p class="text-muted">or click to browse files</p>
                            <input type="file" id="fileInput" style="display: none;" multiple>
                        </div>
                        
                        <!-- Uploaded Files List -->
                        <h6 class="mb-3">Uploaded Files</h6>
                        <div class="table-responsive">
                            <table class="table table-hover" id="filesTable">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="filesList">
                                    <tr>
                                        <td colspan="5" class="text-center py-3">
                                            <i class="fas fa-spinner fa-spin me-2"></i>Loading files...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add New Quiz Set Modal -->
    <div class="modal fade" id="newQuizSetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Quiz Set</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="newQuizSetForm">
                        <div class="mb-3">
                            <label for="quizSetTitleInput" class="form-label">Quiz Set Title</label>
                            <input type="text" class="form-control" id="quizSetTitleInput" required>
                        </div>
                        <div class="mb-3">
                            <label for="quizSetDescription" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="quizSetDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isActiveSwitch" checked>
                                <label class="form-check-label" for="isActiveSwitch">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveNewQuizSetBtn">Create Quiz Set</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Question Modal -->
    <div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionModalTitle">Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="questionForm">
                        <input type="hidden" id="questionId">
                        <input type="hidden" id="quizSetId" value="<?php echo htmlspecialchars($_GET['id'] ?? ''); ?>">
                        <div class="mb-3">
                            <label for="questionText" class="form-label">Question</label>
                            <textarea class="form-control" id="questionText" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Question Type</label>
                            <select class="form-select" id="questionType" required>
                                <option value="Reading">Reading</option>
                                <option value="Listening">Listening</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="optionalText" class="form-label">Optional Text</label>
                            <textarea class="form-control" id="optionalText" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Correct Answer</label>
                            <select class="form-select" id="correctAnswer" required>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div id="optionsContainer">
                            <!-- Options will be added here based on question type -->
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveQuestionBtn">Save Question</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this item? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Global variables
        let currentQuestionId = null;
        let currentFileToDelete = null;
        const quizSetId = document.getElementById('quizSetId')?.value || '';
        
        // Initialize the page when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (!quizSetId) {
                showError('No quiz set ID provided');
                return;
            }
            
            // Load quiz set details
            loadQuizSetDetails();
            
            // Load questions
            loadQuestions();
            
            // Load files
            loadFiles();
            
            // Setup event listeners
            setupEventListeners();
        });
        
        // Load quiz set details
        function loadQuizSetDetails() {
            fetch(`/api/quiz_sets/${quizSetId}?_format=json`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.quizSet) {
                        const quizSet = data.quizSet;
                        document.getElementById('quizSetTitle').textContent = quizSet.title || 'Quiz Set';
                        document.getElementById('createdDate').textContent = formatDate(quizSet.created_at);
                        document.getElementById('modifiedDate').textContent = formatDate(quizSet.updated_at);
                        document.getElementById('statusBadge').textContent = quizSet.is_active ? 'Active' : 'Inactive';
                        document.getElementById('statusBadge').className = `badge ${quizSet.is_active ? 'bg-success' : 'bg-secondary'}`;
                    }
                })
                .catch(error => {
                    console.error('Error loading quiz set details:', error);
                    showError('Failed to load quiz set details');
                });
        }
        
        // Load questions
        function loadQuestions() {
            fetch(`/api/questions?quiz_set_id=${quizSetId}&_format=json`)
                .then(response => response.json())
                .then(data => {
                    const questionsList = document.getElementById('questionsList');
                    
                    if (!data.success || !data.questions || data.questions.length === 0) {
                        questionsList.innerHTML = `
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-question-circle fa-3x mb-3"></i>
                                <p>No questions added yet. Click 'Add Question' to get started.</p>
                            </div>
                        `;
                        document.getElementById('totalQuestions').textContent = '0';
                        return;
                    }
                    
                    // Update question count
                    document.getElementById('totalQuestions').textContent = data.questions.length;
                    
                    // Render questions
                    questionsList.innerHTML = data.questions.map((question, index) => `
                        <div class="card question-card mb-3" data-question-id="${question.id}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h5 class="card-title mb-1">Question ${index + 1}</h5>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary edit-question" data-id="${question.id}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-question" data-id="${question.id}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <p class="card-text mt-2">${escapeHtml(question.question)}</p>
                                ${question.optional_text ? `<p class="text-muted">${escapeHtml(question.optional_text)}</p>` : ''}
                                <div class="options mt-3">
                                    ${['A', 'B', 'C', 'D'].map(letter => {
                                        const isCorrect = question.correct_answer === letter;
                                        return `
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" 
                                                    ${isCorrect ? 'checked' : ''} disabled>
                                                <label class="form-check-label ${isCorrect ? 'fw-bold text-success' : ''}">
                                                    ${letter}. ${escapeHtml(question[`choice_${letter}_text`] || '')}
                                                </label>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        </div>
                    `).join('');
                    
                    // Add event listeners to the new buttons
                    document.querySelectorAll('.edit-question').forEach(btn => {
                        btn.addEventListener('click', handleEditQuestion);
                    });
                    
                    document.querySelectorAll('.delete-question').forEach(btn => {
                        btn.addEventListener('click', handleDeleteQuestion);
                    });
                })
                .catch(error => {
                    console.error('Error loading questions:', error);
                    showError('Failed to load questions');
                });
        }
        
        // Load files
        function loadFiles() {
            fetch(`/api/files?quiz_set_id=${quizSetId}&_format=json`)
                .then(response => response.json())
                .then(data => {
                    const filesList = document.getElementById('filesList');
                    
                    if (!data.success || !data.files || data.files.length === 0) {
                        filesList.innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    No files uploaded yet
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    // Render files
                    filesList.innerHTML = data.files.map(file => `
                        <tr>
                            <td>${escapeHtml(file.name)}</td>
                            <td>${file.type}</td>
                            <td>${formatFileSize(file.size)}</td>
                            <td>${formatDate(file.uploaded_at)}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary view-file" data-url="${escapeHtml(file.url)}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-file" data-id="${file.id}" data-name="${escapeHtml(file.name)}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                    
                    // Add event listeners to the new buttons
                    document.querySelectorAll('.view-file').forEach(btn => {
                        btn.addEventListener('click', handleViewFile);
                    });
                    
                    document.querySelectorAll('.delete-file').forEach(btn => {
                        btn.addEventListener('click', handleDeleteFile);
                    });
                })
                .catch(error => {
                    console.error('Error loading files:', error);
                    showError('Failed to load files');
                });
        }
        
        // Setup event listeners
        function setupEventListeners() {
            // Add question button
            document.getElementById('addQuestionBtn').addEventListener('click', () => {
                // Reset form
                document.getElementById('questionForm').reset();
                document.getElementById('questionId').value = '';
                document.getElementById('questionModalTitle').textContent = 'Add New Question';
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('questionModal'));
                modal.show();
            });
            
            // Save question button
            document.getElementById('saveQuestionBtn').addEventListener('click', saveQuestion);
            
            // File upload
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('fileInput');
            
            if (fileUploadArea && fileInput) {
                // Handle drag and drop
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    fileUploadArea.addEventListener(eventName, preventDefaults, false);
                });
                
                // Highlight drop area
                ['dragenter', 'dragover'].forEach(eventName => {
                    fileUploadArea.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    fileUploadArea.addEventListener(eventName, unhighlight, false);
                });
                
                // Handle dropped files
                fileUploadArea.addEventListener('drop', handleDrop, false);
                
                // Handle click on upload area
                fileUploadArea.addEventListener('click', () => {
                    fileInput.click();
                });
                
                // Handle file input change
                fileInput.addEventListener('change', handleFileSelect, false);
            }
            
            // Delete quiz set button
            document.getElementById('deleteQuizSetBtn').addEventListener('click', () => {
                // Show confirmation dialog
                Swal.fire({
                    title: 'Delete Quiz Set',
                    text: 'Are you sure you want to delete this quiz set? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteQuizSet();
                    }
                });
            });
            
            // Create new quiz set button
            document.getElementById('createNewQuizSetBtn').addEventListener('click', () => {
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('newQuizSetModal'));
                modal.show();
            });
            
            // Save new quiz set button
            document.getElementById('saveNewQuizSetBtn').addEventListener('click', saveNewQuizSet);
        }
        
        // Handle edit question
        function handleEditQuestion(e) {
            const questionId = e.currentTarget.dataset.id;
            
            // Fetch question details
            fetch(`/api/questions/${questionId}?_format=json`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.question) {
                        throw new Error('Failed to load question details');
                    }
                    
                    const question = data.question;
                    
                    // Set form values
                    document.getElementById('questionId').value = question.id;
                    document.getElementById('questionText').value = question.question || '';
                    document.getElementById('optionalText').value = question.optional_text || '';
                    document.getElementById('questionType').value = question.question_type || 'Reading';
                    document.getElementById('correctAnswer').value = question.correct_answer || 'A';
                    
                    // Set modal title
                    document.getElementById('questionModalTitle').textContent = 'Edit Question';
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('questionModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading question details:', error);
                    showError('Failed to load question details');
                });
        }
        
        // Handle delete question
        function handleDeleteQuestion(e) {
            const questionId = e.currentTarget.dataset.id;
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Delete Question',
                text: 'Are you sure you want to delete this question?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteQuestion(questionId);
                }
            });
        }
        
        // Handle view file
        function handleViewFile(e) {
            const fileUrl = e.currentTarget.dataset.url;
            window.open(fileUrl, '_blank');
        }
        
        // Handle delete file
        function handleDeleteFile(e) {
            const fileId = e.currentTarget.dataset.id;
            const fileName = e.currentTarget.dataset.name;
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Delete File',
                text: `Are you sure you want to delete "${fileName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteFile(fileId);
                }
            });
        }
        
        // Save question
        function saveQuestion() {
            const form = document.getElementById('questionForm');
            const formData = new FormData(form);
            
            // Add quiz set ID
            formData.append('quiz_set_id', quizSetId);
            
            // Convert FormData to JSON
            const jsonData = {};
            formData.forEach((value, key) => {
                jsonData[key] = value;
            });
            
            const isEdit = !!jsonData.id;
            const url = isEdit 
                ? `/api/questions/${jsonData.id}` 
                : '/api/questions';
            
            fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(jsonData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: isEdit ? 'Question updated successfully' : 'Question added successfully',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('questionModal'));
                    modal.hide();
                    
                    // Reload questions
                    loadQuestions();
                } else {
                    throw new Error(data.error || 'Failed to save question');
                }
            })
            .catch(error => {
                console.error('Error saving question:', error);
                showError(error.message || 'Failed to save question');
            });
        }
        
        // Delete question
        function deleteQuestion(questionId) {
            if (!questionId) return;
            
            fetch(`/api/questions/${questionId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ action: 'delete' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Question has been deleted.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Reload questions
                    loadQuestions();
                } else {
                    throw new Error(data.error || 'Failed to delete question');
                }
            })
            .catch(error => {
                console.error('Error deleting question:', error);
                showError(error.message || 'Failed to delete question');
            });
        }
        
        // Delete file
        function deleteFile(fileId) {
            if (!fileId) return;
            
            fetch(`/api/files/${fileId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'File has been deleted.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Reload files
                    loadFiles();
                } else {
                    throw new Error(data.error || 'Failed to delete file');
                }
            })
            .catch(error => {
                console.error('Error deleting file:', error);
                showError(error.message || 'Failed to delete file');
            });
        }
        
        // Delete quiz set
        function deleteQuizSet() {
            fetch(`/api/quiz_sets/${quizSetId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ action: 'delete_quiz_set' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message and redirect
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Quiz set has been deleted.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        // Redirect to quiz sets list
                        window.location.href = '/quiz_sets.php';
                    });
                } else {
                    throw new Error(data.error || 'Failed to delete quiz set');
                }
            })
            .catch(error => {
                console.error('Error deleting quiz set:', error);
                showError(error.message || 'Failed to delete quiz set');
            });
        }
        
        // Save new quiz set
        function saveNewQuizSet() {
            const form = document.getElementById('newQuizSetForm');
            const formData = new FormData(form);
            
            // Convert FormData to JSON
            const jsonData = {};
            formData.forEach((value, key) => {
                jsonData[key] = value;
            });
            
            fetch('/api/quiz_sets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(jsonData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Quiz set created successfully',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('newQuizSetModal'));
                    modal.hide();
                    
                    // Reload quiz sets list
                    window.location.href = '/quiz_sets.php';
                } else {
                    throw new Error(data.error || 'Failed to create quiz set');
                }
            })
            .catch(error => {
                console.error('Error creating quiz set:', error);
                showError(error.message || 'Failed to create quiz set');
            });
        }
        
        // File upload handlers
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight() {
            document.getElementById('fileUploadArea').classList.add('bg-light');
        }
        
        function unhighlight() {
            document.getElementById('fileUploadArea').classList.remove('bg-light');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        function handleFileSelect(e) {
            handleFiles(e.target.files);
        }
        
        function handleFiles(files) {
            if (!files || files.length === 0) return;
            
            // Show loading state
            const fileUploadArea = document.getElementById('fileUploadArea');
            const originalHTML = fileUploadArea.innerHTML;
            fileUploadArea.innerHTML = `
                <div class="d-flex flex-column align-items-center">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Uploading...</span>
                    </div>
                    <p class="mb-0">Uploading ${files.length} file(s)...</p>
                </div>
            `;
            
            const formData = new FormData();
            formData.append('quiz_set_id', quizSetId);
            
            // Add all files to FormData
            Array.from(files).forEach((file, index) => {
                formData.append(`file${index}`, file);
            });
            
            // Upload files
            fetch('/api/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Files uploaded successfully',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Reload files
                    loadFiles();
                } else {
                    throw new Error(data.error || 'Failed to upload files');
                }
            })
            .catch(error => {
                console.error('Error uploading files:', error);
                showError(error.message || 'Failed to upload files');
            })
            .finally(() => {
                // Reset file input and upload area
                document.getElementById('fileInput').value = '';
                fileUploadArea.innerHTML = originalHTML;
                
                // Re-add event listeners
                fileUploadArea.addEventListener('click', () => {
                    document.getElementById('fileInput').click();
                });
            });
        }
        
        // Helper functions
        function formatDate(dateString) {
            if (!dateString) return '-';
            
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            
            return new Date(dateString).toLocaleDateString(undefined, options);
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonText: 'OK'
            });
        }
    </script>
</body>
</html>
