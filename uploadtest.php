<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 Script started.<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "📤 POST request received.<br>";

    if (isset($_FILES['fileToUpload'])) {
        echo "📁 File was uploaded (in $_FILES).<br>";

        $file = $_FILES['fileToUpload'];

        echo "<pre>";
        print_r($file);
        echo "</pre>";

        if ($file['error'] === UPLOAD_ERR_OK) {
            echo "✅ Upload successful! File name: " . $file['name'] . "<br>";

            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
                echo "📂 Created upload directory.<br>";
            }

            $uniqueName = uniqid() . "_" . basename($file['name']);
            $targetFile = $uploadDir . $uniqueName;

            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                echo "✅ File saved successfully to: $targetFile";
            } else {
                echo "❌ Failed to move uploaded file.";
            }
        } else {
            echo "❌ Upload error. Error code: " . $file['error'];
        }
    } else {
        echo "⚠️ No file found in \$_FILES.";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
  <label>Select image:</label>
  <input type="file" name="fileToUpload" required />
  <button type="submit">Upload</button>
</form>
