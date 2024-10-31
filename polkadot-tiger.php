<?php
/**
 * Plugin Name: SEO A/B Testing with Polkadot Tiger
 * Plugin URI: https://www.polkadottiger.com
 * Description: Create SEO Metadata A/B Tests to optimize Click-Through Rates and Rankings for your website's pages. Sign up at https://polkadottiger.com
 * Author: Polkadot Tiger
 * Version: 1.1.3
 * Author URI: https://polkadottiger.com
 */

add_filter( 'authenticate', 'polkadot_tiger_authenticate', 10, 3);
add_action( 'admin_menu', 'polkadot_tiger_add_admin_menu' );
add_action( 'admin_init', 'polkadot_tiger_api_key_init' );
add_filter( 'determine_current_user', 'polkadot_tiger_rest_api_auth_handler' );
add_action( 'rest_api_init', 'polkadot_tiger_rest_api_init' );
add_filter( 'wp_rest_server_class', 'polkadot_tiger_wp_rest_server_class' );


function polkadot_tiger_add_admin_menu(  ) {
	add_options_page( 'Polkadot Tiger', 'Polkadot Tiger', 'manage_options', 'polkadot_tiger', 'polkadot_tiger_options_page' );
}

// settings link on plugins page
function polkadot_tiger_action_links( $links ) {
  $links = array_merge( array(
    '<a href="' . esc_url( admin_url( 'options-general.php?page=polkadot_tiger' ) ) . '">' . __( 'Settings', 'polkadot-tiger' ) . '</a>'
  ), $links );
  return $links;
}
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'polkadot_tiger_action_links' );


function polkadot_tiger_api_key_init(  ) {
	register_setting( 'polkadot_tiger_api_key_page', 'polkadot_tiger_api_key' );

	add_settings_section(
		'polkadot_tiger_polkadot_tiger_api_key_page_section',
		__( 'Polkadot Tiger API Key', 'polkadot-tiger' ),
		'polkadot_tiger_api_key_section_callback',
		'polkadot_tiger_api_key_page'
	);

	add_settings_section(
		'polkadot_tiger_polkadot_tiger_api_key_page_section',
		__( 'Polkadot Tiger API Key', 'polkadot-tiger' ),
		'polkadot_tiger_api_key_section_callback',
		'polkadot_tiger_api_key_page'
	);

}


function polkadot_tiger_api_key_render(  ) {
	?>

	<input type='text' name='polkadot_tiger_api_key[polkadot_tiger_text_field_0]' value='<?php echo $options['polkadot_tiger_key_name']; ?>'>
	<?php

}



function polkadot_tiger_api_key_section_callback(  ) {
	$apiKey = get_option( 'polkadot_tiger_api_key' );

	if(!$apiKey) {
		$lowNumber = rand(1, 9);
		$apiKey = 'pdt' . $lowNumber . '-' .  implode('-', str_split(substr(strtolower(md5(microtime().rand(1000, 9999))), 0, 24), 6));
		update_option('polkadot_tiger_api_key', $apiKey);
	}

	echo __( 'Never share your API Key with anyone to keep your website secure.', 'polkadot-tiger' );

	?>
	<br /><br />
	<span style="display: inline-block; max-width: 400px; padding: 4px 8px; font-size: 16px; background: #000; color: #fff;"><?php echo $apiKey; ?></span>

	<?php
}


function polkadot_tiger_options_page(  ) {
		?>
		<form action='options.php' method='post'>

			<?php
			settings_fields( 'polkadot_tiger_api_key_page' );
			do_settings_sections( 'polkadot_tiger_api_key_page' );

			$apiKey = get_option( 'polkadot_tiger_api_key' );
			if(!$apiKey) {
				submit_button();
			} else {
				submit_button("Reset API Key");
			}
			?>

		</form>
		<?php
}

function polkadot_tiger_rest_api_auth_handler( $input_user ){
		// Don't authenticate twice
		if ( ! empty( $input_user ) ) {
			return $input_user;
		}

		// Check that we're trying to authenticate
		if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $input_user;
		}

		$user = polkadot_tiger_authenticate( $input_user, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );

		if ( $user instanceof WP_User ) {
			return $user->ID;
		}

		// If it wasn't a user what got returned, just pass on what we had received originally.
		return $input_user;
	}


/**
 * Prevent caching of unauthenticated status.  See comment below.
 *
 * We don't actually care about the `polkadot_tiger_wp_rest_server_class` filter, it just
 * happens right after the constant we do care about is defined.
 */
