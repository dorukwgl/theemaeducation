<?php

/**
 * Phase 4 Test Script - Quiz Set Model Updates
 * Tests status management and private access control
 */

// Define ROOT_PATH
define('ROOT_PATH', __DIR__);

// Include autoloader
require_once __DIR__ . '/vendor/autoload.php';

use EMA\Config\Constants;
use EMA\Models\QuizSet;
use EMA\Models\Folder;
use EMA\Models\User;
use EMA\Models\Access;
use EMA\Config\Database;

echo "=== Phase 4 Test Script - Quiz Set Model ===\n\n";

// Initialize test counters
$testsPassed = 0;
$testsFailed = 0;

// Helper function to run a test
function runTest($description, $testFunc, &$passed, &$failed) {
    echo "Testing: $description\n";
    try {
        $result = $testFunc();
        if ($result) {
            echo "  ✓ PASSED\n";
            $passed++;
        } else {
            echo "  ✗ FAILED\n";
            $failed++;
        }
    } catch (\Exception $e) {
        echo "  ✗ FAILED: " . $e->getMessage() . "\n";
        $failed++;
    }
    echo "\n";
}

// Test 1: Quiz set status constants are defined
runTest("Quiz set status constants are defined", function() {
    return defined('EMA\Config\Constants::STATUS_PUBLISHED') &&
           defined('EMA\Config\Constants::STATUS_DRAFT') &&
           defined('EMA\Config\Constants::STATUS_ARCHIVED');
}, $testsPassed, $testsFailed);

// Test 2: Quiz set status constants have correct values
runTest("Quiz set status constants have correct values", function() {
    return Constants::STATUS_PUBLISHED === 'published' &&
           Constants::STATUS_DRAFT === 'draft' &&
           Constants::STATUS_ARCHIVED === 'archived';
}, $testsPassed, $testsFailed);

// Test 3: QuizSet::findById() includes status field
runTest("QuizSet::findById() includes status field", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create a test quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set Phase 4 ' . time(),
        'description' => 'Test description',
        'access_type' => Constants::ACCESS_LOGGED_IN,
        'status' => Constants::STATUS_DRAFT,
        'duration_minutes' => 30,
        'passing_score' => 70,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Check if status field is returned
    $quizSet = QuizSet::findById($quizSetId);
    $hasStatus = isset($quizSet['status']) && $quizSet['status'] === Constants::STATUS_DRAFT;

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $hasStatus;
}, $testsPassed, $testsFailed);

// Test 4: QuizSet::create() validates status field
runTest("QuizSet::create() validates status field", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Try to create quiz set with invalid status
    $invalidQuizSetData = [
        'folder_id' => $folderId,
        'name' => 'Invalid Quiz Set ' . time(),
        'status' => 'invalid_status'
    ];
    $result = QuizSet::create($invalidQuizSetData);

    // Cleanup
    Folder::delete($folderId);

    return $result === false;
}, $testsPassed, $testsFailed);

// Test 5: QuizSet::create() accepts valid statuses
runTest("QuizSet::create() accepts valid statuses", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Try creating quiz sets with different valid statuses
    $statuses = [
        Constants::STATUS_PUBLISHED,
        Constants::STATUS_DRAFT,
        Constants::STATUS_ARCHIVED
    ];

    $successCount = 0;
    $quizSetIds = [];

    foreach ($statuses as $status) {
        $quizSetData = [
            'folder_id' => $folderId,
            'name' => 'Test Quiz Set ' . $status . ' ' . time(),
            'status' => $status,
            'created_by' => 1
        ];
        $quizSetId = QuizSet::create($quizSetData);
        if ($quizSetId) {
            $successCount++;
            $quizSetIds[] = $quizSetId;
        }
    }

    // Cleanup
    foreach ($quizSetIds as $id) {
        QuizSet::delete($id);
    }
    Folder::delete($folderId);

    return $successCount === count($statuses);
}, $testsPassed, $testsFailed);

