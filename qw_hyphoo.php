<?php
/*
Plugin Name: Hyphoo
Plugin URI: http://www.plazoo.com/en/tools.asp
Description: This Plugin automatically hyphenates your posts 
Version: 1.0.0
Author: Qlikworld.com (Christian Sand)
*/

$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain( 'qwhyphoo', null, $plugin_dir);

function qw_hyphoo_hyphenate_content($content = '') {
	$qw_hyphoo_content_to_hyphenate = $_POST['qw_hyphoo_edit_option_content_to_hyphenate']; 
	$qw_hyphoo_setlink = $_POST['qw_hyphoo_edit_option_setlink']; 
	$qw_hyphoo_post_title = $_POST['post_title'];
	
	if (is_array($qw_hyphoo_content_to_hyphenate) === true) $qw_hyphoo_content_to_hyphenate = join(',', $qw_hyphoo_content_to_hyphenate);
	$qw_hyphoo_setlink = ($qw_hyphoo_setlink == '1' ? true : false);
	
	if(strpos($qw_hyphoo_content_to_hyphenate, 'c') !== false) {
		return qw_hyphoo_hyphenatetext($content, $qw_hyphoo_setlink, htmlspecialchars($qw_hyphoo_post_title));
	}
	return $content;
}
function qw_hyphoo_hyphenate_excerpt($content = '') {
	$qw_hyphoo_content_to_hyphenate = $_POST['qw_hyphoo_edit_option_content_to_hyphenate']; 
	if (is_array($qw_hyphoo_content_to_hyphenate) === true) $qw_hyphoo_content_to_hyphenate = join(',', $qw_hyphoo_content_to_hyphenate);
	
	if(strpos($qw_hyphoo_content_to_hyphenate, 'e') !== false) {
		return qw_hyphoo_hyphenatetext($content, false, '');
	}
	return $content;
}

