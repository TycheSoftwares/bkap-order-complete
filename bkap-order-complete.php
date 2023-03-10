<?php 
/**
 * Plugin Name: BKAP Order Complete
 * Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-booking-plugin
 * Description: Set order to complete once the booking date has passed.
 * Version: 1.0
 * Author: Tyche Softwares
 * Author URI: http://www.tychesoftwares.com/
 * Text Domain: woocommerce-booking
 * Requires PHP: 5.6
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.0
 */

class BKAP_Order_Complete {

	public function __construct() {

		add_action( 'admin_init', array( &$this, 'bkap_run_order_complete_cron' ) );
		add_action( 'bkap_set_order_complete', array( &$this, 'bk_set_order_complete' ) ); 
		add_action( 'bkap_after_global_holiday_field', array( &$this, 'bkap_add_settings' ) );
	}

	public static function bkap_add_settings() {

		add_settings_field(
			'enable_book_complete_order_time',
			__( 'Enable cron job to set past order status to complete', 'woocommerce-booking' ),
			array( 'BKAP_Order_Complete', 'enable_book_complete_order_time_callback' ),
			'bkap_global_settings_page',
			'bkap_global_settings_section',
			array ( __( 'All the past orders will set to complete automatically.', 'woocommerce-booking' ) )
		);

		add_settings_field(
			'book_complete_order_time',
			__( 'Start time to schedule cron job for order status', 'woocommerce-booking' ),
			array( 'BKAP_Order_Complete', 'book_complete_order_time_callback' ),
			'bkap_global_settings_page',
			'bkap_global_settings_section',
			array ( __( 'Set the time when the cron job would start to change the order status to complete.', 'woocommerce-booking' ) )
		);

		register_setting(
			'bkap_global_settings',
			'woocommerce_booking_global_settings',
			array( 'bkap_global_settings', 'woocommerce_booking_global_settings_callback' )
		);

	}

	public static function enable_book_complete_order_time_callback( $args ) {

		$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		$enable_order_complete = "";

		if ( isset( $saved_settings->enable_book_complete_order_time ) && $saved_settings->enable_book_complete_order_time == "on" ) {
			$enable_order_complete = "checked";
		}

		echo '<input type="checkbox" name="woocommerce_booking_global_settings[enable_book_complete_order_time]" id="enable_book_complete_order_time" '. $enable_order_complete .'>';
		$html = '<label for="enable_book_complete_order_time"> ' . $args[ 0 ] . '</label>';
		echo $html;
	}


	public static function book_complete_order_time_callback( $args ) {
		$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		$start_time_order_complete = "00:00";

		if ( isset( $saved_settings->book_complete_order_time ) && $saved_settings->book_complete_order_time !== '' ) {
			$start_time_order_complete = $saved_settings->book_complete_order_time;
		}

		echo '<input type="text" name="woocommerce_booking_global_settings[book_complete_order_time]" id="book_complete_order_time" value="'. $start_time_order_complete .'" >';
	}

	public static function bkap_run_order_complete_cron() {

		global $wpdb;

		$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		$time           = $saved_settings->book_complete_order_time;
		$timezone       = get_option('timezone_string');

		if ( isset( $saved_settings->enable_book_complete_order_time ) && $saved_settings->enable_book_complete_order_time == "on" ) {

			if ( ! wp_next_scheduled( 'bkap_set_order_complete' ) ) {
				wp_schedule_event( strtotime( $time . " " . $timezone ), 'hourly', 'bkap_set_order_complete' );
			}
		} else {
			wp_clear_scheduled_hook( 'bkap_set_order_complete' );
		}
	}

	public function bk_set_order_complete() {

		global $wpdb;

		$booking_table = $wpdb->prefix . "booking_history";
		$meta_table    = $wpdb->prefix . "postmeta";
		$post_table    = $wpdb->prefix . "posts";
		$current_date  = date( 'Y-m-d', time() );
		$mailer        = WC_Emails::instance();
		$query         = "SELECT order_id from `". $wpdb->prefix ."woocommerce_order_items` WHERE order_item_id in ( SELECT order_item_id FROM `".$wpdb->prefix ."woocommerce_order_itemmeta` WHERE meta_key='_wapbk_checkout_date' AND meta_value < '". $current_date."')";
		$result        = $wpdb->get_results( $query );
		$orders 	   = wc_get_orders( array( 'numberposts' => '100', 'post_status' => 'processing' ) );

		foreach ( $orders as $id => $order ) {
			$count = 0;
			foreach ( $order->get_items() as $item_id => $item ){
				$wapbk_checkout_date = wc_get_order_item_meta( $item_id, '_wapbk_checkout_date' );
				if ( $wapbk_checkout_date != false ) {

					if ( '1970-01-01' == $wapbk_checkout_date && wc_get_order_item_meta( $item_id, '_wapbk_booking_date' ) != false ) {
						$wapbk_checkout_date = wc_get_order_item_meta( $item_id, '_wapbk_booking_date' );
					}

					$time = strtotime( $wapbk_checkout_date );
					$date = date( 'Y-m-d', $time );
					if ( strtotime( $date ) < strtotime( $current_date ) ) {
						$count++;
					}
					break;
				} elseif ( wc_get_order_item_meta( $item_id, '_wapbk_booking_date') != false ) {

					$time = strtotime(wc_get_order_item_meta( $item_id, '_wapbk_booking_date') );
					$date = date( 'Y-m-d', $time );
					if( strtotime( $date ) < strtotime( $current_date ) ) {
						$count++;
					}
					break;
				}
			}

			if ( $count == count( $order->get_items() ) ) {
				$order->update_status( 'completed', 'Booking date passed' );
			}
		}
	}
}
new BKAP_Order_Complete();
