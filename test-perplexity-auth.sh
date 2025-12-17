#!/bin/bash
#
# Perplexity Bridge Authentication Tester
# This script helps verify that your authentication credentials work
# before configuring RSS-Bridge
#
# Usage: ./test-perplexity-auth.sh "your-session-token-here"
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
API_BASE="https://www.perplexity.ai"
API_ENDPOINT="/rest/discover/feed"
API_VERSION="2.18"
USER_AGENT="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36"

# Function to print colored output
print_info() {
    echo -e "${BLUE}ℹ ${1}${NC}"
}

print_success() {
    echo -e "${GREEN}✅ ${1}${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  ${1}${NC}"
}

print_error() {
    echo -e "${RED}❌ ${1}${NC}"
}

print_header() {
    echo ""
    echo "=========================================="
    echo "  Perplexity Bridge Authentication Test"
    echo "=========================================="
    echo ""
}

# Check if token is provided
if [ $# -eq 0 ]; then
    print_header
    print_error "No session token provided!"
    echo ""
    echo "Usage: $0 \"your-session-token-here\""
    echo ""
    echo "To get your session token:"
    echo "  1. Open https://www.perplexity.ai in your browser"
    echo "  2. Log in to your account"
    echo "  3. Press F12 to open Developer Tools"
    echo "  4. Go to Application → Cookies → https://www.perplexity.ai"
    echo "  5. Find cookie: __Secure-next-auth.session-token"
    echo "  6. Copy the entire value"
    echo ""
    exit 1
fi

SESSION_TOKEN="$1"
CF_CLEARANCE="${2:-}"
CF_BM="${3:-}"

print_header

# Validate token format (basic check)
print_info "Validating token format..."
if [[ ! "$SESSION_TOKEN" =~ ^eyJ ]]; then
    print_warning "Token doesn't start with 'eyJ' - might not be a valid JWT/JWE"
    print_warning "Make sure you copied the entire token value"
fi

# Build cookie header
COOKIES="__Secure-next-auth.session-token=${SESSION_TOKEN}"
if [ -n "$CF_CLEARANCE" ]; then
    COOKIES="${COOKIES}; cf_clearance=${CF_CLEARANCE}"
    print_info "Cloudflare clearance cookie included"
fi
if [ -n "$CF_BM" ]; then
    COOKIES="${COOKIES}; __cf_bm=${CF_BM}"
    print_info "Cloudflare BM cookie included"
fi

# Test 1: Basic connectivity
print_info "Test 1: Testing basic connectivity..."
if ! curl -s -o /dev/null -w '' --max-time 10 "${API_BASE}" 2>/dev/null; then
    print_error "Cannot connect to Perplexity.ai"
    print_error "Check your internet connection"
    exit 1
fi
print_success "Basic connectivity OK"

# Test 2: API endpoint with authentication
print_info "Test 2: Testing API authentication..."

URL="${API_BASE}${API_ENDPOINT}?limit=5&offset=0&version=${API_VERSION}&source=default"

# Make request and capture response
RESPONSE=$(curl -s --compressed -w "\n%{http_code}" \
    -H "Cookie: ${COOKIES}" \
    -H "User-Agent: ${USER_AGENT}" \
    -H "Accept: */*" \
    -H "Accept-Language: en-US,en;q=0.9" \
    -H "Accept-Encoding: gzip, deflate, br" \
    -H "Referer: https://www.perplexity.ai/discover" \
    -H "x-app-apiclient: default" \
    -H "x-app-apiversion: ${API_VERSION}" \
    "${URL}" 2>&1)

# Split response body and status code
HTTP_BODY=$(echo "$RESPONSE" | head -n -1)
HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)

echo ""
print_info "HTTP Status Code: ${HTTP_CODE}"

