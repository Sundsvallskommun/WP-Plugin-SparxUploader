<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/Sundsvallskommun/
 * @since      1.0.0
 *
 * @package    Sk_Sparx_Upload
 * @subpackage Sk_Sparx_Upload/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sk_Sparx_Upload
 * @subpackage Sk_Sparx_Upload/admin
 * @author     Daniel Pihlström <daniel.pihlstrom@cybercom.com>
 */
class Sk_Sparx_Upload_Admin {

	private $sparx_dir;
	private $post_type = 'sparx';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $plugin_name The name of this plugin.
	 * @param      string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}


	/**
	 * Setting up acf-json sync for acf fields.
	 *
	 * @since 1.0.0
	 *
	 * @param $paths
	 *
	 * @return array
	 */
	function acf_json( $paths ) {
		$paths[] = plugin_dir_path( __FILE__ ) . '/acf-json';

		return $paths;
	}

	/**
	 * Adding text to acf meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param $field
	 */
	public function acf_meta_box( $field ) {
		global $post;
		if ( $field['key'] == 'field_57daaaeba0fa6' ) {
			$sparx_url = get_field( '_sk_sparx_file_url', $post->ID );
			if ( ! empty( $sparx_url ) ) :
				?>
				<p><?php _e( 'Länk till Sparx projekt: ', 'sk' ); ?> <a
						href="<?php echo $sparx_url; ?>"><?php echo $sparx_url; ?></a></p>

				<?php
			endif;
		}

	}


	/**
	 * Save post hook
	 *
	 * @since 1.0.0
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function save_post( $post_id ) {

		$upload_dir       = wp_upload_dir();
		$sparx_url_folder = $upload_dir['baseurl'] . '/sparx';

		// bail if this is not a sparx cpt.
		if ( $this->post_type != $_POST['post_type'] ) {
			return false;
		}

		$file = get_field( 'sk_sparx_file' );


		// check if we have a file attached
		if ( ! empty( $file['id'] ) ) {

			// delete project if already exists to prevent more than one folder for the same post.
			if( file_exists( $this->sparx_dir . '/' . $post_id )){
				self::rmdir_and_contents( $this->sparx_dir . '/' . $post_id );
			}

			$unzip  = new ZipArchive;
			$result = $unzip->open( get_attached_file( $file['id'] ) );
			if ( $result === true ) {
				$unzip->extractTo( $this->sparx_dir . '/' . $post_id );
				$unzip->close();

				$current_dir = scandir( $this->sparx_dir . '/' . $post_id );

				rename( $this->sparx_dir . '/' . $post_id . '/' . $current_dir[2], $this->sparx_dir . '/' . $post_id . '/' . 'files' );

				update_post_meta( $post_id, '_sk_sparx_file_id', $file['id'] );
				update_post_meta( $post_id, '_sk_sparx_file_url', $sparx_url_folder . '/' . $post_id . '/files/' );

			}

		}

	}

	/**
	 * Hook for deleting sparx folder on delete sparx post.
	 *
	 * @since 1.0.0
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function delete_sparx_project( $post_id ) {
		global $post_type;

		// bail if not our post type
		if ( $post_type != $this->post_type ) {
			return false;
		}
		$file_id = get_field( '_sk_sparx_file_id', $post_id );
		if ( ! empty( $file_id ) ) {
			wp_delete_attachment( $file_id, true );
		}

		self::rmdir_and_contents( $this->sparx_dir . '/' . $post_id );

	}

	/**
	 * Removes a dir recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param $dir
	 *
	 * @return bool
	 */
	public static function rmdir_and_contents($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? self::rmdir_and_contents("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	/**
	 * Create dir for sparx files in upload dir.
	 *
	 * @since 1.0.0
	 *
	 */
	public function create_dir() {
		$upload_dir      = wp_upload_dir();
		$this->sparx_dir = $upload_dir['basedir'] . '/sparx';
		if ( ! is_dir( $this->sparx_dir ) ) {
			wp_mkdir_p( $this->sparx_dir );
		}

		// create an htaccess with direcotory index
		if(! file_exists( $this->sparx_dir . '/.htaccess' ) ){
			$content = 'DirectoryIndex index.html index.htm';
			$file = fopen( $this->sparx_dir . '/.htaccess', 'wb');
			fwrite( $file, $content );
			fclose( $file );
		}
	}

	/**
	 * Custom post type Sparx
	 *
	 * @since 1.0.0
	 *
	 */
	public function register_post_type() {

		$labels = array(
			'name'               => __( 'Sparx', 'sk' ),
			'singular_name'      => __( 'Sparx', 'sk' ),
			'menu_name'          => __( 'EA Sparx', 'sk' ),
			'name_admin_bar'     => __( 'Sparx', 'sk' ),
			'add_new'            => __( 'Skapa ny', 'sk' ),
			'add_new_item'       => __( 'Skapa ny', 'sk' ),
			'new_item'           => __( 'Ny', 'sk' ),
			'edit_item'          => __( 'Redigera', 'sk' ),
			'view_item'          => __( 'Visa', 'sk' ),
			'all_items'          => __( 'Alla', 'sk' ),
			'search_items'       => __( 'Sök', 'sk' ),
			'parent_item_colon'  => __( 'Förälder:', 'sk' ),
			'not_found'          => __( 'Hittade inga poster', 'sk' ),
			'not_found_in_trash' => __( 'Hittade inga poster i papperskorgen.', 'sk' )
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => true,
			'rewrite'             => array( 'slug' => $this->post_type ),
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'menu_icon'           => 'dashicons-portfolio',
			'supports'            => array( 'title' ),
			'exclude_from_search' => true
		);

		register_post_type( $this->post_type, $args );
	}


	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Sk_Sparx_Upload_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Sk_Sparx_Upload_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/sk-sparx-upload-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Sk_Sparx_Upload_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Sk_Sparx_Upload_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/sk-sparx-upload-admin.js', array( 'jquery' ), $this->version, false );

	}

}
