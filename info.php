<?php
ini_set("extension", "fileinfo");

// Check if the function exists
if (function_exists('mime_content_type')) {
    echo "<p style='color: green;'>✓ mime_content_type() function is available</p>";
    
    // Test the function
    $testFile = __FILE__; // Test with this file
    $mime = @mime_content_type($testFile);
    if ($mime !== false) {
        echo "<p>MIME type of this file: <strong>$mime</strong></p>";
    } else {
        echo "<p style='color: orange;'>⚠ Could not determine MIME type (check file permissions)</p>";
    }
} else {
    echo "<p style='color: red;'>✗ mime_content_type() function is NOT available</p>";
}

// Check if the extension is loaded
echo "<h3>Loaded Extensions</h3>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<ul>";
foreach ($extensions as $ext) {
    echo "<li>$ext</li>";
}
echo "</ul>";

// Check php.ini location
echo "<h3>PHP Configuration</h3>";
echo "<p>Loaded Configuration File: " . php_ini_loaded_file() . "</p>";
echo "<p>Additional .ini files parsed: " . implode(", ", php_ini_scanned_files() ?: ['none']) . "</p>";

// Check if we can use finfo as an alternative
if (class_exists('finfo')) {
    echo "<p style='color: green;'>✓ finfo class is available as an alternative</p>";
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file(__FILE__);
        echo "<p>MIME type using finfo: <strong>$mime</strong></p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error using finfo: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ finfo class is not available</p>";
}

phpinfo();
?>