# Analyze response
case "$HTTP_CODE" in
    200)
        print_success "Authentication successful!"
        
        # Validate JSON response
        if echo "$HTTP_BODY" | jq -e . >/dev/null 2>&1; then
            print_success "Valid JSON response received"
            
            # Check for data field
            if echo "$HTTP_BODY" | jq -e '.data' >/dev/null 2>&1; then
                ITEM_COUNT=$(echo "$HTTP_BODY" | jq '.data | length')
                print_success "Found ${ITEM_COUNT} feed items"
                
                # Show first item title if available
                FIRST_TITLE=$(echo "$HTTP_BODY" | jq -r '.data[0].title // "N/A"' 2>/dev/null)
                if [ "$FIRST_TITLE" != "N/A" ]; then
                    echo ""
                    print_info "First article: ${FIRST_TITLE}"
                fi
                
                echo ""
                print_success "🎉 All checks passed! Your authentication is working."
                echo ""
                echo "You can now configure RSS-Bridge with this token:"
                echo ""
                echo "[PerplexityBridge]"
                echo "session_token = \"${SESSION_TOKEN:0:20}...\""
                echo ""
                
            else
                print_warning "Response is valid JSON but missing 'data' field"
                print_warning "API format may have changed"
                echo ""
                echo "Response preview:"
                echo "$HTTP_BODY" | jq '.' 2>/dev/null | head -n 20
            fi
        else
            print_warning "Response is not valid JSON"
            echo ""
            echo "Response preview:"
            echo "$HTTP_BODY" | head -n 20
        fi
        ;;
        
    401)
        print_error "Authentication failed (401 Unauthorized)"
        echo ""
        echo "Possible causes:"
        echo "  • Session token is expired"
        echo "  • Session token is invalid"
        echo "  • Token was not copied correctly"
        echo ""
        echo "Solution:"
        echo "  1. Go to https://www.perplexity.ai"
        echo "  2. Log out and log back in"
        echo "  3. Get a fresh session token"
        echo "  4. Try again"
        ;;
        
    403)
        print_error "Access forbidden (403 Forbidden)"
        echo ""
        echo "Possible causes:"
        echo "  • Cloudflare is blocking the request"
        echo "  • IP address mismatch (token bound to different IP)"
        echo "  • Missing Cloudflare cookies"
        echo ""
        echo "Solution:"
        echo "  1. Get Cloudflare cookies from browser:"
        echo "     - cf_clearance"
        echo "     - __cf_bm"
        echo "  2. Run script with CF cookies:"
        echo "     $0 \"session_token\" \"cf_clearance\" \"cf_bm\""
        ;;
        
    429)
        print_error "Rate limited (429 Too Many Requests)"
        echo ""
        echo "You have made too many requests too quickly."
        echo ""
        echo "Solution:"
        echo "  • Wait 5-10 minutes before trying again"
        echo "  • When using RSS-Bridge, set cache timeout to 30+ minutes"
        ;;
        
    500|502|503)
        print_error "Server error (${HTTP_CODE})"
        echo ""
        echo "Perplexity's servers are experiencing issues."
        echo ""
        echo "Solution:"
        echo "  • Wait a few minutes and try again"
        echo "  • Check status.perplexity.ai (if exists)"
        ;;
        
    000)
        print_error "Connection failed"
        echo ""
        echo "Could not connect to Perplexity API."
        echo ""
        echo "Possible causes:"
        echo "  • Network connectivity issues"
        echo "  • Firewall blocking requests"
        echo "  • DNS resolution problems"
        ;;
        
    *)
        print_warning "Unexpected HTTP status code: ${HTTP_CODE}"
        echo ""
        echo "Response preview:"
        echo "$HTTP_BODY" | head -n 20
        ;;
esac

echo ""

# Test 3: Token expiration estimate
print_info "Test 3: Estimating token expiration..."

# Try to decode the JWT/JWE (basic check)
TOKEN_PARTS=$(echo "$SESSION_TOKEN" | tr '.' '\n' | wc -l)
if [ "$TOKEN_PARTS" -eq 5 ]; then
    print_info "Token format: JWE (JSON Web Encryption) - 5 parts"
    print_warning "Cannot decode expiration from encrypted token"
    print_info "Typical expiration: 24-48 hours from creation"
else
    print_warning "Unexpected token format"
fi

echo ""
print_info "💡 Remember to update your token every 1-2 days!"
echo ""

exit 0
