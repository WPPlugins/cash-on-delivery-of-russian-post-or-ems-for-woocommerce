<?php
/*
Plugin Name: Cash on Delivery of Russian Post or EMS For WooCommerce
Description: The plugin allows you to automatically calculate the tariff cost for Cash on Delivery of "Russian Post" or "EMS"
Version: 1.2.3
Author: Artem Komarov
Author URI: mailto:yumecommerce@gmail.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: codpg-russian-post
Domain Path: /languages
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    // Add trigger
    function codpg_russian_post_script_method() {
        if (is_checkout()) {
            wp_enqueue_script( 'codpg-russian-post-js', plugins_url( '/js/codpg-script.js', __FILE__ ), array(), '1.0.0', true);
        }
    }
    add_action( 'wp_enqueue_scripts', 'codpg_russian_post_script_method' );

	function codpg_russian_post_init_class() {
		if ( ! class_exists( 'WC_CODPG_Russian_Post' ) ) {
		
            class WC_CODPG_Russian_Post extends WC_Payment_Gateway {
                /**
                 * Constructor for the gateway.
                 */
            	public function __construct() {
            		$this->id                 = 'codpg_russian_post';
            		$this->method_title       = __( 'Cash on Delivery of Russian Post or EMS', 'codpg-russian-post' );
            		$this->method_description = __( 'Automatically calculates extra fee based on tariffs of Russian Post or EMS.', 'codpg-russian-post' );
            		$this->has_fields         = false;
            
            		// Load the settings
            		$this->init_form_fields();
            		$this->init_settings();
            
            		// Get settings
            		$this->title              = $this->get_option( 'title' );
            		$this->description        = $this->get_option( 'description' );
            		$this->instructions       = $this->get_option( 'instructions', $this->description );
            		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
            
            		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            		add_action( 'woocommerce_thankyou_cod', array( $this, 'thankyou_page' ) );
            
                	// Customer Emails
                	add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
            	}
            	
                /**
                 * Initialise Gateway Settings Form Fields.
                 */
                public function init_form_fields() {
                	$shipping_methods = array();
            
                	if ( is_admin() )
            	    	foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
            		    	$shipping_methods[ $method->id ] = $method->get_method_title();
            	    	}
            
                	$this->form_fields = array(
            			'enabled' => array(
            				'title'       => __( 'Enable COD', 'codpg-russian-post' ),
            				'label'       => __( 'Enable Cash on Delivery of Russian Post', 'codpg-russian-post' ),
            				'type'        => 'checkbox',
            				'description' => '',
            				'default'     => 'no'
            			),
            			'title' => array(
            				'title'       => __( 'Title', 'codpg-russian-post' ),
            				'type'        => 'text',
            				'description' => __( 'Payment method description that the customer will see on your checkout.', 'codpg-russian-post' ),
            				'default'     => __( 'Cash on Delivery', 'codpg-russian-post' ),
            				'desc_tip'    => true,
            			),
            			'description' => array(
            				'title'       => __( 'Description', 'codpg-russian-post' ),
            				'type'        => 'textarea',
            				'description' => __( 'Payment method description that the customer will see on your website.', 'codpg-russian-post' ),
            				'default'     => __( 'Pay with cash upon delivery.', 'codpg-russian-post' ),
            			),
            			'instructions' => array(
            				'title'       => __( 'Instructions', 'codpg-russian-post' ),
            				'type'        => 'textarea',
            				'description' => __( 'Instructions that will be added to the thank you page.', 'codpg-russian-post' ),
            				'default'     => __( 'Pay with cash upon delivery.', 'codpg-russian-post' ),
            			),
            			'enable_for_methods' => array(
            				'title'             => __( 'Enable for shipping methods', 'codpg-russian-post' ),
            				'type'              => 'multiselect',
            				'class'             => 'wc-enhanced-select',
            				'css'               => 'width: 450px;',
            				'default'           => '',
            				'description'       => __( 'If COD is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'codpg-russian-post' ),
            				'options'           => $shipping_methods,
            				'desc_tip'          => true,
            				'custom_attributes' => array(
            					'data-placeholder' => __( 'Select shipping methods', 'codpg-russian-post' )
            				)
            			)
             	   );
                }
            
            	/**
            	 * Check If The Gateway Is Available For Use.
            	 *
            	 * @return bool
            	 */
            	public function is_available() {
            		$order          = null;
            		$needs_shipping = false;
            
            		// Test if shipping is needed first
            		if ( WC()->cart && WC()->cart->needs_shipping() ) {
            			$needs_shipping = true;
            		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
            			$order_id = absint( get_query_var( 'order-pay' ) );
            			$order    = wc_get_order( $order_id );
            
            			// Test if order needs shipping.
            			if ( 0 < sizeof( $order->get_items() ) ) {
            				foreach ( $order->get_items() as $item ) {
            					$_product = $order->get_product_from_item( $item );
            					if ( $_product && $_product->needs_shipping() ) {
            						$needs_shipping = true;
            						break;
            					}
            				}
            			}
            		}
            
            		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );
            
            
            		// Check methods
            		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {

            
            			// Only apply if all packages are being shipped via chosen methods, or order is virtual
            			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );
            
            			if ( isset( $chosen_shipping_methods_session ) ) {
            				$chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
            			} else {
            				$chosen_shipping_methods = array();
            			}
            
            			$check_method = false;
            
            			if ( is_object( $order ) ) {
            				if ( $order->shipping_method ) {
            					$check_method = $order->shipping_method;
            				}
            
            			} elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
            				$check_method = false;
            			} elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
            				$check_method = $chosen_shipping_methods[0];
            			}
            
            			if ( ! $check_method ) {
            				return false;
            			}
            
            			$found = false;
            
            			foreach ( $this->enable_for_methods as $method_id ) {
            				if ( strpos( $check_method, $method_id ) === 0 ) {
            					$found = true;
            					break;
            				}
            			}
            
            			if ( ! $found ) {
            				return false;
            			}
            		}
            
            		return parent::is_available();
            	}
            
            
                /**
                 * Process the payment and return the result.
                 *
                 * @param int $order_id
                 * @return array
                 */
            	public function process_payment( $order_id ) {
            		$order = wc_get_order( $order_id );
            
                    $order->update_status( 'processing', __( 'Payment to be made upon delivery.', 'codpg-russian-post' ) );
            
            		// Reduce stock levels
            		$order->reduce_order_stock();
            
            		// Remove cart
            		WC()->cart->empty_cart();
            
            		// Return thankyou redirect
            		return array(
            			'result' 	=> 'success',
            			'redirect'	=> $this->get_return_url( $order )
            		);
            	}
            
                /**
                 * Output for the order received page.
                 */
            	public function thankyou_page() {
            		if ( $this->instructions ) {
                    	echo wpautop( wptexturize( $this->instructions ) );
            		}
            	}
            
                /**
                 * Add content to the WC emails.
                 *
                 * @access public
                 * @param WC_Order $order
                 * @param bool $sent_to_admin
                 * @param bool $plain_text
                 */
            	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            		if ( $this->instructions && ! $sent_to_admin && 'codpg_russian_post' === $order->payment_method ) {
            			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            		}
            	} 
            }
        }
	}
	add_action( 'plugins_loaded', 'codpg_russian_post_init_class' );
	
    // add new gateway method
	function codpg_russian_post_add_gateway_class( $methods ) {
	    $methods[] = 'WC_CODPG_Russian_Post'; 
	    return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'codpg_russian_post_add_gateway_class' );

    // Add extra fee based on post tariffs
    function codpg_russian_post_extra_fee() {
        global $woocommerce;

        $cart_total = floatval(preg_replace('#[^\d.]#', '', str_replace('&#8381;', '', $woocommerce->cart->get_cart_total())));
        $shipping_total = WC()->shipping->shipping_total;

        // add full valuation
        $cart_total = $cart_total + $shipping_total;
        
        $tariff = 0;

        // Posts's tariffs
        // https://www.pochta.ru/documents/10231/17590/Почтовый+перевод.pdf/11fed773-7764-4d3e-a723-8acb089b76ee
        if ( $cart_total <= '1000' ) {
            $tariff = 70 + $cart_total * 0.05;
        }
        elseif ( $cart_total >= '1001' && $cart_total <= '5000' ) {
            $tariff = 80 + $cart_total * 0.04;
        }
        elseif ( $cart_total >= '5001' && $cart_total <= '20000' ) {
            $tariff = 180 + $cart_total * 0.02;
        }
        elseif ( $cart_total >= '20001' && $cart_total <= '500000' ) {
            $tariff = 280 + $cart_total * 0.015;
        }

        // Check if method was checked
        if ( isset($_COOKIE['codpg-rp']) && isset($_COOKIE['codpg-country']) && $_COOKIE['codpg-country'] == 'RU' ) {
            $woocommerce->cart->add_fee( __( 'COD Tariff', 'codpg-russian-post' ), $tariff, true, '' );
        } elseif ( isset($_COOKIE['codpg-country']) && $_COOKIE['codpg-country'] != 'RU' ) {
            add_filter( 'woocommerce_available_payment_gateways','codpg_remove_odpg_russian_gateways', 1 );
        }
    }
    add_action( 'woocommerce_cart_calculate_fees', 'codpg_russian_post_extra_fee' ); 

    // remove gateway for international address
    function codpg_remove_odpg_russian_gateways($gateways){
        unset($gateways['codpg_russian_post']);
        return $gateways;
    }

} // if woocommerce is active

// load plugin textdomain.
add_action( 'plugins_loaded', 'codpg_load_textdomain' );
function codpg_load_textdomain() {
    load_plugin_textdomain( 'codpg-russian-post', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' ); 
}