// Test 6: QuizSet::create() validates access_type including private
runTest("QuizSet::create() validates access_type including private", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Try creating quiz set with ACCESS_PRIVATE
    $privateQuizSetData = [
        'folder_id' => $folderId,
        'name' => 'Private Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_PRIVATE,
        'status' => Constants::STATUS_DRAFT,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($privateQuizSetData);

    // Verify it was created
    $success = $quizSetId !== false;
    if ($success) {
        $quizSet = QuizSet::findById($quizSetId);
        $success = $quizSet && $quizSet['access_type'] === Constants::ACCESS_PRIVATE;
        QuizSet::delete($quizSetId);
    }

    // Cleanup
    Folder::delete($folderId);

    return $success;
}, $testsPassed, $testsFailed);

// Test 7: QuizSet::update() can change status
runTest("QuizSet::update() can change status", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set ' . time(),
        'status' => Constants::STATUS_DRAFT,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Update status
    $updated = QuizSet::update($quizSetId, [
        'status' => Constants::STATUS_PUBLISHED
    ]);

    // Verify
    $success = false;
    if ($updated) {
        $quizSet = QuizSet::findById($quizSetId);
        $success = $quizSet && $quizSet['status'] === Constants::STATUS_PUBLISHED;
    }

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $success;
}, $testsPassed, $testsFailed);

// Test 8: QuizSet::update() can change access_type to private
runTest("QuizSet::update() can change access_type to private", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_LOGGED_IN,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Update access type to private
    $updated = QuizSet::update($quizSetId, [
        'access_type' => Constants::ACCESS_PRIVATE
    ]);

    // Verify
    $success = false;
    if ($updated) {
        $quizSet = QuizSet::findById($quizSetId);
        $success = $quizSet && $quizSet['access_type'] === Constants::ACCESS_PRIVATE;
    }

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $success;
}, $testsPassed, $testsFailed);

// Test 9: QuizSet::isQuizSetPublished() works correctly
runTest("QuizSet::isQuizSetPublished() works correctly", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create published quiz set
    $publishedData = [
        'folder_id' => $folderId,
        'name' => 'Published Quiz Set ' . time(),
        'status' => Constants::STATUS_PUBLISHED,
        'created_by' => 1
    ];
    $publishedId = QuizSet::create($publishedData);

    // Create draft quiz set
    $draftData = [
        'folder_id' => $folderId,
        'name' => 'Draft Quiz Set ' . time(),
        'status' => Constants::STATUS_DRAFT,
        'created_by' => 1
    ];
    $draftId = QuizSet::create($draftData);

    // Test
    $publishedCheck = $publishedId && QuizSet::isQuizSetPublished($publishedId);
    $draftCheck = $draftId && !QuizSet::isQuizSetPublished($draftId);

    // Cleanup
    if ($publishedId) QuizSet::delete($publishedId);
    if ($draftId) QuizSet::delete($draftId);
    Folder::delete($folderId);

    return $publishedCheck && $draftCheck;
}, $testsPassed, $testsFailed);

// Test 10: QuizSet::isQuizSetPublic() works correctly
runTest("QuizSet::isQuizSetPublic() works correctly", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create public, published quiz set
    $publicData = [
        'folder_id' => $folderId,
        'name' => 'Public Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_ALL,
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => 1
    ];
    $publicId = QuizSet::create($publicData);

    // Create private quiz set
    $privateData = [
        'folder_id' => $folderId,
        'name' => 'Private Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_PRIVATE,
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => 1
    ];
    $privateId = QuizSet::create($privateData);

    // Test
    $publicCheck = $publicId && QuizSet::isQuizSetPublic($publicId);
    $privateCheck = $privateId && !QuizSet::isQuizSetPublic($privateId);

    // Cleanup
    if ($publicId) QuizSet::delete($publicId);
    if ($privateId) QuizSet::delete($privateId);
    Folder::delete($folderId);

    return $publicCheck && $privateCheck;
}, $testsPassed, $testsFailed);

// Test 11: QuizSet::updateStatus() helper method
runTest("QuizSet::updateStatus() helper method", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set ' . time(),
        'status' => Constants::STATUS_DRAFT,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Update status using helper method
    $updated = QuizSet::updateStatus($quizSetId, Constants::STATUS_PUBLISHED);

    // Verify
    $success = false;
    if ($updated) {
        $quizSet = QuizSet::findById($quizSetId);
        $success = $quizSet && $quizSet['status'] === Constants::STATUS_PUBLISHED;
    }

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $success;
}, $testsPassed, $testsFailed);

