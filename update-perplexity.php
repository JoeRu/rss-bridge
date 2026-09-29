<?php
/**
 * Perplexity Cookie Update
 *
 * Web form + POST API that stores the Perplexity cookies (and the browser's
 * User-Agent, which Cloudflare binds cf_clearance to) in the [PerplexityBridge]
 * section of the config.
 *
 * Writes /config/config.ini.php (the bind-mounted file the entrypoint copies at
 * every start, so the change survives restarts) and /app/config.ini.php (what
 * rss-bridge reads, so it takes effect immediately). Other sections are kept.
 *
 * POST fields: token, cookie (full Cookie header, "Copy as cURL" or the
 * DevTools cookie table), user_agent. Legacy: session_token, cf_clearance, cf_bm.
 */

const SECTION = 'PerplexityBridge';
const PERSISTENT_CONFIG = '/config/config.ini.php';
const LIVE_CONFIG = __DIR__ . '/config.ini.php';
const BACKUP_DIR = '/config/backups';
const INSECURE_TOKENS = ['', 'change-me-in-production'];
const TEST_URL = 'https://www.perplexity.ai/rest/discover/feed?limit=1&offset=0&version=2.18&source=default';

$SECRET_TOKEN = (string) getenv('PERPLEXITY_UPDATE_TOKEN');

/**
 * Accepts a Cookie header value ("a=b; c=d", optionally prefixed "cookie:"),
 * a "Copy as cURL" command (-b '...' or -H 'cookie: ...'), or rows copied from
 * the DevTools cookie table (name<TAB>value<TAB>domain...).
 */
function parseCookies(string $raw): array
{
    $raw = trim($raw);
    if (preg_match('/(?:^|\s)(?:-b|--cookie)\s+([\'"])(.*?)\1/s', $raw, $m)) {
        $raw = $m[2];
    } elseif (preg_match('/(?:^|[\s\'"])cookie:\s*([^\r\n\'"]+)/i', $raw, $m)) {
        $raw = $m[1];
    }

    $cookies = [];
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        if (str_contains($line, "\t")) {
            $cols = explode("\t", $line);
            $pairs = [[trim($cols[0]), trim($cols[1] ?? '')]];
        } else {
            $pairs = [];
            foreach (explode(';', $line) as $pair) {
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', $pair, 2);
                    $pairs[] = [trim($k), trim($v)];
                }
            }
        }
        foreach ($pairs as [$k, $v]) {
            if ($k !== '' && $v !== '' && preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $k)) {
                $cookies[$k] = $v;
            }
        }
    }
    return $cookies;
}

/** NextAuth/Auth.js session cookie, reassembled if it was split into .0, .1, ... chunks. */
function findSessionToken(array $cookies): ?array
{
    foreach (['__Secure-next-auth.session-token', '__Secure-authjs.session-token'] as $name) {
        if (isset($cookies[$name])) {
            return [$name, $cookies[$name]];
        }
        $chunks = [];
        foreach ($cookies as $k => $v) {
            if (preg_match('/^' . preg_quote($name, '/') . '\.(\d+)$/', $k, $m)) {
                $chunks[(int) $m[1]] = $v;
            }
        }
        if ($chunks) {
            ksort($chunks);
            return [$name . ' (' . count($chunks) . ' chunks)', implode('', $chunks)];
        }
    }
    return null;
}

/** Values go inside "..." in an ini file; these would break out of it or be interpolated. */
function iniSafe(string $v): bool
{
    return !preg_match('/["\\\\$\x00-\x1f\x7f]/', $v);
}

/** Replace the whole section (and our marker comments above it), keep everything else. */
function replaceSection(string $content, string $newSection): string
{
    $out = [];
    $inSection = false;
    foreach (preg_split('/\r?\n/', $content) as $line) {
        if (preg_match('/^\s*\[([^\]]+)\]\s*$/', $line, $m)) {
            $inSection = ($m[1] === SECTION);
        }
        if ($inSection || preg_match('/^;\s*(---\s*' . SECTION . '|Updated:|Updated via:)/', $line)) {
            continue;
        }
        $out[] = $line;
    }
    $head = rtrim(implode("\n", $out));
    if ($head === '') {
        $head = '; <?php exit; ?> DO NOT REMOVE THIS LINE';
    }
    return $head . "\n\n" . $newSection;
}

