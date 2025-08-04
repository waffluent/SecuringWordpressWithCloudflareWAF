function verify_ua_signature(): void {

    // these lines must match cf-snippet-origin-hiding-header.js
    $shared_secret = 'your-secret-string-here';
    $header = $_SERVER['HTTP_X_CF_PASS'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $cf_ray = $_SERVER['HTTP_CF_RAY'] ?? '';

    // Function to send 403 and terminate
    $deny = function (): void {
        status_header(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo '403 Forbidden';
        exit;
    };

    // Match <hmac>.<timestamp>.<nonce>
    if (!preg_match('/^([A-Za-z0-9\/+=]+)\.(\d+)\.([a-f0-9]{8})$/', $header, $m)) {
        $deny();
    }

    [$full, $hmac, $timestamp, $nonce] = $m;

    // Timestamp window check (±8 hours)
    $now = time();
    if (abs($now - (int)$timestamp) > 28800) {
        $deny();
    }

    // Prevent replay with APCu
    $nonce_key = "ua_sig_nonce_$nonce";
    if (function_exists('apcu_exists') && apcu_exists($nonce_key)) {
        $deny();
    }

    // Recompute HMAC
    $signed_data = "$user_agent|$cf_ray|$timestamp|$nonce";
    $expected_hmac = base64_encode(hash_hmac('sha256', $signed_data, $shared_secret, true));

    if (!hash_equals($expected_hmac, $hmac)) {
        $deny();
    }

    // Store nonce for short TTL
    if (function_exists('apcu_store')) {
        apcu_store($nonce_key, 1, 300);
    }
}
