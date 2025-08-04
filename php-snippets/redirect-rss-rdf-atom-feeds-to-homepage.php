/**
 * Redirect all users trying to access feeds to the home page.
 */
function disable_feeds() {
	wp_redirect( home_url() );
	die;
}

// Disable global RSS, RDF & Atom feeds.
add_action( 'do_feed',      'disable_feeds', -1 );
add_action( 'do_feed_rdf',  'disable_feeds', -1 );
add_action( 'do_feed_rss',  'disable_feeds', -1 );
add_action( 'do_feed_rss2', 'disable_feeds', -1 );
add_action( 'do_feed_atom', 'disable_feeds', -1 );

// Disable comment feeds.
add_action( 'do_feed_rss2_comments', 'disable_feeds', -1 );
add_action( 'do_feed_atom_comments', 'disable_feeds', -1 );

// Prevent feed links from being inserted in the <head> of the page.
add_action( 'feed_links_show_posts_feed',    '__return_false', -1 );
add_action( 'feed_links_show_comments_feed', '__return_false', -1 );
remove_action( 'wp_head', 'feed_links',       2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
