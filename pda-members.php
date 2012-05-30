<?php
/*
 Plugin Name: Pleasant District Members
 Plugin URI: http://www.pleasantdistrict.org/
 Version: v0.10
 Author: Serenity Investments
 Description: Show Pleasant District members.
 */

include (WP_PLUGIN_DIR . "/PleasantDistrictAssociation/options.php");

/* put the code in a PHP class */
if (!class_exists("PleasantDistrictShortcodes")){
	class PleasantDistrictShortcodes {
		function PleasantDistrictShortcodes() {}
		function add_css () {
			$myStyleUrl = plugins_url('css/style.css', __FILE__);
			// Respects SSL, Style.css is relative to the current file
			$myStyleFile = WP_PLUGIN_DIR . '/PleasantDistrictAssociation/css/style.css';
			if ( file_exists($myStyleFile) ) {
				wp_register_style('PDASheets', $myStyleUrl);
				wp_enqueue_style( 'PDASheets');
			}
		}

		function template_a($json, $cssclass) {
			echo "<div id=\"pda\"><dl class=\"" . $cssclass . "\">";
			foreach ($json->items as $item) {
				if ($item->BusinessWebSite == ''){
					echo ("<dt>" . $item->BusinessName . "</dt>");
				} else {
					echo ("<dt><a href=\"" . $item->BusinessWebSite . "\">" . $item->BusinessName . "</a></dt>");
				}
				echo "<dd><span class=\"category\">" . $item->Category . "</span> <span class=\"description\" >" . $item->BusinessDescription . "</span></dd>";
			}
			echo "</dl></div>";

		}

		function get_categories($json){
			$categories = array();
			foreach ($json->items as $item) {
				if ($item->Category == "") {
					$item->Category = "Miscellaneous";
				}
				if (!array_key_exists( $item->Category, $categories)) {
					$categories[$item->Category] = array();
				}
				$categories[$item->Category][]=$item;
			}

			ksort($categories);
			return $categories;
		}

		function template_b($json, $cssclass) {
			$categories = $this->get_categories($json);
			echo "<div id=\"pda\">";
			foreach ($categories as $cat => $items){
				echo "<h2>" . $cat . " <span class=\"category_count\">" . count($items) ."<span></h2><ul class=\"" . $cssclass . "\">" ;
				foreach ($items as $item) {
					if ($item->BusinessWebSite == ""){
						echo "<li>" . $item->BusinessName ."</li>";
					} else {
						echo "<li><a href=\"" . $item->BusinessWebSite . "\">" . $item->BusinessName . "</a></li>";
					}
				}
				echo "</ul>";
			}
			echo "</div>";

		}

		function template_c($json, $cssclass) {
			/* split data into 4 divs to layed out as columns */
			$categories = $this->get_categories($json);
			$category_count = count($categories);
			$business_count = count($json->items);
			$target_count = ceil(($business_count+$category_count)/4);
			$current_count = 0;
			$column_count = 1;
			echo "<div id=\"pda\">";
			foreach ($categories as $cat => $items){
				if ($current_count == 0){
					echo "<div class=\"pdacol\" id=\"pdacol-" . $column_count . "\">";
				}
				$current_count +=  1;
				$section_count = 0;
				echo "<h2>" . $cat . " <span class=\"category_count\">" . count($items) ."</span></h2><ul class=\"" . $cssclass . "\">" ;
				foreach ($items as $item) {
					$current_count += 1;
					$section_count += 1;
					if ($item->WebSite == ""){
						echo "<li><a href=\"http://www.pleasantdistrict.org/b" . $item->key . ".htm\">" . $item->Name . "</a></li>";
					} else {
						echo "<li><a href=\"" . $item->WebSite . "\">" . $item->Name . "</a></li>";
					}
					if (($current_count > $target_count) and ($section_count > 2) and ((count($items)-$section_count)>2) ){
						$current_count = 0;
						$column_count += 1;
						echo "</ul></div><div class=\"pdacol\" id=\"pdacol-" . $column_count . "\">";
						echo "<h2 class=\"continued\">" . $cat . "</h2>";
						echo "<ul class=\"" . $cssclass . "\">";
					}
				}
				echo "</ul>";
				if ($current_count >= $target_count){
					$current_count = 0;
					$column_count += 1;
					echo "</div>";
				}
			}
			/* close the last column */
			if ($current_count > 0 ) {
				echo "</div>";
			}
			echo "</div>";

		}

			function pda_members($atts) {
			extract(shortcode_atts(array(
	      		'cssclass' => 'pda-list',
				'cache' => 'true',
				'template' => 'a',
				'members' => 'true',
			), $atts));
			global $post;
			$key =  $post->ID . "-" . $cache . $template . $members ;

			$response = get_transient($key);
			if ($response == false or $cache != 'true' ){
				delete_transient($key);
				if ($members == 'true') {
					$response = wp_remote_get('http://www.pleasantdistrict.org/members.json');
				} else {
					$response = wp_remote_get('http://www.pleasantdistrict.org/businesses.json');
				}
				if(!is_wp_error($response) and $cache == 'true') {
					set_transient( $key, $response, 60*60*12 );
				}
			}
			if(is_wp_error($response)) {
				echo ("<div class=\"pda-error\">Error getting Pleasant District Association list.</div>");
			} else {
				$json = json_decode($response['body']);
				if ($json == null) {
					echo ("<div class=\"pda-error\">Error processing results.</div>");
					print_r($response);
				} else {
					if ($template == 'b') {
						$this->template_b($json, $cssclass);
					} elseif ($template == 'c') {
						$this->template_c($json, $cssclass);
					} else {
						$this->template_a($json, $cssclass);
					}
				}

			}

		}

	}
}

if (class_exists("PleasantDistrictShortcodes")) {
	$code = new PleasantDistrictShortcodes();
	if (isset($code)) {
		add_shortcode("pleasant_district_members", array($code, 'pda_members'));
		add_action("wp_print_styles", array($code, 'add_css'));
	}
}
