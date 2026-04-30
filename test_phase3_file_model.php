<?php
/**
 * Phase 3 File Model Test Script
 * Tests all updates to the File model including status field and ACCESS_PRIVATE support
 */

// Define ROOT_PATH before loading autoloader
define('ROOT_PATH', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

use EMA\Config\Config;
use EMA\Config\Constants;
use EMA\Config\Database;
use EMA\Models\File;
use EMA\Models\Folder;
use EMA\Models\Access;
use EMA\Utils\Logger;

// Load configuration
try {
    Config::load();
} catch (Exception $e) {
    echo "Error loading configuration: " . $e->getMessage() . "\n";
    exit(1);
}

echo "==========================================\n";
echo "Phase 3: File Model Updates Test Suite\n";
echo "==========================================\n\n";

// Test counters
$passed = 0;
$failed = 0;

/**
 * Helper function to run a test
 */
function runTest(string $testName, callable $testFunction, int &$passed, int &$failed): void {
    echo "Testing: $testName\n";
    try {
        $result = $testFunction();
        if ($result) {
            echo "  ✓ PASSED\n";
            $passed++;
        } else {
            echo "  ✗ FAILED\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: " . $e->getMessage() . "\n";
        $failed++;
    }
    echo "\n";
}

// Test 1: Constants are defined
runTest("File status constants are defined", function() {
    return defined('EMA\Config\Constants::STATUS_ACTIVE') &&
           defined('EMA\Config\Constants::STATUS_INACTIVE') &&
           defined('EMA\Config\Constants::ACCESS_PRIVATE');
}, $passed, $failed);

// Test 2: Constants have correct values
runTest("File status constants have correct values", function() {
    return Constants::STATUS_ACTIVE === 'active' &&
           Constants::STATUS_INACTIVE === 'inactive' &&
           Constants::ACCESS_PRIVATE === 'private';
}, $passed, $failed);

// Test 3: File::findById() includes status field
runTest("File::findById() includes status field", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create a test file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Test File for Status Check',
        'file_path' => 'test/path.txt',
        'icon_path' => null,
        'access_type' => Constants::ACCESS_LOGGED_IN,
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    $file = File::findById($fileId);
    $hasStatus = isset($file['status']) && $file['status'] === Constants::STATUS_ACTIVE;

    // Cleanup
    File::delete($fileId);

    return $hasStatus;
}, $passed, $failed);

// Test 4: File::create() validates status field
runTest("File::create() validates status field", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Test with invalid status
    $invalidData = [
        'folder_id' => $folder['id'],
        'name' => 'Invalid Status Test',
        'file_path' => 'test/invalid.txt',
        'status' => 'invalid_status'
    ];

    $result = File::create($invalidData);
    return $result === false; // Should fail with invalid status
}, $passed, $failed);

// Test 5: File::create() accepts valid statuses
runTest("File::create() accepts valid statuses", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    $validStatuses = [Constants::STATUS_ACTIVE, Constants::STATUS_INACTIVE];
    $createdFiles = [];

    foreach ($validStatuses as $status) {
        $fileData = [
            'folder_id' => $folder['id'],
            'name' => "Status Test $status",
            'file_path' => "test/$status.txt",
            'status' => $status
        ];

        $fileId = File::create($fileData);
        if ($fileId) {
            $createdFiles[] = $fileId;
        } else {
            // Cleanup and fail
            foreach ($createdFiles as $id) {
                File::delete($id);
            }
            return false;
        }
    }

    // Cleanup
    foreach ($createdFiles as $id) {
        File::delete($id);
    }

    return true;
}, $passed, $failed);

// Test 6: File::create() validates access_type including private
runTest("File::create() validates access_type including private", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    $validAccessTypes = [
        Constants::ACCESS_ALL,
        Constants::ACCESS_LOGGED_IN,
        Constants::ACCESS_PRIVATE
    ];
    $createdFiles = [];

    foreach ($validAccessTypes as $accessType) {
        $fileData = [
            'folder_id' => $folder['id'],
            'name' => "Access Type Test $accessType",
            'file_path' => "test/$accessType.txt",
            'access_type' => $accessType
        ];

        $fileId = File::create($fileData);
        if ($fileId) {
            $createdFiles[] = $fileId;
        } else {
            // Cleanup and fail
            foreach ($createdFiles as $id) {
                File::delete($id);
            }
            return false;
        }
    }

    // Cleanup
    foreach ($createdFiles as $id) {
        File::delete($id);
    }

    return true;
}, $passed, $failed);

// Test 7: File::update() can change status
runTest("File::update() can change status", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create file with active status
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Status Update Test',
        'file_path' => 'test/status_update.txt',
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Update to inactive
    $updated = File::update($fileId, ['status' => Constants::STATUS_INACTIVE]);

    // Verify
    $file = File::findById($fileId);
    $isInactive = $file['status'] === Constants::STATUS_INACTIVE;

    // Cleanup
    File::delete($fileId);

    return $updated && $isInactive;
}, $passed, $failed);

