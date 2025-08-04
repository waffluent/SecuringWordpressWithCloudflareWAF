// block WP enum scans
// https://m0n.co/enum
if (!is_admin()) {
	// default URL format
	if (preg_match('/author=([0-9]*)/i', $_SERVER['QUERY_STRING'])) {
		wp_safe_redirect(home_url() );
		exit;
	}
	add_filter('redirect_canonical', 'check_enum1', 90, 2);
}
function check_enum1($redirect, $request) {
	// permalink URL format
	if (preg_match('/\?author=([0-9]*)(\/*)/i', $request)) {
		wp_safe_redirect(home_url() );
		exit;
	}
	else return $redirect;
}
if (!is_admin()) {
	// default URL format
	if (preg_match('/author\/*/i', $_SERVER['REQUEST_URI'])) {
		wp_safe_redirect(home_url() );
		exit;
	}
	add_filter('redirect_canonical', 'check_enum2', 90, 2);
}
function check_enum2($redirect, $request) {
	// permalink URL format
	if (preg_match('/author\/*/i', $request)) {
		wp_safe_redirect(home_url() );
		exit;
	}
	else return $redirect;
}
if (!is_admin()) {
	// default URL format
	if (preg_match('/category\/*/i', $_SERVER['REQUEST_URI']))  {
		wp_safe_redirect(home_url());
		exit;
	}
	add_filter('redirect_canonical', 'check_enum3', 90, 2);
}
function check_enum3($redirect, $request) {
	// permalink URL format
	if (preg_match('/category\/*/i', $request)) {
		wp_safe_redirect(home_url() );
		exit;
	}
	else return $redirect;
}
