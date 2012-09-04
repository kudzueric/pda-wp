<?php
/*
 Plugin Name: Pleasant District Members
 Plugin URI: http://www.pleasantdistrict.org/
 Version: v0.20
 Author: Serenity Investments
 Description: Display data from Pleasant District Association (pleasantdistrict.org) member site. This includes a widget and shortcodes.
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
		if( WP_DEBUG === true ) delete_transient($key);

		$this->response = get_transient($key);
		if ($this->response === false){
			if ($members == 'true') {
				$this->response = wp_remote_get(self::MEMBERURL);
			} else {
				$this->response = wp_remote_get(self::BUSINESSURL );
			}
			if(!is_wp_error($this->response) ) {
				set_transient( $key, $this->response, 60*5 );
			} else {
				_pdalog($this->response);
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

	/**
	 * return a random member every hour
	 **/
	public function featured() {
		_pdalog("getting random member");
		$key = "pdafeatured";
		if( WP_DEBUG === true ) delete_transient($key);
		$featured = get_transient($key);
		if ( $featured === false) {
			$featured = null;
			if (!$this->error()) {
				$maxitems = count($this->json->items);
				_pdalog("count: " . $maxitems);
				$randitem = rand(0, $maxitems-1);
				_pdalog("random: " . $randitem);
				$featured = $this->json->items[$randitem];
				set_transient($key, $featured, 60*60);
			}
		}
		return $featured;
	}
}

/**
 * Adds Pleasant District Featured member widget.
 */
class PDA_Featured_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
	 		'pda_featured_widget', // Base ID
			'PDA Featured Member', // Name
		array( 'description' => __( 'Get random Pleasant District Association member.', 'text_domain' ), )
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );

		if (empty($title)) $title = "Featured";

		echo $before_widget;
		if ( ! empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}


		$members = new PDAData(true);
		$featured = $members->featured();
		echo '<div class="pda-featured">';
		if ($featured == null ) {
			echo "enjoy the Pleasant District";
		} else {
			_pdalog($featured);
			echo '<span class="name">' . $featured->Name . '</span>';
			echo '<span class="description">'. $featured->Description . '</span>';
		}
		echo '</div>';
		echo $after_widget;

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
			if (empty($item->WebSite) ){
				$ret .=  ("<dt>" . $item->Name . "</dt>");
			} else {
				$ret .=  ("<dt><a href=\"" . $item->WebSite . "\">" . $item->Name . "</a></dt>");
			}
			$ret .=  "<dd><span class=\"category\">" . $item->Category . "</span> <span class=\"description\" >" . $item->Description . "</span></dd>";
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
				if ($item->WebSite == ""){
					$ret .=  "<li>" . $item->Name ."</li>";
				} else {
					$ret .=  "<li><a href=\"" . $item->WebSite . "\">" . $item->Name . "</a></li>";
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
				if (empty($item->WebSite)){
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
			
		if ($members == '' || $members == 'no' || $members == 'false') $members = false;
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
add_action( 'widgets_init', create_function( '', 'register_widget( "PDA_Featured_Widget" );' ) );
add_action("wp_print_styles", array($pdacode, 'add_css'));


if(!function_exists('_pdalog')){
	function _pdalog( $message ) {
		if( WP_DEBUG === true ){
			if( is_array( $message ) || is_object( $message ) ){
				print_r( $message, true );
			} else {
				echo $message ;
			}
		}
	}
}
