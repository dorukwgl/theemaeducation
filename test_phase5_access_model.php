<?php

require_once __DIR__ . '/vendor/autoload.php';

use EMA\Config\Database;
use EMA\Models\Access;
use EMA\Models\User;
use EMA\Models\File;
use EMA\Models\QuizSet;
use EMA\Utils\Logger;

/**
 * Phase 5: Access Model Refactoring Test Suite
 * Tests the refactored Access model that focuses only on individual permission checking
 */

class Phase5AccessModelTest
{
    private int $testUserId;
    private int $testFileId;
    private int $testQuizSetId;
    private array $testResults = [];

    public function __construct()
    {
        echo "=== Phase 5: Access Model Refactoring Tests ===\n\n";
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
            Logger::error('Phase 5 test suite failed', ['error' => $e->getMessage()]);
        }
    }

    private function setupTestData(): void
    {
        echo "Setting up test data...\n";

        // Get existing user
        $userResult = Database::query("SELECT id FROM users LIMIT 1");
        $user = $userResult->fetch_assoc();
        if (!$user) {
            throw new \Exception("No users found in database");
        }
        $this->testUserId = (int) $user['id'];

        // Get existing file
        $fileResult = Database::query("SELECT id, access_type FROM files LIMIT 1");
        $file = $fileResult->fetch_assoc();
        if (!$file) {
            throw new \Exception("No files found in database");
        }
        $this->testFileId = (int) $file['id'];
        echo "  - Test User ID: {$this->testUserId}\n";
        echo "  - Test File ID: {$this->testFileId} (access_type: {$file['access_type']})\n";

        // Get existing quiz set or create a test one
        $quizResult = Database::query("SELECT id, access_type FROM quiz_sets LIMIT 1");
        $quiz = $quizResult->fetch_assoc();
        if (!$quiz) {
            // Create a test quiz set - get a valid folder_id first
            echo "  - No quiz sets found, creating test quiz set...\n";

            // Get an existing folder
            $folderResult = Database::query("SELECT id FROM folders LIMIT 1");
            $folder = $folderResult->fetch_assoc();
            if (!$folder) {
                throw new \Exception("No folders found in database - cannot create test quiz set");
            }
            $folderId = (int) $folder['id'];

            $stmt = Database::prepare(
                "INSERT INTO quiz_sets (folder_id, name, access_type, status, is_published, created_by) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $name = 'Test Quiz Set for Phase 5';
            $accessType = 'logged_in';
            $status = 'published';
            $isPublished = 1;
            $createdBy = $this->testUserId;
            $stmt->bind_param('isssii', $folderId, $name, $accessType, $status, $isPublished, $createdBy);
            $stmt->execute();
            $this->testQuizSetId = $stmt->insert_id;
            echo "  - Created Test Quiz Set ID: {$this->testQuizSetId}\n";
        } else {
            $this->testQuizSetId = (int) $quiz['id'];
            echo "  - Test Quiz Set ID: {$this->testQuizSetId} (access_type: {$quiz['access_type']})\n";
        }
        echo "✅ Test data setup complete\n\n";
    }

    private function runTests(): void
    {
        echo "Running tests...\n\n";

        // Test 1: CheckAccess should only check individual permissions
        $this->testCheckAccessOnlyChecksPermissions();

        // Test 2: CheckAccess with no permission record
        $this->testCheckAccessNoPermission();

        // Test 3: CheckAccess with active permission
        $this->testCheckAccessWithPermission();

        // Test 4: CheckAccess with inactive permission
        $this->testCheckAccessInactivePermission();

        // Test 5: CheckAccess with unlimited access
        $this->testCheckAccessUnlimited();

        // Test 6: CheckAccess with limited access not exceeded
        $this->testCheckAccessLimitedNotExceeded();

        // Test 7: CheckAccess with limited access exceeded
        $this->testCheckAccessLimitedExceeded();

        // Test 8: HasPermissionRecord with existing permission
        $this->testHasPermissionRecordExists();

        // Test 9: HasPermissionRecord with no permission
        $this->testHasPermissionRecordNotExists();

        // Test 10: HasPermissionRecord with inactive permission
        $this->testHasPermissionRecordInactive();

        // Test 11: GetAccessStats returns individual permission stats
        $this->testGetAccessStatsIndividual();

        // Test 12: GrantAccess still works correctly
        $this->testGrantAccess();

        // Test 13: RevokeAccess still works correctly
        $this->testRevokeAccess();

        // Test 14: IncrementAccess still works correctly
        $this->testIncrementAccess();

        // Test 15: GetPermissions still works correctly
        $this->testGetPermissions();

        // Test 16: Backward compatibility with File model
        $this->testFileModelIntegration();

        // Test 17: Backward compatibility with QuizSet model
        $this->testQuizSetModelIntegration();

        // Test 18: Access model does not check access_type
        $this->testNoAccessTypeChecking();
    }

    private function testCheckAccessOnlyChecksPermissions(): void
    {
        $testName = "Test 1: checkAccess only checks individual permissions";
        echo "  Running $testName... ";

        try {
            // Clean up any existing permissions
            $identifier = 'user_' . $this->testUserId;
            Database::prepare(
                "DELETE FROM access_permissions WHERE identifier = ? AND item_id = ?"
            )->execute([$identifier, $this->testFileId]);

            // The method should only check permissions, not access_type
            // Since we have no permission record, it should return false
            $result = Access::checkAccess($this->testUserId, $this->testFileId, 'file');

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected false, got true\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testCheckAccessNoPermission(): void
    {
        $testName = "Test 2: checkAccess with no permission record";
        echo "  Running $testName... ";

        try {
            // Ensure no permission exists
            $identifier = 'user_' . $this->testUserId;
            Database::prepare(
                "DELETE FROM access_permissions WHERE identifier = ? AND item_id = ? AND item_type = ?"
            )->execute([$identifier, $this->testQuizSetId, 'quiz_set']);

            $result = Access::checkAccess($this->testUserId, $this->testQuizSetId, 'quiz_set');

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected false, got true\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testCheckAccessWithPermission(): void
    {
        $testName = "Test 3: checkAccess with active permission";
        echo "  Running $testName... ";

        try {
            // Grant access with 5 uses
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 5);

            $result = Access::checkAccess($this->testUserId, $this->testFileId, 'file');

            if ($result === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true, got false\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testCheckAccessInactivePermission(): void
    {
        $testName = "Test 4: checkAccess with inactive permission";
        echo "  Running $testName... ";

        try {
            // Grant access then deactivate
            Access::grantAccess($this->testUserId, $this->testQuizSetId, 'quiz_set', 5);

            $identifier = 'user_' . $this->testUserId;
            Database::prepare(
                "UPDATE access_permissions SET is_active = 0 WHERE identifier = ? AND item_id = ? AND item_type = ?"
            )->execute([$identifier, $this->testQuizSetId, 'quiz_set']);

            $result = Access::checkAccess($this->testUserId, $this->testQuizSetId, 'quiz_set');

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected false, got true\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testCheckAccessUnlimited(): void
    {
        $testName = "Test 5: checkAccess with unlimited access";
        echo "  Running $testName... ";

        try {
            // Grant unlimited access (0 = unlimited)
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 0);

            $result = Access::checkAccess($this->testUserId, $this->testFileId, 'file');

            if ($result === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true, got false\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testCheckAccessLimitedNotExceeded(): void
    {
        $testName = "Test 6: checkAccess with limited access not exceeded";
        echo "  Running $testName... ";

        try {
            // Grant access with 3 uses, use 1 time
            Access::grantAccess($this->testUserId, $this->testQuizSetId, 'quiz_set', 3);
            Access::incrementAccess($this->testUserId, $this->testQuizSetId, 'quiz_set');

            $result = Access::checkAccess($this->testUserId, $this->testQuizSetId, 'quiz_set');

            if ($result === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true, got false\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testCheckAccessLimitedExceeded(): void
    {
        $testName = "Test 7: checkAccess with limited access exceeded";
        echo "  Running $testName... ";

        try {
            // Grant access with 2 uses, use 3 times
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 2);
            Access::incrementAccess($this->testUserId, $this->testFileId, 'file');
            Access::incrementAccess($this->testUserId, $this->testFileId, 'file');

            $result = Access::checkAccess($this->testUserId, $this->testFileId, 'file');

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected false, got true\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testHasPermissionRecordExists(): void
    {
        $testName = "Test 8: hasPermissionRecord with existing permission";
        echo "  Running $testName... ";

        try {
            // Grant access
            Access::grantAccess($this->testUserId, $this->testQuizSetId, 'quiz_set', 5);

            $result = Access::hasPermissionRecord($this->testUserId, $this->testQuizSetId, 'quiz_set');

            if ($result === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true, got false\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testHasPermissionRecordNotExists(): void
    {
        $testName = "Test 9: hasPermissionRecord with no permission";
        echo "  Running $testName... ";

        try {
            // Use a non-existent item
            $result = Access::hasPermissionRecord($this->testUserId, 99999, 'file');

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected false, got true\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testHasPermissionRecordInactive(): void
    {
        $testName = "Test 10: hasPermissionRecord with inactive permission";
        echo "  Running $testName... ";

        try {
            // Grant access then deactivate
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 5);

            $identifier = 'user_' . $this->testUserId;
            Database::prepare(
                "UPDATE access_permissions SET is_active = 0 WHERE identifier = ? AND item_id = ? AND item_type = ?"
            )->execute([$identifier, $this->testFileId, 'file']);

            $result = Access::hasPermissionRecord($this->testUserId, $this->testFileId, 'file');

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected false, got true\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testGetAccessStatsIndividual(): void
    {
        $testName = "Test 11: getAccessStats returns individual permission stats";
        echo "  Running $testName... ";

        try {
            // Grant some access permissions
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 5);
            Access::incrementAccess($this->testUserId, $this->testFileId, 'file');

            $stats = Access::getAccessStats($this->testFileId, 'file');

            $hasRequiredKeys = isset($stats['total_users_with_individual_access']) &&
                              isset($stats['total_individual_accesses']) &&
                              isset($stats['average_accesses_per_user']) &&
                              isset($stats['users_with_unlimited_access']) &&
                              isset($stats['users_with_limited_access']);

            $correctValues = $stats['total_users_with_individual_access'] >= 1 &&
                            $stats['total_individual_accesses'] >= 1;

            // Should NOT have old keys
            $hasOldKeys = isset($stats['is_public']) || isset($stats['is_logged_in_only']);

            if ($hasRequiredKeys && $correctValues && !$hasOldKeys) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Stats structure incorrect or has old keys\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testGrantAccess(): void
    {
        $testName = "Test 12: grantAccess still works correctly";
        echo "  Running $testName... ";

        try {
            $result = Access::grantAccess($this->testUserId, $this->testQuizSetId, 'quiz_set', 10);

            if ($result === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true, got false\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testRevokeAccess(): void
    {
        $testName = "Test 13: revokeAccess still works correctly";
        echo "  Running $testName... ";

        try {
            // Grant first
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 5);

            // Then revoke
            $result = Access::revokeAccess($this->testUserId, $this->testFileId, 'file');

            // Verify it's revoked
            $hasPermission = Access::hasPermissionRecord($this->testUserId, $this->testFileId, 'file');

            if ($result === true && $hasPermission === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true and no permission\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testIncrementAccess(): void
    {
        $testName = "Test 14: incrementAccess still works correctly";
        echo "  Running $testName... ";

        try {
            // Grant access with limit
            Access::grantAccess($this->testUserId, $this->testQuizSetId, 'quiz_set', 5);

            // Increment
            $result = Access::incrementAccess($this->testUserId, $this->testQuizSetId, 'quiz_set');

            // Check stats
            $stats = Access::getAccessStats($this->testQuizSetId, 'quiz_set');

            if ($result === true && $stats['total_individual_accesses'] >= 1) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected true and incremented count\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testGetPermissions(): void
    {
        $testName = "Test 15: getPermissions still works correctly";
        echo "  Running $testName... ";

        try {
            // Grant some permissions
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 5);

            $permissions = Access::getPermissions($this->testUserId, 'file');

            if (is_array($permissions) && count($permissions) >= 1) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Expected array with at least 1 permission\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testFileModelIntegration(): void
    {
        $testName = "Test 16: Backward compatibility with File model";
        echo "  Running $testName... ";

        try {
            // This test verifies that File model can still use Access methods
            // We simulate what the File model does

            // Get a private file or set one to private
            $stmt = Database::prepare("UPDATE files SET access_type = 'private' WHERE id = ?");
            $stmt->bind_param('i', $this->testFileId);
            $stmt->execute();

            // Grant access
            Access::grantAccess($this->testUserId, $this->testFileId, 'file', 5);

            // Check access the way File model would
            $file = File::findById($this->testFileId);
            $hasIndividualPermission = false;

            if ($file && $file['access_type'] === 'private') {
                $hasIndividualPermission = Access::checkAccess($this->testUserId, $this->testFileId, 'file');
            }

            // Reset access_type
            $stmt = Database::prepare("UPDATE files SET access_type = 'logged_in' WHERE id = ?");
            $stmt->bind_param('i', $this->testFileId);
            $stmt->execute();

            if ($hasIndividualPermission === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - File model integration broken\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testQuizSetModelIntegration(): void
    {
        $testName = "Test 17: Backward compatibility with QuizSet model";
        echo "  Running $testName... ";

        try {
            // This test verifies that QuizSet model can still use Access methods

            // Get a private quiz set or set one to private
            $stmt = Database::prepare("UPDATE quiz_sets SET access_type = 'private' WHERE id = ?");
            $stmt->bind_param('i', $this->testQuizSetId);
            $stmt->execute();

            // Grant access
            Access::grantAccess($this->testUserId, $this->testQuizSetId, 'quiz_set', 5);

            // Check access the way QuizSet model would
            $quizSet = QuizSet::findById($this->testQuizSetId);
            $hasIndividualPermission = false;

            if ($quizSet && $quizSet['access_type'] === 'private') {
                $hasIndividualPermission = Access::checkAccess($this->testUserId, $this->testQuizSetId, 'quiz_set');
            }

            // Reset access_type
            $stmt = Database::prepare("UPDATE quiz_sets SET access_type = 'logged_in' WHERE id = ?");
            $stmt->bind_param('i', $this->testQuizSetId);
            $stmt->execute();

            if ($hasIndividualPermission === true) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - QuizSet model integration broken\n";
                $this->testResults[$testName] = false;
            }
        } catch (\Exception $e) {
            echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = false;
        }
    }

    private function testNoAccessTypeChecking(): void
    {
        $testName = "Test 18: Access model does not check access_type";
        echo "  Running $testName... ";

        try {
            // Set file to public access
            $stmt = Database::prepare("UPDATE files SET access_type = 'all' WHERE id = ?");
            $stmt->bind_param('i', $this->testFileId);
            $stmt->execute();

            // Ensure no individual permission
            $identifier = 'user_' . $this->testUserId;
            Database::prepare(
                "DELETE FROM access_permissions WHERE identifier = ? AND item_id = ? AND item_type = ?"
            )->execute([$identifier, $this->testFileId, 'file']);

            // Access::checkAccess should return false even though access_type is 'all'
            // because it only checks individual permissions
            $result = Access::checkAccess($this->testUserId, $this->testFileId, 'file');

            // Reset access_type
            $stmt = Database::prepare("UPDATE files SET access_type = 'logged_in' WHERE id = ?");
            $stmt->bind_param('i', $this->testFileId);
            $stmt->execute();

            if ($result === false) {
                echo "✅ PASS\n";
                $this->testResults[$testName] = true;
            } else {
                echo "❌ FAIL - Access model is still checking access_type\n";
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
            // Remove test permissions
            $identifier = 'user_' . $this->testUserId;
            $stmt = Database::prepare(
                "DELETE FROM access_permissions WHERE identifier = ? AND (item_id = ? OR item_id = ?)"
            );
            $stmt->bind_param('sii', $identifier, $this->testFileId, $this->testQuizSetId);
            $stmt->execute();

            // Remove test quiz set if we created it (check if it's our test one)
            $quizResult = Database::prepare("SELECT name FROM quiz_sets WHERE id = ?");
            $quizResult->bind_param('i', $this->testQuizSetId);
            $quizResult->execute();
            $quiz = $quizResult->get_result()->fetch_assoc();

            if ($quiz && strpos($quiz['name'], 'Test Quiz Set for Phase 5') !== false) {
                $deleteStmt = Database::prepare("DELETE FROM quiz_sets WHERE id = ?");
                $deleteStmt->bind_param('i', $this->testQuizSetId);
                $deleteStmt->execute();
                echo "  - Removed test quiz set\n";
            }

            echo "✅ Cleanup complete\n";
        } catch (\Exception $e) {
            echo "⚠️ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the tests
$test = new Phase5AccessModelTest();
$test->run();