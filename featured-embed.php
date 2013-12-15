<?php

/*
 * Plugin Name: Featured Embed
 * Plugin URI: https://github.com/piffpaffpuff
 * Description: A plugin that displays embedded media in a similar manner to featured images.
 * Version: 1.0
 * Author: piffpaffpuff
 * Author URI: https://github.com/piffpaffpuff
 * License: GPL3
 *
 * Copyright (C) 2011 Triggvy Gunderson
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/*
 * Exit if accessed directly
 */
if (!defined('ABSPATH')) {
	exit; 
}

/**
 * Main class
 */
if (!class_exists('FeaturedEmbed')) {
class FeaturedEmbed {

	public static $plugin_file_path;
	public static $plugin_directory_url;
	public static $plugin_directory_path;
	public static $plugin_basename;
	public $slug;
		
	/**
	 * Constructor
	 */
	public function __construct() {
		self::$plugin_file_path = __FILE__;
		self::$plugin_directory_url = plugin_dir_url(self::$plugin_file_path);
		self::$plugin_directory_path = plugin_dir_path(self::$plugin_file_path);
		self::$plugin_basename = plugin_basename(self::$plugin_file_path);
		
		$this->slug = 'embed';
	}
	
	/**
	 * Include the classes
	 */
	public function includes() {
		require_once(ABSPATH . WPINC . '/class-oembed.php');
	}
	
	/**
	 * Load the code
	 */
	public function load() {
		// include the classes
		$this->includes();
					
		// load hooks
		add_action('plugins_loaded', array($this, 'load_translation'));
		add_action('init', array($this, 'hooks_init'));
		add_action('admin_init', array($this, 'hooks_admin'));
	}

	/**
	 * Load the translations
	 */
	public function load_translation() {
   		load_plugin_textdomain('featured-embed', false, dirname(self::$plugin_basename) . '/languages/');
	}
		
	/**
	 * Load the main hooks
	 */
	public function hooks_init() {
 		add_theme_support('post-thumbnails');		
		add_action('wp_ajax_add_embed_meta', array($this, 'add_embed_meta_with_ajax'));
		add_action('wp_ajax_delete_embed_meta', array($this, 'delete_embed_meta_with_ajax'));
	}
	
	/**
	 * Load the main hooks
	 */
	public function hooks_admin() {
		add_action('admin_print_styles', array($this, 'add_styles'));
		add_action('admin_print_scripts-post.php', array($this, 'add_scripts'));
		add_action('admin_print_scripts-post-new.php', array($this, 'add_scripts'));
				
		add_action('add_meta_boxes', array($this, 'add_boxes'));
		add_action('save_post', array($this, 'save_box_data'));
	}
		
	/**
	 * Add the styles
	 */
	public function add_styles() {
		wp_enqueue_style('featured-embed', self::$plugin_directory_url . 'css/style.css');
	}
	
	/**
	 * Add the scripts
	 */
	public function add_scripts() {
		wp_enqueue_script('featured-embed', self::$plugin_directory_url . 'js/script.js', array('jquery'));
	}	
	
	/**
	 * Add the meta boxes
	 */
	public function add_boxes() {
		$post_types = get_post_types(array('public'   => true));
		foreach($post_types as $post_type) {
			// Add the meta box to post types that support a featured image
			if(post_type_supports($post_type, 'thumbnail')) {
				add_meta_box('featured-embed-url-box', __('Featured Embed', 'featured-embed'), array($this, 'create_box_url'), $post_type, 'side', 'default');
				add_thickbox();
			}
		}
	}
	
	/**
	 * Create the box information
	 */
	public function create_box_url($post, $metabox) {
		// Get the data
		$data = $this->get_meta($post->ID, 'embed');
		$type = null;
		$source_url = null;
		$iframe_url = null;
		$thumbnail_url = null;
		
		// Set the vars
		if($data) {
			$type = $data->type;
			$source_url = $data->source_url;
			$iframe_url = $data->iframe_url;
			$thumbnail_url = $data->thumbnail_url;
			
			// Add Thickbox Lightbox params to the query to open the url properly
			$args = array(
				'autoplay' => true,
				'TB_iframe' => true,
				'width' => 480,
				'height' => 270
			);
			$iframe_url = add_query_arg($args, $iframe_url);
		}
		
		// Use nonce for verification
		wp_nonce_field(self::$plugin_basename, 'featured_embed_nonce');
		
		?>
		<input type="hidden" id="post_id" value="<?php echo $post->ID; ?>">
		
		<div id="featured-embed-form" class="<?php if(!empty($source_url)) : ?>hidden<?php endif; ?>">
			<p><input type="text" id="featured-embed-url" class="regular-text code" name="featured_embed[url]" placeholder="http://" value="<?php echo $source_url; ?>" title="<?php _e('URL', 'featured-embed'); ?>"></p>
			<p class="howto"><?php _e('Use an URL from YouTube, Vimeo or SoundCloud.', 'featured-embed'); ?></p>
		</div>
		
		<div id="featured-embed-preview" class="<?php if(empty($source_url)) : ?>hidden<?php endif; ?>">
			<div class="preview">
				<a href="<?php echo $iframe_url; ?>" class="thumbnail thickbox <?php echo $type; ?> <?php if(empty($thumbnail_url)) : ?>placeholder<?php endif; ?>" target="_blank">
					<?php if(isset($thumbnail_url)) : ?><img src="<?php echo $thumbnail_url; ?>"><?php endif; ?>
				</a>
				<a href="<?php echo $iframe_url; ?>" class="action thickbox <?php echo $type; ?>" target="_blank">
					<span><?php _e('Preview', 'featured-embed'); ?></span>
				</a>
			</div>
			<a href="#" id="featured-embed-remove"><?php _e('Remove featured embed', 'featured-embed'); ?></a>
		</div>
		<?php
	}
	
	/**
	 * Discover an embed data object
	 *
	 * http://codex.wordpress.org/Embeds
	 * http://oembed.com
	 */
	public function discover_embed_data($url, $width = null) {
		// Check bandcamp url. This needs some custom data.
		// wp_oembed_add_provider('http://bandcamp.com/album/*', 'http://soundcloud.com/oembed');
		// http://ladi6.bandcamp.com
		
		// Get the wordpress internal oembed class
		$oembed = _wp_oembed_get_object();
		
		// Default args
		$default_args = array(
			'discover' => true
		);
		
		// Set a custom width different from the 
		// template $content_width var.
		// Rise the size by factor 1.5 to really 
		// get a thumbnail that is a large enough.
		if(empty($width)) {
			$args = wp_embed_defaults();
			$args['width'] = $args['width'] * 1.5;
			$args['height'] = ($args['width'] / (16/9)) * 1.5;
		} else {
			$args = array(
				'width' => $width * 1.5,
				'height' => ($width / (16/9)) * 1.5
			);
		}

		// Merge args
		$args = wp_parse_args($args, $default_args);
		
		// Discover the embedding code via url		
		$provider = $oembed->discover($url);
		if($provider) {		
			$data = $oembed->fetch($provider, $url, $args);
			if($data) {
				// Parse the html code to find the iframe src url
				// and add it to the output data.
				// Suppress any load errors of domdocument.
				$dom = new DomDocument();
				@$dom->loadHTML($data->html);
				$iframes = $dom->getElementsByTagName('iframe');
				if($iframes->length > 0) {
					$data->iframe_url = $iframes->item(0)->getAttribute('src');
				}

				// Add the source url
				$data->source_url = $url;
				return $data;
			}
		}
		
		return;
	}

	/**
	 * Add meta data
	 */
	public function add_embed_meta($post_id, $url, $embed) {
		$this->set_meta($post_id, 'url', $url);
		$this->set_meta($post_id, 'embed', $embed);
	}

	/**
	 * Add meta data via ajax
	 */
	public function add_embed_meta_with_ajax() {
		// Verify post data and nonce
		if(empty($_POST) || empty($_POST['post_id']) || empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::$plugin_basename)) {
			die();
		}
		
		// Get the embed data
		$url = $_POST['url'];		
		$meta_url = $this->get_meta($_POST['post_id'], 'url');
		
		// Discover the embed code when there is a new one	
		if(!empty($url)) {
			$data = $this->discover_embed_data($url);
		}		
		
		// Set the meta
		if(isset($data)) {
			$this->add_embed_meta($_POST['post_id'], $url, $data);
			die('1');
			exit;
		} else {
			$this->delete_embed_meta($_POST['post_id']);
			die('0');
			exit;
		}
		exit;
	}

	/**
	 * Delete meta data
	 */
	public function delete_embed_meta($post_id) {
		$this->set_meta($post_id, 'url', null);
		$this->set_meta($post_id, 'embed', null);
	}

	/**
	 * Delete meta data via ajax
	 */
	public function delete_embed_meta_with_ajax() {
		// Verify post data and nonce
		if(empty($_POST) || empty($_POST['post_id']) || empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::$plugin_basename)) {
			die();
		}
		
		// Delete the meta
		$this->delete_embed_meta($_POST['post_id']);
		die('1');
		exit;
	}
	
	/**
	 * Save the box data
	 */
	public function save_box_data($post_id) {
		// Verify this came from the our screen and with 
		// proper authorization, because save_post can be 
		// triggered at other times. 
		if(empty($_POST['featured_embed_nonce']) || !wp_verify_nonce( $_POST['featured_embed_nonce'], self::$plugin_basename)) {
			return $post_id;
		}
		
		// Verify if this is an auto save routine. If it 
		// is our form has not been submitted, so we dont 
		// want to do anything. 
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return $post_id;
		}

		// Check permissions
		if($_POST['post_type'] ==  'page') {
			if(!current_user_can('edit_page', $post_id)) {
				return $post_id;
			}
		} else {
			if(!current_user_can('edit_post', $post_id)) {
				return $post_id;
			}
		}
		
		// We're authenticated: Now we need to find 
		// and save the data.
		
		// Save, update or delete the custom field of the post.
		// split all array keys and save them as unique meta to 
		// make them queryable by wordpress.
		if(isset($_POST['featured_embed'])) {	
			// Get the embed data
			$url = $_POST['featured_embed']['url'];		
			$meta_url = $this->get_meta($post_id, 'url');
				
			// Only get data for new urls
			if($url != $meta_url) {
				if(empty($url)) {
					// Reset the data when the url is empty
					$_POST['featured_embed']['url'] = null;
					$_POST['featured_embed']['embed'] = null;
				} else {
					// Discover the embed code when there is a new one	
					$data = $this->discover_embed_data($url);
					if(!empty($data)) {
						$_POST['featured_embed']['embed'] = $data;
					} else {
						$_POST['featured_embed']['url'] = null;
						$_POST['featured_embed']['embed'] = null;
					}
				}	
			}
			
			// Save the meta
			foreach($_POST['featured_embed'] as $key => $value) {
				$this->set_meta($post_id, $key, $value);
			}			
		}
	}
	
	/**
	 * Get the meta value from a key
	 */
	public function get_meta($post_id, $key) {
		return get_post_meta($post_id, '_featured_embed_' . $key, true);
	}
	
	/**
	 * Set the meta value for a key
	 */
	public function set_meta($post_id, $key, $value) {
		return update_post_meta($post_id, '_featured_embed_' . $key, $value);
	}

}
}

