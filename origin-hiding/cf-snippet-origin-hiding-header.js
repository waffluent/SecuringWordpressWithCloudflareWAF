export default {
  async fetch(request) {
    const secret = "your-secret-string-here"; // use the same secret in wp-snippet-origin-hiding-header.php
    const userAgent = request.headers.get("User-Agent") || "";
    const cfRay = request.headers.get("cf-ray") || "";

    // Get UNIX timestamp (seconds)
    const timestamp = Math.floor(Date.now() / 1000);

    // Generate random 8-character hex nonce (4 bytes)
    const randomBytes = crypto.getRandomValues(new Uint8Array(4));
    const nonce = Array.from(randomBytes)
      .map((b) => b.toString(16).padStart(2, "0"))
      .join(""); // e.g., "a3f19c2b"

    // Construct message to sign
    const payload = `${userAgent}|${cfRay}|${timestamp}|${nonce}`;

    const encoder = new TextEncoder();
    const key = await crypto.subtle.importKey(
      "raw",
      encoder.encode(secret),
      { name: "HMAC", hash: "SHA-256" },
      false,
      ["sign"]
    );

    const signature = await crypto.subtle.sign(
      "HMAC",
      key,
      encoder.encode(payload)
    );
    const hmacBase64 = btoa(
      String.fromCharCode(...new Uint8Array(signature))
    );

    // Final format: <hmac>.<timestamp>.<nonce>
    const headerValue = `${hmacBase64}.${timestamp}.${nonce}`;

    const response = await fetch(request);

    // Clone and modify response to inject the header. Wordpress will check that this
    const newResponse = new Response(response.body, response);
    newResponse.headers.append("x-cf-pass", headerValue);

    return newResponse;
  },
};
