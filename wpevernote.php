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

ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . dirname(__FILE__) . "/evernote-sdk-php/lib" . PATH_SEPARATOR);

require_once 'autoload.php';
require_once 'Evernote/Client.php';
require_once 'packages/Errors/Errors_types.php';
require_once 'packages/Types/Types_types.php';
require_once 'packages/Limits/Limits_constants.php';
require_once 'packages/UserStore/UserStore.php';

if (!class_exists('WPEvernote')) {
    class WPEvernote
    {
        var $o;
        var $status = "";

        /* OAuth token. */
        var $token = "";

        var $default_options = array(
            'wpevernote_revision' => 12,
            /* oauth. */
            'wpevernote_consumer_key' => '',
            'wpevernote_consumer_secret' => '',
            'wpevernote_oauth_verifier' => '',
            'wpevernote_access_token' => '',
            'wpevernote_request_token' => '',

            /* sandbox oauth token. */
            'wpevernote_sandbox_token' => '',

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
            add_action('admin_menu', array(&$this, 'admin_menu'));
        }

        /* This function sets $token after OAuth authentication.
         *
         * Returns true if successful.
         */
        function authenticate() {

            if ($this->o['wpevernote_sandbox_token']) {
                $this->token = $this->o['wpevernote_sandbox_token'];
                return true;
            }

            if ($this->o['wpevernote_access_token']) {
                $this->token = $this->o['wpevernote_access_token'];
                return true;
            }

            if (!$this->o['wpevernote_consumer_key'] || !$this->o['wpevernote_consumer_secret']) {
                $this->status = "You need API keys to use this plugin.";
                return false;
            }

            /* Authenticate VIA OAuth.
             * pecl install oauth
             * You should add "extension=oauth.so" to php.ini
             */
            $client = new Evernote\Client(array(
              'consumerKey' => $this->o['wpevernote_consumer_key'],
              'consumerSecret' => $this->o['wpevernote_consumer_secret'],
            ));
            $requestToken = $client->getRequestToken($this->getCallbackUrl());
            $authorizeUrl = $client->getAuthorizeUrl($requestToken['oauth_token']);
        }

        function init() {

            if (isset($_GET['oauth_verifier'])) {
                $client = new Evernote\Client(array(
                    'consumerKey' => $this->o["wpevernote_consumer_key"],
                    'consumerSecret' => $this->o["wpevernote_consumer_secret"]
                ));
                $accessToken = $client->getAccessToken(
                    $requestToken['oauth_token'],
                    $requestToken['oauth_token_secret'],
                    $_GET['oauth_verifier']
                );
                $this->o['wpevernote_access_token'] = $accessToken['oauth_token'];
                update_option("wpevernote-options", $this->o);
            }

            if ($_POST['wpevernote_action'] == 'run') {
                check_admin_referer('wpevernote-2');

                if (!$this->authenticate()) {
                    $this->status = "Unable to gain access token. You need API keys or a sandbox token before you can add a url.";
                    return;
                }

                $this->fetch_posts();
		        $this->status = "All notebooks refreshed.";

            } else if ($_POST['wpevernote_action'] == 'reset') {

                check_admin_referer('wpevernote-3');
                $this->o = $this->default_options;
                $this->status = "Reset";
                update_option("wpevernote-options", $this->default_options);

            } elseif ($_POST['wpevernote_action'] == 'delete') {

                check_admin_referer('wpevernote-4');
                foreach( (array) $_POST['enotebook'] as $post_id_del ) {
                    unset($this->o["wpevernote_notebooks"][$post_id_del]);
                }
                $this->o["wpevernote_notebooks"] = array_values($this->o["wpevernote_notebooks"]);
                update_option("wpevernote-options", $this->o);
                $this->status = "Notebook removed";

		    } elseif ($_POST['wpevernote_action'] == 'add') {

                check_admin_referer('wpevernote-0', 'wpevernote-add');
                if (isset($_POST['wpevernote_pub_url'])){

                    /* Authenticate. */
                    if (!$this->authenticate()) {
                        $this->status = "Unable to gain access token. You need API keys or a sandbox token before you can add a url.";
                        return;
                    }

                    /* Get a client to access NoteStore and UserStore. */
                    $client = new Evernote\Client(array('token' => $this->token));
                    $userStore = $client->getUserStore();

                    /* Get the user for the token. This is needed for user->id. */
                    try {
                        $user = $userStore->getUser($this->token);
                    } catch(EDAM\Error\EDAMSystemException $e) {
                        $this->status = "Incorrect API Credentials";
                        return;
                    } catch(EDAM\Error\EDAMUserException $e) {
                        $this->status = "Incorrect API Credentials";
                        return;
                    }

                    $noteStore = $client->getNoteStore();
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
                $this->o["wpevernote_sandbox_token"] = $_POST['wpevernote_sandbox_token'];
                $this->o["wpevernote_refresh_period"] = $_POST['wpevernote_refresh_period'];
                $this->o["wpevernote_refresh_time"] = $_POST['wpevernote_refresh_time'];
                update_option("wpevernote-options", $this->o);
                $this->status = "Options saved";

                $this->authenticate();
            }

            if ($this->check_refresh())
                $this->fetch_posts();
        }

        function fetch_posts() {

            $client = new Evernote\Client(array('token' => $this->token));
            $noteStore = $client->getNoteStore();
            $userStore = $client->getUserStore();
            $user = $userStore->getUser($this->token);

            foreach ($this->o["wpevernote_notebooks"] as $epnotebook) {

                $notebookName = basename(parse_url($epnotebook['pub_url'], PHP_URL_PATH));
                try {

                    $notebook = $noteStore->getPublicNotebook($user->id, $notebookName);

                } catch(EDAM\Error\EDAMNotFoundException $e) {
                    $this->status = "Invalid Notebook.";
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
                    $posts = query_posts('meta_key=evernote_guid&meta_value='.$note->guid);
                    if ($posts) $new_post['ID'] = $posts[0]->ID;

                    $new_post['post_title'] = $note->title;
                    $new_post['comment_status'] = "closed";
                    $new_post['post_author'] = wp_get_current_user();
                    $new_post['post_content'] = $noteContent;
                    $new_post['post_status'] = "publish";
                    $new_post['post_type'] = 'post';
                    $new_post['post_date'] = date("Y-m-d H:i:s", mktime());
                    $new_post['post_category'] = $note->tagNames[0];
                    $new_post['tags_input'] = $note->tagNames;

                    $post_id = wp_insert_post($new_post);
                    add_post_meta($post_id, 'evernote_guid', $note->guid, true);
                }
            }

		    $this->o["wpevernote_last_refresh"] = mktime();
		    update_option('wpevernote-options', $this->o);
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
            return $thisUrl;
        }
    }

    $evernote = new WPEvernote();
}

?>
