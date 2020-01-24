<?php
/*
Plugin Name: Dezi for WordPress
Plugin URI: https://github.com/APMG/dezi-for-wordpress
Description: Indexes, removes, and updates documents in the Dezi search engine.
Version: 0.1.0
Author: Peter Karman
Author URI: http://apmg.github.com/
License: MIT
*/
/*
    Copyright (c) 2012 American Public Media Group

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
*/

/* this project started as a fork of solr-for-wordpress */

global $wp_version, $version;

$version = '0.1.0';

$errmsg = __('Dezi for WordPress requires WordPress 3.0 or greater. ', 'dezi4wp');
if (version_compare($wp_version, '3.0', '<')) {
    exit ($errmsg);
}

$dezi_client_path = realpath(dirname(__FILE__));
set_include_path(get_include_path() . PATH_SEPARATOR . $dezi_client_path);
require_once 'vendor/autoload.php';


/**
 *
 *
 * @return unknown
 */
function dezi4w_get_option() {
    $indexall = FALSE;
    $option = 'plugin_dezi4w_settings';
    if (is_multisite()) {
        $plugin_dezi4w_settings = get_site_option($option);
        $indexall = $plugin_dezi4w_settings['dezi4w_index_all_sites'];
    }

    if ($indexall) {
        return get_site_option($option);
    } else {
        return get_option($option);
    }
}


/**
 *
 *
 * @param unknown $optval
 */
function dezi4w_update_option($optval) {
    $indexall = FALSE;
    $option = 'plugin_dezi4w_settings';
    if (is_multisite()) {
        $plugin_dezi4w_settings = get_site_option($option);
        $indexall = $plugin_dezi4w_settings['dezi4w_index_all_sites'];
    }

    if ($indexall) {
        update_site_option($option, $optval);
    } else {
        update_option($option, $optval);
    }
}


/**
 * Connect to the dezi service
 *
 * @param unknown $server_id (optional) string/int its either master or array index
 * @return dezi service object
 */
function dezi4w_get_dezi($server_id = NULL) {
    // get the connection options
    $plugin_dezi4w_settings = dezi4w_get_option();
    //if the provided server_id does not exist use the default id 'master'
    //echo '<pre>';
    //print_r($plugin_dezi4w_settings);
    //echo '</pre>';
    if (!isset($plugin_dezi4w_settings['dezi4w_server']['info'][$server_id])) {
        $server_id = $plugin_dezi4w_settings['dezi4w_server']['type']['update'];
    }
    $host = $plugin_dezi4w_settings['dezi4w_server']['info'][$server_id]['host'];
    $port = $plugin_dezi4w_settings['dezi4w_server']['info'][$server_id]['port'];
    $path = $plugin_dezi4w_settings['dezi4w_server']['info'][$server_id]['path'];
    $un   = $plugin_dezi4w_settings['dezi4w_server']['info'][$server_id]['username'];
    $pw   = $plugin_dezi4w_settings['dezi4w_server']['info'][$server_id]['password'];
    // double check everything has been set
    if ( ! ($host and $port and $path) ) {
        error_log("host, port or path are empty, host:$host, port:$port, path:$path");
        return NULL;
    }

    // create the dezi client object
    if ($path == '/') $path = "";
    $uri = "$host:$port$path";
    if (!preg_match('/^https?:/', $uri)) {
        $uri = 'http://'.$uri;
    }
    $construct = array('server'=>$uri);
    if ($un && $pw) {
        $construct['username'] = $un;
        $construct['password'] = $pw;
    }
    $dezi = new \Dezi\Client($construct);

    return $dezi;
}


/**
 * check if the server by pinging it
 *
 * @param server  if wanting to ping a different
 *        server than default provide name
 * @param unknown $server_id (optional)
 * @return boolean
 */
function dezi4w_ping_server($server_id = NULL) {
    $dezi = dezi4w_get_dezi($server_id);
    $ping = FALSE;
    // if we want to check if the server is alive, ping it
    if ($dezi->ping()) {
        $ping = TRUE;
    }
    return $ping;
}


/**
 *
 *
 * @param unknown $post_info
 * @param unknown $domain    (optional)
 * @param unknown $path      (optional)
 * @return unknown
 */
function dezi4w_build_document( $post_info, $domain = NULL, $path = NULL) {
    global $blog_id;
    global $current_blog;

    $doc = NULL;
    $plugin_dezi4w_settings = dezi4w_get_option();
    $exclude_ids = $plugin_dezi4w_settings['dezi4w_exclude_pages'];
    $categoy_as_taxonomy = $plugin_dezi4w_settings['dezi4w_cat_as_taxo'];
    $index_comments = $plugin_dezi4w_settings['dezi4w_index_comments'];
    $index_custom_fields = $plugin_dezi4w_settings['dezi4w_index_custom_fields'];

    if ($post_info) {

        // check if we need to exclude this document
        if (is_multisite() && in_array($current_blog->domain . $post_info->ID, (array)$exclude_ids)) {
            return NULL;
        } elseif ( !is_multisite() && in_array($post_info->ID, (array)$exclude_ids) ) {
            return NULL;
        }

        $doc = new \Dezi\Doc(array('uri'=>urlencode(get_permalink( $post_info->ID ))));
        $auth_info = get_userdata( $post_info->post_author );

        // wpmu specific info
        if (is_multisite()) {
            // if we get here we expect that we've "switched" what blog we're running
            // as

            if ($domain == NULL)
                $domain = $current_blog->domain;

            if ($path == NULL)
                $path = $current_blog->path;


            $blogid = get_blog_id_from_url($domain, $path);
            $doc->set_field( 'id', $domain . $path . $post_info->ID );
            $doc->set_field( 'permalink', get_blog_permalink($blogid, $post_info->ID));
            $doc->set_field( 'blogid', $blogid );
            $doc->set_field( 'blogdomain', $domain );
            $doc->set_field( 'blogpath', $path );
            $doc->set_field( 'wp', 'multisite');
        } else {
            $doc->set_field( 'id', $post_info->ID );
            $doc->set_field( 'permalink', get_permalink( $post_info->ID ) );
            $doc->set_field( 'wp', 'wp');
        }

        $numcomments = 0;
        if ($index_comments) {
            $comments = get_comments("status=approve&post_id={$post_info->ID}");
            $comment_array = array();
            foreach ($comments as $comment) {
                $comment_array[] = $comment->comment_content;
                $numcomments += 1;
            }
            $doc->set_field('comments', $comment_array);
        }

        $doc->set_field( 'swishtitle', $post_info->post_title );
        $doc->set_field( 'body', strip_tags($post_info->post_content) );

        // rawcontent strips out characters lower than 0x20
        $doc->set_field( 'rawcontent', strip_tags(preg_replace('/[^(\x20-\x7F)\x0A]*/', '', $post_info->post_content)));

        // contentnoshortcodes also strips characters below 0x20 but also strips shortcodes
        // used in WP to add images or other content, useful if you're pulling this data
        // into another system
        //
        // For example
        //   [caption id="attachment_92495" align="alignright" width="160" caption="Duane Sand"][/caption] FARGO - Republican U.S. Senate...
        //
        // Will become
        //   FARGO - Republican U.S. Senate...
        $doc->set_field( 'contentnoshortcodes', strip_tags(preg_replace('/[^(\x20-\x7F)\x0A]*/', '', strip_tags(strip_shortcodes($post_info->post_content)))));
        $doc->set_field( 'numcomments', $numcomments );
        $doc->set_field( 'author', $auth_info->display_name );
        $doc->set_field( 'author_s', get_author_posts_url($auth_info->ID, $auth_info->user_nicename));
        $doc->set_field( 'type', $post_info->post_type );
        $doc->set_field( 'date', dezi4w_format_date($post_info->post_date_gmt) );
        $doc->set_field( 'modified', dezi4w_format_date($post_info->post_modified_gmt) );
        $doc->set_field( 'displaydate', $post_info->post_date );
        $doc->set_field( 'displaymodified', $post_info->post_modified );
        $doc->mtime = strtotime($post_info->post_modified);

        $categories = get_the_category($post_info->ID);
        if ( ! $categories == NULL ) {
            $cat_array = array();
            foreach ( $categories as $category ) {
                if ($categoy_as_taxonomy) {
                    $cat_array[] = get_category_parents($category->cat_ID, FALSE, '^^');
                } else {
                    $cat_array[] = $category->cat_name;
                }
            }
            $doc->set_field('categories', $cat_array);
        }

        //get all the taxonomy names used by wp
        $taxonomies = (array)get_taxonomies(array('_builtin'=>FALSE), 'names');
        foreach ($taxonomies as $parent) {
            $terms = get_the_terms( $post_info->ID, $parent );
            if ((array) $terms === $terms) {
                //we are creating *_taxonomy as dynamic fields using our schema
                //so lets set up all our taxonomies in that format
                $parent = $parent."_taxonomy";
                $term_array = array();
                foreach ($terms as $term) {
                    $term_array[] = $term->name;
                }
                $doc->set_field($parent, $term_array);
            }
        }

        $tags = get_the_tags($post_info->ID);
        if ( ! $tags == NULL ) {
            $tag_array = array();
            foreach ( $tags as $tag ) {
                $tag_array[] = $tag->name;
            }
            $doc->set_field('tags', $tag_array);
        }

        if (count($index_custom_fields)>0 && count($custom_fields = get_post_custom($post_info->ID))) {
            foreach ((array)$index_custom_fields as $field_name ) {
                $field = (array)$custom_fields[$field_name];
                $vals = array($field_name.'_str' => array(), $field_name.'_srch' => array());
                foreach ( $field as $key => $value ) {
                    $vals[$field_name . '_str'][] = $value;
                    $vals[$field_name . '_srch'][] = $value;
                }
                foreach ($vals as $k=>$v) {
                    $doc->set_field($k, $v);
                }
            }
        }
    } else {
        // this will fire during blog sign up on multisite, not sure why
        _e('Post Information is NULL', 'dezi4wp');
    }
    error_log("built document for $blog_id - $domain$path with title " .  $post_info->post_title .
        " and status of " . $post_info->post_status);
    return $doc;
}


