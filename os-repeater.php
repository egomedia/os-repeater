<?php
/*
Plugin Name: OS Repeater
Description: Adds a repeater field to the admin 
Version: 0.1
Author: Oli Salisbury
*/

//get repeater values and decode
function os_repeater_values($post_id=NULL) {
	global $post;
	if (!$post_id) { $post_id = $post->ID; }
	$getmeta = get_post_meta($post_id, 'os_repeater', true);
	$getmeta = base64_decode($getmeta);
	$getmeta = json_decode($getmeta, true);
	return $getmeta;
}

//hooks
if (WP_ADMIN) {
	add_action('admin_menu', 'os_repeater_init');
	add_action('save_post',  'os_repeater_save');
	add_action('admin_head', 'os_repeater_init_js');
	add_action('admin_head', 'os_repeater_css');
}

//initialise plugin
function os_repeater_init() {
	wp_enqueue_script('jquery');
	os_repeater_add_meta_box();
}

//Creates meta box on all Posts and Pages
function os_repeater_add_meta_box() {
	$post = get_post($_GET['post']);
	//for products
	if ($post->post_name == 'hotspots') {
		add_meta_box('os_repeater_meta_box', 'Add Hotspot', 'os_repeater_meta_box_html', 'products', 'side');
	}
}

//Inserts HTML for meta box, including all existing attachments
function os_repeater_meta_box_html() { 
	global $post;
	// Use nonce for verification
  echo '<input type="hidden" name="os_repeater_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />'; 
	//get existing meta from database
	$getmeta = os_repeater_values();
	if (!$getmeta) { $getmeta[1] = array(); }
	//print_r($getmeta);
	//parse the html
	$fields = array('top' => 'text', 'right' => 'text', 'txt' => 'textarea', 'image' => 'image');
	echo '<div id="os_repeater_area">';
	foreach ($getmeta as $i => $arr) {
		echo '<ul>';
		foreach ($fields as $id => $type) {
			switch ($type) {
				case 'text':
					echo '<li>';
					echo '<label for="os_repeater_'.$id.'_'.$i.'">'.ucfirst($id).'</label>';
					echo '<input type="text" id="os_repeater_'.$id.'_'.$i.'" name="os_repeater_'.$id.'_'.$i.'" size="30" value="';
					echo $arr[$id];
					echo '" />';
					echo '</li>';
					break;
				case 'textarea':
					echo '<li>';
					echo '<label for="os_repeater_'.$id.'_'.$i.'" style="vertical-align:top;">'.ucfirst($id).'</label>';
					echo '<textarea id="os_repeater_'.$id.'_'.$i.'" name="os_repeater_'.$id.'_'.$i.'" rows="4" cols="100">';
					echo $arr[$id];
					echo '</textarea>';
					echo '</li>';
					break;
				case 'image':
					echo '<li>';
					echo '<label for="os_repeater_'.$id.'_'.$i.'">'.ucfirst($id).'</label>';
					echo '<span class="os_repeater_imageajax">';
					echo '<select id="os_repeater_'.$id.'_'.$i.'" name="os_repeater_'.$id.'_'.$i.'">';
					echo '<option value="">-</option>';
					$q = get_posts('post_type=attachment&post_mime_type=image&posts_per_page=-1&orderby=title&order=ASC&post_parent='.$post->ID);
					foreach ($q as $obj) {
						echo '<option value="'.$obj->ID.'"';
						echo $obj->ID == $arr[$id] ? 'selected="selected"' : '';
						echo '>';
						echo $obj->post_title;
						echo '</option>';
					}
					echo '</select> ';
					echo '<a href="media-upload.php?post_id='.$post->ID.'&TB_iframe=1" class="button button-highlighted thickbox">Upload File</a> ';
					echo '<em>Once you have uploaded new images, you must <strong>update the post</strong> in order to refresh the drop-down lists</em>';
					//echo '<a href="#" class="os_repeater_imageclick" class="button">Refresh</a>';
					echo '</span> ';
					echo '<span class="os_repeater_imageloading" style="display:none;">';
					echo '<img src="images/loading.gif" alt="Loading..." style="vertical-align:middle" />';
					echo '</span>';
					echo '</li>';
					break;
			}
		}
		echo '<li><a href="#" class="button os_repeater_remove">Remove field</a></li>';
		echo '</ul>';
	}
	echo '</div>';
	echo '<p>&nbsp;</p>';
	echo '<p><a href="#" class="button" id="os_repeater_add">Add</a></p>';
	echo '<p>';
	echo '<input type="hidden" id="os_repeater_counter" name="os_repeater_counter" value="'.count($getmeta).'" />';
	echo '<input type="hidden" name="os_repeater_fields" value="';
	foreach ($fields as $id => $type) {
		echo "$id,";
	}
	echo '" />';
	echo '</p>';
}

