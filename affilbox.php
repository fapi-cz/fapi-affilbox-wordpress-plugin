<?php
/*
Plugin Name: AffilBox
Plugin URI: http://www.affilbox.cz
Description: AffilBox plugin
Version: 1.1
Author: AffilBox
Author URI: http://www.affilbox.cz
License: 
*/



add_action('admin_menu', 'affilboxMenu');

function affilboxMenu() {

add_options_page('Nastavení affilboxu', 'AffilBox', 'manage_options', 'options.php', 'affilboxOptions');
}

/* plugin options */

add_action( 'admin_init', 'register_AffilboxPluginSettings' );

function register_AffilboxPluginSettings() { 
register_setting( 'affilboxOptions', 'affilboxOptions' );
add_settings_section( "affilboxMain", "Nastavení údajů do FAPI", "affilboxOptionsSection", "affilbox" );
add_settings_field('affilboxSettingsUsername', 'Přihlašovací jméno', 'affilboxFieldsUsername', 'affilbox', 'affilboxMain');
add_settings_field('affilboxSettingsPassword', 'Heslo', 'affilboxFieldsPassword', 'affilbox', 'affilboxMain');

add_settings_section( "affilboxTracking", "Tracking kód", "affilboxOptionsSectionTracking", "affilbox" );
add_settings_field('affilboxSettingsCode', 'Kód', 'affilboxFieldsCode', 'affilbox', 'affilboxTracking');

} 

register_deactivation_hook( __FILE__, 'affilboxDeactivate' );

function affilboxDeactivate(){
	//
}

register_uninstall_hook( __FILE__, 'affilboxUninstall' );

function affilboxUninstall(){
	if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();
	
	delete_option('affilboxOptions');

	delete_post_meta_by_key( 'affilboxTrackingCode' ); 
	delete_post_meta_by_key( 'affilboxConversionCode' ); 

}

function affilboxOptionsSection() {
}


function affilboxFieldsUsername() {
$options = get_option('affilboxOptions');
echo "<input id='affilboxSettingsUsername' name='affilboxOptions[username]' size='40' type='text' value='{$options['username']}' />";
} 

function affilboxFieldsPassword() {
$options = get_option('affilboxOptions');
echo "<input id='affilboxSettingsPassword' name='affilboxOptions[password]' size='40' type='password' value='{$options['password']}' />";
} 


function affilboxOptionsSectionTracking() {
}

function affilboxFieldsCode() {
$options = get_option('affilboxOptions');
echo "<textarea id='affilboxSettingsCode' name='affilboxOptions[code]'>{$options['code']}</textarea>";
} 




function affilboxOptions(){
echo "
<div class=\"wrap\">
	".screen_icon()."
	<h2>AffilBox</h2>
	<form method=\"post\" action=\"options.php\"> 
	<p>Pomocí těchto údajů se plugin přihlásí do systému FAPI a získá potřebné údaje pro kontrolu zaplaceného obsahu</p>
	";	 settings_fields( 'affilboxOptions' ); 
		 do_settings_sections( 'affilbox' ); 
		 submit_button(); 
	echo "
	</form>
</div>
";
}


/* // plugin options */

/* post meta */

add_action( 'add_meta_boxes', 'affilboxMetaBox' ); 
 
function affilboxMetaBox()  {
  
	add_meta_box( 'affilboxMeta', 'Affilbox', 'affilboxMetaOutput', 'post', 'normal', 'high' );
	add_meta_box( 'affilboxMeta', 'Affilbox', 'affilboxMetaOutput', 'page', 'normal', 'high' );

}  

function affilboxInit() {
   
    wp_register_style( 'affilboxBackendCSS', plugins_url('/css/backend.css', __FILE__), array(), '1.0' );
    wp_enqueue_style( 'affilboxBackendCSS' );
}

add_action('admin_enqueue_scripts','affilboxInit');


function affilboxMetaOutput()  
{  
global $post;  
$values = get_post_custom( $post->ID );  

?>
    
    <div id="affilboxMeta">
    
    <?php
      wp_nonce_field( 'my_meta_box_nonce', 'meta_box_nonce' ); 
    ?>
    
		  <label for="trackingCode">Tracking kód</label>
		  <textarea name="trackingCode" id="trackingCode"><?php echo $values["affilboxTrackingCode"][0]; ?></textarea>  
    
		  <div class="clear"></div>    
    
		  <label for="conversionCode">Konverzní kód</label>
		  <textarea name="conversionCode" id="conversionCode"><?php echo $values["affilboxConversionCode"][0]; ?></textarea>
    
		  <div class="clear"></div>  
	
        
    </div>
    <?php      
}  

add_action( 'save_post', 'affilboxMetaSave' );
function affilboxMetaSave( $post_id )  
{  
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 
     
    if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'my_meta_box_nonce' ) ) return; 
     
    if( !current_user_can( 'edit_post' ) ) return;  
    
    $allowed = array(   
        'a' => array( 
            'href' => array() 
        )  
    );  
      
    if( isset( $_POST['trackingCode'] ) )  
        update_post_meta( $post_id, 'affilboxTrackingCode', $_POST['trackingCode']	 );
    if( isset( $_POST['conversionCode'] ) )  
        update_post_meta( $post_id, 'affilboxConversionCode', $_POST['conversionCode'] );              
}  

/* // post meta */


/* post functions */


function connectFapiAffilbox($vs){
	// TODO FAPI CONNECT
	$fapiSettings = get_option("affilboxOptions");
	$fapiUsername = $fapiSettings["username"];
	$fapiPassword = $fapiSettings["password"];
	
	if(!isset($fapiUsername) or !isset($fapiPassword))
		return 0;


	require_once("FAPIClient.php");
	
	$fapi = new FAPIClient($fapiUsername, $fapiPassword, 'http://api.fapi.cz');

	$invoices = $fapi->invoice->search(array('variable_symbol' => $vs, 'single' => true));
	if(!$invoices)
		return false;
	
	return number_format(round($invoices["total"]-$invoices["total_vat"],2), 2, '.', '');
}

function footerTrackingCode() {

	global $post;
	
	if (get_post_meta($post->ID, "affilboxTrackingCode", TRUE)){
		echo get_post_meta($post->ID, "affilboxTrackingCode", TRUE);
	}else{
		$options = get_option('affilboxOptions');
		echo $options["code"];		
	}
	
}
add_action('wp_footer', 'footerTrackingCode');


function footerConversionCode() {

	global $post;
	
	if (get_post_meta($post->ID, "affilboxConversionCode", TRUE)){
	
		$conversionCode = get_post_meta($post->ID, "affilboxConversionCode", TRUE);
			
		if (isset($_GET["email"])){
			$conversionCode = str_replace("ID_TRANSAKCE",$_GET["email"],$conversionCode);	
		}
		
		if (isset($_GET["cena"])){
			$conversionCode = str_replace("CENA",$_GET["cena"],$conversionCode);	
		}		
		else if (isset($_GET["vs"])){
			$conversionCode = str_replace("CENA",connectFapiAffilbox(intval($_GET["vs"])),$conversionCode);	
		}		
		
		echo $conversionCode;
		
	}
	
}
add_action('wp_footer', 'footerConversionCode');


/* // post functions */
?>