function polkadot_tiger_wp_rest_server_class( $class ) {
	global $current_user;
	if ( defined( 'REST_REQUEST' )
		&& REST_REQUEST
		&& $current_user instanceof WP_User
		&& 0 === $current_user->ID ) {
		/*
		 * For our authentication to work, we need to remove the cached lack
		 * of a current user, so the next time it checks, we can detect that
		 * this is a rest api request and allow our override to happen.  This
		 * is because the constant is defined later than the first get current
		 * user call may run.
		 */
		$current_user = null;
	}
	return $class;
}

/**
 * Check if the current request is an API request
 * for which we should check the HTTP Auth headers.
 *
 * @return boolean
 */
function polkadot_tiger_is_api_request() {
	// Process the authentication only after the APIs have been initialized.
	return ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) );
}

function polkadot_tiger_authenticate($input_user, $username, $password) {
	// if not API request
	if ( ! apply_filters( 'polkadot_tiger_is_api_request', polkadot_tiger_is_api_request() ) ) {
		return $input_user;
	}

	$user = get_user_by( 'login', $username );

	// if no user found for username
	if(!$user) {
		return $input_user;
	}

	$validApiKey = get_option('polkadot_tiger_api_key');

	if($validApiKey !== $password) {
		return $input_user;
	}

	return $user;
}

/**
	 * Handle declaration of REST API endpoints.
	 *
	 */
	function polkadot_tiger_rest_api_init() {

		// verify connection
		register_rest_route( 'pdt/v1', '/verify', array(
			'methods' => WP_REST_Server::EDITABLE,
			'callback' => 'polkadot_tiger_verify_connection',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
		) );

		// Get Yoast SEO Meta Data
		register_rest_route( 'pdt/v1', '/post_seo_meta/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'polkadot_tiger_rest_get_seo_meta',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
		) );

			// Update Yoast SEO Meta Data
		register_rest_route( 'pdt/v1', '/post_seo_meta/update/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::EDITABLE,
			'callback' => 'polkadot_tiger_rest_update_seo_meta',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
		) );

		// search posts
		register_rest_route( 'pdt/v1', '/posts/search', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'polkadot_tiger_rest_find_posts',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
			'args' => array(
				'search_term' => array(
					'required' => true,
				),
			),
		) );

		// get post URL
		register_rest_route( 'pdt/v1', '/post/get-url/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'polkadot_tiger_rest_get_url',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
		) );

		// search pages
		register_rest_route( 'pdt/v1', '/pages/search', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'polkadot_tiger_rest_find_pages',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
			'args' => array(
				'search_term' => array(
					'required' => true,
				),
			),
		) );

		// search products
		register_rest_route( 'pdt/v1', '/products/search', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'polkadot_tiger_rest_find_products',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
			'args' => array(
				'search_term' => array(
					'required' => true,
				),
			),
		));

		// find page by id
		register_rest_route( 'pdt/v1', '/get-page-by-id', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => 'polkadot_tiger_rest_find_page_by_id',
			'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
			'args' => array(
				'page_id' => array(
					'required' => true,
				),
			),
		));

      // find page by url
      register_rest_route( 'pdt/v1', '/get-by-url', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'polkadot_tiger_rest_find_by_url',
        'permission_callback' => 'polkadot_tiger_rest_edit_user_callback',
        'args' => array(
          'url' => array(
            'required' => true,
          ),
        ),
      ));
	}

	/**
	 * Whether or not the current user can edit the specified user.
	 *
	 * @since 0.1-dev
	 *
	 * @access public
	 * @static
	 *
	 * @param $data
	 *
	 * @return bool
	 */
	function polkadot_tiger_rest_edit_user_callback( $data ) {
		return current_user_can( 'edit_user', $data['user_id'] );
	}

	function polkadot_tiger_verify_connection() {
		return 'confirmed';
	}

	/**
	 * Get URL for a specified Post
	 *
	 * @return MetaData|array
	 */
	function polkadot_tiger_rest_get_url($data) {
		$post_id = $data['id'];

		$post = get_post($post_id);

		if ( empty( $post ) ) {
			return new WP_Error( 'no_post', 'Invalid Post', array( 'status' => 404 ) );
		}

		$url = get_permalink($post_id);

		return $url;
	}

	/**
 * Get SEO Meta Data for specified post and return
 *
 * @return MetaData|array
 */
