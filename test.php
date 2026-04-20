<?php
// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_dir = __DIR__ . "/Uploads/";
    
    // Create Uploads directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file already exists
    if (file_exists($target_file)) {
        $message = "Sorry, file already exists.";
        $uploadOk = 0;
    }
    
    // Check file size (5MB max)
    if ($_FILES["fileToUpload"]["size"] > 5000000) {
        $message = "Sorry, your file is too large. Max size is 5MB.";
        $uploadOk = 0;
    }
    
    // Allow certain file formats (you can customize this list)
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
    if (!in_array($fileType, $allowedTypes)) {
        $message = "Sorry, only JPG, JPEG, PNG, GIF, PDF, DOC, DOCX & TXT files are allowed.";
        $uploadOk = 0;
    }
    
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        $message = "Sorry, your file was not uploaded. " . ($message ?? '');
    } else {
        // If everything is ok, try to upload file
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            $message = "The file ". htmlspecialchars(basename($_FILES["fileToUpload"]["name"])). " has been uploaded.";
            $uploadStatus = 'success';
        } else {
            $message = "Sorry, there was an error uploading your file.";
            $uploadStatus = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            line-height: 1.6;
        }
        .upload-form {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .message {
            margin: 20px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
    </style>
</head>
<body>
    <h1>File Upload</h1>
    
    <?php if (isset($message)): ?>
        <div class="message <?php echo $uploadStatus ?? ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="upload-form">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <h3>Select a file to upload:</h3>
            <input type="file" name="fileToUpload" id="fileToUpload" required>
            <p class="hint">Max file size: 5MB | Allowed formats: JPG, JPEG, PNG, GIF, PDF, DOC, DOCX, TXT</p>
            <button type="submit" class="btn">Upload File</button>
        </form>
    </div>
    
    <?php
    // List uploaded files
    $uploadDir = __DIR__ . '/Uploads/';
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        $files = array_diff($files, array('.', '..'));
        
        if (count($files) > 0) {
            echo '<div class="uploaded-files">';
            echo '<h3>Uploaded Files:</h3>';
            echo '<ul>';
            foreach ($files as $file) {
                $filePath = 'Uploads/' . $file;
                echo '<li><a href="' . htmlspecialchars($filePath) . '" target="_blank">' . 
                     htmlspecialchars($file) . '</a> (' . 
                     round(filesize($uploadDir . $file) / 1024, 2) . ' KB)</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    ?>
</body>
</html>