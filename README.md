# Securing Wordpress With Cloudflare WAF
Scripts to harden your Wordpress installation using Cloudflare.

By bret@waffluent.com

### Nothing herein requires more than what's available in the Cloudflare Pro Plan ($20/month as of when I type this: August 2025).

## Origin Hiding using Cloudflare & WordPress HMAC Verification

This security mechanism uses a **shared secret** and **HMAC signatures** to ensure that requests reaching WordPress have passed through Cloudflare **without tampering or bypass**.
It works in two parts:

1. **Cloudflare Worker Snippet**
   Injects a custom request header (`x-cf-pass`) containing an HMAC signature, timestamp, and nonce based on:

   * The `User-Agent` header
   * The `cf-ray` request identifier
   * A server-side secret key
   * A unique timestamp and random nonce

2. **WordPress Verification Function**
   Runs on every protected request and **denies access with `403 Forbidden`** if:

   * The signature is missing or malformed
   * The timestamp is outside the allowed window
   * The nonce has been used before (prevents replay attacks)
   * The HMAC does not match the expected value

This prevents attackers from bypassing Cloudflare or replaying a previously signed request.

---

## Cloudflare Worker Snippet

**Location:** Deployed in Cloudflare Dashboard under **Rules → Snippets**.

**Key steps:**

1. Read:

   * `User-Agent` from request headers
   * `cf-ray` from request headers
2. Generate:

   * Current UNIX timestamp (seconds)
   * 8-character random hex nonce
3. Build payload:

   ```
   <User-Agent>|<cf-ray>|<timestamp>|<nonce>
   ```
4. Sign payload using HMAC-SHA256 with a shared secret.
5. Base64-encode the HMAC.
6. Set the request header:

   ```
   x-cache-key: <hmacBase64>.<timestamp>.<nonce>
   ```
7. Forward the request to the origin.

---

## WordPress Verification Function

**Location:** Theme’s `functions.php` or a security plugin.

**Key steps:**

1. Read:

   * `HTTP_X_CF_PASS` from the incoming request
   * `HTTP_USER_AGENT`
   * `HTTP_CF_RAY`
2. Parse `<hmac>.<timestamp>.<nonce>` from the header.
3. Verify:

   * Format matches expected regex
   * Timestamp is within ±8 hours of the server time
   * Nonce has not been used before (APCu cache prevents reuse)
4. Recompute expected HMAC using the same payload format:

   ```
   <User-Agent>|<cf-ray>|<timestamp>|<nonce>
   ```
5. Compare expected HMAC to received HMAC using `hash_equals`.
6. If **any check fails**, return:

   ```
   HTTP/1.1 403 Forbidden
   Content-Type: text/plain
   Body: "403 Forbidden"
   ```
7. If **all checks pass**, store nonce in APCu (TTL 5 minutes) and continue request processing.

---

## Security Benefits

* **Prevents direct origin access:**
  Requests bypassing Cloudflare will not have a valid `x-cf-pass` signature.
* **Thwarts replay attacks:**
  Nonces are cached and cannot be reused within the TTL.
* **Binds request to unique connection:**
  The `cf-ray` value ties the signature to a specific Cloudflare request.
* **Ties request to client fingerprint:**
  The `User-Agent` is part of the signature, preventing simple header spoofing.

---

## Deployment Steps

### 1. Cloudflare Snippet

* Deploy the CF .js script as a Snippet in your Cloudflare account.
* Use the exact same `secret` value as in WordPress.
* Confirm that the `x-cf-pass` header appears in requests to your origin.

### 2. WordPress

* Add the verification function to your theme or plugin.
* Call `verify_ua_signature();` early in the request lifecycle (e.g., `init` hook).
* Ensure the PHP server has **APCu enabled** for nonce storage.

---

## Example Payload

### Cloudflare Builds:

```
User-Agent: Mozilla/5.0...
cf-ray: 7a8b9cdef1234567-ATL
timestamp: 1733424000
nonce: a3f19c2b
```

Payload to sign:

```
Mozilla/5.0...|7a8b9cdef1234567-ATL|1733424000|a3f19c2b
```

HMAC-SHA256 (Base64):

```
QWxhZGRpbjpvcGVuIHNlc2FtZQ==
```

Header sent:

```
x-cf-pass: QWxhZGRpbjpvcGVuIHNlc2FtZQ==.1733424000.a3f19c2b
```

---

## Notes

