<!--
    Copyright 2014  Christopher Reichert <christopher@reichertbrothers.com>
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
-->
<?php if (isset($_POST['wpevernote_action'])) { ?>
<div id="message" class="updated fade" style="background-color: rgb(255, 251, 204);">
  <p><strong><?php echo $status; ?></strong></p>
</div>
<?php } ?>
<style type="text/css">
    table {
        background-color: #fff;
        border: 2px solid #ccc;
        -moz-border-radius: 10px;
        -webkit-border-radius: 10px;
        border-radius: 10px;
        width:100%;
        padding: 2px;
    }
    .form-table th {
        width:auto;
    }
</style>
<div class="wrap"><h2>WP Evernote</h2>
  <h3>Add Evernote Public Notebook</h3>
  <div class="gdsr">
    <form method="post">
      <?php if (function_exists('wp_nonce_field'))
        wp_nonce_field('wpevernote-0', 'wpevernote-add');
	  ?>
      <input type="hidden" name="wpevernote_action" value="add" />
      <table>
        <tbody>
          <tr>
            <th valign="top" scope="row">Public notebook url:</th>
            <td align="left"><input type="text" name="wpevernote_pub_url" id="pub_url" style="width: 530px" />
              <br/>
              e.g. <a href="http://www.evernote.com/pub/rbchristopher/helloevernote" target="_blank">http://www.evernote.com/pub/rbchristopher/helloevernote</a>
            </td>
          </tr>
          <tr>
            <th scope="row"></th>
            <td><span class="submit">
              <input class="inputbutton" type="submit" value="Add Notebook" name="saving"/>
              </span></td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
  <div class="gdsr">
    <h3>Current Notebooks</h3>
    <form method="post">
      <?php wp_nonce_field('wpevernote-4' ); ?>
      <input type="hidden" name="wpevernote_action" value="delete" />
      <table class="widefat">
        <thead>
          <tr>
            <th scope="col">Notebook</th>
            <th scope="col">Last Refresh</th>
            <th scope="col" style="text-align: right">Remove</th>
          </tr>
        </thead>
        <?php
          if(!(empty($options['wpevernote_notebooks']))) {
	          foreach ($options['wpevernote_notebooks'] as $idx => $tps) {
                  echo "<tr><td><a href=\"".$tps['pub_url']."\">".$tps['pub_url']."</a></td><td>".date("d M y  H:i:s",$tps['last_refresh_notebook'])."</td><td align='right'><input name='enotebook[]' type='checkbox' value='".$idx."'></td></tr>";
              }
	      } else {
		      echo "<tr><td colspan='5' align='center'><strong><em>None</em></strong></td></tr>";
	      }
	      echo "<td colspan='8' align='right'><input class=\"inputbutton\" type=\"submit\" value=\"Remove\" name=\"saving\" /></td></tr>";
        ?>
      </table>
    </form>
    <form method="post">
      <?php wp_nonce_field('wpevernote-2' ); ?>
      <input type="hidden" name="wpevernote_action" value="run" />
      <input class="inputbutton" type="submit" value="Update Notebooks" name="saving" />
    </form>
  </div>
  <div class="gdsr">
    <h3>Options</h3>
    <form method="post">
    <?php if (function_exists('wp_nonce_field')) wp_nonce_field('wpevernote-1', 'wpevernote-main'); ?>
    <input type="hidden" name="wpevernote_action" value="save" />
    <table>
  <tbody>
    <tr><th valign="top" scope="row">Refresh:</th>
      <td align="left">
        <table cellpadding="0" cellspacing="0" class="previewtable">
          <tr>
            <td width="150" height="25">Period:</td>
            <td align="left">
            <select style="width: 180px;" name="wpevernote_refresh_period" id="wpevernote_refresh_period">
              <option value="monthly"<?php echo $options["wpevernote_refresh_period"] == 'monthly' ? ' selected="selected"' : ''; ?>>Monthly</option>
              <option value="weekly"<?php echo $options["wpevernote_refresh_period"] == 'weekly' ? ' selected="selected"' : ''; ?>>Weekly</option>
              <option value="daily"<?php echo $options["wpevernote_refresh_period"] == 'daily' ? ' selected="selected"' : ''; ?>>Daily</option>
            </select>
            </td>
          </tr>
          <tr>
            <td width="150" height="25">Time:</td>
            <td align="left">
              <input maxlength="8" type="text" name="wpevernote_refresh_time" id="wpevernote_refresh_time" value="<?php echo $options["wpevernote_refresh_time"]; ?>" style="width: 170px" /> [format: HH:MM or HH:MM AP (AM/PM)]
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><th valign="top" scope="row">API Keys:</th>
      <td align="left">
        <table cellpadding="0" cellspacing="0" class="previewtable">
          <tr>
            <td width="150" height="25">Consumer Key:</td>
            <td align="left">
              <input maxlength="50" type="text" name="wpevernote_consumer_key" id="wpevernote_consumer_key" value="<?php echo $options["wpevernote_consumer_key"]; ?>" style="width: 170px" />
            </td>
          </tr>
          <tr>
            <td width="150" height="25">Consumer Secret:</td>
            <td align="left">
              <input maxlength="16" type="text" name="wpevernote_consumer_secret" id="wpevernote_consumer_secret" value="<?php echo $options["wpevernote_consumer_secret"]; ?>" style="width: 170px" />
            </td>
          </tr>
          <tr>
            <td width="150" height="25">Sandbox Token (Developer Option):</td>
            <td align="left">
              <input maxlength="128" type="text" name="wpevernote_sandbox_token" id="wpevernote_sandbox_token" value="<?php echo $options["wpevernote_sandbox_token"]; ?>" style="width: 170px" />
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <th valign="top" scope="row">&nbsp;</th>
      <td align="left"><span class="submit">
        <input class="inputbutton" type="submit" value="Save Options" name="saving"/>
      </span></td>
    </tr>
  </tbody>
  </table>
  </form>
  </div>
  <p>A <a href="http://reichertbrothers.com/" target="_blank">Reichert Brothers</a> software product.</p>
  <p>Donations:<br/>BTC: <a href="" target="_blank">1NHHD9DehnVVTDSU71NJkvLePiKYCQGNY3</a>
              <br/>DOGE: <a href="" target="_blank">DBwppk2zKrCqHGxaRJuvLDiKSZVPUzJ81d</a>
  </p>
</div>
