<?php // add the admin options page
add_action('admin_menu', 'pda_plugin_admin_add_page');

function pda_plugin_admin_add_page() {
	add_options_page('Pleasant District Association Plugin', 'Pleasant District Association', 'manage_options', 'pda-settings', 'pda_plugin_options_page');
}

function pda_plugin_options_page() {
?>
<div>
<h2>Pleasant District Association Plugin Options</h2>
Member shortcodes.
<form action="options.php" method="post">
<?php settings_fields('plugin_options'); ?>
<?php do_settings_sections('pda-settings'); ?>

<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
</form></div>

<?php
}?>

<?php // add the admin settings and such
add_action('admin_init', 'pda_plugin_admin_init');

function pda_plugin_admin_init(){
	register_setting( 'plugin_options', 'plugin_options', 'plugin_options_validate' );
	add_settings_section('plugin_main', 'Main Settings', 'pda_plugin_section_text', 'pda-settings');
	add_settings_field('plugin_text_string', 'Plugin Text Input', 'plugin_setting_string', 'plugin', 'plugin_main');
}

function pda_plugin_section_text() {
	?>
	<p>Settings goodness.</p>
	<?php
}

?>

<?php // validate our options
function plugin_options_validate($input) {
$options = get_option('plugin_options');
$options['text_string'] = trim($input['text_string']);
if(!preg_match('/^[a-z0-9]{32}$/i', $options['text_string'])) {
$options['text_string'] = '';
}
return $options;
}
?>
