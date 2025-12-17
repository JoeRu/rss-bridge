<?php
/**
 * Perplexity Cookie Update API
 * 
 * A simple REST API and web interface for updating Perplexity cookies
 * 
 * Security: Uses a secret token to prevent unauthorized updates
 * Access: /update-perplexity.php (shows form) or POST to update
 */

// Configuration
$CONFIG_FILE = __DIR__ . '/config.ini.php';
$CONFIG_DIR_FILE = __DIR__ . '/config/config.ini.php';
$BACKUP_DIR = __DIR__ . '/config/backups';
$SECRET_TOKEN = getenv('PERPLEXITY_UPDATE_TOKEN') ?: 'change-me-in-production';

// Detect which config file to use
$configFile = file_exists($CONFIG_DIR_FILE) ? $CONFIG_DIR_FILE : $CONFIG_FILE;

// Handle POST request (API update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Verify token
    $providedToken = $_POST['token'] ?? $_SERVER['HTTP_X_UPDATE_TOKEN'] ?? '';
    if ($providedToken !== $SECRET_TOKEN) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid token', 'success' => false]);
        exit;
    }
    
    // Get cookies
    $sessionToken = $_POST['session_token'] ?? '';
    $cfClearance = $_POST['cf_clearance'] ?? '';
    $cfBm = $_POST['cf_bm'] ?? '';
    
    // Validate session token
    if (empty($sessionToken)) {
        http_response_code(400);
        echo json_encode(['error' => 'session_token is required', 'success' => false]);
        exit;
    }
    
    // Create backup
    if (!is_dir($BACKUP_DIR)) {
        mkdir($BACKUP_DIR, 0700, true);
    }
    
    if (file_exists($configFile)) {
        $backupFile = $BACKUP_DIR . '/config.' . date('Ymd_His') . '.ini.php';
        copy($configFile, $backupFile);
    }
    
    // Read existing config
    $configContent = file_exists($configFile) ? file_get_contents($configFile) : "; <?php exit; ?> DO NOT REMOVE THIS LINE\n";
    
    // Remove existing PerplexityBridge section
    $configContent = preg_replace(
        '/;\s*---\s*PerplexityBridge.*?\[PerplexityBridge\].*?(?=\n\[|\n;\s*---|$)/s',
        '',
        $configContent
    );
    
    // Add new section
    $timestamp = date('Y-m-d H:i:s T');
    $newSection = "\n\n; --- PerplexityBridge Configuration ---\n";
    $newSection .= "; Updated: $timestamp\n";
    $newSection .= "; Updated via: Web Interface\n\n";
    $newSection .= "[PerplexityBridge]\n\n";
    $newSection .= "; REQUIRED: Session token\n";
    $newSection .= "session_token = \"$sessionToken\"\n\n";
    $newSection .= "; OPTIONAL: Cloudflare cookies\n";
    $newSection .= "cf_clearance = \"$cfClearance\"\n";
    $newSection .= "cf_bm = \"$cfBm\"\n";
    
    $configContent = rtrim($configContent) . $newSection;
    
    // Write config
    if (file_put_contents($configFile, $configContent)) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuration updated successfully',
            'updated_at' => $timestamp,
            'backup_created' => isset($backupFile) ? basename($backupFile) : null
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write config file', 'success' => false]);
    }
    exit;
}