function polkadot_tiger_rest_get_seo_meta($data) {
	$post_id = $data['id'];

	$post = get_post($post_id);

	if ( empty( $post ) ) {
		return new WP_Error( 'no_post', 'Invalid Post', array( 'status' => 404 ) );
	}

	// get yoast seo meta
	$seoMeta = [];
	$seoMeta['meta_title'] = get_post_meta($post_id, '_yoast_wpseo_title', true);
	$seoMeta['meta_description'] = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);

	// if yoast seo meta data is empty, try to get rankmath instead
	if(strlen($seoMeta['meta_title']) < 1 && strlen($seoMeta['meta_description']) < 1) {
		$seoMeta['meta_title'] = get_post_meta($post_id, 'rank_math_title', true);
		$seoMeta['meta_description'] = get_post_meta($post_id, 'rank_math_description', true);
	}

	return json_encode($seoMeta);
}

/**
 * Update SEO Meta Data for specified post and return
 *
 * @return MetaData|array
 */
function polkadot_tiger_rest_update_seo_meta($data) {
	$post_id = $data['id'];

	$post = get_post($post_id);

	if ( empty( $post ) ) {
		return new WP_Error( 'no_post', 'Invalid Post', array( 'status' => 404 ) );
	}

	$meta_title = $data['meta_title'];
	$meta_description = $data['meta_description'];

	// update yoast seo and rankmath meta title values
	update_post_meta($post_id, '_yoast_wpseo_title', $meta_title);
	update_post_meta($post_id, 'rank_math_title', $meta_title);

	// update yoast seo and rankmath meta description values
	update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
	update_post_meta($post_id, 'rank_math_description', $meta_description);

	// update the post modified date to ensure updates are reflected in SEO Sitemaps
  $datetime  = date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) );
  $current_post = array(
    'ID'           => $post_id,
    'post_modified' => $datetime
  );
  // Update the post into the database
  wp_update_post( $current_post );

	$post = get_post($post_id);

	return $post;
}

/**
 * Return 20 posts matching search criteria
 *
 * @return Posts|array
 */
function polkadot_tiger_rest_find_posts($data) {
	$search_term = $data['search_term'];

	$args = array(
		'numberposts' => 20,
		'post_type' => 'post',
		's' => $search_term,
		'post_status' => 'publish',
		'orderby'     => 'title',
		'order'       => 'ASC',
		'posts_per_page' => -1
	);

	$query = new WP_Query($args);
	$posts = $query->posts;

	return $posts;
}

/**
 * Return 20 posts matching search criteria
 *
 * @return Pages|array
 */
function polkadot_tiger_rest_find_pages($data) {
	$search_term = $data['search_term'];

	$args = array(
		'numberposts' => 20,
		'post_type' => 'page',
		's' => $search_term,
		'post_status' => 'publish',
		'orderby'     => 'title',
		'order'       => 'ASC',
		'posts_per_page' => -1
	);

	$query = new WP_Query($args);
	$pages = $query->posts;

	return $pages;
}

/**
 * Return 20 products matching search criteria
 *
 * @return Posts|array
 */
function polkadot_tiger_rest_find_products($data) {

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	  require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}
	$installed = true;

	// multisite
	if ( is_multisite() ) {
	  // this plugin is network activated - Woo must be network activated
	  if ( is_plugin_active_for_network( plugin_basename(__FILE__) ) ) {
	    $installed = is_plugin_active_for_network('woocommerce/woocommerce.php') ? true : false;
	  // this plugin is locally activated - Woo can be network or locally activated
	  } else {
	    $installed = is_plugin_active( 'woocommerce/woocommerce.php')  ? true : false;
	  }
	// this plugin runs on a single site
	} else {
	  $installed =  is_plugin_active( 'woocommerce/woocommerce.php') ? true : false;
	}


	if ( $installed ) {
			$search_term = $data['search_term'];

		$args = array(
			'numberposts' => 20,
			'post_type' => 'product',
			's' => $search_term,
			'post_status' => 'publish',
			'orderby'     => 'title',
			'order'       => 'ASC',
			'posts_per_page' => -1
		);

		$query = new WP_Query($args);
		$products = $query->posts;

		return $products;
	} else {
		return "WooCommerce is not installed or enabled";
	}
}

/**
 * Find page by given ID
 *
 * @return Post|object
 */
function polkadot_tiger_rest_find_page_by_id($data) {
	$pageId = $data['page_id'];

	$post = get_post($pageId);

	return $post;
}

/**
 * Find page by given URL
 *
 * @return Post|object
 */
function polkadot_tiger_rest_find_by_url($data) {
  $pageUrl = $data['url'];

  $postId = url_to_postid($pageUrl);

  return $postId ? get_post($postId) : get_page_by_path($pageUrl);
}



