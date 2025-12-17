/**
 * Perplexity Cookie Extractor & Updater - Bookmarklet
 * 
 * VERSION 2.0 - Now with direct API update!
 * 
 * Features:
 * - Extract cookies from current page
 * - Send directly to RSS-Bridge update API
 * - One-click update (no manual pasting needed!)
 * - Fallback to clipboard copy if API fails
 * 
 * Installation:
 * 1. Create a new bookmark in your browser
 * 2. Name it: "Update Perplexity (One-Click)"
 * 3. Copy the MINIFIED version below as the URL
 * 4. Visit perplexity.ai and click the bookmark
 * 5. Enter your update token (first time only - saved in localStorage)
 * 6. Done!
 * 
 * Configuration:
 * - Update API_URL below to match your RSS-Bridge URL
 * - Get your update token from RSS-Bridge setup
 * 
 * MINIFIED VERSION (copy everything on one line as bookmark URL):
 */

// javascript:(function(){const API_URL='http://localhost:3000/update-perplexity.php';const cookies=document.cookie.split(';').reduce((acc,cookie)=>{const[name,value]=cookie.trim().split('=');if(name.includes('session-token')||name==='cf_clearance'||name==='__cf_bm'){acc[name]=value;}return acc;},{});const sessionToken=cookies['__Secure-next-auth.session-token'];if(!sessionToken){alert('❌ Session token not found!\n\nMake sure you are:\n1. Logged in to Perplexity\n2. On perplexity.ai domain');return;}let updateToken=localStorage.getItem('perplexity_api_token');if(!updateToken){updateToken=prompt('Enter your RSS-Bridge update token:\n\n(This will be saved for future use)');if(!updateToken){return;}localStorage.setItem('perplexity_api_token',updateToken);}const formData=new FormData();formData.append('token',updateToken);formData.append('session_token',sessionToken);formData.append('cf_clearance',cookies['cf_clearance']||'');formData.append('cf_bm',cookies['__cf_bm']||'');const statusDiv=document.createElement('div');statusDiv.style.cssText='position:fixed;top:20px;right:20px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:20px 30px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.3);z-index:999999;font-family:system-ui,-apple-system,sans-serif;font-size:16px;max-width:400px;';statusDiv.innerHTML='<div style="display:flex;align-items:center;gap:10px;"><div style="width:20px;height:20px;border:3px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 1s linear infinite;"></div><div>Updating cookies...</div></div><style>@keyframes spin{to{transform:rotate(360deg);}}</style>';document.body.appendChild(statusDiv);fetch(API_URL,{method:'POST',body:formData}).then(res=>res.json()).then(data=>{if(data.success){statusDiv.style.background='linear-gradient(135deg,#11998e,#38ef7d)';statusDiv.innerHTML='<div style="font-size:24px;margin-bottom:10px;">✅</div><div style="font-weight:600;margin-bottom:8px;">Update Successful!</div><div style="font-size:14px;opacity:0.9;">Configuration updated at:<br>'+data.updated_at+'</div><div style="margin-top:12px;font-size:12px;opacity:0.8;">Restart RSS-Bridge to apply changes</div>';setTimeout(()=>statusDiv.remove(),5000);}else{throw new Error(data.error||'Unknown error');}}).catch(err=>{statusDiv.style.background='linear-gradient(135deg,#eb3349,#f45c43)';statusDiv.innerHTML='<div style="font-size:24px;margin-bottom:10px;">❌</div><div style="font-weight:600;margin-bottom:8px;">Update Failed</div><div style="font-size:14px;opacity:0.9;">'+err.message+'</div><div style="margin-top:12px;font-size:12px;opacity:0.8;">Copying to clipboard as fallback...</div>';const config=`[PerplexityBridge]\nsession_token = "${sessionToken}"\ncf_clearance = "${cookies['cf_clearance']||''}"\ncf_bm = "${cookies['__cf_bm']||''}"\n\n; Updated: ${new Date().toISOString()}`;navigator.clipboard.writeText(config).then(()=>{statusDiv.innerHTML+='<div style="margin-top:8px;color:#90EE90;">📋 Copied to clipboard!</div>';});setTimeout(()=>statusDiv.remove(),7000);});})();

/**
 * READABLE VERSION (for understanding/modification):
 */
(function() {
    // Extract relevant cookies
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [name, value] = cookie.trim().split('=');
        if (name.includes('session-token') || name === 'cf_clearance' || name === '__cf_bm') {
            acc[name] = value;
        }
        return acc;
    }, {});

    // Format as config.ini.php section
    const config = `[PerplexityBridge]
session_token = "${cookies['__Secure-next-auth.session-token'] || 'NOT_FOUND'}"
cf_clearance = "${cookies['cf_clearance'] || ''}"
cf_bm = "${cookies['__cf_bm'] || ''}"

; Updated: ${new Date().toISOString()}`;

    // Copy to clipboard
    navigator.clipboard.writeText(config).then(() => {
        alert('✅ Cookies copied to clipboard!\n\nPaste into config.ini.php\n\nToken expires in ~24-48 hours.');
    }).catch(() => {
        // Fallback: show in prompt
        prompt('Copy this configuration:', config);
    });
})();