/**
 *
 *
 * @param unknown $thedate
 * @return unknown
 */
function dezi4w_format_date( $thedate ) {
    $datere = '/(\d{4}-\d{2}-\d{2})\s(\d{2}:\d{2}:\d{2})/';
    $replstr = '${1}T${2}Z';
    return preg_replace($datere, $replstr, $thedate);
}


/**
 *
 *
 * @param unknown $documents
 * @param unknown $commit    (optional)
 * @param unknown $optimize  (optional)
 */
function dezi4w_post( $documents, $commit = TRUE, $optimize = FALSE) {
    try {
        $dezi = dezi4w_get_dezi();
        if ( ! $dezi == NULL ) {

            if ($documents) {
                error_log("posting " . count($documents) . " documents for blog:" . get_bloginfo('wpurl'));
                foreach ($documents as $doc) {
                    $dezi->index($doc);
                }
            }

            if ($commit) {
                if ($dezi->server_supports_transactions()) {
                    $dezi->commit();
                }
            }

            if ($optimize) {
                //$dezi->optimize(); // TODO?
            }
        }
        else {
            error_log("failed to get a dezi instance created");
        }
    } catch ( Exception $e ) {
        error_log("ERROR: " . $e->getMessage());
        //echo $e->getMessage();
    }
}


/**
 *
 */
function dezi4w_optimize() {
    try {
        $dezi = dezi4w_get_dezi();
        if ( ! $dezi == NULL ) {
            //$dezi->optimize(); // TODO
        }
    } catch ( Exception $e ) {
        error_log($e->getMessage());
    }
}


/**
 *
 *
 * @param unknown $doc_id
 */
function dezi4w_delete( $doc_id ) {
    try {
        $dezi = dezi4w_get_dezi();
        if ( ! $dezi == NULL ) {
            error_log("dezi->delete doc_id=$doc_id"); // TODO
            //$dezi->deleteById( $doc_id );
            //$dezi->commit();
        }
    } catch ( Exception $e ) {
        error_log($e->getMessage());
    }
}


/**
 *
 */
