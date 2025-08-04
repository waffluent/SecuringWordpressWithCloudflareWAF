
add_action(
   // Log outbound WP_HTTP calls, resolve IPs quietly, safely capture client IP
   // VERY useful when debugging if a new or updated WAF rule starts breaking proper plugin callback functionality
   // this shows you what plugins are doing traffic-wise
  'http_api_curl',
  function( $curl_handle, array $r, string $url ) : void {
    // 1) Resolve the host
    $host = parse_url( $url, PHP_URL_HOST );
    if ( ! $host ) {
      return;
    }

    // 2) Quietly lookup A/AAAA—suppress PHP warnings—and ensure we always have an array
    $records = @dns_get_record( $host, DNS_A + DNS_AAAA );
    if ( ! is_array( $records ) ) {
      $records = [];
    }

    // 3) Pull out only the ip/ipv6 keys that exist
    $ips = [];
    foreach ( $records as $rec ) {
      if ( isset( $rec['ip'] ) ) {
        $ips[] = $rec['ip'];
      } elseif ( isset( $rec['ipv6'] ) ) {
        $ips[] = $rec['ipv6'];
      }
    }
    $ips = array_unique( $ips );

    // 4) Safely get the client IP (X-Forwarded-For or REMOTE_ADDR)
    //    no undefined-key notices
    if ( function_exists( 'getallheaders' ) ) {
      $hdrs = getallheaders();
      $client = $hdrs['X-Forwarded-For'] 
             ?? $hdrs['x-forwarded-for'] 
             ?? '';
    } else {
      $client = $_SERVER['HTTP_X_FORWARDED_FOR'] 
             ?? '';
    }
    if ( empty( $client ) ) {
      $client = $_SERVER['REMOTE_ADDR'] 
              ?? '';
    }

    // 5) Build and emit the log line
    $line = sprintf(
      "OUTBOUND [%s] URL=%s HOST=%s HOST_IPs=%s CLIENT_IP=%s",
      date( 'Y-m-d H:i:s' ),
      $url,
      $host,
      implode( ',', $ips ),
      $client
    );

    // 6) Send to the PHP error log (WP Engine will surface this)
    error_log( $line );
  },
  10,
  3
);
