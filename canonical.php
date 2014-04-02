<?php
/**
 * Plugin Name: Canonical Pages
 * Description: Distribute the same page over multiple urls with a canonical tag
 * Version: 0.1
 * Author: Jeroen de Jong
 * Author URI: http://www.allict.nl
 */

class canonicalPage {

    const metaKey = '_canonical_page';

    public function __construct(){
        register_activation_hook( __FILE__, array('canonicalPage', 'canonical_page_activation'));
        register_deactivation_hook( __FILE__, array('canonicalPage', 'canonical_page_deactivation'));
        
        // fix the rewrites
        add_action( 'init', array( $this, 'register_canonical_rewrites' ) );
        
        // add metaboxes to pages
        add_action( 'add_meta_boxes', array($this, 'add_page_info_metaboxes') );
        add_action( 'save_post', array( $this, 'save' ) );
    }

    public function register_canonical_rewrites() {
        global $wpdb;

        // example line, where matches are used..
//        add_rewrite_rule('test\/designa/(.*)','index.php?pagename=magazine&args=$matches[1]','top');
                
        // get all _canonical_page meta values
        $values = $wpdb->get_results("
            SELECT post_id, meta_value
            FROM " . $wpdb->postmeta . " 
            WHERE meta_key = '" . canonicalPage::metaKey . "'
        ");
        
        foreach ($values as $result) {
            $new_slug = $result->meta_value;
        
            add_rewrite_rule($new_slug,'index.php?page_id=' . $result->post_id . '&canonical_page_id=' . $result->post_id,'top');
            add_rewrite_tag('%canonical_page_id%', '([0-9]+)');
        }
        
        // Then flush them
        flush_rewrite_rules(); // @todo: not sure if this should be done everytime..
        
        // by using the rewrite_tag above, you can use $wp_query->query_vars['canonical_page_id'] to get the page_id on the page/post
    }

    public function canonical_page_activation() {
        canonicalPage::register_canonical_rewrites();
        
        //@Todo: looks like they aren't really flushed, only if you click save on the Settings->Permalinks page after disabling the plugin..
    }
  
    public function canonical_page_deactivation() {
        flush_rewrite_rules();
    }
    
    public function add_page_info_metaboxes($post_type) {
        $post_types = array('post', 'page');     //limit meta box to certain post types
        if ( in_array( $post_type, $post_types )) {
            add_meta_box(
                'canonical_page', 
                __('Canonical pages', 'canonical_page'), 
                array($this, 'render_meta_box_content'), 
                'page', 
                'normal', 
                'default'
            );
        }
    }
    
    public function render_meta_box_content($post) {
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'myplugin_inner_custom_box', 'myplugin_inner_custom_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		$value = get_post_meta( $post->ID, canonicalPage::metaKey, true );

		// Display the form, using the current value.
		echo '<label for="' . canonicalPage::metaKey . '">';
		_e( 'Description for this field', 'myplugin_textdomain' );
		echo '</label> ';
		echo '<input type="text" id="' . canonicalPage::metaKey . '" name="' . canonicalPage::metaKey . '"';
        echo ' value="' . esc_attr( $value ) . '" size="25" />';
    }
    
	public function save( $post_id ) {
	
	    //@Todo: slug can only exist once!
	
		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['myplugin_inner_custom_box_nonce'] ) ) {
			return $post_id;
        }

		$nonce = $_POST['myplugin_inner_custom_box_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'myplugin_inner_custom_box' ) ) {
			return $post_id;
        }

		// If this is an autosave, our form has not been submitted,
        //     so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
        }

		// Check the user's permissions.
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
            }
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
            }
		}

		/* OK, its safe for us to save the data now. */

		// Sanitize the user input.
		$mydata = sanitize_text_field( $_POST[canonicalPage::metaKey] );

		// Update the meta field.
		update_post_meta( $post_id, canonicalPage::metaKey, $mydata );
		
		// redo the re-writes
		canonicalPage::register_canonical_rewrites();
	}
}

$ptcfp = new canonicalPage();