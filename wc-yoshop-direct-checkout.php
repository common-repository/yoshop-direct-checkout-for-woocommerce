<?php
/*
Plugin Name: Yoshop Direct Checkout for WooCommerce
Plugin URI: http://www.yoshopm.com/en/shop/
Description: Allow you to implement direct checkout without affecting the existing cart for WooCommerce
Version: 1.2.0
Author: IBEXLAB Co., Ltd.
Author URI: http://www.ibexlab.com
*/

// Define plugin name
define('plugin_name_yoshop_direct_checkout', 'Yoshop Direct Checkout');

// Checks if the WooCommerce plugins is installed and active.
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	if(!class_exists('YoShop_Direct_Checkout')){
		class YoShop_Direct_Checkout{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;
			
			public $tmpCart = null;
			public $originCart = null;
			
			public $is_direct_checkout = false;
			public $is_update_review = false;
				
			/**
			 * Gets things started by adding an action to initialize this plugin once
			 * WooCommerce is known to be active and initialized
			 */
			public function __construct(){
				load_plugin_textdomain('wc-yoshop-direct-checkout', false, dirname(plugin_basename(__FILE__)) . '/languages/');
				
				YoShop_Direct_Checkout::$plugin_prefix = 'wc_direct_checkout_';
				YoShop_Direct_Checkout::$plugin_basefile = plugin_basename(__FILE__);
				YoShop_Direct_Checkout::$plugin_url = plugin_dir_url(YoShop_Direct_Checkout::$plugin_basefile);
				YoShop_Direct_Checkout::$plugin_path = trailingslashit(dirname(__FILE__));
				
				$this->textdomain = 'wc-yoshop-direct-checkout';
				
				$this->options_direct_checkout = array(
					'direct_checkout_enabled' => '',
					'direct_checkout_cart_button_text' => '',
					'direct_checkout_cart_redirect_url' => ''
				);
	
				$this->saved_options_direct_checkout = array();
				
				add_action('woocommerce_init', array(&$this, 'init'));
			}

			/**
			 * Initialize extension when WooCommerce is active
			 */
			public function init(){
				global $woocommerce;
								
				//add menu link for the plugin (backend)
				add_action( 'admin_menu', array( &$this, 'add_menu_direct_checkout' ) );

				if(get_option('direct_checkout_enabled'))
				{
            		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
            		
					add_filter('add_to_cart_redirect', array( &$this, 'custom_add_to_cart_redirect') );
					add_action('woocommerce_after_add_to_cart_button', array( &$this, 'direct_checkout_continue_button2') );
					
					add_action( 'template_redirect', array( $this, 'my_page_template_redirect'), 1, 0 );
				}
			}
			
			function my_page_template_redirect()
			{
				global $woocommerce, $post;
				
				$uri = $_SERVER['REQUEST_URI'];
				$postID = $post->ID;
				$product_id = $_REQUEST['product_id'];
				
				$checkout_url = $woocommerce->cart->get_checkout_url();
				$pos = strpos($checkout_url, "/", strlen("https://"));
				$checkout_uri = substr($checkout_url, $pos, (strlen($checkout_url) - $pos));
				
				// check checkout page
				if ($checkout_uri == substr($uri, 0, strlen($checkout_uri))) {

					$order_received_url = wc_get_endpoint_url( 'order-received', "", get_permalink( wc_get_page_id( 'checkout' ) ) );
					$pos = strpos($order_received_url, "/", strlen("https://"));
					$order_received_uri = substr($order_received_url, $pos, (strlen($order_received_url) - $pos));
					
					if ($order_received_uri == substr($uri, 0, strlen($order_received_uri))) return;
								
					$product_id = $_REQUEST['product_id'];
					$quantity = $_REQUEST['quantity'];

					// product_id나 quantity 값중 하나라도 없으면 PASS
					if ($product_id == "" || $quantity == "") return;

					if (sizeof( $woocommerce->cart->get_cart() ) == 0) {
						// 빈카트일 경우 바로구매하기 품목을 카트에 넣어준다.
						$woocommerce->cart->add_to_cart((int)$product_id, (int)$quantity);
					} else {
						// 비어있지 않을 경우 Direct Checkout 을 설정하고 기존 카트를 저장하고 바로구매하기 카트로 대신한다.
						$originCart = $woocommerce->cart;
					
						$_SESSION["direct_checkout_origin_cart"] = serialize($originCart);
							
						$tmpCart = new WC_Cart();
							
						$tmpCart->add_to_cart((int)$product_id, (int)$quantity);
					
						$woocommerce->cart = $tmpCart;
						$woocommerce->cart->calculate_totals();
					
						$_SESSION["direct_checkout_tmp_cart"] = serialize($tmpCart);
						$_SESSION["is_direct_checkout"] = "true";
					}
				} else {
					// checkout 페이지가 아닐 경우, is_direct_checkout 가 설정되어 있으면 기존 cart정보를 불러오고 초기화 시킨다.
					$is_direct_checkout = $_SESSION["is_direct_checkout"];
					if ($is_direct_checkout == "true") {
						$_originCart = $_SESSION["direct_checkout_origin_cart"];
						$originCart = unserialize($_originCart);
						
						$woocommerce->cart = $originCart;
						$woocommerce->cart->calculate_totals();
							
						$_SESSION["is_direct_checkout"] = "";
						$_SESSION["direct_checkout_origin_cart"] = "";
						$_SESSION["direct_checkout_tmp_cart"] = "";
					}
				}
			}
					
			public function plugin_url() {
				if ( $this->plugin_url )
					return $this->plugin_url;
			
				return $this->plugin_url = untrailingslashit( plugins_url( '/', __FILE__ ) );
			}
							
			public function frontend_scripts() {
				wp_register_script( 'front-js', $this->plugin_url() . '/js/front.js' );
				wp_enqueue_script( 'front-js' );
			}
				
			/**
			 * Set continue shopping button for single product
			 */
			function direct_checkout_continue_button2() {
				global $woocommerce, $post, $product;
				global $wp_query;

				$postID = $wp_query->post->ID;
				
				$direct_checkout_enabled = get_option( 'direct_checkout_enabled' );
				$single_product_title = strip_tags($post->post_title);
	
				if($direct_checkout_enabled == "1"){
					$additional_button_text = get_option( 'direct_checkout_cart_button_text' );
					$checkout_url = $woocommerce->cart->get_checkout_url();
				
					$hidden_txt = '				</form>'
							. ''
							. '<form name="quick_checkout_form" class="quick_checkout_form" action="' . $checkout_url . '" method="GET">'
							. '	<input type="hidden" name="product_id" value="' . $postID . '" />'
							. '<input type="hidden" name="quantity" value="0" />';
				
				
					$html_button = '<button type="text" class="single_add_to_fast_checkout button alt" >' . $additional_button_text . '</button>' . $hidden_txt;
					echo $html_button;
				}	
			}

			/**
			 * Add a menu link to the woocommerce section menu
			 */
			function add_menu_direct_checkout() {
				$wc_page = 'woocommerce';
				$comparable_settings_page = add_submenu_page( $wc_page , __( 'Direct Checkout', $this->textdomain ), __( 'Direct Checkout', $this->textdomain ), 'manage_options', 'wc-direct-checkout', array(
						&$this,
						'settings_page_direct_checkout'
				));
			}
			
			/**
			 * Create the settings page content
			 */
			public function settings_page_direct_checkout() {
			
				// If form was submitted
				if ( isset( $_POST['submitted'] ) )
				{
					check_admin_referer( $this->textdomain );
	
					$this->saved_options_direct_checkout['direct_checkout_enabled'] = ! isset( $_POST['direct_checkout_enabled'] ) ? '1' : $_POST['direct_checkout_enabled'];
					$this->saved_options_direct_checkout['direct_checkout_cart_button_text'] = ! isset( $_POST['direct_checkout_cart_button_text'] ) ? __( 'Direct Checkout', $this->textdomain ) : $_POST['direct_checkout_cart_button_text'];
						
					foreach($this->options_direct_checkout as $field => $value)
					{
						$option_direct_checkout = get_option( $field );
							
						if($option_direct_checkout != $this->saved_options_direct_checkout[$field])
							update_option( $field, $this->saved_options_direct_checkout[$field] );
					}					
						
					// Show message
					echo '<div id="message" class="updated fade"><p>' . __( 'You have saved YoShop Direct Checkout options.', $this->textdomain ) . '</p></div>';
				}
			
				$direct_checkout_enabled			= get_option( 'direct_checkout_enabled' );
				$direct_checkout_cart_button_text	= get_option( 'direct_checkout_cart_button_text' ) ? get_option( 'direct_checkout_cart_button_text' ) : __( 'Direct Checkout', $this->textdomain );
				
				$checked_enabled = '';
			
				if($direct_checkout_enabled)
					$checked_enabled = 'checked="checked"';
				
				$actionurl = $_SERVER['REQUEST_URI'];
				$nonce = wp_create_nonce( $this->textdomain );
			
			
				// Configuration Page
			
				?>
				<div id="icon-options-general" class="icon32"></div>
				<h3><?php _e( 'Direct Checkout Options', $this->textdomain); ?></h3>
				
				
				<table style="width:90%;padding:5px;border-collapse:separate;border-spacing:5px;vertical-align:top;">
				<tr>
					<td colspan="2"><?php _e( 'Allow you to implement direct checkout without affecting the existing cart for WooCommerce.', $this->textdomain ); ?></td>
				</tr>
				<tr>
					<td width="70%" style="vertical-align:top;">
						<form action="<?php echo $actionurl; ?>" method="post">
						<table>
								<tbody>
									<tr>
										<td colspan="2">
											<table class="widefat auto" cellspacing="2" cellpadding="5" border="0">
												<tr>
													<td width="30%"><?php _e( 'Enable', $this->textdomain ); ?></td>
													<td>
														<input class="checkbox" name="direct_checkout_enabled" id="direct_checkout_enabled" value="0" type="hidden">
														<input class="checkbox" name="direct_checkout_enabled" id="direct_checkout_enabled" value="1" type="checkbox" <?php echo $checked_enabled; ?>>
													</td>
												</tr>
												<tr>
													<td width="30%"><?php _e( 'Custom Add to Cart Text', $this->textdomain ); ?></td>
													<td>
														<input name="direct_checkout_cart_button_text" id="direct_checkout_cart_button_text" value="<?php echo $direct_checkout_cart_button_text; ?>" />
													</td>
												</tr>
											</table>
										</td>
									</tr>
									<tr>
										<td colspan=2">
											<input class="button-primary" type="submit" name="Save" value="<?php _e('Save Options', $this->textdomain); ?>" id="submitbutton" />
											<input type="hidden" name="submitted" value="1" /> 
											<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo $nonce; ?>" />
										</td>
									</tr>
								</tbody>
						</table>
						</form>
					
					</td>
					<td width="30%" style="background:#ececec;padding:10px 5px;" valign="top">
						<div>
							<div id="fb-root"></div>
								<script>(function(d, s, id) {
  									var js, fjs = d.getElementsByTagName(s)[0];
									  if (d.getElementById(id)) return;
									  js = d.createElement(s); js.id = id;
									  js.src = "//connect.facebook.net/ko_KR/sdk.js#xfbml=1&version=v2.0";
									  fjs.parentNode.insertBefore(js, fjs);
									}(document, 'script', 'facebook-jssdk'));</script>
							<div class="fb-like" data-href="https://www.facebook.com/pages/YoshopM/328647853989364" data-layout="standard" data-action="like" data-show-faces="true" data-share="true"></div>
						</div>
						<p><?php _e( '<b>Yoshop Direct Checkout for WooCommerce</b> is a FREE woocommerce plugin developed by', $this->textdomain ); ?> <a href="<?php _e( 'http://www.yoshopm.com/en/', $this->textdomain ); ?>" target="_blank" title="Yoshop">YoshopM</a> (Powered by <a href="http://www.ibexalb.com/" target="_blank" >ibexlab</a>). <br /> <?php _e( 'This plugin aims to add direct checkout for WooCommerce.', $this->textdomain ); ?></p>
						
						<?php
							$get_pro_image = YoShop_Direct_Checkout::$plugin_url . '/images/direct-checkout-pro-version.png';
						?>
						<div align="center"><a href="<?php _e( 'http://www.yoshopm.com/en/product/yoshop-direct-checkout-pro/', $this->textdomain ); ?>" target="_blank" title="Yoshop Direct Checkout for WooCommerce PRO"><img src="<?php echo $get_pro_image; ?>" border="0" /></a></div>
						
						<h3>Get More Information</h3>
					
						<p><a href="https://www.facebook.com/pages/YoshopM/328647853989364" target="_blank" title="Get More Information by YoshopM"><?php _e( 'Go to YoshopM Facebook Page</a> to get more information Yoshop Direct Checkout plugins.', $this->textdomain ); ?></p>
											
						<h3>Get More Plugins</h3>
					
						<p><a href="<?php _e( 'http://www.yoshopm.com/en/shop/', $this->textdomain ); ?>" target="_blank" title="Premium &amp; Free Extensions/Plugins for E-Commerce by YoshopM"><?php _e( 'Go to YoshopM Site</a> to get more free and premium extensions/plugins for your ecommerce sites.', $this->textdomain ); ?></p>
											
						</td>
				</tr>
				</table>
				
				
				<br />
				
			<?php
			}
			
			/**
			 * Get the setting options
			 */
			function get_options() {
				
				foreach($this->options_direct_checkout as $field => $value)
				{
					$array_options[$field] = get_option( $field );
				}
					
				return $array_options;
			}

			
		}//end class
			
	}//if class does not exist
	
	$yoshop_direct_checkout = new YoShop_Direct_Checkout();
}
else{
	add_action('admin_notices', 'yoshop_direct_checkout_error_notice');
	function yoshop_direct_checkout_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__(plugin_name_yoshop_direct_checkout.' requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.').'</p></div>';
		}
	}
}

?>
