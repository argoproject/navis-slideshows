<?php
/**
 * Plugin Name: Navis Slideshows
 * Description: Slideshows that take advantage of the Slides jQuery plugin.
 * Version: 0.1
 * Author: Project Argo
 * License: GPLv2
*/
/*
    Copyright 2011 National Public Radio, Inc. 

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Navis_Slideshows {
    static $include_slideshow_deps;

    function __construct() {
        add_action( 'init', array( &$this, 'add_slideshow_header' ) );
        add_action('wp_head', array( &$this, 'argo_slideshow_css' ) );

        add_filter( 
            'wp_footer', array( &$this, 'conditionally_add_slideshow_deps' ) 
        );

        add_filter( 
            'post_gallery', array( &$this, 'handle_slideshow' ), 10, 2 
        );

        if ( ! is_admin() ) 
            return;

        add_action( 
            'save_post', array( &$this, 'tag_post_as_slideshow' ), 10, 2 
        );
        
        add_action( 'admin_menu', array(&$this, 'argo_slideshow_options'));
        
        add_action( 'admin_init', array(&$this, 'settings_init'));
    }


    function add_slideshow_header() {
        // slides-specific CSS
        $slides_css = plugins_url( 'css/slides.css', __FILE__ );
        wp_enqueue_style( 
            'navis-slides', $slides_css, array(), '1.0'
        );
        
		// Check if jQuery is already loaded. If not, load via Google jQuery API
		if ( ! jQuery ) {
			wp_register_script('jqslides', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js', true, '1.7.2', false);

			wp_enqueue_script('jqslides');
		}
    }
    
    //add slideshow width to header
    function argo_slideshow_css() { 
    $sswidth = intval(get_option( 'argoslideshow_default_width', 600 ));
    ?>
    	<style type="text/css">.navis-slideshow  {width: <?php echo($sswidth)?>px;} .navis-slideshow .slides_container div {width: <?php echo($sswidth)?>px;}</style>
    <?php
    }


    /**
     * Register and enqueue the javascript and CSS dependencies
     */
    function conditionally_add_slideshow_deps() {
        if ( ! self::$include_slideshow_deps )
            return;

        // jQuery slides plugin, available at http://slidesjs.com/
        $slides_src = plugins_url( 'js/slides.min.jquery.js', __FILE__ );
        wp_register_script( 
            'jquery-slides', $slides_src, array( 'jqslides' ), '1.1.7', true
        );
        wp_print_scripts( 'jquery-slides' );

        // our custom js
        $show_src = plugins_url( 'js/navis-slideshows.js', __FILE__ );
        wp_register_script( 
            'navis-slideshows', $show_src, array( 'jquery-slides' ), 
            '0.1', true
        );
        wp_print_scripts( 'navis-slideshows' );

    }


    /**
     * 
     * @uses global $post WP Post object
     */
    function handle_slideshow( $output, $attr ) {
        /**
         * Grab attachments
         */
        global $post;
        self::$include_slideshow_deps = true;

        if ( isset( $attr['orderby'] ) ) {
            $attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
            if ( !$attr['orderby'] )
                unset( $attr['orderby'] );
        }

        extract( shortcode_atts( array(
            'order'      => 'ASC',
            'orderby'    => 'menu_order ID',
            'id'         => $post->ID,
            'itemtag'    => 'dl',
            'icontag'    => 'dt',
            'captiontag' => 'dd',
            'columns'    => 3,
            'size'       => 'thumbnail',
        ), $attr ) );

        $id = intval( $id );
        // XXX: this could be factored out to a common function for getting
        // a post's images
        $attachments = get_children( array(
            'post_parent'    => $id, 
            'post_status'    => 'inherit', 
            'post_type'      => 'attachment', 
            'post_mime_type' => 'image', 
            'order'          => $order, 
            'orderby'        => $orderby
        ) );

        if ( empty( $attachments ) )
            return '';

        if ( is_feed() ) {
            $output = "\n";
            foreach ( $attachments as $id => $attachment )
                $output .= wp_get_attachment_link( $id, $size, true ) . "\n";
            return $output;
        }
            
        $postid = $post->ID;
        $plink = get_permalink();

        $output .= '
            <div id="slides-'.$postid.'" class="navis-slideshow">
            <p class="slide-nav">
            <a href="#" class="prev">Prev</a>
            <a href="#" class="next">Next</a>
            </p>

            <div class="slides_container">';

        /*-- Add images --*/
        $count = 0;
        $total = count( $attachments );
        foreach ( $attachments as $id => $attachment ) {
            $count++;
            $image = wp_get_attachment_image_src( $id, "large" );

            // Credit functionality provided by navis-media-credit plugin
            $credit = '';
            if ( function_exists( 'navis_get_media_credit' ) ) {
                $creditor = navis_get_media_credit( $id );
                $credit = $creditor->to_string();
            }

            $themeta = $attachment->post_title;
            $caption = $attachment->post_excerpt;
            $permalink = $attachment->ID;
            $slidediv = $postid . '-slide' . $count;

            // This embeds the first two slides directly in the page
            // and leaves placeholders for the remaining ones to be 
            // loaded just-in-time with JavaScript.
            if ( $count < 3 || $count == $total ) { 
                $output .= sprintf( 
                    '<div id="%s"><img src="%s" width="%d" height="%d" />', 
                    $slidediv, $image[0], $image[1], $image[2] 
                );
            } else {
                $output .= sprintf( 
                    '<div id="%s" data-src="%s*%d*%d" />',
                    $slidediv, $image[0], $image[1], $image[2]
                );
            }

            $output .= '<h6>';

            if ( isset( $credit ) )
                $output .= $credit;

            $output .= ' <a href="#" class="slide-permalink">permalink</a></h6>';
            $output .= '<p>'.$caption.'</p></div>';
        }
        $output .= '</div></div>';
        
        $this->postid = $postid;
        $this->permalink = $plink;
        $this->slide_count = $count;

        $output .= sprintf( 
            "<script>jQuery( document ).ready( function() { " .
                "loadSlideshow( %d, '%s', %d ) } );</script>", 
            $this->postid, $this->permalink, $this->slide_count 
        );

        return $output;
    }


    /**
     * Applies the Slideshow custom taxonomy term to a post when it contains
     * a gallery, and removes it when it doesn't.
     *
     * @param $post_ID ID of post
     * @param $post Post object
     * @todo add checks for post type, taxonomy existence, etc.
     */
    function tag_post_as_slideshow( $post_ID, $post, $taxonomy = 'feature' ) {
        $ss_term = get_term_by( 'slug', 'slideshow', $taxonomy );
        $post_terms = wp_get_object_terms( $post_ID, $taxonomy );
        
        $new_post_terms = array();
        // if we have a [gallery] shortcode in our post
        if ( stripos( $post->post_content, '[gallery' ) !== false ) {
            $seen_ss = false;
            foreach ( $post_terms as $post_term ) {
                if ( $post_term->term_id == $ss_term->term_id ) {
                    $seen_ss = true;
                }
                $new_post_terms[] = $post_term->slug;
            }

            if ( ! $seen_ss )
                $new_post_terms[] = $ss_term->slug;

        } else {
            $new_post_terms = array();
            foreach ( $post_terms as $post_term ) {
                if ( $post_term->term_id == $ss_term->term_id )
                    continue;

                $new_post_terms[] = $post_term->slug;
            }
        }

        wp_set_object_terms( $post_ID, $new_post_terms, $taxonomy );
    }
    
    //slideshow options page 
	
    function argo_slideshow_options() {
        add_options_page('ArgoSlideshow', 'Navis Slideshow', 'manage_options', 
                        'argoslideshow', array(&$this, 'render_options_page'));
    }
    
    function render_options_page() { ?>
        <h2>Navis Slideshow Options</h2>
        <form action="options.php" method="post">
            
            <?php settings_fields('argoslideshow'); ?>
            <?php do_settings_sections('argoslideshow'); ?>
            
            <p><input class="button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
            </form>
        <?php
    }
    
    function settings_init() {
        add_settings_section( 'argoslideshow', '',
            array(&$this, 'settings_section'), 'argoslideshow');
        
        add_settings_field('argoslideshow_default_width', 'Default slideshow width (px)',
            array(&$this, 'default_width_field'), 'argoslideshow', 'argoslideshow');
        register_setting('argoslideshow', 'argoslideshow_default_width');
        
    }
    
    function default_width_field() {
        $option = intval(get_option( 'argoslideshow_default_width', 600 ));
        echo "<input type='text' value='$option' name='argoslideshow_default_width' />";
    }
    
    function settings_section() {}
    
    // end slideshow options page    
}

new Navis_Slideshows;