//plugin css
function os_repeater_css() {
	echo '
	<style type="text/css">
	#os_repeater_area ul { border-bottom:1px solid #ccc; padding:15px; }
	#os_repeater_area ul label { display:inline-block; *display:inline; zoom:1; width:90px; }
	</style>';
}


//javascript in header
function os_repeater_init_js() {
	echo '
	<script type="text/javascript" charset="utf-8">
	//recreates new i value for each attr
	function os_repeater_reorder() { 
		var i=1;
		jQuery("#os_repeater_area ul").each(function(){
			jQuery(this).find("label").each(function() { os_repeater_changeattr(jQuery(this), "for", i); });
			jQuery(this).find(":input").each(function() { os_repeater_changeattr(jQuery(this), "id", i); os_repeater_changeattr(jQuery(this), "name", i); });
			i++;
		});
		//update counter
		var counter = parseInt(jQuery("#os_repeater_area ul").length);
		jQuery("#os_repeater_counter").attr("value", counter);
	}
	//updates each attr with the new i value
	function os_repeater_changeattr(obj, attrib, i) { 
			var master = obj.attr(attrib);
			var master_split = master.split("_", 3);
			obj.attr(attrib, master_split[0]+"_"+master_split[1]+"_"+master_split[2]+"_"+i);
	}
	//onload
	jQuery(document).ready(function(){
		//add new field
		jQuery("#os_repeater_add").click(function() {
			//append new field
			jQuery("#os_repeater_area ul:first").clone().appendTo("#os_repeater_area").find(":input").val("");
			//reorder
			os_repeater_reorder();
			//disable click
			return false;
		});
		//remove field
		jQuery(".os_repeater_remove").live("click", function() {
			//remove field
			jQuery(this).parent().parent().remove();
			//reorder
			os_repeater_reorder();
			//disable click
			return false;
		});
		/*
		//ajax update
		jQuery(".os_repeater_imageclick").click(function() {
			jQuery(".os_repeater_imageajax").slideUp("fast", function() { 
				jQuery(".os_repeater_imageloading").show(); 
			}).load("'.$_SERVER['REQUEST_URI'].' .os_repeater_imageajax:first", function() { 
					jQuery(this).slideDown("fast");
					jQuery(".os_repeater_imageloading").hide();
					os_repeater_reorder();
			});
			return false;
		});
		*/
	});
	</script>';
}

//javascript in footer
function os_repeater_init_footer() {
}

//save
function os_repeater_save($post_id) {
	global $_POST;
	// Check permissions
	if ('page' == $_POST['post_type']) {
		if (!current_user_can('edit_page', $post_id)) { return $post_id; }
	} else {
		if (!current_user_can('edit_post', $post_id)) { return $post_id; }
	}
	//verify nonce
	if (!wp_verify_nonce($_POST['os_repeater_nonce'], basename(__FILE__))) {
			return $post_id;
	}
	// Check if autosaving
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return $post_id;
	} else {
		//localise posted values
		$counter = $_POST['os_repeater_counter'] + 1;
		for ($i=1; $i<$counter; $i++) {
			$os_repeater_fields = explode(",", substr($_POST['os_repeater_fields'], 0, -1));
			foreach ($os_repeater_fields as $field) {
				$values_tree[$i][$field] = $_POST['os_repeater_'.$field.'_'.$i];
			}
		}
		$values_tree_json = json_encode($values_tree);
		$values_tree_64 = base64_encode($values_tree_json);
		//add the value to database if it is not there
		add_post_meta($post_id, 'os_repeater', $values_tree_64, true)
		or
		//update it if the value is already there
		update_post_meta($post_id, 'os_repeater', $values_tree_64);
		//if empty value then delete the meta
		if ($values_tree_64=='bnVsbA==' || $values_tree_64=='eyIxIjp7InRvcCI6IiIsInJpZ2h0IjoiIiwidHh0IjoiIn19') { delete_post_meta($post_id, 'os_repeater'); }
	}
}
?>