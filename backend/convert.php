<?php
header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if image was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No image uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['image'];
$title = isset($_POST['title']) ? trim($_POST['title']) : 'Extracted Document';
$language = isset($_POST['language']) ? $_POST['language'] : 'eng';

try {
    // Create necessary directories
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }
    if (!is_dir('output')) {
        mkdir('output', 0755, true);
    }

    // Save uploaded image temporarily
    $imagePath = 'uploads/' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $uploadedFile['name']);
    if (!move_uploaded_file($uploadedFile['tmp_name'], $imagePath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Perform OCR to extract text
    $extractedText = performOCR($imagePath, $language);
    
    // Generate filename and path for text file
    $safeTitle = preg_replace('/[^a-zA-Z0-9-_]/', '_', $title);
    $filename = $safeTitle . '_' . date('Y-m-d_H-i-s') . '.txt';
    $filepath = 'output/' . $filename;
    
    // Save as text file
    $fileContent = "Document: $title\n";
    $fileContent .= "Generated on: " . date('F j, Y \a\t g:i A') . "\n";
    $fileContent .= "Extracted Text:\n\n";
    $fileContent .= $extractedText;
    $fileContent .= "\n\n---\nTotal words: " . str_word_count($extractedText);
    $fileContent .= "\nTotal characters: " . strlen($extractedText);
    
    if (file_put_contents($filepath, $fileContent) === false) {
        throw new Exception('Failed to save text file');
    }

    // Clean up temporary image
    @unlink($imagePath);

    // Return success with file information
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'fileSize' => filesize($filepath),
        'wordCount' => str_word_count($extractedText),
        'characterCount' => strlen($extractedText),
        'format' => 'txt'
    ]);

} catch (Exception $e) {
    // Clean up on error
    if (isset($imagePath) && file_exists($imagePath)) {
        @unlink($imagePath);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Conversion failed: ' . $e->getMessage()]);
}

function performOCR($imagePath, $language) {
    // Check if Tesseract is available
    $tesseractCheck = shell_exec('which tesseract');
    if (empty($tesseractCheck)) {
        throw new Exception('Tesseract OCR is not installed on the server');
    }
    
    // Create temporary output file
    $outputFile = tempnam(sys_get_temp_dir(), 'ocr_output_');
    
    // Build Tesseract command
    $command = sprintf(
        'tesseract %s %s -l %s 2>&1',
        escapeshellarg($imagePath),
        escapeshellarg($outputFile),
        escapeshellarg($language)
    );
    
    // Execute OCR
    $output = shell_exec($command);
    
    // Check if output file was created
    if (!file_exists($outputFile . '.txt')) {
        throw new Exception("OCR failed: Output file not created. Command output: " . $output);
    }
    
    // Read the output text
    $text = file_get_contents($outputFile . '.txt');
    
    // Clean up temporary files
    @unlink($outputFile . '.txt');
    @unlink($outputFile);
    
    if ($text === false) {
        throw new Exception('Could not read OCR output');
    }
    
    return trim($text);
}
?>
