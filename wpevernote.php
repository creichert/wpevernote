<?php

/*
Plugin Name: WP Evernote
Plugin URI: https://github.com/creichert/wpevernote
Description: WP Evernote allows Evernote users to automatically post
             public notebooks to WordPress.
Author: Christopher Reichert
Author URI: http://reichertbrothers.com
Version: 0.1
License: GPL2
*/

/*  Copyright 2014  Christopher Reichert <christopher@reichertbrothers.com>
	The code used in WP Evernote is based on:
        Plugin Name: EverPress
        Plugin URI: http://mashe.hawksey.info/everpress-plugin/
        Description: EverPress allows Evernote users to automatic post their shared notebooks to WordPress.
        Author: Martin Hawksey
        Author URI: http://mashe.hawksey.info

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . dirname(__FILE__) . "/evernote-cloud-sdk-php/src" . PATH_SEPARATOR);

/* require_once 'OAuth.php'; */

require_once 'autoload.php';
require_once 'Evernote/Client.php';
require_once 'Evernote/Auth/OauthHandler.php';


if (!class_exists('WPEvernote')) {
    class WPEvernote
    {
        var $o;
        var $status = "";

        /* OAuth token. */
        var $token = "";

        // BUG needs to be set in the plugin configuration
        var $SANDBOX = false;

        var $default_options = array(
            'wpevernote_revision' => 12,

            /* oauth. */
            'wpevernote_consumer_key' => '',
            'wpevernote_consumer_secret' => '',
            'wpevernote_oauth_verifier' => '',
            'wpevernote_access_token' => '',
            'wpevernote_request_token' => '',

            /* notebook handling. */
            'wpevernote_refresh_period' => 'daily',
            'wpevernote_refresh_time' => '06:00 AM',
            'wpevernote_notebooks' => array()
        );

        function WPEvernote() {
            $this->install_plugin();
            $this->actions_filters();
        }

        function install_plugin() {

            $this->o = get_option('wpevernote-options');
            if (!is_array($this->o)) {
                update_option('wpevernote-options', $this->default_options);
                $this->o = get_option('wpevernote-options');
            } else {
                foreach ($this->default_options as $key => $value)
                    if (!isset($this->o[$key])) $this->o[$key] = $value;
                $this->o["wpevernote_revision"] = $this->default_options["wpevernote_revision"];
                update_option('wpevernote-options', $this->o);
            }
        }

        function actions_filters() {
            add_action('init', array(&$this, 'init'));
            add_action('init', array(&$this, 'wpevernote_custom_post_type'));
            add_action('admin_menu', array(&$this, 'admin_menu'));
        }

        function wpevernote_custom_post_type() {
            $labels = array(
                'name'          => __( 'Evernote Posts' ),
                'singular_name' => __( 'Evernote Post' ),
                'add_new_item'  => __( 'New Evernote Post' ),
                'edit_item'     => __( 'Edit Evernote Post' ),
                'new_item'      => __( 'New Evernote Post' ),
                'view_item'     => __( 'View Evernote Post' ),
                'all_items'     => __( 'All Evernote Posts' ),
                'search_items'  => __( 'Search Evernote Posts' ),
            );
            $args = array(
                'public'          => true,
                'has_archive'     => true,
                'labels'          => $labels,
                'description'     => 'WPEvernote posts',
                'query_var'       => true,
                'menu_positions'  => '15',
                'capability_type' => 'post',
                'hierarchical'    => true,
                'taxonomies'      => array(),
                'supports'        => array( 'title'
                                           , 'editor'
                                           , 'author'
                                           , 'thumbnail'
                                           , 'page-attributes'
                ),
                'rewrite'         => true
            );
            register_post_type( 'wpevernote', $args );
        }



       function init() {

            /* Authenticate VIA OAuth.
             *
             * This version uses the evernote-cloud-sdk-php package which automates
             * most of the oauth protocol. The PHP OAuth Extension is not needed.
             *
             * Sets $token after succesful authenticion.
             *
             */
            if (isset($_GET['oauth_verifier'])) {

                  if ( $this->evernoteAuthenticate() ) {

                      $this->o['wpevernote_oauth_verifier'] = $_GET['oauth_verifier'];

                      $this->token = $this->o['wpevernote_access_token'];
                      $this->status = 'Content owner authorized the temporary credentials';
                      update_option("wpevernote-options", $this->o);

                  } else {

                      $this->status = "Uknown error authorizing API keys";
                      return false;
                  }

                  // BUG
                  //
                  // When the oauth_verifier header is discovered, we
                  // try to process and redirect the call. However,
                  // the redirect fails and we can't set the header here.
                  //
                  //  $url = (empty($_SERVER['HTTPS'])) ? "http://" : "https://";
                  //  $url .= $_SERVER['SERVER_NAME'];
                  //  $url .= ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) ? "" : (":".$_SERVER['SERVER_PORT']);
                  //  $url .= $_SERVER['SCRIPT_NAME'];
                  //  $url .= '?page=wpevernote/wpevernote.php';
                  //  header('Location: ' . $url);
                  //  return;

            }


            if ($_POST['wpevernote_action'] == 'run') {
                check_admin_referer('wpevernote-2');

                $this->token = $this->o['wpevernote_access_token'];
                if (!$this->token) {
                    $this->status = "Authorize your API keys before refreshing notebooks";
                    return;
                }

                $this->fetch_posts();

            } else if ($_POST['wpevernote_action'] == 'reset') {

                check_admin_referer('wpevernote-3');
                $this->o = $this->default_options;
                $this->status = "Reset";
                update_option("wpevernote-options", $this->default_options);
                return;

            } elseif ($_POST['wpevernote_action'] == 'delete') {

                check_admin_referer('wpevernote-4');
                foreach( (array) $_POST['enotebook'] as $post_id_del ) {
                    unset($this->o["wpevernote_notebooks"][$post_id_del]);
                }
                $this->o["wpevernote_notebooks"] = array_values($this->o["wpevernote_notebooks"]);
                update_option("wpevernote-options", $this->o);
                $this->status = "Notebook removed";
                return;

		    } elseif ($_POST['wpevernote_action'] == 'add') {

                check_admin_referer('wpevernote-0', 'wpevernote-add');

                /* (Christopher): Hack to set key. not sure why token is not being
                 * initialized when set. */
                $this->token = $this->o['wpevernote_access_token'];
                if (!$this->token) {
                    $this->status = "Authorize your API keys before adding notebooks";
                    return;
                }

                if (isset($_POST['wpevernote_pub_url'])){

                    try {

                        /* Get a client to access NoteStore and UserStore. */
                        $client = new Evernote\AdvancedClient($this->token, false);
                        $userStore = $client->getUserStore();
                        $noteStore = $client->getNoteStore();

                        /* Get the user for the token. This is needed for user->id. */
                        $user = $userStore->getUser($this->token);
                    } catch(EDAM\Error\EDAMSystemException $e) {
                        print_r($e);
                        $this->status = "Incorrect API Credentials";
                        return;
                    } catch(EDAM\Error\EDAMUserException $e) {
                        $this->status = "Error authenticating with Evernote. Try authenticating your API keys. Your API keys could be expired.";
                        return;
                    }

                    $notebookName = basename(parse_url($_POST['wpevernote_pub_url'], PHP_URL_PATH));

                    try {

                        $notebook = $noteStore->getPublicNotebook($user->id, $notebookName);

                        $idx = sizeof($this->o["wpevernote_notebooks"]);
                        $this->o["wpevernote_notebooks"][$idx]["pub_url"] = $_POST['wpevernote_pub_url'];
                        $this->o["wpevernote_notebooks"][$idx]["last_refresh_notebook"] = mktime();
                        $this->status = "Notebook added.";
                        update_option("wpevernote-options", $this->o);
                        $this->fetch_posts();
                        return;

                    } catch(EDAM\Error\EDAMNotFoundException $e) {
                        $this->status = "Notebook is not valid.";
                    }
                }

            } elseif ($_POST['wpevernote_action'] == 'save') {

                check_admin_referer('wpevernote-1', 'wpevernote-main');
                $this->o["wpevernote_consumer_key"] = $_POST['wpevernote_consumer_key'];
                $this->o["wpevernote_consumer_secret"] = $_POST['wpevernote_consumer_secret'];
                $this->o["wpevernote_refresh_period"] = $_POST['wpevernote_refresh_period'];
                $this->o["wpevernote_refresh_time"] = $_POST['wpevernote_refresh_time'];
                update_option("wpevernote-options", $this->o);

                /* We can add this to it's own authenticate button. */
                if (!$this->token) {

                    /* Verify the consumer key and consumer secret. */
                    if (!$this->o['wpevernote_consumer_key'] || !$this->o['wpevernote_consumer_secret']) {
                        $this->status = "You need API keys to use this plugin.";
                        return;
                    }

                    if ($this->evernoteAuthenticate()) {
                        $this->status = "Authentication successful.";
                        // header('Location: ' . $this->getAuthorizationUrl());

                        $this->status = "Options saved";
                        update_option("wpevernote-options", $this->o);
                    } else {
                        $this->status = "Uknown authentication error.";
                        return;
                    }
                }
            }

            if ($this->check_refresh() && $this->token) $this->fetch_posts();
        }

        function fetch_posts() {

            try {
                $client = new Evernote\AdvancedClient($this->token, false);
                $noteStore = $client->getNoteStore();
                $userStore = $client->getUserStore();
                $user = $userStore->getUser($this->token);
            } catch(EDAM\Error\EDAMSystemException $e) {
                $this->status = "Incorrect API Credentials";
                return;
            } catch(EDAM\Error\EDAMUserException $e) {
                $this->status = "Error authenticating with Evernote. Try authenticating your API keys. Your API keys could be expired.";
                return;
            }

            foreach ($this->o["wpevernote_notebooks"] as $epnotebook) {

                $notebookName = basename(parse_url($epnotebook['pub_url'], PHP_URL_PATH));
                try {

                    $notebook = $noteStore->getPublicNotebook($user->id, $notebookName);

                } catch(EDAM\Error\EDAMNotFoundException $e) {
                    $this->status = "Invalid Notebook.";
                    return false;
                }

                $NotesMetadataResultSpec = new EDAM\NoteStore\NotesMetadataResultSpec();
                $NotesMetadataResultSpec->includeTitle = true;
                $NoteFilter = new EDAM\NoteStore\NoteFilter();
                $NoteFilter->notebookGuid = $notebook->guid;
                $notes = $noteStore->findNotes($this->token, $NoteFilter, 0, 100);

                foreach ($notes->notes as $note) {

                    /* Get content. No $token needed for public notes. */
                    $noteContent = $noteStore->getNoteContent("", $note->guid);

                    $new_post = array();

                    /* Generate a blog post based on this note. */

                    /* Test if post exists. Use persistent post ID as opposed to title. */
                    // $posts = query_posts(array('post_type' => array('post', 'testimonial'),
                    //                           'meta_query' => array('evernote_guid' => $note->guid)));
                    $posts = query_posts('post_type=wpevernote&meta_key=evernote_guid&meta_value='.$note->guid);
                    $posts = array_merge($posts, query_posts('post_type=wpevernote&meta_key=evernote_guid&meta_value='.$note->guid));

                    // update existing post
                    if ($posts) $new_post['ID'] = $posts[0]->ID;

                    $new_post['post_title'] = $note->title;
                    $new_post['comment_status'] = "closed";
                    $new_post['post_author'] = 1;
                    $new_post['post_content'] = $noteContent;


                    $new_post['post_status'] = "publish";
                    $new_post['post_type'] = 'wpevernote';
                    $new_post['post_date'] = date("Y-m-d H:i:s", mktime());
                    $new_post['post_category'] = $note->tagNames[0];
                    $new_post['tags_input'] = $note->tagNames;

                    $post_id = wp_insert_post($new_post);
                    add_post_meta($post_id, 'evernote_guid', $note->guid, true);
                }
            }

		    $this->o["wpevernote_last_refresh"] = mktime();
		    update_option('wpevernote-options', $this->o);
		    $this->status .= " All notebooks refreshed.";
        }

        function check_refresh() {

            if ($this->o["wpevernote_last_refresh"] == 0) return true;

            $pdate = $this->o["wpevernote_last_refresh"];
            $timeparts = $this->convert_time($this->o["wpevernote_refresh_time"]);
            switch ($this->o["wpevernote_refresh_period"]) {
                case "monthly":
                    $next = mktime(0 + $timeparts[0], 0 + $timeparts[1], 0,
                                date("m", $pdate) + 1, date("d", $pdate), date("Y", $pdate));
                    break;
                case "weekly":
                    $next = mktime(0 + $timeparts[0], 0 + $timeparts[1], 0,
                                date("m", $pdate), date("d", $pdate) + 7, date("Y", $pdate));
                    break;
                case "daily":
                    $next = mktime(0 + $timeparts[0], 0 + $timeparts[1], 0,
                                date("m", $pdate), date("d", $pdate) + 1, date("Y", $pdate));
                    break;
            }

            // $next = strtotime($pdate) + 60;
            if (mktime() >= $next) return true;
            else return false;
        }

        function convert_time($timer) {
            $tp = split(" ", $timer);
            if (count($tp) == 2) {
                if ($tp[1] == "PM") {
                    $tt = split(":", $tp[0]);
                    $tt[0] = $tt[0] + 12;
                    return $tt;
                }
                return split(":", $tp[0]);
            }
            else return split(":", $timer);
        }

        function admin_menu() {
            add_submenu_page('options-general.php', 'WP Evernote', 'WP Evernote', 9, __FILE__, array($this, 'options_panel'));
        }

        function options_panel() {
            $options = $this->o;
            $status = $this->status;
            include(dirname(__FILE__).'/'.'wpevernote-panel.html');
        }

        /* Get the URL of this application. This URL is passed to the server (Evernote)
         * while obtaining unauthorized temporary credentials. The resource owner
         * is redirected to this URL after authorizing the temporary credentials.
         */
        function getCallbackUrl() {
            $thisUrl = (empty($_SERVER['HTTPS'])) ? "http://" : "https://";
            $thisUrl .= $_SERVER['SERVER_NAME'];
            $thisUrl .= ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) ? "" : (":".$_SERVER['SERVER_PORT']);
            $thisUrl .= $_SERVER['SCRIPT_NAME'];
            $thisUrl .= '?page=wpevernote/wpevernote.php';
            $thisUrl .= '&oauth=1';
            return $thisUrl;
        }


        /* Authentication.
         *
         * This function is called twice:
         *  1) request oauth verifier
         *      When the authorize() function is called this will actually redirect
         *       and be  caught at the top of the plugin code when the oauth_verifier
         *       header is checked
         *  2) request access key
         *
         */
        function evernoteAuthenticate() {
            $key      = $this->o['wpevernote_consumer_key'];
            $secret   = $this->o['wpevernote_consumer_secret'];
            $callback = $this->getCallbackUrl();

            try {
                $oauth_handler = new \Evernote\Auth\OauthHandler(false);
                $oauth_data  = $oauth_handler->authorize($key, $secret, $callback);

                if ($oauth_data['oauth_token']) {
                    $this->o['wpevernote_access_token'] = $oauth_data['oauth_token'];
                    update_option('wpevernote-options', $this->o);
                    $this->status = 'API Keys authenticated! Add a notebook.';
                    return true;
                } else {
                    $this->status = 'Failed to obtain token access credentials.';
                    return false;
                }

            } catch (Evernote\Exception\AuthorizationDeniedException $e) {
                //If the user decline the authorization, an exception is thrown.
                echo "Evernote Authentication Declined";
                return false;
            }
        }
    }

    $evernote = new WPEvernote();
}

?>
