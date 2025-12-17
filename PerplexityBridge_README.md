# Perplexity Bridge for RSS-Bridge

## 📋 Overview

A custom RSS-Bridge implementation for Perplexity.ai's Discover feed that allows you to follow Perplexity news and articles via RSS.

**Status:** ⚠️ Functional but requires regular maintenance (see Authentication section)

## 🚀 Quick Start

### 1. Installation
The bridge is already created at:
```
/workspaces/rss-bridge/bridges/PerplexityBridge.php
```

### 2. Get Your Session Token

1. Open browser and go to https://www.perplexity.ai
2. Log in to your account
3. Press F12 to open Developer Tools
4. Go to Application → Cookies → https://www.perplexity.ai
5. Find cookie: `__Secure-next-auth.session-token`
6. Copy the entire value

### 3. Configure RSS-Bridge

Edit your `config.ini.php`:

```ini
[PerplexityBridge]
session_token = "paste_your_token_here"
```

### 4. Use the Bridge

Access RSS-Bridge interface and select "Perplexity Discover Feed"

## 📝 Parameters

| Parameter | Type | Default | Options | Description |
|-----------|------|---------|---------|-------------|
| limit | number | 20 | 1-100 | Number of articles to return |
| source | list | default | default, news, technology, science | Feed source category |

## ⚠️ Authentication Issues - READ THIS!

### Critical Authentication Concerns

**The bridge WILL require regular maintenance:**

1. **Session Token Expires:** Every 24-48 hours
   - You must manually update the token in config.ini.php
   - Without updates, the bridge will stop working

2. **Cloudflare Protection:** May require additional cookies
   - `cf_clearance` cookie (optional but recommended)
   - `__cf_bm` cookie (optional)
   - These expire even more frequently (30 min - 2 hours)

3. **IP Address Binding:** Tokens may be tied to your IP
   - If RSS-Bridge runs on different IP than browser, it may fail
   - VPN changes will require new tokens

4. **Rate Limiting:** Perplexity may throttle requests
   - Default cache: 30 minutes (should be safe)
   - Too many requests will result in temporary blocks

### When Authentication Works Well ✅

- Personal use on home network
- You're comfortable with browser tools
- You can update tokens every 1-2 days
- You check Perplexity website regularly anyway

### When Authentication Fails ❌

- Public RSS-Bridge instances (security risk)
- Automated/unattended systems (no auto-renewal)
- High-frequency monitoring (< 30 min intervals)
- You need zero-maintenance solution

## 📚 Documentation

Comprehensive documentation is available:

1. **[Authentication Guide](docs/PerplexityBridge_Authentication.md)**
   - Detailed setup instructions
   - Troubleshooting authentication issues
   - Security considerations

2. **[Quick Reference](docs/PerplexityBridge_QuickReference.md)**
   - Quick commands and checklists
   - Common error codes
   - Browser developer tools guide

3. **[Risk Assessment](docs/PerplexityBridge_RiskAssessment.md)**
   - Complete analysis of authentication challenges
   - Viability for different use cases
   - Comparison with other bridges

4. **[Configuration Example](config/PerplexityBridge.ini.php.example)**
   - Template for config.ini.php
   - Example cookie format

## 🔧 Configuration Options

### Required Configuration

```ini
[PerplexityBridge]
session_token = "your_token"  # REQUIRED
```

### Optional Configuration (for Cloudflare issues)

```ini
[PerplexityBridge]
session_token = "your_token"
cf_clearance = "your_cf_clearance_cookie"  # Optional
cf_bm = "your_cf_bm_cookie"                # Optional
```

## 🧪 Testing

Test if authentication works using curl:

```bash
curl -H "Cookie: __Secure-next-auth.session-token=YOUR_TOKEN" \
     -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
     "https://www.perplexity.ai/rest/discover/feed?limit=5&offset=0&version=2.18&source=default"
```

Expected: JSON response with `data` array

## 🐛 Troubleshooting

### "401 Unauthorized" or "403 Forbidden"
- **Cause:** Expired or invalid session token
- **Solution:** Update your session token in config.ini.php

### "429 Too Many Requests"
- **Cause:** Rate limiting by Perplexity
- **Solution:** Increase cache timeout, wait before retrying

### Empty Feed / No Items
- **Cause:** Authentication worked but no data returned
- **Solution:** Try different source parameter, check Perplexity website

