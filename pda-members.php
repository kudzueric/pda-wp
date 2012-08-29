<?php
/*
 Plugin Name: Pleasant District Members
 Plugin URI: http://www.pleasantdistrict.org/
 Version: v0.20
 Author: Serenity Investments
 Description: Show Pleasant District members.
 */

/* not yet implemented */
// include (WP_PLUGIN_DIR . "/PleasantDistrictAssociation/options.php");


/**
 * Retrieve and cache PDA data
 **/
 class PDAData {
	const MEMBERURL = 'http://www.pleasantdistrict.org/members.json';
	const BUSINESSURL = 'http://www.pleasantdistrict.org/businesses.json';
	private $response = null;
	private $json = null;
	
	public function PDAData ($members = false) {
		$key =  'pdabusiness' ;
		if ($members) $key = 'pdamember';

		$this->response = get_transient($key);
		if ($this->response === false){
			if ($members == 'true') {
				$this->response = wp_remote_get(self::MEMBERURL);
			} else {
				$this->response = wp_remote_get(self::BUSINESSURL );
			}
			if(!is_wp_error($response) ) {
				set_transient( $key, $this->response, 60*5 );
			} else {
				$this->response = null;
			}
		}
		
		if ($this->response != null) {
			$this->json = json_decode($this->response['body']);
		}
	}
	
	public function error() {
		if ($this->json == null) return true;
	}
	
	public function json () {
		return $this->json;
	}
	
 
 }

/**
 * Handle PDA shortcodes 
**/
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
		$ret = "";
		$ret .= "<div id=\"pda\"><dl class=\"" . $cssclass . "\">";
		foreach ($json->items as $item) {
			if ($item->BusinessWebSite == ''){
				$ret .=  ("<dt>" . $item->BusinessName . "</dt>");
			} else {
				$ret .=  ("<dt><a href=\"" . $item->BusinessWebSite . "\">" . $item->BusinessName . "</a></dt>");
			}
			$ret .=  "<dd><span class=\"category\">" . $item->Category . "</span> <span class=\"description\" >" . $item->BusinessDescription . "</span></dd>";
		}
		$ret .=  "</dl></div>";
		return $ret;
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
		$ret = "";
		$ret .=  "<div id=\"pda\">";
		foreach ($categories as $cat => $items){
			$ret .=  "<h2>" . $cat . " <span class=\"category_count\">" . count($items) ."<span></h2><ul class=\"" . $cssclass . "\">" ;
			foreach ($items as $item) {
				if ($item->BusinessWebSite == ""){
					$ret .=  "<li>" . $item->BusinessName ."</li>";
				} else {
					$ret .=  "<li><a href=\"" . $item->BusinessWebSite . "\">" . $item->BusinessName . "</a></li>";
				}
			}
			$ret .=  "</ul>";
		}
		$ret .=  "</div>";
		return $ret;
	}

	function template_c($json, $cssclass) {
		/* split data into 4 divs to layed out as columns */
		$categories = $this->get_categories($json);
		$category_count = count($categories);
		$business_count = count($json->items);
		$target_count = ceil(($business_count+$category_count)/4);
		$current_count = 0;
		$column_count = 1;
		$ret = "";
		$ret .=  "<div id=\"pda\">";
		foreach ($categories as $cat => $items){
			if ($current_count == 0){
				$ret .=  "<div class=\"pdacol\" id=\"pdacol-" . $column_count . "\">";
			}
			$current_count +=  1;
			$section_count = 0;
			$ret .=  "<h2>" . $cat . " <span class=\"category_count\">" . count($items) ."</span></h2><ul class=\"" . $cssclass . "\">" ;
			foreach ($items as $item) {
				$current_count += 1;
				$section_count += 1;
				if ($item->WebSite == ""){
					$ret .=  "<li><a href=\"http://www.pleasantdistrict.org/b" . $item->key . ".htm\">" . $item->Name . "</a></li>";
				} else {
					$ret .=  "<li><a href=\"" . $item->WebSite . "\">" . $item->Name . "</a></li>";
				}
				if (($current_count > $target_count) and ($section_count > 2) and ((count($items)-$section_count)>2) ){
					$current_count = 0;
					$column_count += 1;
					$ret .=  "</ul></div><div class=\"pdacol\" id=\"pdacol-" . $column_count . "\">";
					$ret .=  "<h2 class=\"continued\">" . $cat . "</h2>";
					$ret .=  "<ul class=\"" . $cssclass . "\">";
				}
			}
			$ret .=  "</ul>";
			if ($current_count >= $target_count){
				$current_count = 0;
				$column_count += 1;
				$ret .=  "</div>";
			}
		}
		/* close the last column */
		if ($current_count > 0 ) {
			$ret .=  "</div>";
		}
		$ret .=  "</div>";
		return $ret;
	}

		function pda_members($atts) {
			global $wp_query;
			if (!$wp_query->is_single() && !$wp_query->is_page()) return;
			extract(shortcode_atts(array(
				'cssclass' => 'pda-list',
				'template' => 'a',
				'members' => 'true',
			), $atts));
			$ret = "";
			
			$data = new PDAData($members);
			if($data->error()) {
				$ret .=  ("<div class=\"pda-error\">Error getting Pleasant District Association list.</div>");
			} else {
				$json = $data->json();
				if ($template == 'b') {
					$ret .= $this->template_b($json, $cssclass);
				} elseif ($template == 'c') {
					$ret .= $this->template_c($json, $cssclass);
				} else {
					$ret .= $this->template_a($json, $cssclass);
				}
			}
			return $ret;
	}

}

$pdacode = new PleasantDistrictShortcodes();
add_shortcode("pleasant_district_members", array($pdacode, 'pda_members'));
add_action("wp_print_styles", array($pdacode, 'add_css'));
