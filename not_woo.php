<?php

/*
 * Plugin Name: not_woo
 * Plugin URI: https://nullstep.com/not_woo
 * Description: A frugal shop plugin for WP
 * Author: nullstep
 * Author URI: https://nullstep.com
 * Version: 1.0.0
 */

defined('ABSPATH') or die('nope');

// defines      

define('_PLUGIN', 'not_woo');

define('_URL', plugin_dir_url(__FILE__));
define('_PATH', plugin_dir_path(__FILE__));

define('_ARGS_NOT_WOO', [
	'shop_active' => [
		'type' => 'integer',
		'default' => 1
	],
	'shop_image' => [
		'type' => 'string',
		'default' => ''
	],
	'paypal_address' => [
		'type' => 'string',
		'default' => ''
	],
	'shop_css' => [
		'type' => 'string',
		'default' => ''
	],
	'shop_css_minified' => [
		'type' => 'string',
		'default' => ''
	],
	'shop_js' => [
		'type' => 'string',
		'default' => ''
	],
	'shop_js_minified' => [
		'type' => 'string',
		'default' => ''
	]
]);

// classes

class not_woo_API {
	public function add_routes() {
		register_rest_route(_PLUGIN . '-plugin-api/v1', '/settings', [
				'methods' => 'POST',
				'callback' => [$this, 'update_settings'],
				'args' => not_woo_Settings::args(),
				'permission_callback' => [$this, 'permissions']
			]
		);
		register_rest_route(_PLUGIN . '-plugin-api/v1', '/settings', [
				'methods' => 'GET',
				'callback' => [$this, 'get_settings'],
				'args' => [],
				'permission_callback' => [$this, 'permissions']
			]
		);
	}

	public function permissions() {
		return current_user_can('manage_options');
	}

	public function update_settings(WP_REST_Request $request) {
		$settings = [];
		foreach (not_woo_Settings::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}
		not_woo_Settings::save_settings($settings);
		return rest_ensure_response(not_woo_Settings::get_settings());
	}

	public function get_settings(WP_REST_Request $request) {
		return rest_ensure_response(not_woo_Settings::get_settings());
	}
}

class not_woo_Settings {
	protected static $option_key = _PLUGIN . '-settings';

	public static function args() {
		$args = _ARGS_NOT_WOO;
		foreach (_ARGS_NOT_WOO as $key => $val) {
			$val['required'] = true;
			switch ($val['type']) {
				case 'integer': {
					$cb = 'absint';
					break;
				}
				default: {
					$cb = 'sanitize_text_field';
				}
				$val['sanitize_callback'] = $cb;
			}
		}
		return $args;
	}