// Test 8: File::update() can change access_type to private
runTest("File::update() can change access_type to private", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create file with logged_in access
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Access Type Update Test',
        'file_path' => 'test/access_update.txt',
        'access_type' => Constants::ACCESS_LOGGED_IN
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Update to private
    $updated = File::update($fileId, ['access_type' => Constants::ACCESS_PRIVATE]);

    // Verify
    $file = File::findById($fileId);
    $isPrivate = $file['access_type'] === Constants::ACCESS_PRIVATE;

    // Cleanup
    File::delete($fileId);

    return $updated && $isPrivate;
}, $passed, $failed);

// Test 9: File::isFileActive() works correctly
runTest("File::isFileActive() works correctly", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create active file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Active File Test',
        'file_path' => 'test/active.txt',
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    $isActive = File::isFileActive($fileId);

    // Update to inactive
    File::update($fileId, ['status' => Constants::STATUS_INACTIVE]);
    $isNotActive = !File::isFileActive($fileId);

    // Cleanup
    File::delete($fileId);

    return $isActive && $isNotActive;
}, $passed, $failed);

// Test 10: File::isFilePublic() works correctly
runTest("File::isFilePublic() works correctly", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create public file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Public File Test',
        'file_path' => 'test/public.txt',
        'access_type' => Constants::ACCESS_ALL,
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    $isPublic = File::isFilePublic($fileId);

    // Update to inactive
    File::update($fileId, ['status' => Constants::STATUS_INACTIVE]);
    $isNotPublic = !File::isFilePublic($fileId);

    // Cleanup
    File::delete($fileId);

    return $isPublic && $isNotPublic;
}, $passed, $failed);

// Test 11: File::updateStatus() helper method
runTest("File::updateStatus() helper method", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Update Status Helper Test',
        'file_path' => 'test/update_status.txt',
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Use helper method
    $updated = File::updateStatus($fileId, Constants::STATUS_INACTIVE);
    $file = File::findById($fileId);
    $isInactive = $file['status'] === Constants::STATUS_INACTIVE;

    // Cleanup
    File::delete($fileId);

    return $updated && $isInactive;
}, $passed, $failed);

// Test 12: File::updateAccessType() helper method
runTest("File::updateAccessType() helper method", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Update Access Type Helper Test',
        'file_path' => 'test/update_access.txt',
        'access_type' => Constants::ACCESS_LOGGED_IN
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Use helper method
    $updated = File::updateAccessType($fileId, Constants::ACCESS_PRIVATE);
    $file = File::findById($fileId);
    $isPrivate = $file['access_type'] === Constants::ACCESS_PRIVATE;

    // Cleanup
    File::delete($fileId);

    return $updated && $isPrivate;
}, $passed, $failed);

// Test 13: File::checkFileAccess() respects status
runTest("File::checkFileAccess() respects status", function() {
    // Get a folder and user to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    $users = Database::query("SELECT id FROM users LIMIT 1");
    if ($users->num_rows === 0) {
        return false; // No users to test with
    }
    $user = $users->fetch_assoc();

    // Create inactive file with public access
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Inactive Public File',
        'file_path' => 'test/inactive_public.txt',
        'access_type' => Constants::ACCESS_ALL,
        'status' => Constants::STATUS_INACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Even public files should not be accessible if inactive
    $hasAccess = File::checkFileAccess($user['id'], $fileId);

    // Cleanup
    File::delete($fileId);

    return !$hasAccess; // Should NOT have access to inactive file
}, $passed, $failed);

// Test 14: File::checkFileAccess() handles private access type
runTest("File::checkFileAccess() handles private access type", function() {
    // Get a folder and user to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    $users = Database::query("SELECT id FROM users LIMIT 1");
    if ($users->num_rows === 0) {
        return false; // No users to test with
    }
    $user = $users->fetch_assoc();

    // Create private file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Private File Test',
        'file_path' => 'test/private.txt',
        'access_type' => Constants::ACCESS_PRIVATE,
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Should not have access without explicit permission
    $noAccess = !File::checkFileAccess($user['id'], $fileId);

    // Grant access
    Access::grantAccess($user['id'], $fileId, 'file');
    $hasAccess = File::checkFileAccess($user['id'], $fileId);

    // Cleanup
    File::delete($fileId);

    return $noAccess && $hasAccess;
}, $passed, $failed);

