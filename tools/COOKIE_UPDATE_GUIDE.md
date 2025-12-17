# Perplexity Cookie Update Guide

## 🚀 Quick Methods (Choose One)

### Method 1: Bookmarklet (Recommended - One Click!)

**Setup (once):**
1. Open [extract-cookies-bookmarklet.js](./extract-cookies-bookmarklet.js)
2. Copy the MINIFIED version (the long line starting with `javascript:`)
3. Create a new bookmark in your browser
4. Name it: **"Extract Perplexity Cookies"**
5. Paste the copied code as the URL

**Usage (every 1-2 days):**
1. Visit https://www.perplexity.ai (must be logged in)
2. Click the **"Extract Perplexity Cookies"** bookmark
3. Cookies are copied to clipboard!
4. Run: `./update-cookies-paste.sh`
5. Paste (Ctrl+V) and press Ctrl+D
6. Restart RSS-Bridge

**Time: ~30 seconds**

---

### Method 2: Interactive Script

**Usage:**
```bash
./update-cookies.sh
```

Follow the prompts to manually enter each cookie.

**Time: ~2 minutes**

---

### Method 3: Command Line (Advanced)

**Usage:**
```bash
./update-cookies.sh \
  "your-session-token" \
  "your-cf-clearance" \
  "your-cf-bm"
```

Or edit directly:
```bash
nano config/config.ini.php
# Find [PerplexityBridge] section
# Update the three values
# Save and exit
```

---

## 📋 Manual Process (Browser DevTools)

If you prefer the traditional way:

1. **Open Browser DevTools**
   - Chrome/Edge: Press `F12` or `Ctrl+Shift+I`
   - Firefox: Press `F12` or `Ctrl+Shift+K`
   - Safari: Enable Developer menu first, then `Cmd+Opt+I`

2. **Navigate to Cookies**
   - Go to **Application** tab (Chrome) or **Storage** tab (Firefox)
   - Expand **Cookies** → `https://www.perplexity.ai`

3. **Copy Cookies**
   - `__Secure-next-auth.session-token` - **Required**
   - `cf_clearance` - Optional but recommended
   - `__cf_bm` - Optional but recommended

4. **Update Config**
   ```ini
   [PerplexityBridge]
   session_token = "paste-token-here"
   cf_clearance = "paste-here"
   cf_bm = "paste-here"
   ```

5. **Restart RSS-Bridge**
   ```bash
   docker restart rss-bridge
   ```

---

## 🔄 Automation Ideas

### Cron Job (Linux/Mac)

Set a reminder to update cookies:
```bash
# Edit crontab
crontab -e

# Add reminder every 2 days at 9am
0 9 */2 * * notify-send "Update Perplexity Cookies" "Run: cd ~/rss-bridge && ./update-cookies.sh"
```

### Windows Task Scheduler

Create a scheduled task to remind you every 2 days.

---

## 🛠 Troubleshooting

### Bookmarklet doesn't work
- Make sure you're on https://www.perplexity.ai
- Check if cookies are blocked
- Try copying the cookie manually
- Check browser console for errors (F12)

### "Session token not found"
- You're not logged in to Perplexity
- Cookies are disabled
- Using private/incognito mode (cookies won't persist)

### Paste script doesn't accept input
- Make sure to press `Ctrl+D` (or `Cmd+D`) after pasting
- Don't press Enter multiple times
- Try the interactive script instead

---

## 📱 Mobile Support

The bookmarklet works on mobile browsers too!

**iOS Safari:**
1. Create a bookmark of any page
2. Edit the bookmark
3. Change the URL to the bookmarklet code
4. Visit Perplexity and tap the bookmark

**Android Chrome:**
Similar process - create bookmark and replace URL.

---

## ⏱ Cookie Lifespan

- **Session Token**: Usually expires in **24-48 hours**
- **Cloudflare cookies**: Can last longer, but refresh recommended

**Best Practice:** Update every **36 hours** or set a reminder.

---

## 🔐 Security Notes

- **Never share cookies** - they're like passwords!
- **Don't commit to git** - config.ini.php should be in .gitignore
- **Use restrictive permissions**: `chmod 600 config.ini.php`
- **Backups are automatic** - stored in `config/backups/`
- **Delete old backups** periodically to avoid accumulation

---

## 💡 Pro Tips

1. **Save the bookmarklet** to your bookmarks bar for quick access
2. **Keep RSS-Bridge running** - no need to restart for cookie updates (depending on cache)
3. **Test immediately** after updating: `bash test-perplexity-auth.sh`
4. **Set a calendar reminder** on your phone
5. **Use a password manager** with secure notes to track update dates

---

## 📊 Update Schedule Recommendations

| Usage Level | Update Frequency | Method |
|-------------|------------------|--------|
| Light (checking 1-2x/day) | Every 48 hours | Bookmarklet |
| Medium (multiple daily checks) | Every 36 hours | Bookmarklet |
| Heavy (automated RSS reader) | Every 24 hours | Automated script |

---

## 🆘 Still Need Help?

1. Check if logged in to Perplexity: https://www.perplexity.ai
2. Test authentication: `./test-perplexity-auth.sh "your-token"`
3. Check RSS-Bridge logs
4. Review [PerplexityBridge_Authentication.md](../docs/PerplexityBridge_Authentication.md)
5. See [PerplexityBridge_Checklist.md](../docs/PerplexityBridge_Checklist.md)
