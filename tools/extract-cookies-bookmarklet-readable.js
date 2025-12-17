/**
 * Perplexity Cookie Auto-Updater - Enhanced Bookmarklet
 * 
 * This is the READABLE version for understanding and customization.
 * The minified version is in extract-cookies-bookmarklet.js
 */

(function() {
    // ============================================
    // CONFIGURATION - Change these values
    // ============================================
    
    // Your RSS-Bridge URL (change if using different host/port)
    const API_URL = 'http://localhost:3000/update-perplexity.php';
    
    // Optional: Hardcode your token here if you don't want to enter it each time
    // Leave empty to use localStorage
    const HARDCODED_TOKEN = '';
    
    // ============================================
    // COOKIE EXTRACTION
    // ============================================
    
    // Extract cookies from current page
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [name, value] = cookie.trim().split('=');
        if (name.includes('session-token') || name === 'cf_clearance' || name === '__cf_bm') {
            acc[name] = value;
        }
        return acc;
    }, {});
    
    const sessionToken = cookies['__Secure-next-auth.session-token'];
    
    // Validate we're on the right page and have the cookie
    if (!sessionToken) {
        alert(
            '❌ Session token not found!\n\n' +
            'Make sure you are:\n' +
            '1. Logged in to Perplexity\n' +
            '2. On perplexity.ai domain'
        );
        return;
    }
    
    // ============================================
    // TOKEN MANAGEMENT
    // ============================================
    
    let updateToken = HARDCODED_TOKEN;
    
    if (!updateToken) {
        // Try to get from localStorage
        updateToken = localStorage.getItem('perplexity_api_token');
        
        if (!updateToken) {
            // Prompt user for token (first time only)
            updateToken = prompt(
                'Enter your RSS-Bridge update token:\n\n' +
                '(This will be saved for future use)'
            );
            
            if (!updateToken) {
                return; // User cancelled
            }
            
            // Save for next time
            localStorage.setItem('perplexity_api_token', updateToken);
        }
    }
    
    // ============================================
    // PREPARE API REQUEST
    // ============================================
    
    const formData = new FormData();
    formData.append('token', updateToken);
    formData.append('session_token', sessionToken);
    formData.append('cf_clearance', cookies['cf_clearance'] || '');
    formData.append('cf_bm', cookies['__cf_bm'] || '');
    
    // ============================================
    // UI FEEDBACK
    // ============================================
    
    // Create status notification
    const statusDiv = document.createElement('div');
    statusDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px 30px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        z-index: 999999;
        font-family: system-ui, -apple-system, sans-serif;
        font-size: 16px;
        max-width: 400px;
        animation: slideIn 0.3s ease-out;
    `;
    
    statusDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="spinner"></div>
            <div>Updating cookies...</div>
        </div>
        <style>
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            .spinner {
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255,255,255,0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
        </style>
    `;
    
    document.body.appendChild(statusDiv);
    
    // ============================================
    // API CALL
    // ============================================
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Success!
            statusDiv.style.background = 'linear-gradient(135deg, #11998e, #38ef7d)';
            statusDiv.innerHTML = `
                <div style="font-size: 24px; margin-bottom: 10px;">✅</div>
                <div style="font-weight: 600; margin-bottom: 8px;">Update Successful!</div>
                <div style="font-size: 14px; opacity: 0.9;">
                    Configuration updated at:<br>
                    ${data.updated_at}
                </div>
                <div style="margin-top: 12px; font-size: 12px; opacity: 0.8;">
                    Restart RSS-Bridge to apply changes
                </div>
            `;
            
            // Auto-remove after 5 seconds
            setTimeout(() => statusDiv.remove(), 5000);
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    })
    .catch(err => {
        // Error - show message and fallback to clipboard
        statusDiv.style.background = 'linear-gradient(135deg, #eb3349, #f45c43)';
        statusDiv.innerHTML = `
            <div style="font-size: 24px; margin-bottom: 10px;">❌</div>
            <div style="font-weight: 600; margin-bottom: 8px;">Update Failed</div>
            <div style="font-size: 14px; opacity: 0.9;">${err.message}</div>
            <div style="margin-top: 12px; font-size: 12px; opacity: 0.8;">
                Copying to clipboard as fallback...
            </div>
        `;
        
        // Fallback: Copy to clipboard
        const config = `[PerplexityBridge]
session_token = "${sessionToken}"
cf_clearance = "${cookies['cf_clearance'] || ''}"
cf_bm = "${cookies['__cf_bm'] || ''}"

; Updated: ${new Date().toISOString()}`;
        
        navigator.clipboard.writeText(config).then(() => {
            statusDiv.innerHTML += `
                <div style="margin-top: 8px; color: #90EE90;">
                    📋 Copied to clipboard!
                </div>
            `;
        });
        
        // Auto-remove after 7 seconds
        setTimeout(() => statusDiv.remove(), 7000);
    });
})();