/** Same request the bridge makes, from this container (i.e. via its egress route). */
function testRequest(string $cookie, string $userAgent): array
{
    $ch = curl_init(TEST_URL);
    $status = ['code' => 0, 'mitigated' => null, 'error' => null];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: */*',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'User-Agent: ' . $userAgent,
            'Referer: https://www.perplexity.ai/discover',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'x-app-apiclient: default',
            'x-app-apiversion: 2.18',
            'Cookie: ' . $cookie,
        ],
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$status) {
            if (stripos($h, 'cf-mitigated:') === 0) {
                $status['mitigated'] = trim(substr($h, 13));
            }
            return strlen($h);
        },
    ]);
    if (curl_exec($ch) === false) {
        $status['error'] = curl_error($ch);
    }
    $status['code'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status;
}

function egressIPv4(): string
{
    $ch = curl_init('https://ifconfig.me/ip');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
    $ip = trim((string) curl_exec($ch));
    curl_close($ch);
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : 'unknown';
}

function fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (in_array($SECRET_TOKEN, INSECURE_TOKENS, true)) {
        fail(503, 'Updates are disabled: set PERPLEXITY_UPDATE_TOKEN for the container.');
    }
    if (!hash_equals($SECRET_TOKEN, (string) ($_POST['token'] ?? $_SERVER['HTTP_X_UPDATE_TOKEN'] ?? ''))) {
        fail(403, 'Invalid token');
    }

    // Collect cookies: the full paste, plus the legacy single fields if given
    $cookies = parseCookies((string) ($_POST['cookie'] ?? ''));
    foreach (['session_token' => '__Secure-next-auth.session-token', 'cf_clearance' => 'cf_clearance', 'cf_bm' => '__cf_bm'] as $field => $name) {
        if (trim($_POST[$field] ?? '') !== '') {
            $cookies[$name] = trim($_POST[$field]);
        }
    }

    $dropped = [];
    foreach ($cookies as $k => $v) {
        if (!iniSafe($v)) {
            $dropped[] = $k;
            unset($cookies[$k]);
        }
    }

    $session = findSessionToken($cookies);
    if ($session === null) {
        fail(400, 'No Perplexity session cookie (__Secure-next-auth.session-token) found. '
            . 'Copy the cookie header from a request in the Network tab; document.cookie does not include it.');
    }

    // cf_clearance is only valid with the User-Agent of the browser it was issued to
    $userAgent = trim((string) ($_POST['user_agent'] ?? ''));
    if ($userAgent === '' && str_starts_with($_SERVER['HTTP_USER_AGENT'] ?? '', 'Mozilla/')) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
    }
    if (!iniSafe($userAgent)) {
        fail(400, 'User-Agent contains characters that cannot be stored.');
    }

    $cookieHeader = implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies));

    $timestamp = date('Y-m-d H:i:s T');
    $newSection = "; --- " . SECTION . " Configuration ---\n"
        . "; Updated: $timestamp\n"
        . "; Updated via: Web Interface\n"
        . "[" . SECTION . "]\n"
        . "; Full Cookie header as the browser sent it; the bridge sends it verbatim\n"
        . "cookie = \"$cookieHeader\"\n"
        . "session_token = \"{$session[1]}\"\n"
        . "cf_clearance = \"" . ($cookies['cf_clearance'] ?? '') . "\"\n"
        . "cf_bm = \"" . ($cookies['__cf_bm'] ?? '') . "\"\n"
        . "; Browser the cookies came from (cf_clearance is bound to it)\n"
        . "user_agent = \"$userAgent\"\n";

    $current = is_file(PERSISTENT_CONFIG) ? (string) file_get_contents(PERSISTENT_CONFIG) : '';
    $updated = replaceSection($current, $newSection);

    // Never write a file rss-bridge could not parse (it would stop serving every bridge)
    $parsed = @parse_ini_string($updated, true, INI_SCANNER_TYPED);
    if ($parsed === false || !isset($parsed[SECTION]['cookie'])) {
        fail(500, 'Refusing to write: the resulting config does not parse.');
    }

    if ($current !== '') {
        if (!is_dir(BACKUP_DIR) && !mkdir(BACKUP_DIR, 0700, true)) {
            fail(500, 'Cannot create ' . BACKUP_DIR);
        }
        $backupFile = BACKUP_DIR . '/config.' . date('Ymd_His') . '.ini.php';
        if (file_put_contents($backupFile, $current, LOCK_EX) === false) {
            fail(500, 'Cannot write backup ' . $backupFile);
        }
        chmod($backupFile, 0600);
    }

    if (file_put_contents(PERSISTENT_CONFIG, $updated, LOCK_EX) === false) {
        fail(500, 'Cannot write ' . PERSISTENT_CONFIG . ' (must be writable by www-data)');
    }
    if (file_put_contents(LIVE_CONFIG, $updated, LOCK_EX) === false) {
        fail(500, 'Saved persistently, but cannot write ' . LIVE_CONFIG . ' - restart the container.');
    }

    $test = testRequest($cookieHeader, $userAgent ?: 'Mozilla/5.0');

    echo json_encode([
        'success' => true,
        'updated_at' => $timestamp,
        'backup_created' => isset($backupFile) ? basename($backupFile) : null,
        'found' => [
            'session_token' => $session[0],
            'cf_clearance' => isset($cookies['cf_clearance']),
            '__cf_bm' => isset($cookies['__cf_bm']),
            'cookies_total' => count($cookies),
            'dropped' => $dropped,
        ],
        'user_agent' => $userAgent,
        'test' => $test,
    ]);
    exit;
}

$egressIp = egressIPv4();
$updatesEnabled = !in_array($SECRET_TOKEN, INSECURE_TOKENS, true);
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
        .ipbox {
            padding: 12px;
            border-radius: 8px;
            background: #fff8e1;
            border: 1px solid #ffe082;
            font-size: 13px;
            color: #5d4a00;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .ipbox code, .help-section code {
            background: rgba(0,0,0,0.06);
            padding: 1px 4px;
            border-radius: 3px;
        }
        .result {
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Perplexity Cookies</h1>
        <p class="subtitle">Updates the PerplexityBridge configuration of this RSS-Bridge</p>

        <div id="alert" class="alert"></div>
<?php if (!$updatesEnabled): ?>
        <div class="alert error" style="display:block">Updates are disabled: <code>PERPLEXITY_UPDATE_TOKEN</code> is not set for the container.</div>
<?php endif; ?>

        <div class="ipbox">
            The bridge reaches Perplexity from <strong><code><?= htmlspecialchars($egressIp) ?></code></strong> (IPv4).
            Cloudflare's <code>cf_clearance</code> is bound to the IP and browser that solved the challenge, so your browser
            must reach perplexity.ai from the same address. Check before copying:
            <a href="https://www.perplexity.ai/cdn-cgi/trace" target="_blank" rel="noopener">perplexity.ai/cdn-cgi/trace</a>
            must show <code>ip=<?= htmlspecialchars($egressIp) ?></code>. If it shows an IPv6 address, see the help below.
        </div>

        <form id="updateForm">
            <div class="form-group">
                <label for="token">Update Token <span class="required">*</span></label>
                <input type="password" id="token" name="token" placeholder="Enter your update token" required>
                <div class="hint">Stored in this browser after the first successful use</div>
            </div>

            <div class="form-group">
                <label for="cookie">Cookies <span class="required">*</span></label>
                <textarea id="cookie" name="cookie" rows="6" required
                    placeholder="Paste the whole cookie header (or &quot;Copy as cURL&quot;) of a perplexity.ai request"></textarea>
                <div class="hint">The session token, <code>cf_clearance</code> and <code>__cf_bm</code> are picked out automatically; all cookies are stored and sent as the browser sent them.</div>
            </div>

            <div class="form-group">
                <label for="user_agent">
                    User-Agent <span class="optional">(filled from this browser)</span>
                </label>
                <input type="text" id="user_agent" name="user_agent" readonly>
                <div class="hint">Copy the cookies from this same browser.</div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn-primary" <?= $updatesEnabled ? '' : 'disabled' ?>>
                    Save &amp; test
                    <div class="spinner"></div>
                </button>
                <button type="button" class="btn-secondary" onclick="window.location.href='/'">
                    Back to RSS-Bridge
                </button>
            </div>
        </form>

        <div class="help-section">
            <h3>📖 How to get the cookies</h3>
            <ol>
                <li>Open <a href="https://www.perplexity.ai/discover" target="_blank" rel="noopener">perplexity.ai/discover</a> in this browser and log in</li>
                <li>Press <strong>F12</strong> → <strong>Network</strong>, reload the page</li>
                <li>Click the first request (<code>discover</code>) → <strong>Headers</strong> → <strong>Request Headers</strong></li>
                <li>Copy the whole value of <code>cookie:</code> (or right-click the request → <strong>Copy → Copy as cURL</strong>)</li>
                <li>Paste it above and click <strong>Save &amp; test</strong> — the result shows whether Perplexity accepts it</li>
            </ol>
            <p class="hint" style="margin-top:10px"><code>document.cookie</code> is not enough: the session cookie is HttpOnly.</p>

            <h3 style="margin-top:20px">🌐 If trace shows IPv6</h3>
            <ol>
                <li><strong>Firefox:</strong> <code>about:config</code> → <code>network.dns.disableIPv6</code> = <code>true</code> (set it back afterwards)</li>
                <li><strong>Any browser:</strong> add <code>104.18.26.48 www.perplexity.ai</code> to the hosts file for the moment of copying, then remove it</li>
                <li>A web page cannot make the browser use IPv4 for another site, so this has to happen in the browser or the OS.</li>
            </ol>
        </div>
    </div>

    <script>
        const form = document.getElementById('updateForm');
        const alertBox = document.getElementById('alert');

        // The bridge must send the same User-Agent as the browser the cookies came from
        document.getElementById('user_agent').value = navigator.userAgent;

        try {
            const savedToken = localStorage.getItem('perplexity_update_token');
            if (savedToken) {
                document.getElementById('token').value = savedToken;
            }
        } catch (e) {}

        function showAlert(message, type) {
            alertBox.className = 'alert result ' + type;
            alertBox.textContent = message;
            alertBox.style.display = 'block';
        }

        function describe(result) {
            const f = result.found;
            const t = result.test;
            let verdict;
            if (t.code === 200) {
                verdict = '✅ Perplexity accepted the cookies (HTTP 200).';
            } else if (t.mitigated) {
                verdict = '❌ Cloudflare still blocks (HTTP ' + t.code + ', cf-mitigated: ' + t.mitigated + ').\n'
                    + 'Most likely the cookies came from a different IP (IPv6?) or browser than the bridge uses.';
            } else {
                verdict = '⚠️ Perplexity answered HTTP ' + t.code + (t.error ? ' (' + t.error + ')' : '') + '.';
            }
            return 'Saved ' + result.updated_at + '.\n'
                + 'Session cookie: ' + f.session_token + '\n'
                + 'cf_clearance: ' + (f.cf_clearance ? 'yes' : 'no') + ', __cf_bm: ' + (f.__cf_bm ? 'yes' : 'no')
                + ', cookies stored: ' + f.cookies_total
                + (f.dropped.length ? '\nDropped (unstorable characters): ' + f.dropped.join(', ') : '') + '\n\n'
                + verdict;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');

            const formData = new FormData(form);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    try { localStorage.setItem('perplexity_update_token', formData.get('token')); } catch (e) {}
                    document.getElementById('cookie').value = '';
                    showAlert(describe(result), result.test.code === 200 ? 'success' : 'error');
                } else {
                    showAlert('❌ ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                showAlert('❌ Network error: ' + error.message, 'error');
            } finally {
                submitBtn.classList.remove('loading');
            }
        });
    </script>
</body>
</html>