// Test 12: QuizSet::updateAccessType() helper method
runTest("QuizSet::updateAccessType() helper method", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_LOGGED_IN,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Update access type using helper method
    $updated = QuizSet::updateAccessType($quizSetId, Constants::ACCESS_PRIVATE);

    // Verify
    $success = false;
    if ($updated) {
        $quizSet = QuizSet::findById($quizSetId);
        $success = $quizSet && $quizSet['access_type'] === Constants::ACCESS_PRIVATE;
    }

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $success;
}, $testsPassed, $testsFailed);

// Test 13: QuizSet::checkQuizSetAccess() respects status
runTest("QuizSet::checkQuizSetAccess() respects status", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create draft quiz set (not published)
    $draftData = [
        'folder_id' => $folderId,
        'name' => 'Draft Quiz Set ' . time(),
        'status' => Constants::STATUS_DRAFT,
        'is_published' => true,
        'access_type' => Constants::ACCESS_ALL,
        'created_by' => 1
    ];
    $draftId = QuizSet::create($draftData);

    // Create published quiz set
    $publishedData = [
        'folder_id' => $folderId,
        'name' => 'Published Quiz Set ' . time(),
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'access_type' => Constants::ACCESS_ALL,
        'created_by' => 1
    ];
    $publishedId = QuizSet::create($publishedData);

    // Test access (using user ID 1)
    $draftAccess = $draftId && !QuizSet::checkQuizSetAccess(1, $draftId);
    $publishedAccess = $publishedId && QuizSet::checkQuizSetAccess(1, $publishedId);

    // Cleanup
    if ($draftId) QuizSet::delete($draftId);
    if ($publishedId) QuizSet::delete($publishedId);
    Folder::delete($folderId);

    return $draftAccess && $publishedAccess;
}, $testsPassed, $testsFailed);

// Test 14: QuizSet::checkQuizSetAccess() handles private access type
runTest("QuizSet::checkQuizSetAccess() handles private access type", function() {
    // Get an existing user
    $userResult = Database::query("SELECT id FROM users LIMIT 1");
    if (!$userResult || $userResult->num_rows === 0) {
        return false; // No users to test with
    }
    $user = $userResult->fetch_assoc();
    $userId = (int) $user['id'];

    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create private quiz set
    $privateData = [
        'folder_id' => $folderId,
        'name' => 'Private Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_PRIVATE,
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => $userId
    ];
    $privateId = QuizSet::create($privateData);
    if (!$privateId) {
        Folder::delete($folderId);
        return false;
    }

    // Test access without permission
    $noAccess = !QuizSet::checkQuizSetAccess($userId, $privateId);

    // Grant access to user
    $granted = Access::grantAccess($userId, $privateId, 'quiz_set');

    // Test access with permission
    $hasAccess = $granted && QuizSet::checkQuizSetAccess($userId, $privateId);

    // Cleanup
    QuizSet::delete($privateId);
    Folder::delete($folderId);

    return $noAccess && $hasAccess;
}, $testsPassed, $testsFailed);

// Test 15: QuizSet::getAllQuizSets() includes status
runTest("QuizSet::getAllQuizSets() includes status", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set ' . time(),
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => 1
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Get quiz sets
    $quizSets = QuizSet::getAllQuizSets(1, 10, $folderId, null, false, true, true, null);

    // Check if status field is present
    $hasStatus = !empty($quizSets) && isset($quizSets[0]['status']);

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $hasStatus;
}, $testsPassed, $testsFailed);