/*
 * Instance
 */
$featured_embed = new FeaturedEmbed();
$featured_embed->load();

/*
 * Template functions
 */
 
/**
 * Has embed html
 */
function has_featured_embed($post_id = null) {
	if(empty($post_id)) {
		global $post;
		$post_id = $post->ID;
	}

	global $featured_embed;
	if($featured_embed->get_meta($post_id, 'url')) {
		return true;
	}
	return false;
}

/**
 * Echo embed preview
 */
function featured_embed_preview($post_id = null) {
	if(empty($post_id)) {
		global $post;
		$post_id = $post->ID;
	}
	
	// Load the embed data
	global $featured_embed;
	$data = $featured_embed->get_meta($post_id, 'embed');
	$type = null;
	$source_url = null;
	$iframe_url = null;
	$thumbnail_url = null;
	$ratio = null;
	
	// Set the vars when an embed data exists
	if($data) {
		$type = $data->type;
		$source_url = $data->source_url;
		$iframe_url = $data->iframe_url;
		$thumbnail_url = $data->thumbnail_url;
		
		// Check the aspect ratio
		if(isset($data->thumbnail_width)) {
			$ratio = $data->thumbnail_width / $data->thumbnail_height;
		}
		
		// Set autoplay to true when it is a video
		if($type == 'video') {
			$args = array(
				'autoplay' => true
			);
			$iframe_url = add_query_arg($args, $iframe_url);
		}
	}
	
	// Use the featured image as preview when it is set.
	$post_thumbnail_id = get_post_thumbnail_id($post_id);
	if($post_thumbnail_id) {
		$post_thumbnail_data = wp_get_attachment_image_src($post_thumbnail_id, $size);
		$thumbnail_url = $post_thumbnail_data[0];
	}

	if($data) : ?>
	<div class="featured-embed-preview">
		<a href="<?php echo $iframe_url; ?>" class="thumbnail <?php echo $type ?> <?php if(empty($thumbnail_url)) : ?>placeholder<?php endif; ?> <?php if(isset($ratio) && $ratio < 16/9) : ?>no-widescreen<?php endif; ?>" target="_blank">
			<?php if(isset($thumbnail_url)) : ?>
				<img src="<?php echo $thumbnail_url; ?>">
			
				<?php /*// Add the thumbnail as background image to crop the display area. ?>
				<?php if(isset($ratio) && $ratio < 16/9 && $type == 'video') : ?>
					<!-- background-thumbnail -->
					<span style="background-image: url('<?php echo $thumbnail_url; ?>');"></span>
					<!-- /background-thumbnail -->
				<?php endif; */ ?>
				
			<?php endif; ?>
		</a>
		<a href="<?php echo $iframe_url; ?>" class="action <?php echo $type ?>" target="_blank">
			<span><?php if($type == 'video' || $type == 'rich') : ?><?php _e('Play', 'featured-embed'); ?><?php else : ?><?php _e('View', 'featured-embed'); ?><?php endif; ?></span>
		</a>
	</div>
	<?php endif;
}

?>