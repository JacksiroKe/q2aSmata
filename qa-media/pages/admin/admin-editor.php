<?php
/*
	Question2Answer by Gideon Greenspan and contributors
	http://www.question2answer.org/

	File: qa-include/qa-page-admin-editor.php
	Description: Added this file for loading html editor


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

	require_once QA_INCLUDE_DIR.'db/recalc.php';
	require_once QA_INCLUDE_DIR.'app/admin.php';
	require_once QA_INCLUDE_DIR.'db/admin.php';


//	Check admin privileges (do late to allow one DB query)

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;
	$saved=false;		
	
	$errors = array();
	$securityexpired = false;

	$formokhtml = null;
	
	$item = qa_get('item');
	$checkboxtodisplay = null;
	
	if (isset($item)) {
		$showoptions = array('show_' . $item, $item);	
		$checkboxtodisplay = array(
			'logo_url' => 'option_logo_show',
			'logo_width' => 'option_logo_show',
			'logo_height' => 'option_logo_show',
			'custom_sidebar' => 'option_show_custom_sidebar',
			'custom_sidepanel' => 'option_show_custom_sidepanel',
			'custom_header' => 'option_show_custom_header',
			'custom_footer' => 'option_show_custom_footer',
			'custom_in_head' => 'option_show_custom_in_head',
			'custom_home_heading' => 'option_show_custom_home',
			'custom_home_content' => 'option_show_custom_home',
			'home_description' => 'option_show_home_description',
		);
		
		if (qa_clicked('doresetoptions')) {
			if (!qa_check_form_security_code('admin/layout', qa_post_text('code')))
				$securityexpired = true;

			else {
				qa_reset_options($getoptions);
				$formokhtml = qa_lang_html('admin/options_reset');
			}
		} elseif (qa_clicked('dosaveoptions')) {		
			foreach ($getoptions as $optionname) {
				$optionvalue = qa_post_text('option_' . $optionname);
				if (
						(@$optiontype[$optionname] == 'number') ||
						(@$optiontype[$optionname] == 'checkbox') ||
						((@$optiontype[$optionname] == 'number-blank') && strlen($optionvalue))
				)
					$optionvalue = (int) $optionvalue;
					
				qa_set_option($optionname, $optionvalue);
			}

			$formokhtml = qa_lang_html('admin/options_saved');
		}
		
		$getoptions = array();
		foreach ($showoptions as $optionname)
			if (strlen($optionname) && (strpos($optionname, '/') === false)) // empties represent spacers in forms
				$getoptions[] = $optionname;
		
	//	Get the information to display
		$options = qa_get_options($getoptions);

		$qa_content=qa_content_prepare();

		$qa_content['title']=qa_lang_html('admin/admin_title').' ::  Editor';
		$qa_content['error']=qa_admin_page_error();	
		
		$in=array();	
		$editorname=isset($in['editor']) ? $in['editor'] : qa_opt('editor_for_qs');
		$editor=qa_load_editor(@$in['content'], @$in['format'], $editorname);
		
		$field = qa_editor_load_field($editor, $qa_content, @$in['content'], 
				@$in['format'], $item, 12, false);
		
		$qa_content['script_rel'][] = 'qa-content/qa-admin.js?'.QA_VERSION;

		$qa_content['form'] = array(
			'ok' => $formokhtml,

			'tags' => 'method="post" action="'.qa_self_html().'" name="admin_form" onsubmit="document.forms.admin_form.has_js.value=1; return true;"',

			'style' => 'tall',

			'fields' => array(
				array(
					'id' => 'custom_home_content',
					'type' => 'textarea',
					'label' => qa_lang_html('options/show_'.$item),
					'value' => qa_opt($item),
					'tags' => 'name="'.$item.'" id="'.$item.'"',
					'rows' => 12,
					'error' => qa_html(@$errors[$item]),
				),	
				
			),

			'buttons' => array(
				'save' => array(
					'tags' => 'id="dosaveoptions"',
					'label' => qa_lang_html('admin/save_options_button'),
				),

				'reset' => array(
					'tags' => 'name="doresetoptions" onclick="return confirm('.qa_js(qa_lang_html('admin/reset_options_confirm')).');"',
					'label' => qa_lang_html('admin/reset_options_button'),
				),
			),

			'hidden' => array(
				'dosaveoptions' => '1', // for IE
				'has_js' => '0',
				'code' => qa_get_form_security_code('admin/layouteditor'),
			),
		);

	}
	
	$qa_content['navigation']['sub']=qa_admin_sub_navigation();


	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
