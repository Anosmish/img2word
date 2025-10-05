<?php
header('Content-Type: application/json');

// Enable CORS for Netlify frontend
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

// Get form data
$title = isset($_POST['title']) ? trim($_POST['title']) : 'Extracted Document';
$language = isset($_POST['language']) ? $_POST['language'] : 'eng';
$formatting = isset($_POST['formatting']) ? $_POST['formatting'] : 'preserve';
$fontSize = isset($_POST['fontSize']) ? intval($_POST['fontSize']) : 12;
$includeImage = isset($_POST['includeImage']) ? $_POST['includeImage'] === '1' : true;
$addTimestamp = isset($_POST['addTimestamp']) ? $_POST['addTimestamp'] === '1' : true;

$uploadedFile = $_FILES['image'];

// Validate inputs
if (empty($title)) {
    $title = 'Extracted Document';
}

if ($fontSize < 8 || $fontSize > 72) {
    $fontSize = 12;
}

// Check if the uploaded file is an image
$imageInfo = @getimagesize($uploadedFile['tmp_name']);
if (!$imageInfo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Uploaded file is not a valid image']);
    exit;
}

// Check file size
if ($uploadedFile['size'] > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File size too large. Maximum 20MB allowed.']);
    exit;
}

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
    
    // Format the text
    $formattedText = formatText($extractedText, $formatting);
    
    // Generate Word document
    $result = generateWordDocument($title, $formattedText, $imagePath, [
        'includeImage' => $includeImage,
        'addTimestamp' => $addTimestamp,
        'fontSize' => $fontSize
    ]);

    // Clean up temporary image
    @unlink($imagePath);

    // Return success with file information
    echo json_encode([
        'success' => true,
        'filename' => $result['filename'],
        'filepath' => $result['filepath'],
        'fileSize' => $result['fileSize'],
        'wordCount' => str_word_count($formattedText),
        'characterCount' => strlen($formattedText)
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

function formatText($text, $formatting) {
    if (empty($text)) {
        return 'No text could be extracted from the image.';
    }
    
    switch ($formatting) {
        case 'paragraph':
            // Replace multiple newlines with paragraph breaks
            $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
            $text = preg_replace('/[ \t]+/', ' ', $text);
            break;
            
        case 'raw':
            // Keep raw text as is
            break;
            
        case 'preserve':
        default:
            // Preserve original line breaks but clean up extra spaces
            $text = preg_replace('/[ \t]+/', ' ', $text);
            break;
    }
    
    return $text;
}

function generateWordDocument($title, $text, $imagePath, $options) {
    // Check if PHPWord is available
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception('PHPWord dependencies not found. Please run composer install.');
    }
    
    require_once 'vendor/autoload.php';
    
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    // Create section
    $section = $phpWord->addSection();
    
    // Add title
    $titleStyle = [
        'bold' => true,
        'size' => $options['fontSize'] + 4,
        'color' => '2B5797'
    ];
    $section->addText($title, $titleStyle, ['alignment' => 'center']);
    $section->addTextBreak(2);
    
    // Add timestamp if requested
    if ($options['addTimestamp']) {
        $timestampStyle = ['italic' => true, 'size' => $options['fontSize'] - 2, 'color' => '666666'];
        $section->addText('Generated on: ' . date('F j, Y \a\t g:i A'), $timestampStyle);
        $section->addTextBreak(1);
    }
    
    // Add image if requested
    if ($options['includeImage'] && file_exists($imagePath)) {
        try {
            $imageInfo = @getimagesize($imagePath);
            if ($imageInfo) {
                // Calculate dimensions to fit within page
                $maxWidth = 400;
                $maxHeight = 300;
                
                $width = $imageInfo[0];
                $height = $imageInfo[1];
                
                if ($width > $maxWidth || $height > $maxHeight) {
                    $ratio = min($maxWidth/$width, $maxHeight/$height);
                    $width = $width * $ratio;
                    $height = $height * $ratio;
                }
                
                $section->addImage(
                    $imagePath,
                    [
                        'width' => $width,
                        'height' => $height,
                        'alignment' => 'center'
                    ]
                );
                $section->addTextBreak(1);
                $section->addText('Source Image:', ['bold' => true, 'size' => $options['fontSize']]);
                $section->addTextBreak(1);
            }
        } catch (Exception $e) {
            // If image can't be added, continue without it
            error_log("Could not add image to Word document: " . $e->getMessage());
        }
    }
    
    // Add extracted text
    $textStyle = ['size' => $options['fontSize']];
    $section->addText('Extracted Text:', ['bold' => true, 'size' => $options['fontSize']]);
    $section->addTextBreak(1);
    
    // Split text into paragraphs and add them
    $paragraphs = explode("\n\n", $text);
    foreach ($paragraphs as $paragraph) {
        if (trim($paragraph)) {
            $section->addText(trim($paragraph), $textStyle);
            $section->addTextBreak(1);
        }
    }
    
    // Generate filename and path
    $safeTitle = preg_replace('/[^a-zA-Z0-9-_]/', '_', $title);
    $filename = $safeTitle . '_' . date('Y-m-d_H-i-s') . '.docx';
    $filepath = 'output/' . $filename;
    
    // Save document
    try {
        $phpWord->save($filepath);
    } catch (Exception $e) {
        throw new Exception('Failed to save Word document: ' . $e->getMessage());
    }
    
    // Get file size
    $fileSize = filesize($filepath);
    
    if ($fileSize === false) {
        throw new Exception('Failed to get file size of generated document');
    }
    
    return [
        'filename' => $filename,
        'filepath' => $filepath,
        'fileSize' => $fileSize
    ];
}
?>