function dezi4w_delete_all() {
    try {
        $dezi = dezi4w_get_dezi();
        if ( ! $dezi == NULL ) {
            error_log("dezi->delete_all still TODO");
            //$dezi->deleteByQuery( '*:*' );
            //$dezi->commit();
        }
    } catch ( Exception $e ) {
        echo $e->getMessage();
    }
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_delete_blog($blogid) {
    try {
        $dezi = dezi4w_get_dezi();
        if ( ! $dezi == NULL ) {
            error_log("dezi->delete blogid:$blogid still TODO");
            //$dezi->deleteByQuery( "blogid:{$blogid}" );
            //$dezi->commit();
        }
    } catch ( Exception $e ) {
        echo $e->getMessage();
    }
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_load_blog_all($blogid) {
    global $wpdb;
    $documents = array();
    $cnt = 0;
    $batchsize = 10;

    $bloginfo = get_blog_details($blogid, FALSE);

    if ($bloginfo->public && !$bloginfo->archived && !$bloginfo->spam && !$bloginfo->deleted) {
        $postids = $wpdb->get_results("SELECT ID FROM {$wpdb->base_prefix}{$blogid}_posts WHERE post_status = 'publish';");
        for ($idx = 0; $idx < count($postids); $idx++) {
            $postid = $ids[$idx];
            $documents[] = dezi4w_build_document( get_blog_post($blogid, $postid->ID), $bloginfo->domain, $bloginfo->path );
            $cnt++;
            if ($cnt == $batchsize) {
                dezi4w_post($documents);
                $cnt = 0;
                $documents = array();
            }
        }

        if ($documents) {
            dezi4w_post($documents);
        }
    }
}


/**
 *
 *
 * @param unknown $post_id
 */
function dezi4w_handle_modified( $post_id ) {
    global $current_blog;
    $post_info = get_post( $post_id );
    $plugin_dezi4w_settings = dezi4w_get_option();
    $index_pages = $plugin_dezi4w_settings['dezi4w_content']['index']['page'];
    $index_posts = $plugin_dezi4w_settings['dezi4w_content']['index']['post'];

    dezi4w_handle_status_change( $post_id, $post_info );

    if (($index_pages && $post_info->post_type == 'page' && $post_info->post_status == 'publish') ||
        ($index_posts && $post_info->post_type == 'post' && $post_info->post_status == 'publish')) {

        // make sure this blog is not private or a spam if indexing on a multisite install
        if (is_multisite() && ($current_blog->public != 1 || $current_blog->spam == 1 || $current_blog->archived == 1)) {
            return;
        }

        $docs = array();
        $doc = dezi4w_build_document( $post_info , $current_blog->domain , $current_blog->path );
        if ( $doc ) {
            $docs[] = $doc;
            dezi4w_post( $docs );
        }
    }
}


/**
 *
 *
 * @param unknown $post_id
 * @param unknown $post_info (optional)
 */
function dezi4w_handle_status_change( $post_id, $post_info = null ) {
    global $current_blog;

    if ( ! $post_info ) {
        $post_info = get_post( $post_id );
    }

    $plugin_dezi4w_settings = dezi4w_get_option();
    $private_page = $plugin_dezi4w_settings['dezi4w_private_page'];
    $private_post = $plugin_dezi4w_settings['dezi4w_private_post'];

    if ( ($private_page && $post_info->post_type == 'page') || ($private_post && $post_info->post_type == 'post') ) {

        /**
         * We need to check if the status of the post has changed.
         * Inline edits won't have the prev_status of original_post_status,
         * instead we check of the _inline_edit variable is present in the $_POST variable
         */
        if ( ($_POST['prev_status'] == 'publish' || $_POST['original_post_status'] == 'publish' ||
                ( isset( $_POST['_inline_edit'] ) && !empty( $_POST['_inline_edit']) ) )  &&
            ($post_info->post_status == 'draft' || $post_info->post_status == 'private') ) {

            if (is_multisite()) {
                dezi4w_delete( $current_blog->domain . $current_blog->path . $post_info->ID );
            } else {
                dezi4w_delete( $post_info->ID );
            }
        }
    }
}


/**
 *
 *
 * @param unknown $post_id
 */
function dezi4w_handle_delete( $post_id ) {
    global $current_blog;
    $post_info = get_post( $post_id );
    error_log("deleting post titled '" . $post_info->post_title . "' for " . $current_blog->domain . $current_blog->path);
    $plugin_dezi4w_settings = dezi4w_get_option();
    $delete_page = $plugin_dezi4w_settings['dezi4w_delete_page'];
    $delete_post = $plugin_dezi4w_settings['dezi4w_delete_post'];

    if ( ($delete_page && $post_info->post_type == 'page') || ($delete_post && $post_info->post_type == 'post') ) {
        if (is_multisite()) {
            dezi4w_delete( $current_blog->domain . $current_blog->path . $post_info->ID );
        } else {
            dezi4w_delete( $post_info->ID );
        }
    }
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_deactivate_blog($blogid) {
    dezi4w_delete_blog($blogid);
}


/**
 *
 */
function dezi4w_deactivate_plugin() {
    // option_name=plugin_dezi4w_settings
    global $wpdb;
    $table_name = $wpdb->prefix . "options";
    $sql = "delete from $table_name where option_name='plugin_dezi4w_settings'";
    $result = $wpdb->query($sql);
    if ($result === false) {
        error_log("deactivate clean up failed. db error: " . $wpdb->last_error);
    }
    else {
        error_log("dezi-for-wordpress is deactivated");
    }
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_activate_blog($blogid) {
    dezi4w_apply_config_to_blog($blogid);
    dezi4w_load_blog_all($blogid);
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_archive_blog($blogid) {
    dezi4w_delete_blog($blogid);
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_unarchive_blog($blogid) {
    dezi4w_apply_config_to_blog($blogid);
    dezi4w_load_blog_all($blogid);
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_spam_blog($blogid) {
    dezi4w_delete_blog($blogid);
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_unspam_blog($blogid) {
    dezi4w_apply_config_to_blog($blogid);
    dezi4w_load_blog_all($blogid);
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_delete_blog($blogid) {
    dezi4w_delete_blog($blogid);
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_handle_new_blog($blogid) {
    dezi4w_apply_config_to_blog($blogid);
    dezi4w_load_blog_all($blogid);
}


/**
 * This function indexes all the different content types.
 * This does not include attachments and revisions
 *
 * @param unknown $prev
 * @param unknown $type (optional) what content to index: post type machine name or all content.
 * @return string (json reply)
 */
function dezi4w_load_all_posts($prev, $type = 'all') {
    global $wpdb, $current_blog, $current_site;
    $documents = array();
    $cnt = 0;
    $batchsize = 250;
    $last = "";
    $found = FALSE;
    $end = FALSE;
    $percent = 0;

    //multisite logic is decided dezi4w_get_option
    $plugin_dezi4w_settings = dezi4w_get_option();
    $blog_id = $blog->blog_id;

    //retrieve the post types that can be indexed
    $indexable_content = $plugin_dezi4w_settings['dezi4w_content']['index'];
    $indexable_type = array_keys($indexable_content);
    //if the provided $type is not allowed to be index, lets stop
    if (!in_array($type, $indexable_type) && $type != 'all') {
        return false;
    }
    //lets setup our where clause to find the appropriate posts
    $where_and = ($type == 'all') ?"AND post_type IN ('".implode("', '", $indexable_type). "')" : " AND post_type = '$type'";
    if ($plugin_dezi4w_settings['dezi4w_index_all_sites']) {

        // there is potential for this to run for an extended period of time, depending on the # of blgos
        error_log("starting batch import, setting max execution time to unlimited");
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        // get a list of blog ids
        $bloglist = $wpdb->get_col("SELECT * FROM {$wpdb->base_prefix}blogs WHERE spam = 0 AND deleted = 0", 0);
        error_log("pushing posts from " . count($bloglist) . " blogs into Dezi");
        foreach ($bloglist as $bloginfo) {

            // for each blog we need to import we get their id
            // and tell wordpress to switch to that blog
            $blog_id = trim($bloginfo);

            error_log("switching to blogid $blog_id");

            // attempt to save some memory by flushing wordpress's cache
            wp_cache_flush();

            // everything just works better if we tell wordpress
            // to switch to the blog we're using, this is a multi-site
            // specific function
            switch_to_blog($blog_id);

            // now we actually gather the blog posts

            $postids = $wpdb->get_results("SELECT ID FROM {$wpdb->base_prefix}{$bloginfo}_posts WHERE post_status = 'publish' $where_and ORDER BY ID;");
            $postcount = count($postids);
            error_log("building $postcount documents for " . substr(get_bloginfo('wpurl'), 7));
            for ($idx = 0; $idx < $postcount; $idx++) {

                $postid = $postids[$idx]->ID;
                $last = $postid;
                $percent = (floatval($idx) / floatval($postcount)) * 100;
                if ($prev && !$found) {
                    if ($postid === $prev) {
                        $found = TRUE;
                    }

                    continue;
                }

                if ($idx === $postcount - 1) {
                    $end = TRUE;
                }

                // using wpurl is better because it will return the proper
                // URL for the blog whether it is a subdomain install or otherwise
                $documents[] = dezi4w_build_document( get_blog_post($blog_id, $postid), substr(get_bloginfo('wpurl'), 7), $current_site->path );
                $cnt++;
                if ($cnt == $batchsize) {
                    dezi4w_post( $documents, false, false);
                    dezi4w_post(false, true, false);
                    wp_cache_flush();
                    $cnt = 0;
                    $documents = array();
                }
            }
            // post the documents to Dezi
            // and reset the batch counters
            dezi4w_post( $documents, false, false);
            dezi4w_post(false, true, false);
            $cnt = 0;
            $documents = array();
            error_log("finished building $postcount documents for " . substr(get_bloginfo('wpurl'), 7));
            wp_cache_flush();
        }

        // done importing so lets switch back to the proper blog id
        restore_current_blog();
    } else {
        $posts = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' $where_and ORDER BY ID;" );
        $postcount = count($posts);
        for ($idx = 0; $idx < $postcount; $idx++) {
            $postid = $posts[$idx]->ID;
            $last = $postid;
            $percent = (floatval($idx) / floatval($postcount)) * 100;
            if ($prev && !$found) {
                if ($postid === $prev) {
                    $found = TRUE;
                }
                continue;
            }

            if ($idx === $postcount - 1) {
                $end = TRUE;
            }
            $documents[] = dezi4w_build_document( get_post($postid) );
            $cnt++;
            if ($cnt == $batchsize) {
                dezi4w_post( $documents, FALSE, FALSE);
                $cnt = 0;
                $documents = array();
                wp_cache_flush();
                break;
            }
        }
    }

    if ( $documents ) {
        dezi4w_post( $documents , FALSE, FALSE);
    }

    if ($end) {
        dezi4w_post(FALSE, TRUE, FALSE);
        printf("{\"type\": \"%s\", \"last\": \"%s\", \"end\": true, \"percent\": \"%.2f\"}", $type, $last, $percent);
    } else {
        printf("{\"type\": \"%s\", \"last\": \"%s\", \"end\": false, \"percent\": \"%.2f\"}", $type, $last, $percent);
    }
}


/**
 *
 */
function dezi4w_search_form() {
    $sort = $_GET['sort'];
    $order = $_GET['order'];
    $server = isset($_GET['server']) ? $_GET['server'] : null;


    if ($sort == 'date') {
        $sortval = __('<option value="score">Score</option><option value="date" selected="selected">Date</option><option value="modified">Last Modified</option>');
    } elseif ($sort == 'modified') {
        $sortval = __('<option value="score">Score</option><option value="date">Date</option><option value="modified" selected="selected">Last Modified</option>');
    } else {
        $sortval = __('<option value="score" selected="selected">Score</option><option value="date">Date</option><option value="modified">Last Modified</option>');
    }

    if ($order == 'asc') {
        $orderval = __('<option value="desc">Descending</option><option value="asc" selected="selected">Ascending</option>');
    } else {
        $orderval = __('<option value="desc" selected="selected">Descending</option><option value="asc">Ascending</option>');
    }
    //if server id has been defined keep hold of it
    $serverval = "";
    if ($server) {
        $serverval = '<input name="server" type="hidden" value="'.$server.'" />';
    }
    $form = __('<form name="searchbox" method="get" id="searchbox" action=""><input type="text" id="qrybox" name="s" value="%s"/><input type="submit" id="searchbtn" /><label for="sortselect" id="sortlabel">Sort By:</label><select name="sort" id="sortselect">%s</select><label for="orderselect" id="orderlabel">Order By:</label><select name="order" id="orderselect">%s</select>%s</form>');

    printf($form, htmlspecialchars(stripslashes($_GET['s'])), $sortval, $orderval, $serverval);
}


/**
 *
 *
 * @return unknown
 */
function dezi4w_search_results() {
    $qry = stripslashes($_GET['s']);
    $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
    $count = isset($_GET['count']) ? $_GET['count'] : 25;
    $fq = isset($_GET['fq']) ? $_GET['fq'] : null;
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'score';
    $order = isset($_GET['order']) ? $_GET['order'] : 'desc';
    $isdym = isset($_GET['isdym']) ? $_GET['isdym'] : null;
    $server = isset($_GET['server']) ? $_GET['server'] : null;

    $plugin_dezi4w_settings = dezi4w_get_option();
    $output_info = $plugin_dezi4w_settings['dezi4w_output_info'];
    $output_pager = $plugin_dezi4w_settings['dezi4w_output_pager'];
    $output_facets = $plugin_dezi4w_settings['dezi4w_output_facets'];
    $results_per_page = $plugin_dezi4w_settings['dezi4w_num_results'];
    $categoy_as_taxonomy = $plugin_dezi4w_settings['dezi4w_cat_as_taxo'];
    $dym_enabled = $plugin_dezi4w_settings['dezi4w_enable_dym'];
    $out = array();
    $out['hits'] = "0"; // default

    if ( ! $qry ) {
        $qry = '';
    }
    //if server value has been set lets set it up here
    // and add it to all the search urls henceforth
    if ($server) {
        $serverval = '&server='.$server;
    }
    // set some default values
    if ( ! $offset ) {
        $offset = 0;
    }

    // only use default if not specified in post information
    if ( ! $count ) {
        $count = $results_per_page;
    }

    if ( ! $fq ) {
        $fq = '';
    }

    if ( $sort && $order ) {
        $sortby = $sort . ' ' . $order;
    } else {
        $sortby = '';
        $order = '';
    }

    if ( ! $isdym ) {
        $isdym = 0;
    }

    $fqstr = '';
    $fqitms = explode('\|\|', stripslashes($fq));
    $selectedfacets = array();
    foreach ($fqitms as $fqitem) {
        if ($fqitem) {
            $splititm = explode(':', $fqitem, 2);
            $selectedfacet = array();
            $selectedfacet['name'] = sprintf(__("%s:%s"), ucwords(preg_replace('/_str$/i', '', $splititm[0])), str_replace("^^", "/", $splititm[1]));
            $removelink = '';
            foreach ($fqitms as $fqitem2) {
                if ($fqitem2 && !($fqitem2 === $fqitem)) {
                    $splititm2 = explode(':', $fqitem2, 2);
                    $removelink = $removelink . urlencode('||') . $splititm2[0] . ':' . urlencode($splititm2[1]);
                }
            }

            if ($removelink) {
                $selectedfacet['removelink'] = htmlspecialchars(sprintf(__("?s=%s&fq=%s"), urlencode($qry), $removelink));
            } else {
                $selectedfacet['removelink'] = htmlspecialchars(sprintf(__("?s=%s"), urlencode($qry)));
            }
            //if server is set add it on the end of the url
            $selectedfacet['removelink'] .=$serverval;

            $fqstr = $fqstr . urlencode('||') . $splititm[0] . ':' . urlencode($splititm[1]);

            $selectedfacets[] = $selectedfacet;
        }
    }

    if ($qry) {
        $response = dezi4w_query( $qry, $offset, $count, $fqitms, $sortby, $server );

        if ($response) {

            if ($output_info) {
                $out['hits']  = sprintf(__("%d"), $response->total);
                $out['qtime'] = $response->search_time;
            }

            if ($output_pager) {
                // calculate the number of pages
                $numpages = ceil($response->total / $count);
                $currentpage = ceil($offset / $count) + 1;
                $pagerout = array();

                if ($numpages == 0) {
                    $numpages = 1;
                }

                foreach (range(1, $numpages) as $pagenum) {
                    if ( $pagenum != $currentpage ) {
                        $offsetnum = ($pagenum - 1) * $count;
                        $pageritm = array();
                        $pageritm['page'] = sprintf(__("%d"), $pagenum);
                        $pagerlink = sprintf(__("?s=%s&offset=%d&count=%d"), urlencode($qry), $offsetnum, $count);
                        if ($order != "")
                            $pagerlink .= sprintf("&order=%s", $order);

                        if ($sort != "")
                            $pagerlink .= sprintf("&sort=%s", $sort);

                        if ($fqstr) $pagerlink .= '&fq=' . $fqstr;
                        $pageritm['link'] = htmlspecialchars($pagerlink);
                        //if server is set add it on the end of the url
                        $selectedfacet['removelink'] .=$serverval;
                        $pagerout[] = $pageritm;
                    } else {
                        $pageritm = array();
                        $pageritm['page'] = sprintf(__("%d"), $pagenum);
                        $pageritm['link'] = "";
                        $pagerout[] = $pageritm;
                    }
                }

                $out['pager'] = $pagerout;
            }

            if ($output_facets) {
                // handle facets
                $facetout = array();

                if ($response->facets) {
                    foreach ($response->facets as $facetfield => $facet) {
                        //echo '<pre>';
                        //print_r($facet);
                        //echo '</pre>';
                        $facetinfo = array();
                        $facetitms = array();
                        $facetinfo['name'] = ucwords(preg_replace('/_str$/i', '', $facetfield));

                        // categories is a taxonomy
                        if ($categoy_as_taxonomy && $facetfield == 'categories') {
                            // generate taxonomy and counts
                            $taxo = array();
                            foreach ($facet as $facet_instance) {
                                $taxovals = explode('^^', rtrim($facet_instance['term'], '^^'));
                                $taxo = dezi4w_gen_taxo_array($taxo, $taxovals);
                            }
                            $fqstr_safe = $fqstr;
                            if (isset($serverval)) {
                                $fqstr_safe .= $serverval;
                            }
                            $facetitms = dezi4w_get_output_taxo($facet, $taxo, '', $fqstr_safe, $facetfield);

                        }
                        else {
                            foreach ($facet as $facet_instance) {
                                $facetitm = array();
                                $facetitm['count'] = sprintf(__("%d"), $facet_instance['count']);
                                $facetitm['link'] = htmlspecialchars(sprintf(__('?s=%s&fq=%s:%s%s', 'dezi4wp'), urlencode($qry), $facetfield, urlencode('"' . $facet_instance['term'] . '"'), $fqstr));
                                //if server is set add it on the end of the url
                                if (isset($serverval)) {
                                    $facetitm['link'] .= $serverval;
                                }
                                $facetitm['name'] = $facet_instance['term'];
                                $facetitms[] = $facetitm;
                            }
                        }

                        $facetinfo['items'] = $facetitms;
                        $facetout[$facetfield] = $facetinfo;
                    }
                }

                $facetout['selected'] = $selectedfacets;
                $out['facets'] = $facetout;
            }

            $resultout = array();

            if ($response->total != 0) {
                //echo '<pre>';
                foreach ( $response->results as $doc ) {
                    //print_r($doc);
                    $resultinfo = array();
                    $docid = strval(array_shift($doc->get_field('id')));
                    $resultinfo['permalink'] = array_shift($doc->get_field('permalink'));
                    $resultinfo['title'] = $doc->title;
                    $resultinfo['author'] = array_shift($doc->get_field('author'));
                    $resultinfo['authorlink'] = htmlspecialchars(array_shift($doc->get_field('author_s')));
                    $resultinfo['numcomments'] = array_shift($doc->get_field('numcomments'));
                    $resultinfo['date'] = array_shift($doc->get_field('displaydate'));

                    if ($doc->numcomments === 0) {
                        $resultinfo['comment_link'] = array_shift($doc->get_field('permalink')) . "#respond";
                    } else {
                        $resultinfo['comment_link'] = array_shift($doc->get_field('permalink')) . "#comments";
                    }

                    $resultinfo['score'] = $doc->score;
                    $resultinfo['id'] = $docid;
                    $resultinfo['teaser'] = $doc->summary;
                    
                    // add whatever other fields were returned if not already defined
                    foreach ($response->fields as $fname) {
                        if (!isset($resultinfo[$fname])) {
                            $resultinfo[$fname] = $doc->get_field($fname);
                        }
                    }
                    
                    /*
                    $docteaser = $teasers[$docid];
                    if ($docteaser->content) {
                        $resultinfo['teaser'] = sprintf(__("...%s..."), implode("...", $docteaser->content));
                    } else {
                        $words = explode(' ', $doc->content);
                        $teaser = implode(' ', array_slice($words, 0, 30));
                        $resultinfo['teaser'] = sprintf(__("%s..."), $teaser);
                    }
                    */
                    $resultout[] = $resultinfo;
                }
                //echo '</pre>';
            }
            $out['results'] = $resultout;
        }
    }

    // pager and results count helpers
    $out['query'] = htmlspecialchars($qry);
    $out['offset'] = strval($offset);
    $out['count'] = strval($count);
    $out['firstresult'] = strval($offset + 1);
    $out['lastresult'] = strval(min($offset + $count, $out['hits']));
    $out['sortby'] = $sortby;
    $out['order'] = $order;
    if (!isset($serverval)) {
        $serverval = "";
    }
    $out['sorting'] = array(
        'scoreasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=score&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'scoredesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=score&order=desc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'dateasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=date&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'datedesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=date&order=desc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'modifiedasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=modified&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'modifieddesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=modified&order=desc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'commentsasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=numcomments&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
        'commentsdesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=numcomments&order=desc%s', urlencode($qry), stripslashes($fq), $serverval))
    );

    return $out;
}


/**
 *
 *
 * @param unknown $items
 * @param unknown $pre          (optional)
 * @param unknown $post         (optional)
 * @param unknown $before       (optional)
 * @param unknown $after        (optional)
 * @param unknown $nestedpre    (optional)
 * @param unknown $nestedpost   (optional)
 * @param unknown $nestedbefore (optional)
 * @param unknown $nestedafter  (optional)
 */
function dezi4w_print_facet_items($items, $pre = "<ul>", $post = "</ul>", $before = "<li>", $after = "</li>",
    $nestedpre = "<ul>", $nestedpost = "</ul>", $nestedbefore = "<li>", $nestedafter = "</li>") {
    if (!$items) {
        return;
    }
    printf(__("%s\n"), $pre);
    foreach ($items as $item) {
        printf(__("%s<a href=\"%s\">%s (%s)</a>%s\n"), $before, $item["link"], $item["name"], $item["count"], $after);
        $item_items = isset($item["items"]) ? true : false;

        if ($item_items) {
            dezi4w_print_facet_items($item["items"], $nestedpre, $nestedpost, $nestedbefore, $nestedafter,
                $nestedpre, $nestedpost, $nestedbefore, $nestedafter);
        }
    }
    printf(__("%s\n"), $post);
}


/**
 *
 *
 * @param unknown $facet
 * @param unknown $taxo
 * @param unknown $prefix
 * @param unknown $fqstr
 * @param unknown $field
 * @return unknown
 */
function dezi4w_get_output_taxo($facet, $taxo, $prefix, $fqstr, $field) {
    $qry = stripslashes($_GET['s']);

    if (count($taxo) == 0) {
        return;
    } else {
        $facetitms = array();
        // turn the facet inside out to access by term
        $io_facet = array();
        foreach ($facet as $f) {
            $io_facet[$f['term']] = $f['count'];
        }
        foreach ($taxo as $taxoname => $taxoval) {
            $newprefix = $prefix . $taxoname . '^^';
            $facetitm = array();
            $facetitm['count'] = sprintf(__("%d"), $io_facet[$newprefix]);
            $facetitm['link'] = htmlspecialchars(sprintf(__('?s=%s&fq=%s:%s%s', 'dezi4wp'), $qry, $field,  urlencode('"' . $newprefix . '"'), $fqstr));
            $facetitm['name'] = $taxoname;
            $outitms = dezi4w_get_output_taxo($facet, $taxoval, $newprefix, $fqstr, $field);
            if ($outitms) {
                $facetitm['items'] = $outitms;
            }
            $facetitms[] = $facetitm;
        }

        return $facetitms;
    }
}


/**
 *
 *
 * @param unknown $in
 * @param unknown $vals
 * @return unknown
 */
function dezi4w_gen_taxo_array($in, $vals) {
    if (count($vals) == 1) {
        if ( ! isset($in[$vals[0]]) ) {
            $in[$vals[0]] = array();
        }
        return $in;
    }
    else {
        if (isset($in[$vals[0]])) {
            $in[$vals[0]] = dezi4w_gen_taxo_array($in[$vals[0]], array_slice($vals, 1));
        }
        return $in;
    }
}


/**
 * Query the required server
 * passes all parameters to the appropriate function based on the server name
 * This allows for extensible server/core based query functions.
 * TODO allow for similar theme/output function
 *
 * @param unknown $qry
 * @param unknown $offset
 * @param unknown $count
 * @param unknown $fq
 * @param unknown $sortby
 * @param unknown $server (optional)
 * @return unknown
 */
function dezi4w_query( $qry, $offset, $count, $fq, $sortby, $server = NULL) {
    //NOTICE: does this needs to be cached to stop the db being hit to grab the options everytime search is being done.
    $plugin_dezi4w_settings = dezi4w_get_option();
    //if no server has been provided use the default server
    if (!$server) {
        $server = $plugin_dezi4w_settings['dezi4w_server']['type']['search'];
    }
    $dezi = dezi4w_get_dezi($server);
    if (!function_exists($function = 'dezi4w_'.$server.'_query')) {
        $function = 'dezi4w_master_query';
    }

    return $function($dezi, $qry, $offset, $count, $fq, $sortby, $plugin_dezi4w_settings);
}


/**
 *
 *
 * @param unknown $dezi
 * @param unknown $qry
 * @param unknown $offset
 * @param unknown $count
 * @param unknown $fq
 * @param unknown $sortby
 * @param unknown $plugin_dezi4w_settings (reference)
 * @return unknown
 */
function dezi4w_master_query($dezi, $qry, $offset, $count, $fq, $sortby, &$plugin_dezi4w_settings) {

    $response = NULL;
    $facet_fields = array();
    $number_of_tags = $plugin_dezi4w_settings['dezi4w_max_display_tags'];

    if ($plugin_dezi4w_settings['dezi4w_facet_on_categories']) {
        $facet_fields[] = 'categories';
    }

    $facet_on_tags = $plugin_dezi4w_settings['dezi4w_facet_on_tags'];
    if ($facet_on_tags) {
        $facet_fields[] = 'tags';
    }

    if ($plugin_dezi4w_settings['dezi4w_facet_on_author']) {
        $facet_fields[] = 'author';
    }

    if ($plugin_dezi4w_settings['dezi4w_facet_on_type']) {
        $facet_fields[] = 'type';
    }


    $facet_on_custom_taxonomy = $plugin_dezi4w_settings['dezi4w_facet_on_taxonomy'];
    if (count($facet_on_custom_taxonomy)) {
        $taxonomies = (array)get_taxonomies(array('_builtin'=>FALSE), 'names');
        foreach ($taxonomies as $parent) {
            $facet_fields[] = $parent."_taxonomy";
        }
    }

    $facet_on_custom_fields = $plugin_dezi4w_settings['dezi4w_facet_on_custom_fields'];
    if (count($facet_on_custom_fields)) {
        foreach ( $facet_on_custom_fields as $field_name ) {
            $facet_fields[] = $field_name . '_str';
        }
    }

    if ( $dezi ) {
        $params = array();
        $params['q'] = $qry;
        //error_log("fq=".var_export($fq,true));
        if ($fq and count($fq)) {
            $valid_fq = array();
            foreach ($fq as $str) {
                if ($str and strlen($str)) {
                    $valid_fq[] = $str;
                }
            }
            if ($valid_fq) {
                $params['q'] .= sprintf(" AND (%s)", implode(' AND ', $valid_fq));
            }
        }
        $params['o'] = $offset;
        $params['p'] = $count;  // page size
        $params['h'] = 1;       // use highlighting
        $params['f'] = 1;       // return facets
        $params['r'] = 1;       // return results
        $params['s'] = $sortby;

        try {
            $response = $dezi->search($params);
            if ( $response === 0 ) {
                $response = NULL;
            }
        }
        catch(Exception $e) {
            error_log("failed to query dezi for " . print_r($qry, true) . print_r($params, true));
            $response = NULL;
        }
    }
    //echo '<pre>';
    //print_r($dezi->last_response);
    //echo '</pre>';
    return $response;
}


/**
 *
 */
function dezi4w_options_init() {
    $method = "";
    if (isset($_POST['method'])) {
        $method = $_POST['method'];
    }
    if ($method === "load") {
        $type = $_POST['type'];
        $prev = $_POST['prev'];

        if ($type) {
            dezi4w_load_all_posts($prev, $type);
            exit;
        } else {
            return;
        }
    }
    register_setting('dezi4w-options-group', 'plugin_dezi4w_settings', 'dezi4w_sanitise_options' );
}


/**
 * Sanitises the options values
 *
 * @param unknown $options array of dezi4w settings options
 * @return $options sanitised values
 */
function dezi4w_sanitise_options($options) {
    $options['dezi4w_dezi_host'] = wp_filter_nohtml_kses($options['dezi4w_dezi_host']);
    $options['dezi4w_dezi_port'] = absint($options['dezi4w_dezi_port']);
    $options['dezi4w_dezi_path'] = wp_filter_nohtml_kses($options['dezi4w_dezi_path']);
    $options['dezi4w_dezi_username'] = wp_filter_nohtml_kses($options['dezi4w_dezi_username']);
    $options['dezi4w_dezi_password'] = wp_filter_nohtml_kses($options['dezi4w_dezi_password']);
    $options['dezi4w_dezi_update_host'] = wp_filter_nohtml_kses($options['dezi4w_dezi_update_host']);
    $options['dezi4w_dezi_update_port'] = absint($options['dezi4w_dezi_update_port']);
    $options['dezi4w_dezi_update_path'] = wp_filter_nohtml_kses($options['dezi4w_dezi_update_path']);
    $options['dezi4w_dezi_update_username'] = wp_filter_nohtml_kses($options['dezi4w_dezi_update_username']);
    $options['dezi4w_dezi_update_password'] = wp_filter_nohtml_kses($options['dezi4w_dezi_update_password']);
    $options['dezi4w_index_pages'] = absint($options['dezi4w_index_pages']);
    $options['dezi4w_index_posts'] = absint($options['dezi4w_index_posts']);
    $options['dezi4w_index_comments'] = absint($options['dezi4w_index_comments']);
    $options['dezi4w_delete_page'] = absint($options['dezi4w_delete_page']);
    $options['dezi4w_delete_post'] = absint($options['dezi4w_delete_post']);
    $options['dezi4w_private_page'] = absint($options['dezi4w_private_page']);
    $options['dezi4w_private_post'] = absint($options['dezi4w_private_post']);
    $options['dezi4w_output_info'] = absint($options['dezi4w_output_info']);
    $options['dezi4w_output_pager'] = absint($options['dezi4w_output_pager']);
    $options['dezi4w_output_facets'] = absint($options['dezi4w_output_facets']);
    $options['dezi4w_exclude_pages'] = dezi4w_filter_str2list($options['dezi4w_exclude_pages']);
    $options['dezi4w_num_results'] = absint($options['dezi4w_num_results']);
    $options['dezi4w_cat_as_taxo'] = absint($options['dezi4w_cat_as_taxo']);
    $options['dezi4w_max_display_tags'] = absint($options['dezi4w_max_display_tags']);
    $options['dezi4w_facet_on_categories'] = absint($options['dezi4w_facet_on_categories']);
    $options['dezi4w_facet_on_tags'] = absint($options['dezi4w_facet_on_tags'] );
    $options['dezi4w_facet_on_author'] = absint($options['dezi4w_facet_on_author']);
    $options['dezi4w_facet_on_type'] = absint($options['dezi4w_facet_on_type']);
    $options['dezi4w_index_all_sites'] = absint($options['dezi4w_index_all_sites']);
    $options['dezi4w_enable_dym'] = absint($options['dezi4w_enable_dym'] );
    $options['dezi4w_connect_type'] = wp_filter_nohtml_kses($options['dezi4w_connect_type']);
    $options['dezi4w_index_custom_fields'] = dezi4w_filter_str2list($options['dezi4w_index_custom_fields']);
    $options['dezi4w_facet_on_custom_fields'] = dezi4w_filter_str2list($options['dezi4w_facet_on_custom_fields']);
    return $options;
}


/**
 *
 *
 * @param unknown $input
 * @return unknown
 */
function dezi4w_filter_str2list_numeric($input) {
    $final = array();
    if ($input != "") {
        foreach ( explode(',', $input) as $val ) {
            $val = trim($val);
            if ( is_numeric($val) ) {
                $final[] = $val;
            }
        }
    }

    return $final;
}


/**
 *
 *
 * @param unknown $input
 * @return unknown
 */
function dezi4w_filter_str2list($input) {
    $final = array();
    if ($input != "") {
        foreach ( explode(',', $input) as $val ) {
            $final[] = trim($val);
        }
    }

    return $final;
}


/**
 *
 *
 * @param unknown $input
 * @return unknown
 */
function dezi4w_filter_list2str($input) {
    if (!is_array($input)) {
        return "";
    }

    $outval = implode(',', $input);
    if (!$outval) {
        $outval = "";
    }

    return $outval;
}


/**
 *
 */
function dezi4w_add_pages() {
    $addpage = FALSE;

    if (is_multisite() && is_site_admin()) {
        $plugin_dezi4w_settings = dezi4w_get_option();
        $indexall = $plugin_dezi4w_settings['dezi4w_index_all_sites'];
        if (($indexall && is_main_blog()) || !$indexall) {
            $addpage = TRUE;
        }
    } elseif (!is_multisite() && is_admin()) {
        $addpage = TRUE;
    }

    if ($addpage) {
        //add_options_page('Dezi Options', 'Dezi Options', 8, __FILE__, 'dezi4w_options_page');
        $mypage = add_options_page('Dezi Options', 'Dezi Options', 8, __FILE__, 'dezi4w_options_page' );
        add_action( "admin_print_scripts-$mypage", 'dezi4w_admin_head' );

    }
}


/**
 *
 */
function dezi4w_options_page() {
    if ( file_exists( dirname(__FILE__) . '/dezi-options-page.php' )) {
        include dirname(__FILE__) . '/dezi-options-page.php';
    } else {
        _e("<p>Couldn't locate the options page.</p>", 'dezi4wp');
    }
}


/**
 *
 */
function dezi4w_admin_head() {
    // include our default css and js
    $plugindir = plugins_url().'/'.dirname(plugin_basename(__FILE__));
    wp_enqueue_script('dezi4w', $plugindir . '/template/dezi4w.js');
    echo "<link rel='stylesheet' href='$plugindir/template/search.css' type='text/css' media='screen' />\n";
}


/**
 *
 */
function dezi4w_default_head() {
    // include our default css
    $plugindir = plugins_url().'/'.dirname(plugin_basename(__FILE__));
    echo "<link rel='stylesheet' href='$plugindir/template/search.css' type='text/css' media='screen' />\n";
}


/**
 *
 */
function dezi4w_autosuggest_head() {
    if (file_exists(dirname(__FILE__) . '/template/autocomplete.css')) {
        printf(__("<link rel=\"stylesheet\" href=\"%s\" type=\"text/css\" media=\"screen\" />\n"), plugins_url('/template/autocomplete.css', __FILE__));
    }
?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $("#s").suggest("?method=autocomplete",{});
        $("#qrybox").suggest("?method=autocomplete",{});
    });
</script>
<?php
}


/**
 *
 */
function dezi4w_template_redirect() {
    wp_enqueue_script('suggest');

    // not a search page; don't do anything and return
    // thanks to the Better Search plugin for the idea:  http://wordpress.org/extend/plugins/better-search/
    $search = stripos($_SERVER['REQUEST_URI'], '?s=');
    $autocomplete = stripos($_SERVER['REQUEST_URI'], '?method=autocomplete');

    if ( ($search || $autocomplete) == FALSE ) {
        return;
    }

    if ($autocomplete) {
        $q = stripslashes($_GET['q']);
        $limit = $_GET['limit'];

        dezi4w_autocomplete($q, $limit);
        exit;
    }

    // If there is a template file then we use it
    if (locate_template( array( 'dezi4w_search.php' ), FALSE, TRUE)) {
        // use theme file
        locate_template( array( 'dezi4w_search.php' ), TRUE, TRUE);
    } elseif (file_exists(dirname(__FILE__) . '/template/dezi4w_search.php')) {
        // use plugin supplied file
        add_action('wp_head', 'dezi4w_default_head');
        include_once dirname(__FILE__) . '/template/dezi4w_search.php';
    } else {
        // no template files found, just continue on like normal
        // this should get to the normal WordPress search results
        return;
    }

    exit;
}


/**
 *
 */
function dezi4w_mlt_widget() {
    register_widget('dezi4w_MLTWidget');
}


class dezi4w_MLTWidget extends WP_Widget {


    /**
     *
     */
    function dezi4w_MLTWidget() {
        $widget_ops = array('classname' => 'widget_dezi4w_mlt', 'description' => __( "Displays a list of pages similar to the page being viewed") );
        $this->WP_Widget('mlt', __('Similar'), $widget_ops);
    }


    /**
     *
     *
     * @param unknown $args
     * @param unknown $instance
     */
    function widget( $args, $instance ) {

        die("TODO dezi widget");


        extract($args);
        $title = apply_filters('widget_title', empty($instance['title']) ? __('Similar') : $instance['title']);
        $count = empty($instance['count']) ? 5 : $instance['count'];
        if (!is_numeric($count)) {
            $count = 5;
        }

        $showauthor = $instance['showauthor'];

        $dezi = dezi4w_get_dezi();
        $response = NULL;

        if ((!is_single() && !is_page()) || !$dezi) {
            return;
        }

        $params = array();
        $qry = 'permalink:' . $dezi->escape(get_permalink());
        $params['fl'] = 'title,permalink,author';
        $params['mlt'] = 'true';
        $params['mlt.count'] = $count;
        $params['mlt.fl'] = 'title,content';

        $response = $dezi->search($qry, 0, 1, $params);
        if ( ! $response->getHttpStatus() == 200 ) {
            return;
        }

        echo $before_widget;
        if ( $title )
            echo $before_title . $title . $after_title;

        $mltresults = $response->moreLikeThis;
        foreach ($mltresults as $mltresult) {
            $docs = $mltresult->docs;
            echo "<ul>";
            foreach ($docs as $doc) {
                if ($showauthor) {
                    $author = " by {$doc->author}";
                }
                echo "<li><a href=\"{$doc->permalink}\" title=\"{$doc->title}\">{$doc->title}</a>{$author}</li>";
            }
            echo "</ul>";
        }

        echo $after_widget;
    }


    /**
     *
     *
     * @param unknown $new_instance
     * @param unknown $old_instance
     * @return unknown
     */
    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => '', 'count' => 5, 'showauthor' => 0) );
        $instance['title'] = strip_tags($new_instance['title']);
        $cnt = strip_tags($new_instance['count']);
        $instance['count'] = is_numeric($cnt) ? $cnt : 5;
        $instance['showauthor'] = $new_instance['showauthor'] ? 1 : 0;

        return $instance;
    }


    /**
     *
     *
     * @param unknown $instance
     */
    function form( $instance ) {
        $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'count' => 5, 'showauthor' => 0) );
        $title = strip_tags($instance['title']);
        $count = strip_tags($instance['count']);
        $showauthor = $instance['showauthor'] ? 'checked="checked"' : '';
?>
            <p>
                <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count:'); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo esc_attr($count); ?>" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('showauthor'); ?>"><?php _e('Show Author?:'); ?></label>
                <input class="checkbox" type="checkbox" <?php echo $showauthor; ?> id="<?php echo $this->get_field_id('showauthor'); ?>" name="<?php echo $this->get_field_name('showauthor'); ?>" />
            </p>
<?php
    }


}


/**
 *
 *
 * @param unknown $q
 * @param unknown $limit
 */
function dezi4w_autocomplete($q, $limit) {
    $dezi = dezi4w_get_dezi();
    $response = NULL;

    if (!$dezi) {
        return;
    }

    $params = array();
    $params['c'] = '1'; // just count, no results or facets
    $params['q'] = $q;

    $response = $dezi->search($params);
    if ( $response === 0 ) {
        return;
    }

    $terms = $response->suggestions;
    //error_log("autocomplete limit=$limit");
    //error_log(var_export($terms,true));
    $i = 0;
    foreach ($terms as $term) {
        printf("%s\n", $term);
        if (isset($limit) && ++$i > $limit) {
            break;
        }
    }
}


/**
 * copies config settings from the main blog
 * to all of the other blogs
 */
function dezi4w_copy_config_to_all_blogs() {
    global $wpdb;

    $blogs = $wpdb->get_results("SELECT blog_id FROM $wpdb->blogs WHERE spam = 0 AND deleted = 0");

    $plugin_dezi4w_settings = dezi4w_get_option();
    foreach ($blogs as $blog) {
        switch_to_blog($blog->blog_id);
        wp_cache_flush();
        error_log("pushing config to {$blog->blog_id}");
        dezi4w_update_option($plugin_dezi4w_settings);
    }

    wp_cache_flush();
    restore_current_blog();
}


/**
 *
 *
 * @param unknown $blogid
 */
function dezi4w_apply_config_to_blog($blogid) {
    error_log("applying config to blog with id $blogid");
    if (!is_multisite())
        return;

    wp_cache_flush();
    $plugin_dezi4w_settings = dezi4w_get_option();
    switch_to_blog($blogid);
    wp_cache_flush();
    dezi4w_update_option($plugin_dezi4w_settings);
    restore_current_blog();
    wp_cache_flush();
}


/**
 * Retrieve a list of post types that exists
 *
 * @return array
 */
function dezi4w_get_all_post_types() {
    global $wpdb;
    //remove the defualt attachment/revision and menu from the returned types.
    $query = $wpdb->get_results("SELECT DISTINCT(post_type) FROM $wpdb->posts WHERE post_type NOT IN('attachment', 'revision', 'nav_menu_item') ORDER BY post_type");
    if ($query) {
        $types = array();
        foreach ( $query as $type ) {
            $types[] = $type->post_type;
        }
        return $types;
    }
}


add_action( 'template_redirect', 'dezi4w_template_redirect', 1 );
add_action( 'publish_post', 'dezi4w_handle_modified' );
add_action( 'publish_page', 'dezi4w_handle_modified' );
add_action( 'save_post', 'dezi4w_handle_modified' );
add_action( 'delete_post', 'dezi4w_handle_delete' );
add_action( 'trash_post', 'dezi4w_handle_delete' );
add_action( 'admin_menu', 'dezi4w_add_pages');
add_action( 'admin_init', 'dezi4w_options_init');
add_action( 'widgets_init', 'dezi4w_mlt_widget');
add_action( 'wp_head', 'dezi4w_autosuggest_head');
register_deactivation_hook( __FILE__, 'dezi4w_deactivate_plugin' );

if (is_multisite()) {
    add_action( 'deactivate_blog', 'dezi4w_handle_deactivate_blog');
    add_action( 'activate_blog', 'dezi4w_handle_activate_blog');
    add_action( 'archive_blog', 'dezi4w_handle_archive_blog');
    add_action( 'unarchive_blog', 'dezi4w_handle_unarchive_blog');
    add_action( 'make_spam_blog', 'dezi4w_handle_spam_blog');
    add_action( 'unspam_blog', 'dezi4w_handle_unspam_blog');
    add_action( 'delete_blog', 'dezi4w_handle_delete_blog');
    add_action( 'wpmu_new_blog', 'dezi4w_handle_new_blog');
}