* **Shared secret must match exactly** in both Cloudflare and WordPress code.
* Clock skew greater than 8 hours will cause verification failures.
* If APCu is unavailable, replay protection will not work (but signature validation still will).
* You can lower the timestamp window for stricter security.


Here’s a **README-style explanation** of that script:

---

# WordPress Author & Category Enumeration Blocker

## Overview

This script is designed to **block enumeration scans** in WordPress that attempt to list site authors or categories by manipulating URLs.
Attackers often exploit these scans to gather valid usernames or taxonomy slugs, which can then be used in brute-force or targeted attacks.

The script detects suspicious URL patterns and redirects the requester to the home page, effectively blocking the information leak.

---

## What It Blocks

### 1. **Author Enumeration**

WordPress exposes author archives by default. For example:

* Default query string format:

  ```
  /?author=1
  /?author=2
  ```
* Permalink format:

  ```
  /author/1/
  /author/john-doe/
  ```

These requests can reveal usernames to attackers.
This script detects these patterns in **both query strings and permalinks** and blocks them.

---

### 2. **Category Enumeration**

Similar to author enumeration, category archives are accessible by default:

* Permalink format:

  ```
  /category/news/
  /category/uncategorized/
  ```

Attackers can use this to map your site structure or look for weak content filtering.
The script blocks these requests as well.

---

## How It Works

1. **Runs only for non-admin users**

   ```php
   if (!is_admin()) { ... }
   ```

   This ensures that legitimate admin actions in `/wp-admin` are not affected.

2. **Regex pattern matching**

   * Checks `$_SERVER['QUERY_STRING']` for query string formats (e.g., `?author=1`)
   * Checks `$_SERVER['REQUEST_URI']` for permalink formats (e.g., `/author/1/`)
   * Uses `preg_match()` to detect suspicious patterns

3. **Immediate redirect**

   ```php
   wp_safe_redirect(home_url());
   exit;
   ```

   Redirects suspicious requests to the site’s home page and halts execution.

4. **Redirect canonical filter**

   * Hooks into `redirect_canonical` with a high priority (`90`) to intercept WordPress’s own canonical redirection.
   * Prevents attackers from bypassing checks via canonical URLs.

---

## Benefits

* **Prevents username disclosure** — stops one of the most common reconnaissance steps in WordPress attacks.
* **Reduces brute-force attack success rates** — without valid usernames, attackers are forced to guess both username and password.
* **Minimizes site structure leakage** — hides category slugs from casual scraping.
* **Lightweight and fast** — no database queries, only string checks.

---

## Limitations

* Blocks all access to author and category archive pages, even for legitimate visitors.
* If your theme or SEO strategy relies on author/category archive pages, you may need to whitelist certain requests.
* Does not prevent enumeration via the WordPress REST API — that must be handled separately.

---

## Reference

