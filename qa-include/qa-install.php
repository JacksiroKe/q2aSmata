<?php
/*
	Question2Answer by Gideon Greenspan and contributors
	http://www.question2answer.org/

	Description: User interface for installing, upgrading and fixing the database


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../');
	exit;
}

require_once QA_INCLUDE_DIR.'db/install.php';

qa_report_process_stage('init_install');


// Define database failure handler for install process, if not defined already (file could be included more than once)

if (!function_exists('qa_install_db_fail_handler')) {
	/**
	 * Handler function for database failures during the installation process
	 * @param $type
	 * @param int $errno
	 * @param string $error
	 * @param string $query
	 */
	function qa_install_db_fail_handler($type, $errno = null, $error = null, $query = null)
	{
		global $pass_failure_from_install;

		$pass_failure_type = $type;
		$pass_failure_errno = $errno;
		$pass_failure_error = $error;
		$pass_failure_query = $query;
		$pass_failure_from_install = true;

		require QA_INCLUDE_DIR.'qa-install.php';

		qa_exit('error');
	}
}


if (ob_get_level() > 0) {
	// clears any current theme output to prevent broken design
	ob_end_clean();
}

$pgtitle = $success = $errorhtml = $suggest = '';
$buttons = array();
$fields = array();
$fielderrors = array();
$hidden = array();

// Process user handling higher up to avoid 'headers already sent' warning

if (!isset($pass_failure_type) && qa_clicked('super')) {
	require_once QA_INCLUDE_DIR.'db/admin.php';
	require_once QA_INCLUDE_DIR.'db/users.php';
	require_once QA_INCLUDE_DIR.'app/users-edit.php';

	if (qa_db_count_users() == 0) { // prevent creating multiple accounts
		$infirstname = qa_post_text('qa_firstname');
		$inlastname = qa_post_text('qa_lastname');
		$insex = qa_post_text('qa_sex');
		$incountry = qa_post_text('qa_country');
		$inmobile = qa_post_text('qa_mobile');
		$inemail = qa_post_text('qa_email');
		$inpassword = qa_post_text('qa_password');
		$inhandle = qa_post_text('qa_handle');

		$fielderrors = array_merge(
			qa_handle_email_filter($inhandle, $inemail),
			qa_password_validate($inpassword)
		);

		if (empty($fielderrors)) {
			require_once QA_INCLUDE_DIR.'app/users.php';

			$userid = qa_create_new_user($infirstname, $inlastname, $insex, $incountry, $inmobile, $inemail, $inpassword, $inhandle, QA_USER_LEVEL_SUPER);
			qa_set_logged_in_user($userid, $inhandle);

			qa_set_option('feedback_email', $inemail);

			$success .= "Congratulations - Your Question2Answer site is ready to go!\n\nYou are logged in as the super administrator and can start changing settings.\n\nThank you for installing Question2Answer.";
		}
	}
}

if (qa_clicked('connectdb')) {
	$database = qa_post_text('qa_database');
	$username = qa_post_text('qa_username');
	$password = qa_post_text('qa_password');
			
	$filename = QA_BASE_DIR . 'qa-config.php';
	$lines = file($filename, FILE_IGNORE_NEW_LINES );
	$lines[37] = "	define('QA_MYSQL_USERNAME', '".$username."');";
	$lines[38] = "	define('QA_MYSQL_PASSWORD', '".$password."');";
	$lines[39] = "	define('QA_MYSQL_DATABASE', '".$database."');";
	file_put_contents($filename, implode("\n", $lines));
	header("location: index.php");
}


// Output start of HTML early, so we can see a nicely-formatted list of database queries when upgrading

