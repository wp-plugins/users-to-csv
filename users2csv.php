<?php
/*
Plugin Name: Users to CSV
Plugin URI: http://yoast.com/wordpress/users-to-csv/
Description: This plugin adds an administration screen which allows you to dump your users and/or unique commenters to a csv file.<br/> Built with code borrowed from <a href="http://www.mt-soft.com.ar/2007/06/19/csv-dump/">IAM CSV dump</a>.
Author: Joost de Valk
Version: 1.4.5
Author URI: http://yoast.com/

Copyright 2008-2010 Joost de Valk (email: joost@yoast.com)

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
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( is_admin() ) {

	if ($_GET['page'] == "users2csv.php") {
		function _valToCsvHelper($val, $separator, $trimFunction) {
			if ($trimFunction) $val = $trimFunction($val);
			//If there is a separator (;) or a quote (") or a linebreak in the string, we need to quote it.
			$needQuote = FALSE;
			do {
				if (strpos($val, '"') !== FALSE) {
					$val = str_replace('"', '""', $val);
					$needQuote = TRUE;
					break;
				}
				if (strpos($val, $separator) !== FALSE) {
					$needQuote = TRUE;
					break;
				}
				if ((strpos($val, "\n") !== FALSE) || (strpos($val, "\r") !== FALSE)) { // \r is for mac
					$needQuote = TRUE;
					break;
				}
			} 
			while (FALSE);
			if ($needQuote) {
				$val = '"' . $val . '"';
			}
			return $val;
		}

		function arrayToCsvString($array, $separator=';', $trim='both', $removeEmptyLines=TRUE) {
			if (!is_array($array) || empty($array)) return '';
			switch ($trim) {
				case 'none':
					$trimFunction = FALSE;
					break;
				case 'left':
					$trimFunction = 'ltrim';
					break;
				case 'right':
					$trimFunction = 'rtrim';
					break;
				default: //'both':
					$trimFunction = 'trim';
				break;
			}
			$ret = array();
			reset($array);
			if (is_array(current($array))) {
				while (list(,$lineArr) = each($array)) {
					if (!is_array($lineArr)) {
						//Could issue a warning ...
						$ret[] = array();
					} else {
						$subArr = array();
						while (list(,$val) = each($lineArr)) {
							$val      = _valToCsvHelper($val, $separator, $trimFunction);
							$subArr[] = $val;
						}
					}
					$ret[] = join($separator, $subArr);
				}
				$crlf = _define_newline();
				return join($crlf, $ret);
			} else {
				while (list(,$val) = each($array)) {
					$val   = _valToCsvHelper($val, $separator, $trimFunction);
					$ret[] = $val;
				}
				return join($separator, $ret);
			}
		}

		function _define_newline() {
			$unewline = "\r\n";
			if (strstr(strtolower($_SERVER["HTTP_USER_AGENT"]), 'win')) {
			   $unewline = "\r\n";
			} else if (strstr(strtolower($_SERVER["HTTP_USER_AGENT"]), 'mac')) {
			   $unewline = "\r";
			} else {
			   $unewline = "\n";
			}
			return $unewline;
		}

		function _get_browser_type() {
			$USER_BROWSER_AGENT="";

			if (ereg('OPERA(/| )([0-9].[0-9]{1,2})', strtoupper($_SERVER["HTTP_USER_AGENT"]), $log_version)) {
				$USER_BROWSER_AGENT='OPERA';
			} else if (ereg('MSIE ([0-9].[0-9]{1,2})',strtoupper($_SERVER["HTTP_USER_AGENT"]), $log_version)) {
				$USER_BROWSER_AGENT='IE';
			} else if (ereg('OMNIWEB/([0-9].[0-9]{1,2})', strtoupper($_SERVER["HTTP_USER_AGENT"]), $log_version)) {
				$USER_BROWSER_AGENT='OMNIWEB';
			} else if (ereg('MOZILLA/([0-9].[0-9]{1,2})', strtoupper($_SERVER["HTTP_USER_AGENT"]), $log_version)) {
				$USER_BROWSER_AGENT='MOZILLA';
			} else if (ereg('KONQUEROR/([0-9].[0-9]{1,2})', strtoupper($_SERVER["HTTP_USER_AGENT"]), $log_version)) {
		    	$USER_BROWSER_AGENT='KONQUEROR';
			} else {
		    	$USER_BROWSER_AGENT='OTHER';
			}
	
			return $USER_BROWSER_AGENT;
		}

		function _get_mime_type() {
			$USER_BROWSER_AGENT= _get_browser_type();

			$mime_type = ($USER_BROWSER_AGENT == 'IE' || $USER_BROWSER_AGENT == 'OPERA')
				? 'application/octetstream'
				: 'application/octet-stream';
			return $mime_type;
		}

		function createcsv($table = 'users', $sep) {
			global $wpdb;
			// Get the columns and create the first row of the CSV
			switch($table) {
				case 'comments':
					$fields = array('Name','E-Mail','URL');
					break;
				case 'users':
				default:
					$fields = array('URL','E-Mail','URL','Display Name','Registration Date','First Name','Last Name','Nickname');
					break;
			}
			$csv = arrayToCsvString($fields, $sep);
			$csv .= _define_newline();

			// Query the entire contents from the Users table and put it into the CSV
			switch($table) {
				case 'comments':
					$query = "SELECT DISTINCT comment_author, comment_author_email, comment_author_url FROM $wpdb->comments WHERE comment_approved = '1'";
					break;
				case 'users':
				default:
					$query = "SELECT ID as UID, user_email, user_url, user_nicename, user_registered FROM $wpdb->users";
					break;
			}
			$results = $wpdb->get_results($query,ARRAY_A);
			$i=0;
			if ($table == 'users') {
				while ($i < count($results)) {
					$query = "SELECT meta_value FROM ".$wpdb->prefix."usermeta WHERE user_id = ".$results[$i]['UID']." AND meta_key = ";
					$fnquery = $query . "'first_name'";
					$results[$i]['first_name'] = $wpdb->get_var($fnquery);
					$lnquery = $query . "'last_name'";
					$results[$i]['last_name'] = $wpdb->get_var($lnquery);
					$nnquery = $query . "'nickname'";
					$results[$i]['nickname'] = $wpdb->get_var($nnquery);
					$i++;
				}
			}
			$csv .= arrayToCsvString($results, $sep);

			$now = gmdate('D, d M Y H:i:s') . ' GMT';

			header('Content-Type: ' . _get_mime_type());
			header('Expires: ' . $now);

			header('Content-Disposition: attachment; filename="'.$table.'.csv"');
			header('Pragma: no-cache');

			echo $csv;
		}

		function yoast_getcsv() {
			if (isset($_GET['csv']) && $_GET['csv'] == "true") {
				if ( !current_user_can('edit_users') )
					wpdie('No, that won\'t be working, sorry.');
				$table = $_GET['table'];
				$sep = ";";
				if (isset($_GET['sep'])) {
					$sep = $_GET['sep'];
					if ($sep == "tab") {
						$sep = "\t";
					}
				}
				// echo $table;
				createcsv($table, $sep);
				exit;
			}			
		}
		add_action('admin_menu','yoast_getcsv');
	}

	if ( ! class_exists( 'Users2CSV' ) && !$_GET['csv'] == "true" ) {

		class Users2CSV {

			function add_config_page() {
				global $wpdb;
				add_submenu_page('users.php', 'Export Users and Commenters to CSV file', 'Users2CSV', 'edit_users', basename(__FILE__), array('Users2CSV','config_page'));
				add_filter( 'plugin_action_links', array( 'Users2CSV', 'filter_plugin_actions'), 10, 2 );
				add_filter( 'ozh_adminmenu_icon', array( 'Users2CSV', 'add_ozh_adminmenu_icon' ) );				
			}
		
			function config_page() {
				$baseurl = admin_url( 'users.php?page=' . basename(__FILE__) );
			?>
			<div class="wrap" style="max-width:600px !important;">
				<h2>Export Users and Commenters to CSV file</h2>
				<p>
					Note: this plugin was created to allow easy creation of a mailing list for a blog, please make sure you get people to opt-in after your first message to them!
				</p>
				<p><strong>Normal export with semicolons as separator:</strong></p>
				<ul>
					<li><a href="<?php echo $baseurl ?>&amp;csv=true&amp;table=users">Export Users</a></li>
					<li><a href="<?php echo $baseurl ?>&amp;csv=true&amp;table=comments">Export Unique Commenters</a></li>
				</ul>
				<p><strong>Export with tabs as separator:</strong></p>
				<ul>
					<li><a href="<?php echo $baseurl ?>&amp;csv=true&amp;table=users&amp;sep=tab">Export Users</a></li>
					<li><a href="<?php echo $baseurl ?>&amp;csv=true&amp;table=comments&amp;sep=tab">Export Unique Commenters</a></li>
				</ul>
				
				<h2>Support</h2>
				<p>Having issues with this plugin? Please check the <a href="http://wordpress.org/tags/users-to-csv">support forums</a> if the issue has been addressed already, if it hasn't been, feel free to open a new thread there and I'll respond as soon as possible!</p>	
							
				<h2>Do you like this plugin?</h2>
				<ul style="list-style-type:square; padding-left: 30px;">
					<li>Blog about it! Tell your readers you like it! (And don't forget to link to its <a href="http://yoast.com/wordpress/users-to-csv/">homepage</a> :) )</li>
					<li>Give it a <a href="http://wordpress.org/extend/plugins/users-to-csv/">good rating on WordPress.org</a>.</li>
					<li>Or <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=3262694">send me a small donation</a>! (through PayPal, if you don't have an account, you can donate with your credit card or bank account through PayPal too!)</li>
				</li>
			</div>
			<?php
			}
			
			function add_ozh_adminmenu_icon($hook) {
				static $users2csvicon;
				if (!$users2csvicon) {
					$users2csvicon = plugin_dir_url( __FILE__ ). '/icon-csv.png';
				}
				if ($hook == 'users2csv.php') return $users2csvicon;
				return $hook;
			}

			function filter_plugin_actions( $links, $file ){
				//Static so we don't call plugin_basename on every plugin row.
				static $this_plugin;
				if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

				if ( $file == $this_plugin ){
					$settings_link = '<a href="'.admin_url('users.php?page=users2csv.php').'">' . __('Export') . '</a>';
					array_unshift( $links, $settings_link ); // before other links
				}
				return $links;
			}
			
		}
		add_action('admin_menu', array('Users2CSV','add_config_page'));
	}

}
?>
