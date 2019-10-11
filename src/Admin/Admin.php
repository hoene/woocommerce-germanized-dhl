<?php

namespace Vendidero\Germanized\DHL\Admin;
use Vendidero\Germanized\DHL\Package;
use Vendidero\Germanized\DHL\ShippingMethod;
use Vendidero\Germanized\Shipments\Shipment;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Admin class.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );

		add_action( 'admin_init', array( __CLASS__, 'download_label' ) );
		add_action( 'woocommerce_gzd_shipments_meta_box_shipment_after_right_column', array( 'Vendidero\Germanized\DHL\Admin\MetaBox', 'output' ), 10, 1 );

		// Legacy meta box
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_legacy_meta_box' ), 20 );

		// Shipments
		add_filter( 'woocommerce_gzd_shipments_table_actions', array( __CLASS__, 'table_label_download' ), 10, 2 );
		add_action( 'woocommerce_gzd_shipments_table_actions_end', array( __CLASS__, 'table_label_generate' ), 10, 1 );
		add_filter( 'woocommerce_gzd_shipments_table_bulk_actions', array( __CLASS__, 'table_bulk_actions' ), 10, 1 );

		// Returns
		add_filter( 'woocommerce_gzd_return_shipments_table_actions', array( __CLASS__, 'table_label_download' ), 10, 2 );
		add_action( 'woocommerce_gzd_return_shipments_table_actions_end', array( __CLASS__, 'table_label_generate' ), 10, 1 );
		add_filter( 'woocommerce_gzd_return_shipments_table_bulk_actions', array( __CLASS__, 'table_bulk_actions' ), 10, 1 );

		// Bulk Labels
		add_filter( 'woocommerce_gzd_shipments_table_bulk_action_handlers', array( __CLASS__, 'register_bulk_handler' ) );
		add_action( 'woocommerce_gzd_shipments_table_bulk_action_labels_handled', array( __CLASS__, 'add_bulk_download' ), 10, 1 );
		add_action( 'woocommerce_gzd_return_shipments_table_bulk_action_labels_handled', array( __CLASS__, 'add_bulk_download' ), 10, 1 );

		// Template check
		add_filter( 'woocommerce_gzd_template_check', array( __CLASS__, 'add_template_check' ), 10, 1 );

		// Check upload folder
        add_action( 'admin_notices', array( __CLASS__, 'check_upload_dir' ) );

        // Password Settings
        add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_gzd_dhl_api_sandbox_password', array( __CLASS__, 'sanitize_password_field' ), 10, 3 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_gzd_dhl_api_password', array( __CLASS__, 'sanitize_password_field' ), 10, 3 );

	    // Product Options
		add_action( 'woocommerce_product_options_shipping', array( __CLASS__, 'product_options' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ), 10, 1 );

		// Reveiver ID options
        add_action( 'woocommerce_admin_field_dhl_receiver_ids', array( __CLASS__, 'output_receiver_ids_field' ) );
        add_action( 'woocommerce_gzd_admin_settings_after_save_dhl_labels', array( __CLASS__, 'save_receiver_ids' ) );
	}

	public static function save_receiver_ids() {
		$receiver = array();

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce verification already handled in WC_Admin_Settings::save()
		if ( isset( $_POST['receiver_id'] ) ) {

			$receiver_ids    = wc_clean( wp_unslash( $_POST['receiver_id'] ) );
			$countries       = wc_clean( wp_unslash( $_POST['receiver_country'] ) );

			foreach ( $receiver_ids as $i => $name ) {
				$country = isset( $countries[ $i ] ) ? substr( strtoupper( $countries[ $i ] ), 0, 2 ) : '';
				$slug    = sanitize_key( $receiver_ids[ $i ] . '_' . $country );

				$receiver[ $slug ] = array(
					'id'      => $receiver_ids[ $i ],
					'country' => $country,
                    'slug'    => $slug,
				);
			}
		}
		// phpcs:enable

		update_option( 'woocommerce_gzd_dhl_retoure_receiver_ids', $receiver );
    }

	public static function output_receiver_ids_field( $value ) {
		ob_start();
		?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php esc_html_e( 'Receiver Ids', 'woocommerce-germanized-dhl' ); ?></th>
            <td class="forminp" id="dhl_receiver_ids">
                <div class="wc_input_table_wrapper">
                    <table class="widefat wc_input_table sortable" cellspacing="0">
                        <input type="text" name="dhl_settings_hider" style="display: none" data-show_if_woocommerce_gzd_dhl_label_retoure_enable="" />
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Receiver Id', 'woocommerce-germanized-dhl' ); ?> <?php echo wc_help_tip( __( 'Find your Receiver Ids within your DHL contract data.', 'woocommerce-germanized' ) ); ?></th>
                            <th><?php esc_html_e( 'Country Code', 'woocommerce-germanized-dhl' ); ?> <?php echo wc_help_tip( __( 'Leave empty to use the Receiver Id as fallback.', 'woocommerce-germanized' ) ); ?></th>
                        </tr>
                        </thead>
                        <tbody class="receiver_ids">
						<?php
						$i = -1;
						if ( Package::get_return_receivers() ) {
							foreach ( Package::get_return_receivers() as $receiver ) {
								$i++;

								echo '<tr class="receiver">
										<td><input type="text" value="' . esc_attr( wp_unslash( $receiver['id'] ) ) . '" name="receiver_id[' . esc_attr( $i ) . ']" /></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $receiver['country'] ) ) . '" name="receiver_country[' . esc_attr( $i ) . ']" /></td>
									</tr>';
							}
						}
						?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add receiver', 'woocommerce-germanized-dhl' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected receiver(s)', 'woocommerce-germanized-dhl' ); ?></a></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <script type="text/javascript">
                    jQuery(function() {
                        jQuery('#dhl_receiver_ids').on( 'click', 'a.add', function(){

                            var size = jQuery('#dhl_receiver_ids').find('tbody .receiver').length;

                            jQuery('<tr class="receiver">\
									<td><input type="text" name="receiver_id[' + size + ']" /></td>\
									<td><input type="text" name="receiver_country[' + size + ']" /></td>\
								</tr>').appendTo('#dhl_receiver_ids table tbody');

                            return false;
                        });
                    });
                </script>
            </td>
        </tr>
		<?php
		$html = ob_get_clean();

		echo $html;
    }

	public static function product_options() {
		global $post, $thepostid;

		$thepostid     = $post->ID;
		$_product      = wc_get_product( $thepostid );
		$dhl_product   = wc_gzd_dhl_get_product( $_product );

		$countries = WC()->countries->get_countries();
		$countries = array_merge( array( '0' => __( 'Select a country', 'woocommerce-germanized-dhl' )  ), $countries );

		woocommerce_wp_text_input( array( 'id' => '_dhl_hs_code', 'label' => __( 'Harmonized Tariff Schedule (DHL)', 'woocommerce-germanized-dhl' ), 'desc_tip' => true, 'description' => __( 'This code is needed for customs of international shipping.', 'woocommerce-germanized-dhl' ) ) );
		woocommerce_wp_select( array( 'options' => $countries, 'id' => '_dhl_manufacture_country', 'label' => __( 'Country of manufacture (DHL)', 'woocommerce-germanized-dhl' ), 'desc_tip' => true, 'description' => __( 'The country of manufacture is needed for customs of international shipping.', 'woocommerce-germanized-dhl' ) ) );
	}

    public static function save_product( $product ) {
	    $hs_code = isset( $_POST['_dhl_hs_code'] ) ? wc_clean( $_POST['_dhl_hs_code'] ) : '';
	    $country = isset( $_POST['_dhl_manufacture_country'] ) ? wc_clean( $_POST['_dhl_manufacture_country'] ) : '';

	    $dhl_product = wc_gzd_dhl_get_product( $product );
	    $dhl_product->set_hs_code( $hs_code );
	    $dhl_product->set_manufacture_country( $country );
    }

	public static function sanitize_password_field( $value, $option, $raw_value ) {
		$value = is_null( $raw_value ) ? '' : $raw_value;

		return trim( $value );
    }

	public static function check_upload_dir() {
		$dir     = Package::get_upload_dir();
		$path    = $dir['basedir'];
		$dirname = basename( $path );

		if ( @is_dir( $dir['basedir'] ) )
			return;
		?>
        <div class="error">
            <p><?php printf( __( 'DHL label upload directory missing. Please manually create the folder %s and make sure that it is writeable.', 'woocommerce-germanized-dhl' ), '<i>wp-content/uploads/' . $dirname . '</i>' ); ?></p>
        </div>
		<?php
    }

	public static function add_template_check( $check ) {
		$check['dhl'] = array(
			'title'             => __( 'DHL', 'woocommerce-germanized-dhl' ),
			'path'              => Package::get_path() . '/templates',
			'template_path'     => Package::get_template_path(),
			'outdated_help_url' => $check['germanized']['outdated_help_url'],
			'files'             => array(),
			'has_outdated'      => false,
		);

		return $check;
    }

	/**
	 * @param BulkLabel $handler
	 */
	public static function add_bulk_download( $handler ) {
		if ( ( $path = $handler->get_file() ) && file_exists( $path ) ) {

			$download_url = add_query_arg( array(
				'action'   => 'wc-gzd-dhl-download-export-label',
				'force'    => 'no'
			), wp_nonce_url( admin_url(), 'dhl-download-export-label' ) );
			?>
			<div class="wc-gzd-dhl-bulk-downloads">
				<a class="button button-primary" href="<?php echo $download_url; ?>" target="_blank"><?php _e( 'Download labels', 'woocommerce-germanized-dhl' ); ?></a>
			</div>
			<?php
		}
	}

	public static function table_bulk_actions( $actions ) {
		$actions['labels'] = __( 'Generate labels', 'woocommerce-germanized-dhl' );

		return $actions;
	}

	public static function register_bulk_handler( $handlers ) {
		$handlers['labels'] = '\Vendidero\Germanized\DHL\Admin\BulkLabel';

		return $handlers;
	}

	public static function table_label_generate( $shipment ) {

		if ( wc_gzd_dhl_shipment_needs_label( $shipment ) ) {
			include Package::get_path() . '/includes/admin/views/html-shipment-label-backbone.php';
		}
	}

	/**
	 * @param array $actions
	 * @param Shipment $shipment
	 */
	public static function table_label_download( $actions, $shipment ) {
		if ( $label = wc_gzd_dhl_get_shipment_label( $shipment ) ) {
			$actions['download_dhl_label'] = array(
				'url'    => $label->get_download_url(),
				'name'   => __( 'Download DHL label', 'woocommerce-germanized-dhl' ),
				'action' => 'download-dhl-label download',
				'target' => '_blank'
			);

			if ( 'return' === $label->get_type() ) {
				$actions['email_dhl_label'] = array(
					'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_gzd_dhl_email_return_label&label_id=' . $label->get_id() ), 'email-dhl-label' ),
					'name'   => __( 'Send DHL label to customer', 'woocommerce-germanized-dhl' ),
					'action' => 'email-send-dhl-label email',
				);
            }
		} elseif ( wc_gzd_dhl_shipment_needs_label( $shipment ) ) {
			$actions['generate_dhl_label'] = array(
				'url'    => '#',
				'name'   => __( 'Generate DHL label', 'woocommerce-germanized-dhl' ),
				'action' => 'generate-dhl-label generate',
			);
		}

		return $actions;
	}

	public static function add_legacy_meta_box() {
		global $post;

		if ( ! Importer::is_plugin_enabled() && ( $post && 'shop_order' === $post->post_type && get_post_meta(  $post->ID, '_pr_shipment_dhl_label_tracking' ) ) ) {
			add_meta_box( 'woocommerce-gzd-shipment-dhl-legacy-label', __( 'DHL Label', 'woocommerce-germanized-dhl' ), array( __CLASS__, 'legacy_meta_box' ), 'shop_order', 'side', 'high' );
		}
	}

	public static function legacy_meta_box() {
		global $post;

		$order_id = $post->ID;
		$order    = wc_get_order( $order_id );
		$meta     = $order->get_meta( '_pr_shipment_dhl_label_tracking' );

		if ( ! empty( $meta ) ) {
			echo '<p>' . __( 'This label has been generated by the DHL for WooCommerce Plugin and is shown for legacy purposes.', 'woocommerce-germanized-dhl' ) . '</p>';
			echo '<a class="button button-primary" target="_blank" href="' . self::get_legacy_label_download_url( $order_id ) . '">' . __( 'Download label', 'woocommerce-germanized-dhl' ) . '</a>';
		}
	}

	public static function get_legacy_label_download_url( $order_id ) {
		$url = add_query_arg( array( 'action' => 'wc-gzd-dhl-download-legacy-label', 'order_id' => $order_id, 'force' => 'yes' ), wp_nonce_url( admin_url(), 'dhl-download-legacy-label' ) );

		return $url;
	}

	public static function download_label() {
		if ( isset( $_GET['action'] ) && 'wc-gzd-dhl-download-label' === $_GET['action'] ) {
			if ( isset( $_GET['label_id'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'dhl-download-label' ) ) {

				$label_id = absint( $_GET['label_id'] );
				$args     = wp_parse_args( $_GET, array(
					'force'  => 'no',
					'print'  => 'no',
					'path'   => '',
				) );

				DownloadHandler::download_label( $label_id, $args );
			}
		} elseif( isset( $_GET['action'] ) && 'wc-gzd-dhl-download-legacy-label' === $_GET['action'] ) {
			if ( isset( $_GET['order_id'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'dhl-download-legacy-label' ) ) {

				$order_id = absint( $_GET['order_id'] );
				$args     = wp_parse_args( $_GET, array(
					'force'  => 'no',
					'print'  => 'no',
				) );

				DownloadHandler::download_legacy_label( $order_id, $args );
			}
		} elseif( isset( $_GET['action'] ) && 'wc-gzd-dhl-download-export-label' === $_GET['action'] ) {
			if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'dhl-download-export-label' ) ) {

				$args = wp_parse_args( $_GET, array(
					'force'  => 'no',
					'print'  => 'no',
				) );

				DownloadHandler::download_export( $args );
			}
		}
	}

	public static function admin_styles() {
		global $wp_scripts;

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register admin styles.
		wp_register_style( 'woocommerce_gzd_dhl_admin', Package::get_assets_url() . '/css/admin' . $suffix . '.css', array( 'woocommerce_admin_styles' ), Package::get_version() );

		// Admin styles for WC pages only.
		if ( in_array( $screen_id, self::get_screen_ids() ) ) {
			wp_enqueue_style( 'woocommerce_gzd_dhl_admin' );
		}
	}

	protected static function get_table_screen_ids() {
	    return array(
            'woocommerce_page_wc-gzd-shipments',
            'woocommerce_page_wc-gzd-return-shipments'
        );
    }

	public static function admin_scripts() {
		global $post;

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'wc-gzd-admin-dhl-backbone', Package::get_assets_url() . '/js/admin-dhl-backbone' . $suffix . '.js', array( 'jquery', 'woocommerce_admin', 'wc-backbone-modal' ), Package::get_version(), true );
		wp_register_script( 'wc-gzd-admin-dhl', Package::get_assets_url() . '/js/admin-dhl' . $suffix . '.js', array( 'wc-gzd-admin-shipments', 'wc-gzd-admin-dhl-backbone' ), Package::get_version(), true );
		wp_register_script( 'wc-gzd-admin-dhl-table', Package::get_assets_url() . '/js/admin-dhl-table' . $suffix . '.js', array( 'wc-gzd-admin-dhl-backbone' ), Package::get_version(), true );
		wp_register_script( 'wc-gzd-admin-dhl-shipping-method', Package::get_assets_url() . '/js/admin-dhl-shipping-method' . $suffix . '.js', array( 'jquery' ), Package::get_version(), true );

		// Orders.
		$is_edit_order = in_array( str_replace( 'edit-', '', $screen_id ), wc_get_order_types( 'order-meta-boxes' ) );

		// Table
		if ( $is_edit_order || in_array(  $screen_id, self::get_table_screen_ids() ) ) {
			wp_enqueue_script( 'wc-gzd-admin-dhl-backbone' );

			wp_localize_script(
				'wc-gzd-admin-dhl-backbone',
				'wc_gzd_admin_dhl_backbone_params',
				array(
					'ajax_url'                 => admin_url( 'admin-ajax.php' ),
					'create_label_form_nonce'  => wp_create_nonce( 'create-dhl-label-form' ),
					'create_label_nonce'       => wp_create_nonce( 'create-dhl-label' ),
				)
			);
		}

		// Shipping zone methods
		if ( 'woocommerce_page_wc-settings' === $screen_id && isset( $_GET['tab'] ) && 'shipping' === $_GET['tab'] && isset( $_GET['zone_id'] ) ) {
			wp_enqueue_script( 'wc-gzd-admin-dhl-shipping-method' );
        }

		if ( in_array( $screen_id, self::get_table_screen_ids() ) ) {
			wp_enqueue_script( 'wc-gzd-admin-dhl-table' );
		}

		if ( $is_edit_order ) {
			wp_enqueue_script( 'wc-gzd-admin-dhl' );

			wp_localize_script(
				'wc-gzd-admin-dhl',
				'wc_gzd_admin_dhl_params',
				array(
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'remove_label_nonce'         => wp_create_nonce( 'remove-dhl-label' ),
					'edit_label_nonce'           => wp_create_nonce( 'edit-dhl-label' ),
					'send_label_nonce'           => wp_create_nonce( 'email-dhl-label' ),
					'i18n_remove_label_notice'   => __( 'Do you really want to delete the label?', 'woocommerce-germanized-dhl' ),
					'i18n_create_label_enabled'  => __( 'Create new DHL label', 'woocommerce-germanized-dhl' ),
					'i18n_create_label_disabled' => __( 'Please save the shipment before creating a new label', 'woocommerce-germanized-dhl' ),
				)
			);
		}
	}

	public static function get_screen_ids() {
		$screen_ids = self::get_table_screen_ids();

		foreach ( wc_get_order_types() as $type ) {
			$screen_ids[] = $type;
			$screen_ids[] = 'edit-' . $type;
		}

		return $screen_ids;
	}
}