// Test 15: File::getFilesByFolder() filters by status
runTest("File::getFilesByFolder() filters by status", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create active file
    $activeFileData = [
        'folder_id' => $folder['id'],
        'name' => 'Active File for Filter Test ' . time(),
        'file_path' => 'test/active_filter_' . time() . '.txt',
        'status' => Constants::STATUS_ACTIVE
    ];

    $activeFileId = File::create($activeFileData);

    // Create inactive file
    $inactiveFileData = [
        'folder_id' => $folder['id'],
        'name' => 'Inactive File for Filter Test ' . time(),
        'file_path' => 'test/inactive_filter_' . time() . '.txt',
        'status' => Constants::STATUS_INACTIVE
    ];

    $inactiveFileId = File::create($inactiveFileData);

    if (!$activeFileId || !$inactiveFileId) {
        // Cleanup
        if ($activeFileId) File::delete($activeFileId);
        if ($inactiveFileId) File::delete($inactiveFileId);
        return false;
    }

    // Get only active files (default)
    $activeFiles = File::getFilesByFolder($folder['id'], null, false);

    // Get all files including inactive
    $allFiles = File::getFilesByFolder($folder['id'], null, true);

    // Find our test files in the results
    $foundActive = false;
    $foundInactive = false;
    $foundInactiveInActive = false;

    foreach ($activeFiles as $file) {
        if ($file['id'] === $activeFileId) {
            $foundActive = true;
        }
        if ($file['id'] === $inactiveFileId) {
            $foundInactiveInActive = true;
        }
    }

    foreach ($allFiles as $file) {
        if ($file['id'] === $inactiveFileId) {
            $foundInactive = true;
        }
    }

    // Cleanup
    File::delete($activeFileId);
    File::delete($inactiveFileId);

    // Check that active file is in active list, inactive file is NOT in active list, and inactive file is in all files
    return $foundActive && !$foundInactiveInActive && $foundInactive;
}, $passed, $failed);

// Test 16: File::getFilesByFolderPaginated() includes status
runTest("File::getFilesByFolderPaginated() includes status", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create file
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Paginated Status Test',
        'file_path' => 'test/paginated_status.txt',
        'status' => Constants::STATUS_ACTIVE
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // Get paginated files
    $result = File::getFilesByFolderPaginated($folder['id'], 1, 10);

    // Cleanup
    File::delete($fileId);

    // Check if status field is present
    if (empty($result['files'])) {
        return false;
    }

    return isset($result['files'][0]['status']) &&
           $result['files'][0]['status'] === Constants::STATUS_ACTIVE;
}, $passed, $failed);

// Test 17: File::getPublicFilesPaginated() helper method
runTest("File::getPublicFilesPaginated() helper method", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create public file
    $publicFileData = [
        'folder_id' => $folder['id'],
        'name' => 'Public Paginated Test',
        'file_path' => 'test/public_paginated.txt',
        'access_type' => Constants::ACCESS_ALL,
        'status' => Constants::STATUS_ACTIVE
    ];

    $publicFileId = File::create($publicFileData);

    // Create private file
    $privateFileData = [
        'folder_id' => $folder['id'],
        'name' => 'Private Paginated Test',
        'file_path' => 'test/private_paginated.txt',
        'access_type' => Constants::ACCESS_PRIVATE,
        'status' => Constants::STATUS_ACTIVE
    ];

    $privateFileId = File::create($privateFileData);

    if (!$publicFileId || !$privateFileId) {
        // Cleanup
        if ($publicFileId) File::delete($publicFileId);
        if ($privateFileId) File::delete($privateFileId);
        return false;
    }

    // Get public files only
    $result = File::getPublicFilesPaginated($folder['id'], 1, 10);

    // Cleanup
    File::delete($publicFileId);
    File::delete($privateFileId);

    // Should have 1 public file
    return count($result['files']) === 1 &&
           $result['files'][0]['access_type'] === Constants::ACCESS_ALL;
}, $passed, $failed);

// Test 18: File::getFileStats() includes status
runTest("File::getFileStats() includes status", function() {
    // Get a folder to use
    $folders = Database::query("SELECT id FROM folders LIMIT 1");
    if ($folders->num_rows === 0) {
        return false; // No folders to test with
    }
    $folder = $folders->fetch_assoc();

    // Create file with minimal data to avoid potential issues
    $fileData = [
        'folder_id' => $folder['id'],
        'name' => 'Stats Status Test ' . time(),
        'file_path' => 'test/stats_status_' . time() . '.txt',
        'status' => Constants::STATUS_ACTIVE,
        'access_type' => Constants::ACCESS_LOGGED_IN
    ];

    $fileId = File::create($fileData);
    if (!$fileId) {
        return false;
    }

    // First verify file exists
    $file = File::findById($fileId);
    if (!$file) {
        File::delete($fileId);
        return false;
    }

    // Get stats
    $stats = File::getFileStats($fileId);

    // Cleanup
    File::delete($fileId);

    // Check if stats array is not empty and has required fields
    if (empty($stats)) {
        return false; // Stats should not be empty
    }

    // Check if status and is_active fields are present and correct
    $hasStatus = isset($stats['status']) && $stats['status'] === Constants::STATUS_ACTIVE;
    $hasIsActive = isset($stats['is_active']) && $stats['is_active'] === true;
    $hasFileId = isset($stats['file_id']) && $stats['file_id'] === $fileId;

    return $hasStatus && $hasIsActive && $hasFileId;
}, $passed, $failed);

// Final Summary
echo "==========================================\n";
echo "Test Summary\n";
echo "==========================================\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Success Rate: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n\n";

if ($failed === 0) {
    echo "✓ All tests passed! Phase 3 is complete.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the errors above.\n";
    exit(1);
}