	public static function get_settings() {
		$defaults = [];
		foreach (_ARGS_NOT_WOO as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		$saved = get_option(self::$option_key, []);
		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public static function save_settings(array $settings) {
		$defaults = [];
		foreach (_ARGS_NOT_WOO as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		foreach ($settings as $i => $setting) {
			if (!array_key_exists($i, $defaults)) {
				unset($settings[$i]);
			}
			if ($i == 'theme_css') {
				$settings['theme_css_minified'] = minify_css($setting);
			}
			if ($i == 'theme_js') {
				$settings['theme_js_minified'] = minify_js($setting);
			}
		}
		update_option(self::$option_key, $settings);
	}
}

class not_woo_Menu {
	protected $slug = _PLUGIN . '-menu';
	protected $assets_url;

	public function __construct($assets_url) {
		$this->assets_url = $assets_url;
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'register_assets']);
	}

	public function add_page() {
		add_menu_page(
			_PLUGIN,
			_PLUGIN,
			'manage_options',
			$this->slug,
			[$this, 'render_admin'],
			'dashicons-cart',
			3
		);

		// add taxonomies menus

		$types = [
			'section' => 'product'
		];

		foreach ($types as $type => $child) {
			add_submenu_page(
				$this->slug,
				$type . 's',
				$type . 's',
				'manage_options',
				'/edit-tags.php?taxonomy=' . $type . '&post_type=' . $child
			);
		}

		// add posts menus

		$types = [
			'product',
			'order',
			'customer'
		];

		foreach ($types as $type) {
			add_submenu_page(
				$this->slug,
				$type . 's',
				$type . 's',
				'manage_options',
				'/edit.php?post_type=' . $type
			);
		}
	}

	public function register_assets() {
		wp_register_script($this->slug, $this->assets_url . '/not_woo.js', ['jquery']);
		wp_register_style($this->slug, $this->assets_url . '/not_woo.css');
		wp_localize_script($this->slug, _PLUGIN, [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(_PLUGIN . '-plugin-api/v1/settings')),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public function enqueue_assets() {
		if (!wp_script_is($this->slug, 'registered')) {
			$this->register_assets();
		}
		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function render_admin() {
		wp_enqueue_media();
		$this->enqueue_assets();
?>
		<style>
			#wpwrap {
				background: url(<?php echo $this->assets_url . '/not_woo.svg'; ?>) no-repeat;
			}
		</style>
		<h2>not_woo</h2>
		<p style="max-width:500px">Configure your shop settings...</p>
		<form id="not_woo-form" method="post">
			<div class="form-block">
				<label for="shop_active">
					Shop Active:
				</label>
				<select id="shop_active" name="shop_active">
					<option value="0">Off</option>
					<option value="1">On</option>
				</select>					
			</div>
			<div class="form-block">
				<label for="shop_image">
					Shop Image:
				</label>
				<input id="shop_image" type="text" name="shop_image">
				<input data-id="shop_image" type="button" class="button-primary choose-file-button" value="Select...">
			</div>
			<div class="form-block">
				<label for="paypal_address">
					Paypal Address:
				</label>
				<input id="paypal_address" type="text" name="paypal_address">
			</div>
			<div class="form-block">
				<label for="shop_css" class="top">
					Shop CSS:
				</label>
				<textarea id="shop_css" class="tabs" name="shop_css"></textarea>
			</div>
			<div class="form-block-ns">
				<label for="shop_js" class="top">
					Shop JS:
				</label>
				<textarea id="shop_js" class="tabs" name="shop_js"></textarea>
			</div>
			<div>
				<?php submit_button(); ?>
			</div>
			<div id="feedback">
			</div>
		</form>
<?php
	}
}

// functions

function not_woo_init($dir) {
	if (is_admin()) {
		new not_woo_Menu(_URL);
	}

	// set up post types

	$types = [
		'product',
		'order',
		'customer'
	];

	foreach ($types as $type) {
		$uc_type = ucwords($type);

		$labels = [
			'name' => $uc_type . 's',
			'singular_name' => $uc_type,
			'menu_name' => $uc_type . 's',
			'name_admin_bar' => $uc_type . 's',
			'add_new' => 'Add New',
			'add_new_item' => 'Add New ' . $uc_type,
			'new_item' => 'New ' . $uc_type,
			'edit_item' => 'Edit ' . $uc_type,
			'view_item' => 'View ' . $uc_type,
			'all_items' => $uc_type . 's',
			'search_items' => 'Search ' . $uc_type . 's',
			'not_found' => 'No ' . $uc_type . 's Found'
		];

		register_post_type($type, [
			'supports' => [
				'title',
				'thumbnail',
				'revisions',
				'post-formats'
			],
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_in_menu' => false,
			'query_var' => true,
			'has_archive' => false,
			'rewrite' => ['slug' => $type]
		]);
	}

	// set up taxonomies

	$types = [
		'section' => 'product'
	];

	foreach ($types as $type => $child) {
		$uc_type = ucwords($type);

		$labels = [
			'name' => $uc_type . 's',
			'singular_name' => $uc_type,
			'search_items' => 'Search ' . $uc_type . 's',
			'all_items' => 'All ' . $uc_type . 's',
			'parent_item' => 'Parent ' . $uc_type,
			'parent_item_colon' => 'Parent ' . $uc_type . ':',
			'edit_item' => 'Edit ' . $uc_type, 
			'update_item' => 'Update ' . $uc_type,
			'add_new_item' => 'Add New ' . $uc_type,
			'new_item_name' => 'New ' . $uc_type . ' Name',
			'menu_name' => $uc_type . 's',
		];

		register_taxonomy($type, [$child], [
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_rest' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'rewrite' => ['slug' => $type],
		]);
	}
}

function not_woo_api_init() {
	not_woo_Settings::args();
	$api = new not_woo_API();
	$api->add_routes();
}

function not_woo_add_metaboxes() {
    $screens = ['product'];
    foreach ($screens as $screen) {
        add_meta_box(
            'not_woo_meta_box',
            'Product Data',
            'not_woo_product_metabox',
            $screen
        );
    }
}

function not_woo_product_metabox($post) {
	$prefix = '_not_woo-product_';
	$keys = [
		'sku',
		'price',
		'desc',
		'data'
	];
	foreach ($keys as $key) {
		$$key = get_post_meta($post->ID, $prefix . $key, true);
	}
    wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
    ?>
    <style>
		#not_woo_meta_box label {
			display: inline-block;
			font-size: 12px;
		}
		#not_woo_meta_box input,
		#not_woo_meta_box textarea {
			width: 300px;
			margin: 0 0 5px;
			padding: 3px;
		}
    </style>
    <div>
    	<br>
		<label>SKU:</label>
		<br>
		<input name="_not_woo-product_sku" value="<?php echo $sku; ?>">
		<br>
		<label>Price:</label>
		<br>
		<input name="_not_woo-product_price" value="<?php echo $price; ?>">
		<br>
		<label>Description:</label>
		<br>
		<textarea name="_not_woo-product_desc"><?php echo $desc; ?></textarea>
		<br>
		<label>Data:</label>
		<br>
		<textarea name="_not_woo-product_data"><?php echo $data; ?></textarea>
	</div>
    <?php
}

function not_woo_save_postdata($post_id) {
	$prefix = '_not_woo-product_';
	$keys = [
		'sku',
		'price',
		'desc',
		'data'
	];
	foreach ($keys as $key) {
		if (array_key_exists($prefix . $key, $_POST)) {
			update_post_meta(
				$post_id,
				$prefix . $key,
				$_POST[$prefix . $key]
			);
		}
	}
}

function not_woo_set_current_menu($parent_file) {
	global $submenu_file, $current_screen, $pagenow;

	if ($current_screen->id == 'edit-section') {
		if ($pagenow == 'post.php') {
			$submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
		}
		if ($pagenow == 'edit-tags.php') {
			$submenu_file = 'edit-tags.php?taxonomy=section&post_type=' . $current_screen->post_type;
		}
		$parent_file = _PLUGIN . '-menu';
	}
	return $parent_file;
}

//     ▄██████▄    ▄██████▄   
//    ███    ███  ███    ███  
//    ███    █▀   ███    ███  
//   ▄███         ███    ███  
//  ▀▀███ ████▄   ███    ███  
//    ███    ███  ███    ███  
//    ███    ███  ███    ███  
//    ████████▀    ▀██████▀   

add_action('init', 'not_woo_init');
add_action('rest_api_init', 'not_woo_api_init');
add_action('add_meta_boxes', 'not_woo_add_metaboxes');
add_action('save_post', 'not_woo_save_postdata');

add_filter('parent_file', 'not_woo_set_current_menu');

// EOF