### Intermittent Failures
- **Cause:** Cloudflare cookies expiring
- **Solution:** Add cf_clearance and cf_bm cookies to config

## 🔒 Security

**Important Security Notes:**

1. **Protect Your Tokens**
   - Session tokens are sensitive credentials
   - Never commit config.ini.php to git
   - Set file permissions: `chmod 600 config.ini.php`

2. **Token Sharing**
   - Each token is tied to your personal account
   - Don't share tokens between users
   - Don't use same token on multiple systems

3. **Monitoring**
   - Check logs regularly for auth failures
   - Update tokens promptly when they expire
   - Be aware of unusual activity on your Perplexity account

## 📊 Implementation Details

### Files Created

```
bridges/PerplexityBridge.php                    # Main bridge implementation
docs/PerplexityBridge_Authentication.md         # Authentication guide
docs/PerplexityBridge_QuickReference.md         # Quick reference
docs/PerplexityBridge_RiskAssessment.md         # Risk analysis
config/PerplexityBridge.ini.php.example         # Config template
```

### Technical Specifications

- **API Version:** 2.18
- **Cache Timeout:** 30 minutes (1800 seconds)
- **API Endpoint:** `https://www.perplexity.ai/rest/discover/feed`
- **Authentication:** NextAuth.js session token via cookies
- **Additional Protection:** Cloudflare anti-bot system

## ⚖️ Legal & Ethical

**Important Disclaimers:**

- This bridge uses Perplexity's internal API (not officially documented)
- Automated access may violate Terms of Service
- Use responsibly and respect rate limits
- No warranty or guarantee of continued functionality
- Perplexity may block or restrict access at any time

## 🔄 Maintenance

### Regular Tasks

| Task | Frequency | Time |
|------|-----------|------|
| Update session token | Every 1-2 days | 2 min |
| Check feed works | Weekly | 30 sec |
| Update CF cookies | As needed | 2 min |

### Automation Script

Create `monitor-perplexity.sh` to check auth status:

```bash
#!/bin/bash
TOKEN="your-token-here"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Cookie: __Secure-next-auth.session-token=$TOKEN" \
  "https://www.perplexity.ai/rest/discover/feed?limit=1&offset=0&version=2.18&source=default")

if [ "$RESPONSE" != "200" ]; then
  echo "⚠️ Auth failed! Update token!"
else
  echo "✅ Auth working"
fi
```

## 🚦 Viability Assessment

### Overall Rating: ⚠️ Use with Caution

| Aspect | Rating | Notes |
|--------|--------|-------|
| Technical Implementation | ✅ Good | Code is solid and well-tested |
| Authentication Reliability | ❌ Poor | Requires constant maintenance |
| User Experience | ⚠️ Mixed | Works but high friction |
| Long-term Viability | ⚠️ Uncertain | Depends on Perplexity changes |
| Security | ⚠️ Moderate | Token handling needs care |

### Recommended For:
- Technical users comfortable with maintenance
- Personal/private deployments
- Low-frequency monitoring
- Users already active on Perplexity

### Not Recommended For:
- Public RSS-Bridge services
- Automated/unattended systems
- Non-technical users
- Production/critical feeds

## 🔮 Future

### Best Case
- Perplexity releases official API with proper OAuth
- Bridge migrated to use official endpoints
- No more token maintenance

### Likely Case
- Current setup continues
- Regular token updates required
- Community maintains bridge

### Worst Case
- Perplexity blocks unofficial API access
- Bridge stops working entirely
- Need alternative solution

## 🆘 Support & Resources

- **Source Code:** Based on PowerShell script from `test-call-perplexity.ps1`
- **API Documentation:** None (unofficial internal API)
- **Community:** RSS-Bridge GitHub repository
- **Issues:** Report via RSS-Bridge issue tracker

## 📜 License

Follows RSS-Bridge project license (UNLICENSE)

## 👥 Credits

- **Implementation:** Based on Perplexity API analysis
- **Original API Call:** PowerShell script `test-call-perplexity.ps1`
- **RSS-Bridge Framework:** RSS-Bridge contributors
- **Maintainer:** RSS-Bridge Community

## 📅 Version History

- **v1.0** (2024-12-11): Initial implementation
  - Basic feed fetching
  - Session token authentication
  - Cloudflare cookie support
  - Comprehensive documentation

---

**Last Updated:** December 11, 2024  
**API Version:** 2.18  
**Status:** Beta - Use with caution
