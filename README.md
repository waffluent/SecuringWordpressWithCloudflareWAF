# Securing Wordpress With Cloudflare WAF
Scripts to harden your Wordpress installation using Cloudflare.

### Nothing herein requires more than what's available in the Cloudflare Pro Plan ($20/month as of when I type this: August 2025).

## Origin Hiding using Cloudflare & WordPress HMAC Verification

## Overview

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