// Show HTML form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Perplexity Cookies</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .optional {
            color: #999;
            font-weight: normal;
            font-size: 12px;
        }
        
        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        button {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .help-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .help-section h3 {
            margin-bottom: 10px;
            color: #667eea;
            font-size: 16px;
        }
        
        .help-section ol {
            margin-left: 20px;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .help-section li {
            margin-bottom: 8px;
        }
        
        .bookmarklet-box {
            margin-top: 15px;
            padding: 12px;
            background: white;
            border: 2px dashed #667eea;
            border-radius: 8px;
            font-size: 12px;
        }
        
        .bookmarklet-box a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 25px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
        
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        button.loading .spinner {
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        button.loading {
            pointer-events: none;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Update Perplexity Cookies</h1>
        <p class="subtitle">Keep your RSS feed authentication up to date</p>
        
        <div id="alert" class="alert"></div>
        
        <form id="updateForm">
            <div class="form-group">
                <label for="token">
                    Update Token <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="token" 
                    name="token" 
                    placeholder="Enter your update token"
                    required
                    autocomplete="off"
                >
                <div class="hint">Security token to authorize updates (set in environment)</div>
            </div>
            
            <div class="form-group">
                <label for="session_token">
                    Session Token <span class="required">*</span>
                </label>
                <textarea 
                    id="session_token" 
                    name="session_token" 
                    placeholder="Paste __Secure-next-auth.session-token value here (starts with eyJ...)"
                    required
                ></textarea>
                <div class="hint">Cookie: __Secure-next-auth.session-token</div>
            </div>
            
            <div class="form-group">
                <label for="cf_clearance">
                    Cloudflare Clearance <span class="optional">(optional)</span>
                </label>
                <textarea 
                    id="cf_clearance" 
                    name="cf_clearance" 
                    placeholder="Paste cf_clearance value here (if needed)"
                ></textarea>
                <div class="hint">Cookie: cf_clearance</div>
            </div>
            
            <div class="form-group">
                <label for="cf_bm">
                    Cloudflare BM <span class="optional">(optional)</span>
                </label>
                <input 
                    type="text" 
                    id="cf_bm" 
                    name="cf_bm" 
                    placeholder="Paste __cf_bm value here (if needed)"
                >
                <div class="hint">Cookie: __cf_bm</div>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn-primary">
                    Update Configuration
                    <div class="spinner"></div>
                </button>
                <button type="button" class="btn-secondary" onclick="window.location.href='/'">
                    Back to RSS-Bridge
                </button>
            </div>
        </form>
        
        <div class="help-section">
            <h3>📖 How to get cookies:</h3>
            <ol>
                <li>Open <a href="https://www.perplexity.ai" target="_blank">perplexity.ai</a> and log in</li>
                <li>Press <strong>F12</strong> to open Developer Tools</li>
                <li>Go to <strong>Application</strong> → <strong>Cookies</strong> → <strong>https://www.perplexity.ai</strong></li>
                <li>Find <code>__Secure-next-auth.session-token</code> and copy its value</li>
                <li>Optionally copy <code>cf_clearance</code> and <code>__cf_bm</code></li>
                <li>Paste here and click Update</li>
            </ol>
            
            <div class="bookmarklet-box">
                💡 <strong>Pro Tip:</strong> Use the <a href="javascript:alert('Install the bookmarklet from tools/extract-cookies-bookmarklet.js')">cookie extractor bookmarklet</a> for one-click extraction!
            </div>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('updateForm');
        const alertBox = document.getElementById('alert');
        
        // Load token from localStorage if available
        const savedToken = localStorage.getItem('perplexity_update_token');
        if (savedToken) {
            document.getElementById('token').value = savedToken;
        }
        
        function showAlert(message, type) {
            alertBox.className = 'alert ' + type;
            alertBox.textContent = message;
            alertBox.style.display = 'block';
            
            // Auto-hide after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    alertBox.style.display = 'none';
                }, 5000);
            }
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');
            
            const formData = new FormData(form);
            
            // Save token to localStorage for convenience
            localStorage.setItem('perplexity_update_token', formData.get('token'));
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('✅ Configuration updated successfully! Restart RSS-Bridge to apply changes.', 'success');
                    
                    // Clear sensitive fields
                    document.getElementById('session_token').value = '';
                    document.getElementById('cf_clearance').value = '';
                    document.getElementById('cf_bm').value = '';
                } else {
                    showAlert('❌ Error: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                showAlert('❌ Network error: ' + error.message, 'error');
            } finally {
                submitBtn.classList.remove('loading');
            }
        });
        
        // Show info message on load
        window.addEventListener('load', () => {
            showAlert('ℹ️ This page allows you to update Perplexity authentication cookies. Tokens typically expire every 24-48 hours.', 'info');
        });
    </script>
</body>
</html>
