<?php
/*
Plugin Name: Users to CSV
Plugin URI: http://www.joostdevalk.nl/wordpress/users-to-csv/
Description: This plugin adds an administration screen which allows you to dump your users and/or unique commenters to a csv file. Built with code borrowed from <a href="http://www.mt-soft.com.ar/2007/06/19/csv-dump/">IAM CSV dump</a>.
Author: Joost de Valk
Version: 1.0
Author URI: http://www.joostdevalk.nl/
*/

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

function createcsv($table = 'users') {
	global $wpdb;
	// Get the columns and create the first row of the CSV
	switch($table) {
		case 'users':
			$query = "SHOW COLUMNS FROM $wpdb->users";
			$results = $wpdb->get_results($query,ARRAY_A);
			$fields = array();
			foreach ($results as $result) {
				$fields[] = $result['Field'];
			}
			break;
		case 'comments':
			$fields = array('comment_author','comment_author_email','comment_author_url');
			break;
		default:
			$query = "SHOW COLUMNS FROM $wpdb->users";
			$results = $wpdb->get_results($query,ARRAY_A);
			$fields = array();
			foreach ($results as $result) {
				$fields[] = $result['Field'];
			}
			break;
	}
	$csv = arrayToCsvString($fields);
	$csv .= _define_newline();

	// Query the entire contents from the Users table and put it into the CSV
	switch($table) {
		case 'users':
			$query = "SELECT * FROM $wpdb->users";
			break;
		case 'comments':
			$query = "SELECT DISTINCT comment_author, comment_author_email, comment_author_url FROM $wpdb->comments WHERE comment_approved = '1'";
			break;
		default:
			$query = "SELECT * FROM $wpdb->users";
			break;
	}
	$results = $wpdb->get_results($query,ARRAY_A);
	$csv .= arrayToCsvString($results);

	$now = gmdate('D, d M Y H:i:s') . ' GMT';

	header('Content-Type: ' . _get_mime_type());
	header('Expires: ' . $now);

	header('Content-Disposition: attachment; filename="'.$table.'.csv"');
	header('Pragma: no-cache');

	echo $csv;
}

if ($_GET['csv'] == "true") {
	$table = $_GET['table'];
	// echo $table;
	createcsv($table);
	exit;
}
/*
 * Admin User Interface
 */

if ( ! class_exists( 'Users2CSV' ) && !$_GET['csv'] == "true" ) {

	class Users2CSV {

		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_submenu_page('users.php', 'Export Users and Commenters to CSV file', 'Users2CSV', 1, basename(__FILE__), array('Users2CSV','config_page'));
			}
		}
		
		function config_page() {
		?>
		<div class="wrap">
			<h2>Export Users and Commenters to CSV file</h2>
			<p>
				Note: this plugin was created to allow easy creation of a mailinglist for a blog, please make sure you get people to opt-in after your first message to them!
			</p>
			<ul>
				<li><a href="<?php bloginfo('url');?>/wp-admin/users.php?page=<?php echo basename(__FILE__); ?>&amp;csv=true&amp;table=users">Export Users to CSV file</a></li>
				<li><a href="<?php bloginfo('url');?>/wp-admin/users.php?page=<?php echo basename(__FILE__); ?>&amp;csv=true&amp;table=comments">Export Unique Commenters to CSV file</a></li>
			</ul>
		</div>
		<?php
		}
	}
}

add_action('admin_menu', array('Users2CSV','add_config_page'));

?>