// Test 16: QuizSet::getAllQuizSets() filters by status
runTest("QuizSet::getAllQuizSets() filters by status", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create published quiz set
    $publishedData = [
        'folder_id' => $folderId,
        'name' => 'Published Quiz Set ' . time(),
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => 1
    ];
    $publishedId = QuizSet::create($publishedData);

    // Create draft quiz set
    $draftData = [
        'folder_id' => $folderId,
        'name' => 'Draft Quiz Set ' . time(),
        'status' => Constants::STATUS_DRAFT,
        'is_published' => true,
        'created_by' => 1
    ];
    $draftId = QuizSet::create($draftData);

    // Get only published quiz sets
    $publishedQuizSets = QuizSet::getAllQuizSets(1, 10, $folderId, null, false, false, true, Constants::STATUS_PUBLISHED);

    // Verify
    $onlyPublished = true;
    foreach ($publishedQuizSets as $quizSet) {
        if ($quizSet['status'] !== Constants::STATUS_PUBLISHED) {
            $onlyPublished = false;
            break;
        }
    }

    // Cleanup
    if ($publishedId) QuizSet::delete($publishedId);
    if ($draftId) QuizSet::delete($draftId);
    Folder::delete($folderId);

    return $onlyPublished;
}, $testsPassed, $testsFailed);

// Test 17: QuizSet::getPublicQuizSetsPaginated() helper method
runTest("QuizSet::getPublicQuizSetsPaginated() helper method", function() {
    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create public, published quiz set
    $publicData = [
        'folder_id' => $folderId,
        'name' => 'Public Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_ALL,
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => 1
    ];
    $publicId = QuizSet::create($publicData);

    // Create private quiz set
    $privateData = [
        'folder_id' => $folderId,
        'name' => 'Private Quiz Set ' . time(),
        'access_type' => Constants::ACCESS_PRIVATE,
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => 1
    ];
    $privateId = QuizSet::create($privateData);

    // Get public quiz sets
    $result = QuizSet::getPublicQuizSetsPaginated($folderId, 1, 10);

    // Verify only public quiz sets are returned
    $onlyPublic = true;
    if (!empty($result['quiz_sets'])) {
        foreach ($result['quiz_sets'] as $quizSet) {
            if ($quizSet['access_type'] !== Constants::ACCESS_ALL) {
                $onlyPublic = false;
                break;
            }
        }
    }

    // Check pagination metadata
    $hasPagination = isset($result['pagination']) &&
                     isset($result['pagination']['page']) &&
                     isset($result['pagination']['per_page']) &&
                     isset($result['pagination']['total']);

    // Cleanup
    if ($publicId) QuizSet::delete($publicId);
    if ($privateId) QuizSet::delete($privateId);
    Folder::delete($folderId);

    return $onlyPublic && $hasPagination;
}, $testsPassed, $testsFailed);

// Test 18: QuizSet::getQuizSetStats() includes status
runTest("QuizSet::getQuizSetStats() includes status", function() {
    // Get an existing user
    $userResult = Database::query("SELECT id FROM users LIMIT 1");
    if (!$userResult || $userResult->num_rows === 0) {
        return false; // No users to test with
    }
    $user = $userResult->fetch_assoc();
    $userId = (int) $user['id'];

    // Create a test folder
    $folderData = [
        'name' => 'Test Folder Phase 4 ' . time(),
        'icon_path' => null,
        'access_type' => 'logged_in'
    ];
    $folderId = Folder::create($folderData);
    if (!$folderId) return false;

    // Create quiz set
    $quizSetData = [
        'folder_id' => $folderId,
        'name' => 'Test Quiz Set ' . time(),
        'status' => Constants::STATUS_PUBLISHED,
        'is_published' => true,
        'created_by' => $userId
    ];
    $quizSetId = QuizSet::create($quizSetData);
    if (!$quizSetId) {
        Folder::delete($folderId);
        return false;
    }

    // Get statistics
    $stats = QuizSet::getQuizSetStats($quizSetId);

    // Check if status field is present
    $hasStatus = isset($stats['status']) && $stats['status'] === Constants::STATUS_PUBLISHED;
    $hasIsActive = isset($stats['is_active']) && $stats['is_active'] === true;

    // Cleanup
    QuizSet::delete($quizSetId);
    Folder::delete($folderId);

    return $hasStatus && $hasIsActive;
}, $testsPassed, $testsFailed);

// Print summary
echo "=== Test Summary ===\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";
echo "Success Rate: " . round(($testsPassed / ($testsPassed + $testsFailed)) * 100, 2) . "%\n";

if ($testsFailed === 0) {
    echo "\n✓ All tests passed! Phase 4 implementation is working correctly.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}
