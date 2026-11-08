<?php
// ============================================
// OBGEcom AI Bot - File Upload (Simplified)
// ============================================

// Define the exact path to your config file
$configPath =  '../../config/ask.php';

// Check if config file exists
if (!file_exists($configPath)) {
    die('Configuration file not found at: ' . $configPath);
}

// Load the config file
require_once $configPath;

// Now we should have access to the database and session classes
// Check if user is logged in
if (!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../../lg');
    exit;
}

// Get database instance
$db = DB::getInstance();

// Get user data from session
if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
    Redirect::to('../../lg');
    exit;
}

// Get user from database
$user = $db->getThisQuery(
    "SELECT id, name, role, is_verified, email FROM users WHERE email = ? OR username = ? OR phone = ? LIMIT 1",
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

if (!$user || empty($user[0]['id'])) {
    // User not found, logout
    if (class_exists('User')) {
        $userObj = new User();
        $userObj->logout();
    }
    session_destroy();
    Redirect::to('../../lg');
    exit;
}

// Check if user is super admin
if ($user[0]['role'] !== 'super') {
    Redirect::to('../home');
    exit;
}

// Store user ID
$userId = $user[0]['id'];

// Function to get file icon based on extension
function getFileIcon($extension) {
    $icons = [
        'pdf' => '📄',
        'txt' => '📝',
        'doc' => '📘',
        'docx' => '📘',
        'xls' => '📊',
        'xlsx' => '📊',
        'csv' => '📊',
        'ppt' => '📽',
        'pptx' => '📽',
        'jpg' => '🖼',
        'jpeg' => '🖼',
        'png' => '🖼',
        'gif' => '🖼',
        'zip' => '📦',
        'rar' => '📦',
        'mp3' => '🎵',
        'mp4' => '🎬',
        'json' => '📋',
        'xml' => '📋',
    ];
    return isset($icons[strtolower($extension)]) ? $icons[strtolower($extension)] : '📎';
}

// Get existing uploaded files
$uploadedFiles = [];
$uploadDir = './uploads/';
$storageFile = './storage.txt';

// Create uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Scan for existing files
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $uploadDir . $file;
            if (file_exists($filePath)) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $uploadedFiles[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'date' => date("Y-m-d H:i:s", filemtime($filePath)),
                    'icon' => getFileIcon($extension),
                    'type' => function_exists('mime_content_type') ? mime_content_type($filePath) : 'application/octet-stream',
                    'path' => $filePath
                ];
            }
        }
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 B";
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// AJAX request handling for file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'file' => null];
    
    try {
        $file = $_FILES['file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
            throw new Exception($errorMsg);
        }
        
        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds 10MB limit');
        }
        
        // Check file name security
        $originalName = basename($file['name']);
        if (preg_match('/[\/\\\\]/', $originalName)) {
            throw new Exception('Invalid file name');
        }
        
        // Allowed file types and extensions
        $allowedTypes = [
            'text/plain' => ['txt'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/csv' => ['csv'],
            'application/json' => ['json'],
            'text/xml' => ['xml'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'application/zip' => ['zip'],
            'application/x-rar-compressed' => ['rar'],
        ];
        
        // Get MIME type
        $mimeType = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } else {
            // Fallback to extension check
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            foreach ($allowedTypes as $type => $exts) {
                if (in_array($extension, $exts)) {
                    $mimeType = $type;
                    break;
                }
            }
        }
        
        // Validate MIME type
        if (empty($mimeType) || !isset($allowedTypes[$mimeType])) {
            $allowedExtensions = [];
            foreach ($allowedTypes as $exts) {
                $allowedExtensions = array_merge($allowedExtensions, $exts);
            }
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', array_unique($allowedExtensions)));
        }
        
        // Check extension matches MIME type
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes[$mimeType])) {
            throw new Exception('File extension does not match MIME type');
        }
        
        // Secure filename
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
        $destination = $uploadDir . $safeName;
        
        // Check if file already exists
        if (file_exists($destination)) {
            throw new Exception('File with this name already exists');
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Log the upload in storage.txt
            $logEntry = date('Y-m-d H:i:s') . " | SUPER_ADMIN:{$userId} | UPLOAD:{$safeName} | TYPE:{$mimeType} | SIZE:" . $file['size'] . "\n";
            file_put_contents($storageFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            // Also append file content to storage.txt for AI to read (for text files)
            if (in_array($extension, ['txt', 'csv', 'json', 'xml'])) {
                $fileContent = file_get_contents($destination);
                $separator = "\n\n" . str_repeat("=", 80) . "\n";
                $fileHeader = "\n[FILE: {$originalName} - Uploaded: " . date('Y-m-d H:i:s') . "]\n";
                file_put_contents($storageFile, $separator . $fileHeader . $fileContent, FILE_APPEND | LOCK_EX);
            }
            
            $response['success'] = true;
            $response['message'] = 'File uploaded successfully';
            $response['file'] = [
                'name' => $safeName,
                'original' => $originalName,
                'size' => formatFileSize($file['size']),
                'date' => date('Y-m-d H:i:s'),
                'icon' => getFileIcon($extension),
                'type' => $mimeType
            ];
        } else {
            throw new Exception('Failed to move uploaded file. Check directory permissions.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Upload error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}

// Handle file deletion (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        $filename = basename($_POST['filename']);
        $filepath = $uploadDir . $filename;
        
        // Security check - prevent directory traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            throw new Exception('Invalid filename');
        }
        
        if (file_exists($filepath)) {
            if (unlink($filepath)) {
                // Log deletion
                $logEntry = date('Y-m-d H:i:s') . " | SUPER_ADMIN:{$userId} | DELETE:{$filename}\n";
                file_put_contents($storageFile, $logEntry, FILE_APPEND | LOCK_EX);
                
                $response['success'] = true;
                $response['message'] = 'File deleted successfully';
            } else {
                throw new Exception('Failed to delete file. Check file permissions.');
            }
        } else {
            throw new Exception('File not found');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Delete error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    if (class_exists('User')) {
        $userObj = new User();
        $userObj->logout();
    }
    session_destroy();
    Redirect::to('../../');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Bot - File Upload | OBGEcom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header h1 i {
            color: #667eea;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .user-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .user-info strong {
            color: #764ba2;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .upload-area {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 3px dashed #ddd;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            transform: translateY(-5px);
        }
        
        .upload-area.dragover {
            border-color: #764ba2;
            background: rgba(118, 75, 162, 0.05);
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .upload-area h3 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .file-types {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .files-list {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .files-list h2 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .file-item:hover {
            background: #eef2ff;
            transform: translateX(5px);
        }
        
        .file-icon {
            font-size: 2.5rem;
            margin-right: 20px;
            min-width: 50px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .original-name {
            color: #666;
            font-size: 0.9rem;
            font-style: italic;
        }
        
        .file-meta {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .delete-btn {
            color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }
        
        .delete-btn:hover {
            background: rgba(231, 76, 60, 0.2);
        }
        
        .progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            transform: translateX(150%);
            transition: transform 0.4s ease;
            z-index: 1000;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            border-left: 4px solid #2ecc71;
        }
        
        .notification.error {
            border-left: 4px solid #e74c3c;
        }
        
        .notification.warning {
            border-left: 4px solid #f39c12;
        }
        
        .notification-icon {
            font-size: 1.5rem;
        }
        
        .notification.success .notification-icon {
            color: #2ecc71;
        }
        
        .notification.error .notification-icon {
            color: #e74c3c;
        }
        
        .notification.warning .notification-icon {
            color: #f39c12;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        
        .loading.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .stats {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
            min-width: 150px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .header-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .upload-area {
                padding: 30px 20px;
            }
            
            .file-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .file-actions {
                width: 100%;
                justify-content: center;
            }
            
            .stats {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-robot"></i> AI Bot Knowledge Base</h1>
            <p>Upload files to enhance your AI assistant's knowledge. The bot will use these files to provide better answers to user questions.</p>
            
            <div class="header-info">
                <div class="user-info">
                    Logged in as: <strong><?php echo htmlspecialchars($user[0]['name'] ?? 'Super Admin'); ?></strong> 
                    | Role: <strong><?php echo htmlspecialchars($user[0]['role'] ?? 'super'); ?></strong>
                </div>
                <a href="?logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo count($uploadedFiles); ?></div>
                <div class="stat-label">Files Uploaded</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php 
                    $totalSize = array_sum(array_column($uploadedFiles, 'size'));
                    echo formatFileSize($totalSize);
                ?></div>
                <div class="stat-label">Total Size</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">
                    <?php 
                    if (file_exists($storageFile)) {
                        echo formatFileSize(filesize($storageFile));
                    } else {
                        echo '0 B';
                    }
                    ?>
                </div>
                <div class="stat-label">Knowledge Base Size</div>
            </div>
        </div>
        
        <div class="upload-area" id="uploadArea">
            <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h3>Drag & Drop Files Here</h3>
            <p>Upload documentation files that the AI assistant can use to answer questions.</p>
            
            <div class="file-types">
                <strong>Supported formats:</strong> PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, JSON, XML, Images (JPG, PNG, GIF), Archives (ZIP, RAR)
                <br>
                <strong>Max file size:</strong> 10MB per file
            </div>
            
            <button class="btn" id="browseBtn">
                <i class="fas fa-folder-open"></i> Browse Files
            </button>
            <input type="file" class="file-input" id="fileInput" multiple>
            <div class="progress-bar" id="progressBar"></div>
        </div>
        
        <div class="files-list">
            <h2><i class="fas fa-folder-open"></i> Uploaded Files</h2>
            
            <div id="filesContainer">
                <?php if (empty($uploadedFiles)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No files uploaded yet</h3>
                        <p>Upload some files to get started! The AI will use these files to answer questions.</p>
                        <p style="margin-top: 10px; font-size: 0.9rem; color: #764ba2;">
                            <i class="fas fa-lightbulb"></i> Tip: Start with a text file containing platform documentation
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($uploadedFiles as $file): ?>
                        <div class="file-item" data-filename="<?php echo htmlspecialchars($file['name']); ?>">
                            <div class="file-icon"><?php echo $file['icon']; ?></div>
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                                <div class="original-name">Original: <?php echo htmlspecialchars($file['name']); ?></div>
                                <div class="file-meta">
                                    <span><i class="fas fa-file"></i> <?php echo strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)); ?></span>
                                    <span><i class="fas fa-weight-hanging"></i> <?php echo formatFileSize($file['size']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo $file['date']; ?></span>
                                    <span><i class="fas fa-info-circle"></i> <?php echo $file['type']; ?></span>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="action-btn delete-btn" onclick="deleteFile('<?php echo htmlspecialchars($file['name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>
    
    <div class="notification" id="notification">
        <div class="notification-icon"></div>
        <div class="notification-content"></div>
    </div>
    
    <script>
        // DOM Elements
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const browseBtn = document.getElementById('browseBtn');
        const progressBar = document.getElementById('progressBar');
        const filesContainer = document.getElementById('filesContainer');
        const loading = document.getElementById('loading');
        const notification = document.getElementById('notification');
        
        // Event Listeners
        browseBtn.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadArea.classList.add('dragover');
        }
        
        function unhighlight() {
            uploadArea.classList.remove('dragover');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        // File handling functions
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        function handleFileSelect(e) {
            const files = e.target.files;
            handleFiles(files);
        }
        
        function handleFiles(files) {
            if (files.length === 0) return;
            
            // Validate files before upload
            const validFiles = [];
            const invalidFiles = [];
            
            Array.from(files).forEach(file => {
                // Check file size (10MB limit)
                if (file.size > 10 * 1024 * 1024) {
                    invalidFiles.push(`${file.name} - Exceeds 10MB limit`);
                } else {
                    validFiles.push(file);
                }
            });
            
            // Show warnings for invalid files
            if (invalidFiles.length > 0) {
                showNotification(invalidFiles.join(', ') + ' - File too large', 'warning');
            }
            
            if (validFiles.length === 0) {
                if (invalidFiles.length > 0) {
                    showNotification('No valid files to upload. All files exceed size limit.', 'error');
                }
                return;
            }
            
            // Reset file input
            fileInput.value = '';
            
            // Upload each valid file
            validFiles.forEach(file => {
                uploadFile(file);
            });
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('loadstart', () => {
                loading.classList.add('show');
            });
            
            xhr.addEventListener('loadend', () => {
                loading.classList.remove('show');
                progressBar.style.width = '0%';
            });
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            showNotification(response.message || 'File uploaded successfully!', 'success');
                            addFileToList(response.file);
                            
                            // Update stats
                            updateStats();
                        } else {
                            showNotification(response.message || 'Upload failed!', 'error');
                        }
                    } catch (e) {
                        showNotification('Error processing response!', 'error');
                        console.error('Upload error:', e);
                    }
                }
            };
            
            xhr.open('POST', window.location.href, true);
            xhr.send(formData);
        }
        
        function deleteFile(filename) {
            if (!confirm('Are you sure you want to delete this file?\n\nThis will remove it from the AI knowledge base.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('filename', filename);
            
            loading.classList.add('show');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'File deleted successfully!', 'success');
                    // Remove file from list
                    const fileElement = document.querySelector(`.file-item[data-filename="${filename}"]`);
                    if (fileElement) {
                        fileElement.style.opacity = '0';
                        fileElement.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            fileElement.remove();
                            // Show empty state if no files left
                            if (filesContainer.children.length === 0) {
                                filesContainer.innerHTML = `
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <h3>No files uploaded yet</h3>
                                        <p>Upload some files to get started! The AI will use these files to answer questions.</p>
                                        <p style="margin-top: 10px; font-size: 0.9rem; color: #764ba2;">
                                            <i class="fas fa-lightbulb"></i> Tip: Start with a text file containing platform documentation
                                        </p>
                                    </div>
                                `;
                            }
                            // Update stats
                            updateStats();
                        }, 300);
                    }
                } else {
                    showNotification(data.message || 'Delete failed!', 'error');
                }
            })
            .catch(error => {
                showNotification('Error deleting file!', 'error');
                console.error('Delete error:', error);
            })
            .finally(() => {
                loading.classList.remove('show');
            });
        }
        
        function addFileToList(fileData) {
            // Remove empty state if present
            const emptyState = filesContainer.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.setAttribute('data-filename', fileData.name);
            fileItem.innerHTML = `
                <div class="file-icon">${fileData.icon}</div>
                <div class="file-info">
                    <div class="file-name">${fileData.name}</div>
                    <div class="original-name">Original: ${fileData.original}</div>
                    <div class="file-meta">
                        <span><i class="fas fa-file"></i> ${fileData.name.split('.').pop().toUpperCase()}</span>
                        <span><i class="fas fa-weight-hanging"></i> ${fileData.size}</span>
                        <span><i class="fas fa-calendar"></i> ${fileData.date}</span>
                        <span><i class="fas fa-info-circle"></i> ${fileData.type}</span>
                    </div>
                </div>
                <div class="file-actions">
                    <button class="action-btn delete-btn" onclick="deleteFile('${fileData.name}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            // Add animation
            fileItem.style.opacity = '0';
            fileItem.style.transform = 'translateY(20px)';
            filesContainer.prepend(fileItem);
            
            setTimeout(() => {
                fileItem.style.opacity = '1';
                fileItem.style.transform = 'translateY(0)';
            }, 10);
        }
        
        function showNotification(message, type = 'success') {
            const icon = type === 'success' ? 
                '<i class="fas fa-check-circle"></i>' : 
                type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' :
                '<i class="fas fa-exclamation-circle"></i>';
            
            notification.className = `notification ${type} show`;
            notification.querySelector('.notification-icon').innerHTML = icon;
            notification.querySelector('.notification-content').textContent = message;
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }
        
        function updateStats() {
            // This would need a server call to get updated stats
            // For simplicity, we'll reload the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        // Show welcome notification
        window.addEventListener('load', () => {
            showNotification('Welcome, Super Admin! Ready to upload files for the AI bot.', 'success');
        });
    </script>
</body>
</html>