?><!DOCTYPE html>
<html>
	<head>
		<title>Question2Answer!</title>
		<style>
			body { font-family: arial,sans-serif; font-size:0px; margin: padding: 0;  background: url(qa-media/bg.jpg) fixed; background-size: 100%; color: #000; } h1{font-size:30px;} .selecta, input[type="text"],input[type="email"],input[type="password"],textarea{font-size:18px; padding:5px;width:100%; color:#000; } table{ width:98%;} input[type="submit"]{ color:#000; padding:5px 20px; font-size:25px; margin: 10px; } img { border: 0; } .outer_one { } .inner_one { } .inner_other { margin-top:10px; padding:20px;  } #content { margin: 0 auto;	width: 800px; } .title-section-error { background-color: rgba(256,0,0, 0.5); border-radius: 3px; border: 1px solid #f00; color: #fff; font-weight: bold; padding: 12px ;} .title-section-success { background-color: rgba(0,256,0, 0.5);  border-radius: 3px; border: 1px solid #0f0; color: #fff; font-weight: bold; padding: 12px ;} #debug { margin-top: 50px; }.main-section-error { font-size:20px;}.main-section-success { font-size:20px;} .content-section-error { background:rgba(256,256,256, 0.5); margin-top: 10px; font-size:20px; padding: 10px; border-radius: 3px; border: 1px solid #fff; }.content-section-success { background:rgba(256,256,256, 0.5); margin-top: 10px; font-size:20px; border-radius: 3px; border: 1px solid #000; padding: 10px; } .content-section-footer{background: rgba(0,0,0, 0.5); color: #fff; border-radius: 3px; border: 1px solid #000;}
		</style>
	</head>
	<body>
		<div id="content">
<?php
if (isset($pass_failure_type)) {
	// this page was requested due to query failure, via the fail handler
	switch ($pass_failure_type) {
		case 'connect':
			$pgtitle .= 'Database connection Failed!';
			$errorhtml .= 'Could not establish database connection. Please enter the correct details.';
			$fields = array(
				'qa_database' => array( 'label' => 'Database Name:', 'type' => 'text', 'tags' => 'required' ),
				'qa_username' => array( 'label' => 'Database Username:', 'type' => 'text', 'tags' => 'required' ),
				'qa_password' => array( 'label' => 'Database Password:', 'type' => 'password' ),
			);
			$buttons = array('connectdb' => 'Connect to the Database');
			break;
			
		case 'select':
			$pgtitle .= 'Database switching Failed';
			$errorhtml .= 'Could not switch to the Question2Answer database. Please check the database name in the config file, and if necessary create the database in MySQL and grant appropriate user privileges.';
			break;

		case 'query':
			global $pass_failure_from_install;

			if (@$pass_failure_from_install) {
				$pgtitle .= 'Installation Query Failed';
				$errorhtml .= "Question2Answer was unable to perform the installation query below. Please check the user in the config file has CREATE and ALTER permissions:\n\n".qa_html($pass_failure_query."\n\nError ".$pass_failure_errno.": ".$pass_failure_error."\n\n");
			}
			else {
				$pgtitle = 'A Database Query Failed';
				$errorhtml .= "An Question2Answer database query failed when generating this page.\n\nA full description of the failure is available in the web server's error log file.";
			}
			break;
	}
}
else {
	// this page was requested by user GET/POST, so handle any incoming clicks on buttons

	if (qa_clicked('create')) {
		qa_db_install_tables();

		if (QA_FINAL_EXTERNAL_USERS) {
			if (defined('QA_FINAL_WORDPRESS_INTEGRATE_PATH')) {
				require_once QA_INCLUDE_DIR.'db/admin.php';
				require_once QA_INCLUDE_DIR.'app/format.php';

				// create link back to WordPress home page
				qa_db_page_move(qa_db_page_create(get_option('blogname'), QA_PAGE_FLAGS_EXTERNAL, get_option('home'), null, null, null), 'O', 1);

				$success .= 'Your Question2Answer database has been created and integrated with your WordPress site.';

			}
			elseif (defined('QA_FINAL_JOOMLA_INTEGRATE_PATH')) {
				require_once QA_INCLUDE_DIR.'db/admin.php';
				require_once QA_INCLUDE_DIR.'app/format.php';
				$jconfig = new JConfig();

				// create link back to Joomla! home page (Joomla doesn't have a 'home' config setting we can use like WP does, so we'll just assume that the Joomla home is the parent of the Q2A site. If it isn't, the user can fix the link for themselves later)
				qa_db_page_move(qa_db_page_create($jconfig->sitename, QA_PAGE_FLAGS_EXTERNAL, '../', null, null, null), 'O', 1);
				$success .= 'Your Question2Answer database has been created and integrated with your Joomla! site.';
			}
			else {
				$success .= 'Your Question2Answer database has been created for external user identity management. Please read the online documentation to complete integration.';
			}
		}
		else {
			$success .= 'Your Question2Answer database has been created.';
		}
	}

	if (qa_clicked('nonuser')) {
		qa_db_install_tables();
		$success .= 'The additional Question2Answer database tables have been created.';
	}

	if (qa_clicked('upgrade')) {
		qa_db_upgrade_tables();
		$success .= 'Your Question2Answer database has been updated.';
	}

	if (qa_clicked('repair')) {
		qa_db_install_tables();
		$success .= 'The Question2Answer database tables have been repaired.';
	}

	qa_initialize_postdb_plugins();
	if (qa_clicked('module')) {
		$moduletype = qa_post_text('moduletype');
		$modulename = qa_post_text('modulename');

		$module = qa_load_module($moduletype, $modulename);

		$queries = $module->init_queries(qa_db_list_tables());

		if (!empty($queries)) {
			if (!is_array($queries))
				$queries = array($queries);

			foreach ($queries as $query)
				qa_db_upgrade_query($query);
		}

		$success .= 'The '.$modulename.' '.$moduletype.' module has completed database initialization.';
	}

}

if (qa_db_connection(false) !== null && !@$pass_failure_from_install) {
	$check = qa_db_check_tables(); // see where the database is at

	switch ($check) {
		case 'none':
			if (@$pass_failure_errno == 1146) // don't show error if we're in installation process
				$errorhtml = '';
			$pgtitle = 'Welcome to Question2Answer';
			$errorhtml .= 'Welcome to Question2Answer. It\'s time to set up your database!';

			if (QA_FINAL_EXTERNAL_USERS) {
				if (defined('QA_FINAL_WORDPRESS_INTEGRATE_PATH')) {
					$errorhtml .= "\n\nWhen you click below, your Question2Answer site will be set up to integrate with the users of your WordPress site <a href=\"".qa_html(get_option('home'))."\" target=\"_blank\">".qa_html(get_option('blogname'))."</a>. Please consult the online documentation for more information.";
				}
				elseif (defined('QA_FINAL_JOOMLA_INTEGRATE_PATH')) {
					$jconfig = new JConfig();
					$errorhtml .= "\n\nWhen you click below, your Question2Answer site will be set up to integrate with the users of your Joomla! site <a href=\"../\" target=\"_blank\">".$jconfig->sitename."</a>. It's also recommended to install the Joomla QAIntegration plugin for additional user-access control. Please consult the online documentation for more information.";
				}
				else {
					$errorhtml .= "\n\nWhen you click below, your Question2Answer site will be set up to integrate with your existing user database and management. Users will be referenced with database column type ".qa_html(qa_get_mysql_user_column_type()).". Please consult the online documentation for more information.";
				}

				$buttons = array('create' => 'Set up the Database');
			}
			else {
				$errorhtml .= "\n\nWhen you click below, your Question2Answer database will be set up to manage user identities and logins internally.\n\nIf you want to offer a single sign-on for an existing user base or website, please consult the online documentation before proceeding.";
				$buttons = array('create' => 'Set up the Database including User Management');
			}
			break;

		case 'old-version':
			$pgtitle = 'Need to Upgrade Database';
			// don't show error if we need to upgrade
			if (!@$pass_failure_from_install)
				$errorhtml = '';

			// don't show error before this
			$errorhtml .= 'Your Question2Answer database needs to be upgraded for this version of the software.';
			$buttons = array('upgrade' => 'Upgrade the Database');
			break;

		case 'non-users-missing':		
			$pgtitle = 'Non-Users Missing';
			$errorhtml = 'This Question2Answer site is sharing its users with another APS site, but it needs some additional database tables for its own content. Please click below to create them.';
			$buttons = array('nonuser' => 'Set up the Tables');
			break;

		case 'table-missing':
			$pgtitle = 'Database missing Tables';
			$errorhtml .= 'One or more tables are missing from your Question2Answer database.';
			$buttons = array('repair' => 'Repair the Database');
			break;

		case 'column-missing':
			$pgtitle = 'Database Missing Columns';
			$errorhtml .= 'One or more Question2Answer database tables are missing a column.';
			$buttons = array('repair' => 'Repair the Database');
			break;

		default:
			require_once QA_INCLUDE_DIR.'db/admin.php';

			if (!QA_FINAL_EXTERNAL_USERS && qa_db_count_users() == 0) {
				$pgtitle = 'Set up a Super Admin';
				$errorhtml .= "There are currently no users in the Question2Answer database.\n\nPlease enter your details below to create the super administrator:";
				$fields = array(
					'qa_firstname' => array('label' => 'First Name:', 'type' => 'text' ),
					'qa_lastname' => array('label' => 'Last Name:', 'type' => 'text' ),
					'qa_sex' => array('label' => 'You are a:', 'type' => 'radio', 
						'options' => array("Male", "Female"), 'values' => array("1", "2")),
					'qa_country' => array('label' => 'Your Country:', 'type' => 'select'                                         , 
						'options' => array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia", "Botswana", "Brazil", "British Indian Ocean Territory", "British Virgin Islands", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos Islands", "Colombia", "Comoros", "Cook Islands", "Costa Rica", "Croatia", "Cuba", "Curacao", "Cyprus", "Czech", "Democratic Republic of the Congo", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands", "Faroe Islands", "Fiji", "Finland", "France", "French Polynesia", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guam", "Guatemala", "Guernsey", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Isle of Man", "Israel", "Italy", "Ivory Coast", "Jamaica", "Japan", "Jersey", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kosovo", "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "North", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto", "Qatar", "Republic of the", "Reunion", "Romania", "Russia", "Rwanda", "Saint Barthelemy", "Saint Helena", "Saint Kitts and Nevis", "Saint Lucia", "Saint Martin", "Saint Pierre and Miquelon", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Sint Maarten", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan", "SuriLineOne", "Svalbard and Jan Mayen", "Swaziland", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "U.S. Virgin Islands", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican", "Venezuela", "Vietnam", "Wallis and Futuna", "Western Sahara", "Yemen", "Zambia", "Zimbabwe"),
						'values' => ''),
					'qa_mobile' => array('label' => 'Mobile Phone:', 'type' => 'text' ),
					'qa_email' => array('label' => 'Email address:', 'type' => 'email' ),
					'qa_handle' => array('label' => 'Username:', 'type' => 'text' ),
					'qa_password' => array('label' => 'Password:', 'type' => 'password' ),
					//'passcon' => array('label' => 'Confirm Password:', 'type' => 'password' ),
				);
				$buttons = array('super' => 'Set up the Super Administrator');
			}
			else {
				$tables = qa_db_list_tables();

				$moduletypes = qa_list_module_types();

				foreach ($moduletypes as $moduletype) {
					$modules = qa_load_modules_with($moduletype, 'init_queries');

					foreach ($modules as $modulename => $module) {
						$queries = $module->init_queries($tables);
						if (!empty($queries)) {
							// also allows single query to be returned
							$errorhtml = strtr(qa_lang_html('admin/module_x_database_init'), array(
								'^1' => qa_html($modulename),
								'^2' => qa_html($moduletype),
								'^3' => '',
								'^4' => '',
							));

							$buttons = array('module' => 'Initialize the Database');

							$hidden['moduletype'] = $moduletype;
							$hidden['modulename'] = $modulename;
							break;
						}
					}
				}
			}
			break;
	}
}

if (empty($errorhtml)) {
	if (empty($success)) {
		$pgtitle = 'Database has been checked';
		$success = 'Your Question2Answer database has been checked with no problems.';
	}
	$suggest = '<a href="'.qa_path_html('admin', null, null, QA_URL_FORMAT_SAFEST).'">Go to admin center</a>';
}

if (strlen($errorhtml)) {
	echo '<div class="main-section-error rounded">
			<div class="title-section-error rounded_i">'."\n\t\t	
				<h1>".$pgtitle."</h1>\n\t\t
			</div>\n\t\t";
	echo '<form method="post" action="'.qa_path_html('install', null, null, QA_URL_FORMAT_SAFEST).'">'."\n\t\t";
	echo '<div class="content-section-error">'."\n\t\t";
	echo '<p class="msg-error">'.nl2br(qa_html($errorhtml))."</p>\n\t\t";
} 
elseif (strlen($success)) {
	echo '<div class="main-section-success rounded">
			<div class="title-section-success rounded_i">'."\n\t\t	
				<h1>".$pgtitle."</h1>\n\t\t
			</div>\n\t\t";
	echo '<form method="post" action="'.qa_path_html('install', null, null, QA_URL_FORMAT_SAFEST).'">'."\n\t\t";
	echo '<div class="content-section-success">'."\n\t\t";
	echo '<p class="msg-success">'.nl2br(qa_html($success))."</p>\n\t\t";
}

if (strlen($suggest)) echo '<p>'.$suggest.'</p>'."\n\t\t";

// Very simple general form display logic (we don't use theme since it depends on tons of DB options)

if (count($fields)) {
	echo "\n\t<hr/>\n\t".'<table style="text-align:left;">'."\n\t\t";
	
	foreach($fields as $name => $field) {
		echo '<tr><th>'.qa_html($field['label']).'</th><td>'."\n\t\t";
		if (array_key_exists('tags', $field)) $tags =  ' ' . qa_html($field['tags']);
		else $tags = '';
		switch ( $field['type'] ) {		
			case  'select'	:	{
					echo '<select class="selecta" name="'.qa_html($name).'"'.$tags.'>';
					foreach ($field['options'] as $option) 
						echo '<option value="'.$option.'">'.$option.'</option>'."\n\t\t\t";
					echo "</select></td>\n\t\t";
				}
				break;
			case 'radio' 	:	{
					$i = 0; 
					foreach ($field['options'] as $option) {
						echo '<label class="required input_radio">',
						'<input type="'.qa_html($field['type']).'" name="'.qa_html($name).
						'" value="'.$field['values'][$i].'"'.$tags.'/> '.$option.' </label>';
						$i++;
					}
					echo "</td>\n\t\t";
				}
				break;
			default:
				echo '<input type="'.qa_html($field['type']).'" name="'.qa_html($name).'"'.$tags.'/></td>'."\n\t\t";
		}
		
		if (isset($fielderrors[$name]))
			echo '<td class="msg-error"><small>'.qa_html($fielderrors[$name]).'</small></td>'."\n\t\t";
		else
			echo "<td></td>\n\t\t";
		echo "</tr>\n\t\t";
	}
	echo '</table>';
}

foreach ($buttons as $name => $value)
	echo '<div align="right"><input type="submit" name="'.qa_html($name).'" value="'.qa_html($value).'"/></div>'."\n\t\t";

foreach ($hidden as $name => $value)
	echo '<input type="hidden" name="'.qa_html($name).'" value="'.qa_html($value).'"/>'."\n\t\t";

qa_db_disconnect();

?>
			<br><br></div>

		</form>
		<div class="content-section-footer inner_other">
			<center>
				<p>Copyright &copy; Question2Answer by Gideon Greenspan and contributors <?php echo date('Y') ?></p>
			</center>
		</div>
	</body>
</html>
