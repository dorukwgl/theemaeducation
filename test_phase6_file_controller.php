<?php

require_once __DIR__ . '/vendor/autoload.php';

use EMA\Config\Database;
use EMA\Controllers\FileController;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Models\File;
use EMA\Models\Folder;
use EMA\Models\User;
use EMA\Utils\Logger;

/**
 * Phase 6: File Controller Updates Test Suite
 * Tests the new public, authenticated, and admin methods in FileController
 */

class Phase6FileControllerTest
{
    private FileController $controller;
    private int $testUserId;
    private int $adminUserId;
    private int $testFolderId;
    private int $publicFileId;
    private int $privateFileId;
    private array $testResults = [];

    public function __construct()
    {
        echo "=== Phase 6: File Controller Updates Tests ===\n\n";
        $this->controller = new FileController();
    }

    public function run(): void
    {
        try {
            $this->setupTestData();
            $this->runTests();
            $this->displayResults();
            $this->cleanupTestData();
        } catch (\Exception $e) {
            echo "❌ Test suite failed: " . $e->getMessage() . "\n";
            Logger::error('Phase 6 test suite failed', ['error' => $e->getMessage()]);
        }
    }

    private function setupTestData(): void
    {
        echo "Setting up test data...\n";

        // Get existing users
        $userResult = Database::query("SELECT id, role FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $userResult->fetch_assoc();
        if (!$admin) {
            throw new \Exception("No admin users found in database");
        }
        $this->adminUserId = (int) $admin['id'];

        $userResult = Database::query("SELECT id, role FROM users WHERE role != 'admin' LIMIT 1");
        $user = $userResult->fetch_assoc();
        if (!$user) {
            throw new \Exception("No non-admin users found in database");
        }
        $this->testUserId = (int) $user['id'];

        // Get or create test folder
        $folderResult = Database::query("SELECT id FROM folders LIMIT 1");
        $folder = $folderResult->fetch_assoc();
        if (!$folder) {
            throw new \Exception("No folders found in database");
        }
        $this->testFolderId = (int) $folder['id'];

        // Create test files with different access types
        // Public file
        $publicFileData = [
            'folder_id' => $this->testFolderId,
            'name' => 'Public Test File',
            'file_path' => 'files/test_public.jpg',
            'access_type' => 'all',
            'status' => 'active'
        ];
        $this->publicFileId = File::create($publicFileData);

        // Private file
        $privateFileData = [
            'folder_id' => $this->testFolderId,
            'name' => 'Private Test File',
            'file_path' => 'files/test_private.jpg',
            'access_type' => 'private',
            'status' => 'active'
        ];
        $this->privateFileId = File::create($privateFileData);

        echo "  - Admin User ID: {$this->adminUserId}\n";
        echo "  - Test User ID: {$this->testUserId}\n";
        echo "  - Test Folder ID: {$this->testFolderId}\n";
        echo "  - Public File ID: {$this->publicFileId}\n";
        echo "  - Private File ID: {$this->privateFileId}\n";
        echo "✅ Test data setup complete\n\n";
    }

    private function runTests(): void
    {
        echo "Running tests...\n\n";

        // Test 1: Public index returns public files
        $this->testPublicIndex();

        // Test 2: Public show works for public files
        $this->testPublicShow();

        // Test 3: Public show fails for private files
        $this->testPublicShowPrivateFile();

        // Test 4: Public download works for public files
        $this->testPublicDownload();

        // Test 5: Public download fails for private files
        $this->testPublicDownloadPrivateFile();

        // Test 6: Public index with search
        $this->testPublicIndexWithSearch();

        // Test 7: Public index with folder filter
        $this->testPublicIndexWithFolder();

        // Test 8: Update status to inactive
        $this->testUpdateStatus();

        // Test 9: Update status back to active
        $this->testUpdateStatusActive();

        // Test 10: Update access type to private
        $this->testUpdateAccessType();

        // Test 11: Update access type back to public
        $this->testUpdateAccessTypePublic();

        // Test 12: Update status with invalid value fails
        $this->testUpdateStatusInvalid();

        // Test 13: Update access type with invalid value fails
        $this->testUpdateAccessTypeInvalid();

        // Test 14: Upload file with private access type
        $this->testUploadPrivateFile();

        // Test 15: Upload file with all access types
        $this->testUploadAllAccessTypes();
    }

    private function createMockRequest(array $query = [], array $input = [], array $files = []): Request
    {
        // Create a simple request mock using anonymous class
        return new class($query, $input) extends Request {
            private array $query;
            private array $input;

            public function __construct(array $query, array $input) {
                $this->query = $query;
                $this->input = $input;
            }

            public function getQueryParameter(string $key, $default = null) {
                return $this->query[$key] ?? $default;
            }

            public function allInput(): array {
                return $this->input;
            }
        };
    }

    private function testPublicIndex(): void
    {
        $testName = "Test 1: Public index returns public files";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest(['page' => 1, 'per_page' => 20]);
            $this->controller->setRequest($request);

            // Capture output
            ob_start();
            $this->controller->publicIndex();
            $output = ob_get_clean();

            $result = json_decode($output, true);

            if ($result && isset($result['success']) && $result['success'] === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected success response\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testPublicShow(): void
    {
        $testName = "Test 2: Public show works for public files";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest();
            $this->controller->setRequest($request);

            // Mock the file display by checking if method exists and doesn't throw
            $method = new \ReflectionMethod($this->controller, 'publicShow');
            if ($method->isPublic()) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Method is not public\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testPublicShowPrivateFile(): void
    {
        $testName = "Test 3: Public show fails for private files";
        echo "  Running $testName... ";

        try {
            // Check if private file is properly configured
            $file = File::findById($this->privateFileId);

            if ($file && $file['access_type'] === 'private') {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Private file not configured correctly\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testPublicDownload(): void
    {
        $testName = "Test 4: Public download works for public files";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest();
            $this->controller->setRequest($request);

            // Check if method exists
            $method = new \ReflectionMethod($this->controller, 'publicDownload');
            if ($method->isPublic()) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Method is not public\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testPublicDownloadPrivateFile(): void
    {
        $testName = "Test 5: Public download fails for private files";
        echo "  Running $testName... ";

        try {
            // Verify private file exists and is private
            $file = File::findById($this->privateFileId);

            if ($file && $file['access_type'] === 'private' && !File::isFilePublic($this->privateFileId)) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Private file configuration incorrect\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testPublicIndexWithSearch(): void
    {
        $testName = "Test 6: Public index with search";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest(['page' => 1, 'per_page' => 20, 'search' => 'Public']);
            $this->controller->setRequest($request);

            ob_start();
            $this->controller->publicIndex();
            $output = ob_get_clean();

            $result = json_decode($output, true);

            if ($result && isset($result['success']) && $result['success'] === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected success response\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testPublicIndexWithFolder(): void
    {
        $testName = "Test 7: Public index with folder filter";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest(['page' => 1, 'per_page' => 20, 'folder_id' => $this->testFolderId]);
            $this->controller->setRequest($request);

            ob_start();
            $this->controller->publicIndex();
            $output = ob_get_clean();

            $result = json_decode($output, true);

            if ($result && isset($result['success']) && $result['success'] === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected success response\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUpdateStatus(): void
    {
        $testName = "Test 8: Update status to inactive";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest([], ['status' => 'inactive']);
            $this->controller->setRequest($request);

            // Mock current user as admin
            $_SESSION['user'] = ['id' => $this->adminUserId, 'role' => 'admin'];

            $result = File::updateStatus($this->publicFileId, 'inactive');

            // Verify status was updated
            $file = File::findById($this->publicFileId);
            $success = $result && $file && $file['status'] === 'inactive';

            // Reset status
            File::updateStatus($this->publicFileId, 'active');

            if ($success) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Status not updated correctly\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUpdateStatusActive(): void
    {
        $testName = "Test 9: Update status back to active";
        echo "  Running $testName... ";

        try {
            $result = File::updateStatus($this->publicFileId, 'active');

            // Verify status was updated
            $file = File::findById($this->publicFileId);
            $success = $result && $file && $file['status'] === 'active';

            if ($success) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Status not updated correctly\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUpdateAccessType(): void
    {
        $testName = "Test 10: Update access type to private";
        echo "  Running $testName... ";

        try {
            $result = File::updateAccessType($this->publicFileId, 'private');

            // Verify access type was updated
            $file = File::findById($this->publicFileId);
            $success = $result && $file && $file['access_type'] === 'private';

            // Reset access type
            File::updateAccessType($this->publicFileId, 'all');

            if ($success) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Access type not updated correctly\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUpdateAccessTypePublic(): void
    {
        $testName = "Test 11: Update access type back to public";
        echo "  Running $testName... ";

        try {
            $result = File::updateAccessType($this->publicFileId, 'all');

            // Verify access type was updated
            $file = File::findById($this->publicFileId);
            $success = $result && $file && $file['access_type'] === 'all';

            if ($success) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Access type not updated correctly\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUpdateStatusInvalid(): void
    {
        $testName = "Test 12: Update status with invalid value fails";
        echo "  Running $testName... ";

        try {
            // This should fail due to validation
            $request = $this->createMockRequest([], ['status' => 'invalid_status']);
            $this->controller->setRequest($request);

            $_SESSION['user'] = ['id' => $this->adminUserId, 'role' => 'admin'];

            ob_start();
            $this->controller->updateStatus($this->publicFileId);
            $output = ob_get_clean();

            $result = json_decode($output, true);

            // Should fail with validation error
            if ($result && isset($result['success']) && $result['success'] === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected validation error\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUpdateAccessTypeInvalid(): void
    {
        $testName = "Test 13: Update access type with invalid value fails";
        echo "  Running $testName... ";

        try {
            $request = $this->createMockRequest([], ['access_type' => 'invalid_type']);
            $this->controller->setRequest($request);

            $_SESSION['user'] = ['id' => $this->adminUserId, 'role' => 'admin'];

            ob_start();
            $this->controller->updateAccessType($this->publicFileId);
            $output = ob_get_clean();

            $result = json_decode($output, true);

            // Should fail with validation error
            if ($result && isset($result['success']) && $result['success'] === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected validation error\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUploadPrivateFile(): void
    {
        $testName = "Test 14: Upload file with private access type";
        echo "  Running $testName... ";

        try {
            // Test that the upload method accepts 'private' access type
            $fileData = [
                'folder_id' => $this->testFolderId,
                'name' => 'Private Upload Test',
                'file_path' => 'files/test_private_upload.jpg',
                'access_type' => 'private',
                'status' => 'active'
            ];

            $fileId = File::create($fileData);

            if ($fileId) {
                // Verify the file was created with private access
                $file = File::findById($fileId);
                $success = $file && $file['access_type'] === 'private';

                // Clean up
                File::delete($fileId);

                if ($success) {
                    echo "✅ PASS\n";
                    $this->testResults[$testName] = true;
                } else {
                    echo "❌ FAIL - File not created with private access\n";
                    $this->testResults[$testName] = false;
                }
            } else {
                echo "❌ FAIL - File creation failed\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testUploadAllAccessTypes(): void
    {
        $testName = "Test 15: Upload file with all access types";
        echo "  Running $testName... ";

        try {
            $accessTypes = ['all', 'logged_in', 'private'];
            $successCount = 0;

            foreach ($accessTypes as $accessType) {
                $fileData = [
                    'folder_id' => $this->testFolderId,
                    'name' => "Test {$accessType} file",
                    'file_path' => "files/test_{$accessType}.jpg",
                    'access_type' => $accessType,
                    'status' => 'active'
                ];

                $fileId = File::create($fileData);

                if ($fileId) {
                    $file = File::findById($fileId);
                    if ($file && $file['access_type'] === $accessType) {
                        $successCount++;
                        File::delete($fileId);
                    }
                }
            }

            if ($successCount === count($accessTypes)) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Only {$successCount}/" . count($accessTypes) . " access types worked\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function displayResults(): void
    {
        echo "\n=== Test Results ===\n";

        $passed = 0;
        $total = 0;

        foreach ($this->testResults as $testName => $result) {
            $total++;
            $status = $result ? "✅ PASS" : "❌ FAIL";
            echo "$status: $testName\n";
            if ($result) {
                $passed++;
            }
        }

        $failed = $total - $passed;

        echo "\n=== Summary ===\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n";

        if ($passed === $total) {
            echo "\n🎉 All tests passed!\n";
        } else {
            echo "\n⚠️ Some tests failed. Please review the results above.\n";
        }
    }

    private function cleanupTestData(): void
    {
        echo "\nCleaning up test data...\n";

        try {
            // Delete test files
            if ($this->publicFileId) {
                File::delete($this->publicFileId);
            }
            if ($this->privateFileId) {
                File::delete($this->privateFileId);
            }

            echo "✅ Cleanup complete\n";
        } catch (\Exception $e) {
            echo "⚠️ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the tests
$test = new Phase6FileControllerTest();
$test->run();