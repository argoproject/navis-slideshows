<?php
/**
 * Plugin Name: Navis Slideshows
 * Description: Slideshows that take advantage of the Slides jQuery plugin.
 * Version: 0.1
 * Author: Marc Lavallee and Wes Lindamood
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
    function __construct() {
        add_filter( 'post_gallery', 'handle_slideshow', 10, 2 );
        add_filter( 'the_posts', 'conditionally_add_slideshow_deps' );

        if ( ! is_admin() ) 
            return;

        add_action( 'save_post', 'tag_post_as_slideshow', 10, 2 );
    }

    /**
     * Register and enqueue the javascript and CSS dependencies
     */
    function conditionally_add_slideshow_deps( $posts ) {
        if ( empty( $posts ) )
            return $posts;

        $shortcode_found = false; 
        foreach ( $posts as $post ) {
            if ( stripos( $post->post_content, '[gallery' ) !== false ) {
                $shortcode_found = true; 
                break;
            }
        } 

        if ( $shortcode_found ) {
            // jQuery slides plugin, available at http://slidesjs.com/
            wp_enqueue_script( 
                'jquery-slides', 
                navis_get_theme_script_url( 'slides.min.jquery.js' ),
                array( 'jquery' ), '1.1.4', true
            );

            // slides-specific CSS
            wp_enqueue_style( 
                'slides',
                navis_get_theme_style_url( 'slides.css' ),
                array(), '1.0'
            );
        }

        return $posts;
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
        $count = 1;
        foreach ( $attachments as $id => $attachment ) {
            $image = wp_get_attachment_image_src($id, "large");
            $credit = argo_get_media_credit( $id );
            $themeta = $attachment->post_title;
            $caption = $attachment->post_excerpt;
            $permalink = $attachment->ID;
            $slidenum = $count; // - 1;
            if ( $count < 3 ) { // bake the first two images into the page
                $output .= '<div id="' . $postid . '-slide' . $slidenum . '"><img src="'.$image[0].'" width="'.$image[1].'" height="'.$image[2].'"/>';
            } else {
                $output .= '<div id="' . $postid . '-slide' . $slidenum . '" data-src="' . $image[0] . '*' . $image[1] . '*' . $image[2] .'">';
            }
            $output .= '<h6>'.$credit.' <a href="#" class="slide-permalink">permalink</a></h6>';
            $output .= '<p>'.$caption.'</p></div>';
            $count++;
        }
        $output .= '</div></div>';

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
}

new Navis_Slideshows;