Enumeration attack primer: [https://m0n.co/enum](https://m0n.co/enum)

Here’s a **README-style explanation** for the outbound HTTP logging hook you posted:

---

# WordPress Outbound HTTP Call Logger with DNS & Client IP Resolution

## Overview

This script hooks into WordPress’s HTTP API to log **every outbound HTTP request** made by plugins, themes, or WordPress core via `WP_HTTP`.
It provides deep visibility into what external calls your site is making, which is especially useful when diagnosing **WAF rule interference** or debugging **plugin callback issues**.

By logging destination IPs, hostnames, and client IPs, this tool helps security and debugging efforts without breaking site functionality.

---

## Why It’s Useful

* **Debugging WAF interference**
  When a WAF blocks external callbacks (e.g., payment gateways, webhooks, API integrations), this logger lets you see exactly which calls are being made, so you can adjust WAF rules accordingly.

* **Security visibility**
  Reveals all external services your site is talking to — handy for spotting unexpected calls that may indicate a compromise or malicious plugin behavior.

* **IP resolution**
  Logs not just the hostname but also the resolved IPs (both IPv4 and IPv6), allowing you to cross-check with firewall allowlists or blocklists.

---

## How It Works

### 1. **Hook into Outbound Requests**

```php
add_action('http_api_curl', function( $curl_handle, array $r, string $url ) { ... }, 10, 3);
```

This action runs whenever WordPress uses cURL to make an HTTP(S) request.

---

### 2. **Extract the Hostname**

```php
$host = parse_url($url, PHP_URL_HOST);
```

The hostname is pulled from the request URL. If parsing fails, the function exits early.

---

### 3. **Resolve IP Addresses**

```php
$records = @dns_get_record($host, DNS_A + DNS_AAAA);
```

* Uses `dns_get_record()` to fetch both IPv4 (`A`) and IPv6 (`AAAA`) DNS records.
* Suppresses PHP warnings to avoid polluting logs if DNS resolution fails.
* Collects and deduplicates IP addresses.

---

### 4. **Determine Client IP**

Tries multiple methods to safely get the originating client IP:

1. `X-Forwarded-For` header (case-insensitive) if available
2. Fallback to `REMOTE_ADDR` from the server environment
3. Avoids PHP notices by using null-coalescing lookups

---

### 5. **Log the Request**

Builds a structured log entry:

```
OUTBOUND [YYYY-MM-DD HH:MM:SS] URL=<full URL> HOST=<hostname> HOST_IPs=<comma-separated IPs> CLIENT_IP=<origin IP>
```

Sends it to PHP’s `error_log()` — in environments like WP Engine, these logs are visible in the hosting panel.

---

## Example Log Output

```
OUTBOUND [2025-08-04 14:32:11] URL=https://api.stripe.com/v1/charges HOST=api.stripe.com HOST_IPs=54.187.174.169,34.210.109.7 CLIENT_IP=203.0.113.25
```

---

## Benefits

* **Full visibility** into external requests for auditing and compliance.
* **Helps whitelist external API endpoints** in strict WAF environments.
* **Identifies misbehaving plugins** making unauthorized calls.
* **DNS/IP verification** ensures the request isn’t being redirected to an unexpected host.

---

## Deployment

1. Add the snippet to your theme’s `functions.php` or a custom must-use plugin.
2. No configuration is required — logging starts immediately.
3. Monitor your PHP error log for entries.
4. In production environments, consider sending logs to a SIEM or security log aggregator.

---

## Notes

* **Performance impact:** Minimal — DNS lookups are fast, but if a host has slow DNS resolution, the hook will block until it completes.
* **Security:** This script only logs; it doesn’t block. For blocking malicious destinations, you would need additional logic.
* **Privacy:** Avoid logging sensitive data embedded in URLs (tokens, API keys) in production logs.

Here’s a **README-style explanation** for your feed-disabling code:

---

# WordPress Feed Access Disabler

## Overview

This script completely **disables all WordPress-generated feeds** (RSS, RDF, Atom) for posts, comments, and categories.
Instead of serving feed content, it **redirects any feed requests to the site’s home page**.

This is useful for site owners who:

* Don’t publish syndication feeds
* Want to **reduce scraping** of their content
* Improve performance by eliminating unnecessary feed generation

---

## What It Does

### 1. Redirects Feed Requests

```php
function disable_feeds() {
    wp_redirect(home_url());
    die;
}
```

Whenever a feed endpoint is requested, this function sends the requester to the home page and stops execution.

---

### 2. Disables All Feed Types

The following hooks are intercepted with high priority (`-1`) to ensure feed generation never occurs:

* **Global feeds**:

  * `do_feed` (default feed)
  * `do_feed_rdf`
  * `do_feed_rss`
  * `do_feed_rss2`
  * `do_feed_atom`
* **Comment feeds**:

  * `do_feed_rss2_comments`
  * `do_feed_atom_comments`

---

### 3. Removes Feed Links from `<head>`

By default, WordPress injects feed link `<link>` tags in the `<head>` section of every page.
This script removes them so visitors and bots are not presented with feed URLs:

```php
add_action('feed_links_show_posts_feed', '__return_false', -1);
add_action('feed_links_show_comments_feed', '__return_false', -1);
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3);
```

---

## Benefits

* **Stops content scrapers** from pulling your posts via RSS or Atom feeds.
* **Reduces server load** by removing feed generation code paths.
* **Cleans up HTML output** by removing unused `<link>` feed tags.
* **Improves privacy** if you don’t want posts or comments syndicated.

---

## Example Behavior

### Before:

Request:

```
GET /feed
```

Response:

```
200 OK
(Content: RSS or Atom feed)
```

### After:

Request:

```
GET /feed
```

Response:

```
301 Moved Permanently
Location: https://yoursite.com/
```

---

## Notes

* If you still want feeds for specific purposes (e.g., podcast syndication, internal API), you’ll need to **whitelist those endpoints** before this redirect runs.
* This only affects **built-in WordPress feed endpoints** — custom feed endpoints created by plugins may still work unless explicitly blocked.
* Disabling feeds can impact SEO if your syndication strategy relies on them.