function unichr($u) { return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES'); }

function qw_hyphoo_prepare_content($content = '') {
	if (strpos($content, unichr(173)) !== false) { $content = str_replace(unichr(173), '&shy;', $content); }
	return $content;
}

function qw_hyphoo_remove_link($content = '') {
	// check for old Link and remove it
	$qw_hyphoo_linkpos = strpos($content, '<div id="qwhpoorm">');
	if ($qw_hyphoo_linkpos === false) {$qw_hyphoo_linkpos = strpos($content, '<div id="qwhpoolnk" '); }
	if ($qw_hyphoo_linkpos !== false) { $content = substr($content, 0, $qw_hyphoo_linkpos); }
	return qw_hyphoo_prepare_content($content);
}

function qw_hyphoo_hyphenatetext($content = '', $setlink = true, $title = '') {
	
	// write timestamp to DB of last hyphenation
	$doHyphenation = $_POST['qw_hyphoo_edit_option_hyphenate'];
	$qw_hyphoo_language = $_POST['qw_hyphoo_edit_option_language']; 
	
	if (strpos(',h,r,n,', ','.$doHyphenation.',') === false) $doHyphenation = 'n';
	if (strpos(',DE,EN,FR,CS,NL,RU,', ','.$qw_hyphoo_language.',') === false) $qw_hyphoo_language = 'DE';
	
	$qw_hyphoo_todaycounter = get_option('qw_hyphoo_settings_todaycounter', 0);
	$qw_hyphoo_timestamp_lasthyphenation = strtotime(get_option('qw_hyphoo_settings_timestamp_lasthyphenation', strtotime('1900-01-01')));
	
	$qw_hyphoo_plazoolink = qw_hyphoo_get_plazoolink($qw_hyphoo_language);
	$qw_hyphoo_txt_linktext = sprintf(__('created with "hypho-o" by <a title="%s" href="http://www.plazoo.com/%s">plazoo.com</a>', 'qwhyphoo'), $title, $qw_hyphoo_plazoolink);
	
    $current_date = strtotime(date_i18n('Y-m-d H:i:s'));
    $secondsdiff = ($current_date - $qw_hyphoo_timestamp_lasthyphenation);
	
	if (strlen(trim($content)) > 0) {
		
		if (($doHyphenation == 'h') && ($qw_hyphoo_todaycounter < 5)) {
			
			// only save if the last hyphenation is older than 30 seconds (because content + excerpt will cause this function to be called twice)
			if ($secondsdiff > 30) { 
				$qw_hyphoo_todaycounter += 1;
				update_option('qw_hyphoo_settings_todaycounter', $qw_hyphoo_todaycounter); 
				update_option('qw_hyphoo_settings_timestamp_lasthyphenation', date_i18n('Y-m-d H:i:s')); 
			}
			
			$soapsource = qw_hyphoo_getsoapdata('html', $content, 'html', '&shy;', '', $qw_hyphoo_language);
			$soapresult = qw_hyphoo_callwebservice('hyphoo.webservices.plazoo.com', '80', '/service.asmx', $soapsource, 'http://tempuri.org/Hyphenate');
			$hyphenatedtext = qw_hyphoo_gettext_from_soap_result($soapresult);
			
			// Only insert a Link ti us when the hyphenator was used
			if (strlen($hyphenatedtext) > 0) { $content = $hyphenatedtext; }
		}
		elseif ($doHyphenation == 'r') { $content = qw_hyphoo_remove_soft_hyphens($content); }
		
		if ($setlink === true) {
			if (strpos($content, '<!--more-->') === false) { $content .= '<div id="qwhpoorm"><!--more--></div>'; }
			$content .= '<div id="qwhpoolnk" style="text-align:right;font-size:8pt;"><br /><br />'.$qw_hyphoo_txt_linktext.'</div>';
		}
	}
	
	return $content;
}

function qw_hyphoo_get_plazoolink($language) {
	$language = strtolower($language);
	return ($language === 'en' ? '' : ($language === 'de' ? 'ge/' : $language.'/'));
}

function qw_hyphoo_add_admin_menues() {
	$qw_hyphoo_txt_options = __('Hypho-o Options', 'qwhyphoo');
	$qw_hyphoo_txt_settings = __('Hypho-o', 'qwhyphoo');
	add_meta_box('qw_hyphoo_edit_options', $qw_hyphoo_txt_options, 'qw_hyphoo_print_edit_options_box', 'post', 'side', 'high');
	add_meta_box('qw_hyphoo_edit_options', $qw_hyphoo_txt_options, 'qw_hyphoo_print_edit_options_box', 'page', 'side', 'high');
	add_meta_box('qw_hyphoo_edit_options', $qw_hyphoo_txt_options, 'qw_hyphoo_print_edit_options_box', 'comment', 'normal', 'high');
	add_options_page('Hyphoo Settings', $qw_hyphoo_txt_settings, 'manage_options', 'qw_hyphoo_options', 'qw_hyphoo_print_settings_page');
}

function qw_hyphoo_print_edit_options_box($post) {
	$post_type = get_post_type($post);
	$qw_hyphoo_default_hyphenate = get_option('qw_hyphoo_settings_default_hyphenate', 'n');
	$qw_hyphoo_language = get_option('qw_hyphoo_settings_language', 'DE'); 
	$qw_hyphoo_content_to_hyphenate = get_option('qw_hyphoo_settings_content_to_hyphenate', ''); 
	$qw_hyphoo_todaycounter = get_option('qw_hyphoo_settings_todaycounter', 0);
	
	$qw_hyphoo_timestamp_lasthyphenation = strtotime(date('Y-m-d', strtotime(get_option('qw_hyphoo_settings_timestamp_lasthyphenation', strtotime('1900-01-01')))));
    $current_date = strtotime(date_i18n('Y-m-d'));
    $daysdiff = ($current_date - $qw_hyphoo_timestamp_lasthyphenation) / (60 * 60 * 24);
	
	$qw_hyphoo_txt_never = __('- never -', 'qwhyphoo');
	$qw_hyphoo_txt_dailylimitreached = __('daily limit reached', 'qwhyphoo');
	
	if ($daysdiff > 0) { 
		$qw_hyphoo_todaycounter = 0;
		update_option('qw_hyphoo_settings_todaycounter', 0); 
	}

	if (strpos(',h,r,n,', ','.$qw_hyphoo_default_hyphenate.',') === false) $qw_hyphoo_default_hyphenate = 'n';
	if (strpos(',DE,EN,FR,CS,NL,RU,', ','.$qw_hyphoo_language.',') === false) $qw_hyphoo_language = 'DE';
	?>
	<img src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/qw_hyphoo/hyphoo-logo.png" alt="" align="right" />
	<h4 style="display:inline;"><?php _e('Language', 'qwhyphoo') ?> :</h4>
	<select name="qw_hyphoo_edit_option_language">
		<option value="CS"<?php if ($qw_hyphoo_language == 'CS') { echo ' selected'; } ?>><?php _e('Czech', 'qwhyphoo') ?></option>
		<option value="NL"<?php if ($qw_hyphoo_language == 'NL') { echo ' selected'; } ?>><?php _e('Dutch', 'qwhyphoo') ?></option>
		<option value="EN"<?php if ($qw_hyphoo_language == 'EN') { echo ' selected'; } ?>><?php _e('English', 'qwhyphoo') ?></option>
		<option value="FR"<?php if ($qw_hyphoo_language == 'FR') { echo ' selected'; } ?>><?php _e('French', 'qwhyphoo') ?></option>
		<option value="DE"<?php if ($qw_hyphoo_language == 'DE') { echo ' selected'; } ?>><?php _e('German', 'qwhyphoo') ?></option>
		<option value="PL"<?php if ($qw_hyphoo_language == 'PL') { echo ' selected'; } ?>><?php _e('Polish', 'qwhyphoo') ?></option>
		<option value="RU"<?php if ($qw_hyphoo_language == 'RU') { echo ' selected'; } ?>><?php _e('Russian', 'qwhyphoo') ?></option>
	</select>
	<br />
	<h4><?php _e('When saving do the following', 'qwhyphoo') ?> :</h4>
	<input type="radio" name="qw_hyphoo_edit_option_hyphenate" value="h"<?php echo ($qw_hyphoo_default_hyphenate == 'h' ? ' checked' : '') ?>> <?php _e('Hyphenate content', 'qwhyphoo') ?>
	<br /><input type="radio" name="qw_hyphoo_edit_option_hyphenate" value="r"<?php echo ($qw_hyphoo_default_hyphenate == 'r' ? ' checked' : '') ?>> <?php _e('Remove Soft-Hyphens', 'qwhyphoo') ?>
	<br /><input type="radio" name="qw_hyphoo_edit_option_hyphenate" value="n"<?php echo ($qw_hyphoo_default_hyphenate == 'n' ? ' checked' : '') ?>> <?php _e('Do nothing', 'qwhyphoo') ?>
	<?php if ($post_type.'' === 'post') { ?>
	<h4><?php _e('Content to hyphenate', 'qwhyphoo') ?> :</h4>
	<input type="checkbox" name="qw_hyphoo_edit_option_content_to_hyphenate[]" value="c"<?php echo (strpos($qw_hyphoo_content_to_hyphenate, 'c') !== false ? ' checked' : ''); ?>> <?php _e('Main Content', 'qwhyphoo') ?>
	<br /><input type="checkbox" name="qw_hyphoo_edit_option_content_to_hyphenate[]" value="e"<?php echo (strpos($qw_hyphoo_content_to_hyphenate, 'e') !== false ? ' checked' : ''); ?>> <?php _e('Excerpt', 'qwhyphoo') ?>
	<?php } else { ?>
	<input type="hidden" name="qw_hyphoo_edit_option_content_to_hyphenate[]" value="c" />
	<?php }  
	
	if ($post_type.'' !== '') { ?>
	<h4><?php _e('Add a link to us (plazoo.com)', 'qwhyphoo') ?> :</h4>
	<i><?php _e('If you like this plugin you can support us by setting a link to us (plazoo.com).', 'qwhyphoo') ?></i>
	<br /><br /><input type="checkbox" name="qw_hyphoo_edit_option_setlink" value="1" checked="checked"><?php _e('Yes, insert a Link at the end of this artikle.', 'qwhyphoo') ?>
	<?php }  ?>
	<br /><hr /><br />
	<small>
		<i><b><?php _e('Last hyphenation', 'qwhyphoo') ?> : </b> <?php echo get_option('qw_hyphoo_settings_timestamp_lasthyphenation', $qw_hyphoo_txt_never); ?></i>
		<br /><i><b><?php _e('Today hyphenations', 'qwhyphoo') ?> : </b> <?php echo ($qw_hyphoo_todaycounter >= 5 ? '<span style="color:#ce0000;font-weight:bold;">'.$qw_hyphoo_todaycounter.'</span> - '.$qw_hyphoo_txt_dailylimitreached : $qw_hyphoo_todaycounter); ?></i>
	</small>
	<?php
}

function qw_hyphoo_print_settings_page() {
	
	$is_updated = false;
	/* functionality to save settings */ 
	if ( isset($_POST['submit']) ) {
		//$qw_hyphoo_api_key = $_POST['qw_hyphoo_settings_api_key'];
		$qw_hyphoo_default_hyphenate = $_POST['qw_hyphoo_settings_default_hyphenate'];
		$qw_hyphoo_content_to_hyphenate = $_POST['qw_hyphoo_settings_content_to_hyphenate'];
		$qw_hyphoo_language = $_POST['qw_hyphoo_settings_language'];
		
		if (is_array($qw_hyphoo_content_to_hyphenate) === true) $qw_hyphoo_content_to_hyphenate = join(',', $qw_hyphoo_content_to_hyphenate);

		$qw_hyphoo_content_to_hyphenate = str_replace(' ', '', $qw_hyphoo_content_to_hyphenate);
		//if ($qw_hyphoo_api_key == 'please enter a API-Key') $qw_hyphoo_api_key = '';
		if (strpos(',h,r,n,', ','.$qw_hyphoo_default_hyphenate.',') === false) $qw_hyphoo_default_hyphenate = 'n';
		if (strpos(',DE,EN,FR,CS,NL,RU,', ','.$qw_hyphoo_language.',') === false) $qw_hyphoo_language = 'EN';

		//update_option('qw_hyphoo_settings_api_key', $qw_hyphoo_api_key);
		update_option('qw_hyphoo_settings_default_hyphenate', $qw_hyphoo_default_hyphenate);
		update_option('qw_hyphoo_settings_content_to_hyphenate', $qw_hyphoo_content_to_hyphenate.'');
		update_option('qw_hyphoo_settings_language', $qw_hyphoo_language);
		$is_updated = true;
	}
	
	// $qw_hyphoo_api_key = get_option('qw_hyphoo_settings_api_key', 'api_key'); 
	$qw_hyphoo_default_hyphenate = get_option('qw_hyphoo_settings_default_hyphenate', 'n'); 
	$qw_hyphoo_content_to_hyphenate = get_option('qw_hyphoo_settings_content_to_hyphenate', ''); 
	$qw_hyphoo_language = get_option('qw_hyphoo_settings_language', ''); 
	if ($qw_hyphoo_language.'' == '') { 
		$qw_hyphoo_language = get_bloginfo('language');
		if (strlen($qw_hyphoo_language) == 5) { $qw_hyphoo_language = substr($qw_hyphoo_language, -2); } 
		else { $qw_hyphoo_language = 'EN'; }
	}
	
?>	
<div class="wrap">
	<div class="icon32" style="background:transparent url(<?php echo get_option('siteurl'); ?>/wp-content/plugins/qw_hyphoo/hyphoo-logo.png) no-repeat;"><br /></div><h2><?php _e('Hypho-o Settings Page', 'qwhyphoo') ?></h2>
		
	<?php if ($is_updated == true) { ?>
	<div class="updated"><?php _e('The data has been saved successfully.', 'qwhyphoo') ?></div>
	<?php } ?>
	
	<br />
	<?php _e('Hypho-o (by plazoo.com) is a free plugin to automatically hyphenate your articles and pages in wordpress. In this version you can hyphenate up to 5 Texts each day.', 'qwhyphoo') ?>
	<br />
	<h3><?php _e('Defaultsettings', 'qwhyphoo') ?></h3>
	<form method="post" action="">
		<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e('When saving do the following', 'qwhyphoo') ?></th>
			<td>
				<input type="radio" name="qw_hyphoo_settings_default_hyphenate" value="h"<?php echo ($qw_hyphoo_default_hyphenate == 'h' ? ' checked' : ''); ?>> <?php _e('Hyphenate content', 'qwhyphoo') ?>
				<br /><input type="radio" name="qw_hyphoo_settings_default_hyphenate" value="r"<?php echo ($qw_hyphoo_default_hyphenate == 'r' ? ' checked' : ''); ?>> <?php _e('Remove Soft-Hyphens', 'qwhyphoo') ?>
				<br /><input type="radio" name="qw_hyphoo_settings_default_hyphenate" value="n"<?php echo ($qw_hyphoo_default_hyphenate == 'n' ? ' checked' : ''); ?>> <?php _e('Do nothing', 'qwhyphoo') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Content to hyphenate', 'qwhyphoo') ?></th>
			<td>
				<input type="checkbox" name="qw_hyphoo_settings_content_to_hyphenate[]" value="c"<?php echo (strpos($qw_hyphoo_content_to_hyphenate, 'c') !== false ? ' checked' : ''); ?>> <?php _e('Main Content', 'qwhyphoo') ?>
				<br /><input type="checkbox" name="qw_hyphoo_settings_content_to_hyphenate[]" value="e"<?php echo (strpos($qw_hyphoo_content_to_hyphenate, 'e') !== false ? ' checked' : ''); ?>> <?php _e('Excerpt', 'qwhyphoo') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Default language of articles', 'qwhyphoo') ?></th>
			<td>
				<select name="qw_hyphoo_settings_language">
					<option value="CS"<?php if ($qw_hyphoo_language == 'CS') { echo ' selected'; } ?>><?php _e('Czech', 'qwhyphoo') ?></option>
					<option value="NL"<?php if ($qw_hyphoo_language == 'NL') { echo ' selected'; } ?>><?php _e('Dutch', 'qwhyphoo') ?></option>
					<option value="EN"<?php if ($qw_hyphoo_language == 'EN') { echo ' selected'; } ?>><?php _e('English', 'qwhyphoo') ?></option>
					<option value="FR"<?php if ($qw_hyphoo_language == 'FR') { echo ' selected'; } ?>><?php _e('French', 'qwhyphoo') ?></option>
					<option value="DE"<?php if ($qw_hyphoo_language == 'DE') { echo ' selected'; } ?>><?php _e('German', 'qwhyphoo') ?></option>
					<option value="PL"<?php if ($qw_hyphoo_language == 'PL') { echo ' selected'; } ?>><?php _e('Polish', 'qwhyphoo') ?></option>
					<option value="RU"<?php if ($qw_hyphoo_language == 'RU') { echo ' selected'; } ?>><?php _e('Russian', 'qwhyphoo') ?></option>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">&nbsp;</th>
			<td>
				<input type="submit" name="submit" value="<?php _e('Save settings', 'qwhyphoo') ?>" class="button-primary"/>
			</td>
		</tr>
		</table>
	</form>
</div>
<?php
}

/*
Adding filters and actions
*/
add_filter('content_save_pre', 'qw_hyphoo_hyphenate_content');
add_filter('excerpt_save_pre', 'qw_hyphoo_hyphenate_excerpt');
add_filter('comment_save_pre', 'qw_hyphoo_hyphenate_content');
add_filter('content_edit_pre', 'qw_hyphoo_remove_link');
add_filter('excerpt_edit_pre', 'qw_hyphoo_prepare_content');

if (is_admin()) {
		/* Use the admin_menu action to define the custom boxes */
		add_action('admin_menu', 'qw_hyphoo_add_admin_menues');
}

/* 
################################################################
			Helper Functions
################################################################
*/

function qw_hyphoo_getsoapdata($datatype = 'text', $text = '', $texttype = 'text', $hyphen = '&shy;', $customhtmlattribute = '', $language = 'DE') {

	$data = '';
	
	if ($datatype == 'url') { 
		$method = 	'HyphenateUrl';
		$data =  	'<Url>'.htmlspecialchars($text).'</Url><UserAgent>'.htmlspecialchars($_SERVER['HTTP_USER_AGENT']).'</UserAgent>';
	} else {
		$method = 	'Hyphenate';
		$data = 	'<Text>'.htmlspecialchars($text).'</Text>';
	}
	
	
	$retVal = '<?xml version="1.0" encoding="utf-8"?>';
	$retVal .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
	$retVal .= '<soap:Body><'.$method.' xmlns="http://tempuri.org/">';
	$retVal .= '	<Language>'.$language.'</Language>';
	$retVal .= '	<Country>none</Country>';
	$retVal .= $data;
	$retVal .= '	<HyphenationSign>'.htmlspecialchars($hyphen).'</HyphenationSign>';
	$retVal .= '	<CustomHTMLAttribute>'.htmlspecialchars($customhtmlattribute).'</CustomHTMLAttribute>';
	$retVal .= '	<TextType>'.$texttype.'</TextType>';
	$retVal .= '	<minLeft>0</minLeft>';
	$retVal .= '	<minRight>0</minRight>';
	$retVal .= '</'.$method.'></soap:Body></soap:Envelope>';
	
	return $retVal;

}

function qw_hyphoo_callwebservice($host, $port, $path, $data, $action) {

	$d = '';
	$source = $data; //urlencode($data);
    $fp = fsockopen($host,$port,$errno,$errstr,$timeout=30); 
    if(!$fp) 
		die($_err.$errstr.$errno); 
	else { 
        fputs($fp, "POST $path HTTP/1.1\r\n"); 
        fputs($fp, "Host: $host\r\n");
		if ($action != '') fputs($fp, "SOAPAction: $action\r\n");
		fputs($fp, "User-Agent: ".$_SERVER['HTTP_USER_AGENT']."\r\n"); 
        fputs($fp, "Content-type: text/xml; charset=utf-8\r\n"); 
        fputs($fp, "Content-length: ".strlen($source)."\r\n"); 
        fputs($fp, "Connection: close\r\n\r\n"); 
        fputs($fp, $source."\r\n\r\n"); 
        
        while(!feof($fp)) $d .= fgets($fp,4096); 
        fclose($fp); 
    } 
	return $d; 
	
}

function qw_hyphoo_remove_soft_hyphens($content) {
	$content = str_replace('­', '', $content); // sign is not a normal "-" 
	$content = str_replace('&shy;', '', $content);
	$content = str_replace('&#173;', '', $content);
	return $content;
}

function qw_hyphoo_gettext_from_soap_result($soap_result) {
	
	$retval = '';
	$start = strpos($soap_result, '<text><![CDATA[');
	if ($start !== false) {
		$end = strpos($soap_result, ']]>', $start + 15);
		$retval = substr($soap_result, $start + 15, $end - $start - 15);
		$retval = str_replace('&#173;', '&shy;', $retval);
	}
	
	
	return $retval;
	
}



?>