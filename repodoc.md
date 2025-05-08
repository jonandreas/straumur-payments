This file is a merged representation of the entire codebase, combined into a single document by Repomix.
The content has been processed where content has been formatted for parsing in markdown style, security check has been disabled.

# Directory Structure
```
assets/
  js/
    frontend/
      straumur-block-payment-method.asset.php
      straumur-block-payment-method.js
includes/
  class-wc-straumur-api.php
  class-wc-straumur-block-support.php
  class-wc-straumur-order-handler.php
  class-wc-straumur-payment-gateway.php
  class-wc-straumur-settings.php
  class-wc-straumur-webhook-handler.php
src/
  index.js
package.json
straumur-payments-for-woocommerce.php
```

# Files

## File: assets/js/frontend/straumur-block-payment-method.asset.php
```php
<?php return array('dependencies' => array('react-jsx-runtime'), 'version' => 'aba3a644065bed2632c0');
```

## File: assets/js/frontend/straumur-block-payment-method.js
```javascript
(()=>{"use strict";const t=window.ReactJSXRuntime,{__,sprintf:e}=window.wp.i18n,{decodeEntities:n}=window.wp.htmlEntities,{registerPaymentMethod:s}=window.wc.wcBlocksRegistry,{getSetting:i}=window.wc.wcSettings,o=i("straumur_data",{}),r=__("Straumur Payments","woo-gutenberg-products-block"),a=n(o.title)||r,c=()=>n(o.description||"Secure payment via Straumur Hosted checkout"),u=e=>{const{PaymentMethodLabel:n}=e.components;return(0,t.jsx)(n,{text:a})};s({name:"straumur",label:(0,t.jsx)(u,{}),content:(0,t.jsx)(c,{}),edit:(0,t.jsx)(c,{}),canMakePayment:()=>!0,ariaLabel:a,supports:{features:o.supports}})})();
```

## File: includes/class-wc-straumur-block-support.php
```php
<?php
/**
 * Straumur Webhook Class
 *
 * Registers Straumur payment method with the new Cart and Checkout blocks.
 */

declare(strict_types=1);

namespace Straumur\Payments;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Straumur_Block_Support extends AbstractPaymentMethodType {

	/**
	 * Initialize the class by hooking into WooCommerce Blocks.
	 */
	public static function init(): void {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			array( __CLASS__, 'register_payment_method_type' )
		);
	}

	/**
	 * Register the payment method type.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Payment method registry.
	 * @return void
	 */
	public static function register_payment_method_type( $registry ): void {
		$instance = new self();
		$registry->register( $instance );
	}

	/**
	 * Implement required method from IntegrationInterface.
	 */
	public function initialize() {
	}

	public function get_name(): string {
		return 'straumur';
	}

	public function get_payment_method_title(): string {
		return WC_Straumur_Settings::get_title();
	}

	public function get_payment_method_description(): string {
		return WC_Straumur_Settings::get_description();
	}

	public function get_payment_method_script_handles(): array {
		$this->register_scripts();
		return array( 'straumur-block-payment-method' );
	}

	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->get_payment_method_title(),
			'description' => $this->get_payment_method_description(),
			'supports'    => array( 'products', 'subscriptions' ),
		);
	}

	public function register_scripts(): void {
		$asset_path = STRAUMUR_PAYMENTS_PLUGIN_DIR . 'assets/js/frontend/straumur-block-payment-method.asset.php';
		$asset      = file_exists( $asset_path )
			? include $asset_path
			: array(
				'dependencies' => array(),
				'version'      => STRAUMUR_PAYMENTS_VERSION,
			);

		wp_register_script(
			'straumur-block-payment-method',
			STRAUMUR_PAYMENTS_PLUGIN_URL . 'assets/js/frontend/straumur-block-payment-method.js',
			array_merge( $asset['dependencies'], array( 'wc-blocks-registry', 'wc-settings' ) ),
			$asset['version'],
			true
		);
	}

	public function is_active(): bool {
		$settings = get_option( 'woocommerce_straumur_settings', array() );
		return isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}
}
```

## File: includes/class-wc-straumur-settings.php
```php
<?php
/**
 * Straumur Settings Class
 *
 * Provides and validates the settings fields used by the Straumur payment gateway.dsd
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

use WC_Admin_Settings;
use function esc_html__;
use function esc_url_raw;
use function home_url;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Straumur_Settings
 *
 * @since 1.0.0
 */
class WC_Straumur_Settings {

	/**
	 * Option key for Straumur settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static string $option_key = 'woocommerce_straumur_settings';

	/**
	 * Caches the Straumur settings to prevent repeated database calls.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private static array $cached_settings = array();

	/**
	 * Retrieve the gateway form fields for the settings page (all fields).
	 * We'll split these into tabs in the gateway class.
	 *
	 * @since 1.0.0
	 * @return array Settings fields.
	 */
	public static function get_form_fields(): array {
		$webhook_url = home_url( '/wp-json/straumur/v1/payment-callback' );

		return array(
			'enabled'                     => array(
				'title'   => esc_html__( 'Enable/Disable', 'straumur-payments-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable Straumur Payments', 'straumur-payments-for-woocommerce' ),
				'default' => 'yes',
			),
			'test_mode'                   => array(
				'title'       => esc_html__( 'Test Mode', 'straumur-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable Test Mode', 'straumur-payments-for-woocommerce' ),
				'default'     => 'yes',
				'description' => esc_html__( 'If enabled, the gateway will use the sandbox URL.', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => false,
			),
			'title'                       => array(
				'title'       => esc_html__( 'Title', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__(
					'This controls the title which the user sees during checkout.',
					'straumur-payments-for-woocommerce'
				),
				'default'     => esc_html__( 'Straumur Payments', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'                 => array(
				'title'       => esc_html__( 'Description', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__(
					'This controls the description which the user sees during checkout.',
					'straumur-payments-for-woocommerce'
				),
				'default'     => esc_html__( 'Pay via Straumur Hosted Checkout.', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'theme_key'                   => array(
				'title'       => esc_html__( 'Theme key', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Theme key, logo colors, etc.', 'straumur-payments-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'authorize_only'              => array(
				'title'       => esc_html__( 'Authorize Only (Manual Capture)', 'straumur-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable authorize only mode. Payments will require manual capture.', 'straumur-payments-for-woocommerce' ),
				'default'     => 'no',
				'description' => esc_html__( 'If enabled, payments will be authorized but not captured automatically.', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => false,
			),
			'complete_order_on_payment'   => array(
				'title'       => esc_html__( 'Mark Order as Completed', 'straumur-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__(
					'If enabled, orders will be marked as Completed after a successful payment instead of Processing.',
					'straumur-payments-for-woocommerce'
				),
				'default'     => 'no',
			),
			'items'                       => array(
				'title'       => esc_html__( 'Send cart items', 'straumur-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__(
					'Send cart items to the checkout page. Disable if using incompatible plugins.',
					'straumur-payments-for-woocommerce'
				),
				'default'     => 'yes',
				'desc_tip'    => false,
			),
			'checkout_expiry'             => array(
				'title'       => esc_html__( 'Checkout Expiry (hours)', 'straumur-payments-for-woocommerce' ),
				'type'        => 'select',
				'default'     => '1',
				'options'     => array(
					'0.0833' => esc_html__( '5 minutes', 'straumur-payments-for-woocommerce' ),
					'0.1667' => esc_html__( '10 minutes', 'straumur-payments-for-woocommerce' ),
					'0.25'   => esc_html__( '15 minutes', 'straumur-payments-for-woocommerce' ),
					'0.5'    => esc_html__( '30 minutes', 'straumur-payments-for-woocommerce' ),
					'1'      => esc_html__( '1 hour', 'straumur-payments-for-woocommerce' ),
					'2'      => esc_html__( '2 hours', 'straumur-payments-for-woocommerce' ),
					'4'      => esc_html__( '4 hours', 'straumur-payments-for-woocommerce' ),
					'6'      => esc_html__( '6 hours', 'straumur-payments-for-woocommerce' ),
					'12'     => esc_html__( '12 hours', 'straumur-payments-for-woocommerce' ),
					'24'     => esc_html__( '24 hours', 'straumur-payments-for-woocommerce' ),
				),
				'description' => esc_html__( 'How long before checkout sessions expire (approx).', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'abandon_url'                 => array(
				'title'       => esc_html__( 'Abandon URL', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Optional URL to redirect shoppers if they cancel or abandon the payment page.', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'custom_success_url'          => array(
				'title'       => esc_html__( 'Custom Success URL', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Optional URL to redirect shoppers after a successful payment. If left empty, the default WooCommerce thank-you page is used.', 'straumur-payments-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'terminal_identifier'         => array(
				'title'       => esc_html__( 'Payment Page Terminal Identifier', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'The Terminal Identifier provided by Straumur.', 'straumur-payments-for-woocommerce' ),
				'default'     => 'd67efaf00c8c',
				'desc_tip'    => true,
			),
			'gateway_terminal_identifier' => array(
				'title'       => esc_html__( 'Payment Gateway Terminal Identifier', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'The Terminal Identifier provided by Straumur.', 'straumur-payments-for-woocommerce' ),
				'default'     => '0531e49886d1',
				'desc_tip'    => true,
			),
			'api_key'                     => array(
				'title'       => esc_html__( 'API Key', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'The API Key provided by Straumur.', 'straumur-payments-for-woocommerce' ),
				'default'     => '8ee1da747aafad5a281807dd837489172370ad5b710b8d9625',
				'desc_tip'    => true,
			),
			'hmac_key'                    => array(
				'title'       => esc_html__( 'HMAC Key', 'straumur-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Your HMAC secret key used to validate incoming webhooks.', 'straumur-payments-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_url'                 => array(
				'title'             => esc_html__( 'Webhook URL', 'straumur-payments-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__(
					'Use this URL in your Straumur dashboard to configure webhooks. Click the field to select all.',
					'straumur-payments-for-woocommerce'
				),
				'default'           => $webhook_url,
				'desc_tip'          => false,
				'custom_attributes' => array(
					'readonly' => 'readonly',
					'onclick'  => 'this.select()',
				),
			),
		);
	}

	/**
	 * Validate the production URL field.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $test_mode True if test mode is enabled.
	 * @param string $value     The production URL value.
	 *
	 * @return string The validated or sanitized production URL.
	 */
	public static function validate_production_url_field( bool $test_mode, string $value ): string {
		if ( ! $test_mode && empty( $value ) ) {
			WC_Admin_Settings::add_error(
				esc_html__(
					'Production URL is required when Test Mode is disabled.',
					'straumur-payments-for-woocommerce'
				)
			);
			return '';
		}
		// Sanitize the URL before saving.
		return esc_url_raw( $value );
	}

	/**
	 * Retrieve the saved settings for the Straumur gateway.
	 * Caches after first load.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private static function get_settings(): array {
		if ( empty( self::$cached_settings ) ) {
			self::$cached_settings = get_option( self::$option_key, array() );
		}
		return self::$cached_settings;
	}

	/**
	 * Check if the gateway is enabled.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = self::get_settings();
		return ( isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] );
	}

	/**
	 * Get the gateway title.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_title(): string {
		$settings = self::get_settings();
		return $settings['title'] ?? esc_html__( 'Straumur Payments', 'straumur-payments-for-woocommerce' );
	}

	/**
	 * Get the gateway description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_description(): string {
		$settings = self::get_settings();
		return $settings['description'] ?? esc_html__(
			'Pay securely using Straumur hosted checkout.',
			'straumur-payments-for-woocommerce'
		);
	}

	/**
	 * Get the Straumur API key.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_api_key(): string {
		$settings = self::get_settings();
		return $settings['api_key'] ?? '';
	}

	/**
	 * Get the Straumur Gateway Terminal Identifier.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_gateway_terminal_identifier(): string {
		$settings = self::get_settings();
		return $settings['gateway_terminal_identifier'] ?? '';
	}

	/**
	 * Get the Straumur Payment Page Terminal Identifier.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_terminal_identifier(): string {
		$settings = self::get_settings();
		return $settings['terminal_identifier'] ?? '';
	}

	/**
	 * Get the Straumur theme key.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_theme_key(): string {
		$settings = self::get_settings();
		return $settings['theme_key'] ?? '';
	}

	/**
	 * Check if payments are authorize-only.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_authorize_only(): bool {
		$settings = self::get_settings();
		return ( isset( $settings['authorize_only'] ) && 'yes' === $settings['authorize_only'] );
	}

	/**
	 * Check if orders should be marked as completed on payment.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_complete_order_on_payment(): bool {
		$settings = self::get_settings();
		return ( isset( $settings['complete_order_on_payment'] ) && 'yes' === $settings['complete_order_on_payment'] );
	}

	/**
	 * Check if test mode is enabled.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_test_mode(): bool {
		$settings = self::get_settings();
		return ( isset( $settings['test_mode'] ) && 'yes' === $settings['test_mode'] );
	}

	/**
	 * Get the Abandon URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_abandon_url(): string {
		$settings = self::get_settings();
		return ! empty( $settings['abandon_url'] ) ? $settings['abandon_url'] : '';
	}

	/**
	 * Get the Custom Success URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_custom_success_url(): string {
		$settings = self::get_settings();
		return ! empty( $settings['custom_success_url'] ) ? $settings['custom_success_url'] : '';
	}

	/**
	 * Get the production URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_production_url(): string {
		$settings = self::get_settings();
		// Default is the live Straumur endpoint, sanitized.
		return esc_url_raw( $settings['production_url'] ?? 'https://greidslugatt.straumur.is/api/v1/' );
	}

	/**
	 * Get the HMAC key for validating Straumur webhooks.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_hmac_key(): string {
		$settings = self::get_settings();
		return $settings['hmac_key'] ?? '';
	}

	/**
	 * Whether cart items should be sent to Straumur.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function send_items(): bool {
		$settings = self::get_settings();
		return ( isset( $settings['items'] ) && 'yes' === $settings['items'] );
	}

	/**
	 * Get the Straumur webhook URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_webhook_url(): string {
		$settings = self::get_settings();
		return ! empty( $settings['webhook_url'] )
			? $settings['webhook_url']
			: home_url( '/wp-json/straumur/v1/payment-callback' );
	}

	/**
	 * Get the checkout expiry (in hours) from the settings.
	 *
	 * @since 1.0.0
	 * @return float
	 */
	public static function get_checkout_expiry(): float {
		$settings = self::get_settings();
		return isset( $settings['checkout_expiry'] )
			? (float) $settings['checkout_expiry']
			: 1.0;
	}
}
```

## File: src/index.js
```javascript
const { __, sprintf } = window.wp.i18n;
const { decodeEntities } = window.wp.htmlEntities;
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;


const settings = getSetting( 'straumur_data', {} );

const defaultLabel = __(
    'Straumur Payments',
    'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
    return decodeEntities( settings.description || 'Secure payment via Straumur Hosted checkout' );

};
/**
 * Label component

 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={ label } />;
};


const Straumur = {
    name: "straumur",
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( Straumur );
```

## File: package.json
```json
{
  "name": "straumur-payments",
  "version": "1.1.0",
  "scripts": {
    "build": "wp-scripts build src/index.js --output-path=assets/js/frontend --output-filename=straumur-block-payment-method.js --externals=@woocommerce/blocks-registry=wc-blocks-registry --externals=@woocommerce/settings=wc-settings",
    "start": "wp-scripts start src/index.js --output-path=assets/js/frontend --output-filename=straumur-block-payment-method.js --externals=@woocommerce/blocks-registry=wc-blocks-registry --externals=@woocommerce/settings=wc-settings",
    "packages-update": "wp-scripts packages-update",
    "check-engines": "wp-scripts check-engines",
    "make:pot": "wp i18n make-pot . languages/straumur-payments-for-woocommerce.pot",
    "merge:po": "wp i18n update-po languages/straumur-payments-for-woocommerce.pot languages/",
    "make:mo": "wp i18n make-mo languages/",
    "update:translations": "npm run make:pot && npm run merge:po && npm run make:mo"
  },
  "devDependencies": {
    "@woocommerce/dependency-extraction-webpack-plugin": "^2.2.0",
    "@wordpress/scripts": "^30.14.0"
  }
}
```

## File: straumur-payments-for-woocommerce.php
```php
<?php

/**
 * Plugin Name:     Straumur Payments For WooCommerce
 * Plugin URI:      https://straumur.is/veflausnir
 * Description:     Facilitates seamless payments using Straumur's Hosted Checkout in WooCommerce.
 * Author:          Straumur
 * Author URI:      https://straumur.is
 * Text Domain:     straumur-payments-for-woocommerce
 * Domain Path:     /languages
 * Version:         2.0.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.1
 * WC tested up to: 9.7
 * WC Payment Gateway: yes
 * WC Subscriptions Support: yes
 * WC Blocks Support: yes
 *
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Define plugin constants.
 */
define( 'STRAUMUR_PAYMENTS_VERSION', '2.0.0' );
define( 'STRAUMUR_PAYMENTS_MAIN_FILE', __FILE__ );
define( 'STRAUMUR_PAYMENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STRAUMUR_PAYMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Show an admin notice if WooCommerce is not active.
 *
 * @since 1.0.0
 * @return void
 */
function straumur_payments_woocommerce_missing_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="error"><p><strong>' . esc_html__(
		'Straumur Payments for WooCommerce requires WooCommerce to be installed and active.',
		'straumur-payments-for-woocommerce'
	) . '</strong></p></div>';
}

/**
 * Enqueue scripts for block-based checkout integration.
 *
 * @since 2.0.0
 * @return void
 */
function straumur_enqueue_block_payment_scripts(): void {
	if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'wc_enqueue_js' ) ) {
		return;
	}

	// Only enqueue on the block-based checkout page, if the function exists.
	if ( function_exists( 'wc_blocks_is_checkout' ) && wc_blocks_is_checkout() ) {
		wp_register_script(
			'straumur-block-integration',
			STRAUMUR_PAYMENTS_PLUGIN_URL . 'assets/js/straumur-block-payment-method.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			STRAUMUR_PAYMENTS_VERSION,
			true
		);
		wp_enqueue_script( 'straumur-block-integration' );
	}
}

/**
 * Filter tokens for block-based checkout.
 *
 * @since 2.0.0
 * @param array  $tokens     Array of token data.
 * @param int    $user_id    User ID.
 * @param string $gateway_id Gateway ID.
 * @return array Filtered tokens.
 */
function straumur_filter_block_checkout_tokens( $tokens, $user_id, $gateway_id ) {
	if ( $gateway_id !== 'straumur' ) {
		return $tokens;
	}

	$is_renewal = function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal();

	if ( $is_renewal ) {
		return $tokens;
	}

	foreach ( $tokens as $token_id => $token ) {
		if ( $token instanceof \WC_Payment_Token && $token->get_meta( 'subscription_only' ) === 'yes' ) {
			unset( $tokens[ $token_id ] );
		}
	}

	return array_values( $tokens );
}

/**
 * Filter payment tokens at the core data source level.
 *
 * @since 2.0.0
 * @param array $tokens      Array of token objects.
 * @param int   $customer_id Customer ID.
 * @return array Filtered tokens.
 */
function straumur_filter_customer_payment_tokens( $tokens, $customer_id ) {
	unset( $customer_id ); // Intentionally unused parameter.

	if ( is_admin() || ! is_checkout() || is_wc_endpoint_url() ) {
		return $tokens;
	}

	$is_renewal = function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal();

	if ( $is_renewal ) {
		return $tokens;
	}

	foreach ( $tokens as $key => $token ) {
		if ( $token instanceof \WC_Payment_Token
			&& $token->get_gateway_id() === 'straumur'
			&& $token->get_meta( 'subscription_only' ) === 'yes'
		) {
			unset( $tokens[ $key ] );
		}
	}

	return array_values( $tokens );
}

/**
 * Initialize the Straumur Payments plugin.
 *
 * Checks if WooCommerce is active and loads the payment gateway and other classes.
 *
 * @since 1.0.0
 * @return void
 */
function straumur_payments_init(): void {
	// Check if WooCommerce is active.
	if ( ! function_exists( 'WC' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\straumur_payments_woocommerce_missing_notice' );
		return;
	}

	// Load text domain for translations.
	load_plugin_textdomain(
		'straumur-payments-for-woocommerce',
		false,
		dirname( plugin_basename( STRAUMUR_PAYMENTS_MAIN_FILE ) ) . '/languages'
	);

	// Include required plugin files.
	require_once STRAUMUR_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-straumur-settings.php';
	require_once STRAUMUR_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-straumur-api.php';
	require_once STRAUMUR_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-straumur-order-handler.php';
	require_once STRAUMUR_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-straumur-payment-gateway.php';
	require_once STRAUMUR_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-straumur-block-support.php';
	require_once STRAUMUR_PAYMENTS_PLUGIN_DIR . 'includes/class-wc-straumur-webhook-handler.php';

	// Register block-based checkout integrations if available.
	if ( class_exists( __NAMESPACE__ . '\\WC_Straumur_Block_Support' ) ) {
		WC_Straumur_Block_Support::init();
	}

	// Token filtering hooks
	add_filter(
		'woocommerce_store_api_get_customer_payment_tokens',
		__NAMESPACE__ . '\\straumur_filter_block_checkout_tokens',
		10,
		3
	);
	add_filter(
		'woocommerce_get_customer_payment_tokens',
		__NAMESPACE__ . '\\straumur_filter_customer_payment_tokens',
		10,
		2
	);

	// Declare compatibility for WooCommerce Blocks (Cart and Checkout).
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'cart_checkout_blocks',
					__FILE__,
					true
				);
			}
		}
	);

	// Enqueue block scripts at the right time.
	add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\straumur_enqueue_block_payment_scripts' );

	// Add the gateway to WooCommerce.
	add_filter( 'woocommerce_payment_gateways', __NAMESPACE__ . '\\add_straumur_payment_gateway' );

	// Declare HPOS (High Performance Order Storage) compatibility.
	add_action(
		'before_woocommerce_init',
		static function () {
			if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					STRAUMUR_PAYMENTS_MAIN_FILE,
					true
				);
			}
		}
	);

	// Add plugin action links.
	add_filter(
		'plugin_action_links_' . plugin_basename( STRAUMUR_PAYMENTS_MAIN_FILE ),
		__NAMESPACE__ . '\\straumur_payments_action_links'
	);

	// Add plugin row meta.
	add_filter( 'plugin_row_meta', __NAMESPACE__ . '\\straumur_payments_plugin_row_meta', 10, 2 );

	// Instantiate the order handler (manages captures, refunds, cancellations).
	$straumur_order_handler = new WC_Straumur_Order_Handler();

	// Hook into order status transitions without returning values explicitly.
	add_action(
		'woocommerce_order_status_on-hold_to_cancelled',
		static function ( $order_id ) use ( $straumur_order_handler ): void {
			try {
				$straumur_order_handler->handle_cancellation( $order_id );
			} catch ( \Exception $e ) {
				wc_get_logger()->error( 'Cancellation error: ' . $e->getMessage(), array( 'source' => 'straumur' ) );
			}
		}
	);

	add_action(
		'woocommerce_order_status_on-hold_to_processing',
		static function ( $order_id ) use ( $straumur_order_handler ): void {
			try {
				$straumur_order_handler->handle_capture( $order_id );
			} catch ( \Exception $e ) {
				wc_get_logger()->error( 'Capture error: ' . $e->getMessage(), array( 'source' => 'straumur' ) );
			}
		}
	);

	add_action(
		'woocommerce_order_status_on-hold_to_completed',
		static function ( $order_id ) use ( $straumur_order_handler ): void {
			try {
				$straumur_order_handler->handle_capture( $order_id );
			} catch ( \Exception $e ) {
				wc_get_logger()->error( 'Capture error: ' . $e->getMessage(), array( 'source' => 'straumur' ) );
			}
		}
	);

	add_action(
		'woocommerce_order_status_processing_to_refunded',
		static function ( $order_id ) use ( $straumur_order_handler ): void {
			try {
				$straumur_order_handler->handle_refund( $order_id );
			} catch ( \Exception $e ) {
				wc_get_logger()->error( 'Refund error: ' . $e->getMessage(), array( 'source' => 'straumur' ) );
			}
		}
	);

	add_action(
		'woocommerce_order_status_completed_to_refunded',
		static function ( $order_id ) use ( $straumur_order_handler ): void {
			try {
				$straumur_order_handler->handle_refund( $order_id );
			} catch ( \Exception $e ) {
				wc_get_logger()->error( 'Refund error: ' . $e->getMessage(), array( 'source' => 'straumur' ) );
			}
		}
	);
}

/**
 * Add Straumur Payment Gateway to WooCommerce gateways.
 *
 * @since 1.0.0
 *
 * @param array $gateways Existing WooCommerce payment gateways.
 * @return array Modified list of payment gateways.
 */
function add_straumur_payment_gateway( array $gateways ): array {
	$gateways[] = '\\Straumur\\Payments\\WC_Straumur_Payment_Gateway';
	return $gateways;
}

/**
 * Add action links in the Plugins list page.
 *
 * @since 1.0.0
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function straumur_payments_action_links( array $links ): array {
	$settings_url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=straumur' );
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $settings_url ),
		esc_html__( 'Settings', 'straumur-payments-for-woocommerce' )
	);
	array_unshift( $links, $settings_link );

	return $links;
}

/**
 * Add plugin row meta links in the Plugins list page.
 *
 * @since 1.0.0
 *
 * @param array  $links Existing meta links.
 * @param string $file  Plugin file path.
 * @return array Modified plugin meta links.
 */
function straumur_payments_plugin_row_meta( array $links, string $file ): array {
	if ( plugin_basename( STRAUMUR_PAYMENTS_MAIN_FILE ) === $file ) {
		$docs_link    = '<a href="https://docs.straumur.is" target="_blank" rel="noopener noreferrer">' .
			esc_html__( 'Documentation', 'straumur-payments-for-woocommerce' ) . '</a>';
		$support_link = '<a href="https://straumur.is/hafa-samband" target="_blank" rel="noopener noreferrer">' .
			esc_html__( 'Support', 'straumur-payments-for-woocommerce' ) . '</a>';

		$links[] = $docs_link;
		$links[] = $support_link;
	}

	return $links;
}


// Initialize the plugin after all plugins are loaded, ensuring WooCommerce is ready.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\straumur_payments_init' );
```

## File: includes/class-wc-straumur-api.php
```php
<?php

/**
 * Straumur API Class
 *
 * Communicates with Straumur's API to handle payment sessions, status retrieval,
 * captures, cancellations, and reversals.
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

use Straumur\Payments\WC_Straumur_Settings;
use WC_Logger_Interface;
use WP_Error;

use function wc_get_logger;
use function trailingslashit;
use function wp_remote_request;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_body;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function is_wp_error;
use function wp_json_encode;

/**
 * Class WC_Straumur_API
 *
 * @since 1.0.0
 */
class WC_Straumur_API
{

	/**
	 * Holds the singleton instance.
	 *
	 * @var WC_Straumur_API|null
	 */
	private static $instance = null;

	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Whether test mode is active.
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Terminal identifier provided by Straumur (non-token transactions).
	 *
	 * @var string
	 */
	private $terminal_identifier;

	/**
	 * Gateway terminal identifier (used for tokenized payments).
	 *
	 * @var string
	 */
	private $gateway_terminal_identifier;

	/**
	 * Theme key for customizing the hosted checkout interface.
	 *
	 * @var string
	 */
	private $theme_key;

	/**
	 * If true, payments are authorized only and require manual capture.
	 *
	 * @var bool
	 */
	private $authorize_only;

	/**
	 * Timeout for requests (in seconds).
	 *
	 * @var int
	 */
	private $timeout = 60;

	/**
	 * Base URL for the API endpoint.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * WooCommerce logger instance.
	 *
	 * @var WC_Logger_Interface
	 */
	private $logger;

	/**
	 * Logging context array.
	 *
	 * @var array
	 */
	private $context = array(
		'source' => 'straumur-api',
	);

	/**
	 * Whether line items should be included in the hosted checkout request.
	 *
	 * @var bool
	 */
	private $send_items;

	/**
	 * The checkout expiry in fractional hours (e.g., 0.0833 for 5 minutes).
	 *
	 * @var float
	 */
	private $checkout_expiry;

	/**
	 * Get the singleton instance.
	 *
	 * @return WC_Straumur_API
	 */
	public static function instance(): WC_Straumur_API
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $authorize_only Whether to authorize only (no auto-capture).
	 */
	public function __construct(bool $authorize_only = false)
	{
		$this->authorize_only              = $authorize_only;
		$this->api_key                     = WC_Straumur_Settings::get_api_key();
		$this->theme_key                   = WC_Straumur_Settings::get_theme_key();
		$this->terminal_identifier         = WC_Straumur_Settings::get_terminal_identifier();
		$this->gateway_terminal_identifier = WC_Straumur_Settings::get_gateway_terminal_identifier();
		$this->test_mode                   = WC_Straumur_Settings::is_test_mode();
		$this->send_items                  = WC_Straumur_Settings::send_items();

		// Retrieve checkout expiry from settings, ensuring it's within a valid range.
		$hours = (float) WC_Straumur_Settings::get_checkout_expiry();
		if ($hours < 0.0833) {
			$hours = 0.0833;
		} elseif ($hours > 24) {
			$hours = 24;
		}
		$this->checkout_expiry = $hours;

		// Determine the base URL (test vs. production).
		$production_url = WC_Straumur_Settings::get_production_url();
		$this->base_url = $this->test_mode
			? 'https://checkout-api.staging.straumur.is/api/v1/'
			: trailingslashit($production_url);

		$this->logger = wc_get_logger();
	}

	/**
	 * Create a payment session for the hosted checkout.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $amount          Minor units (e.g. 50000 => 500.00).
	 * @param string $currency        ISO currency code (e.g., "ISK").
	 * @param string $return_url      URL to which Straumur will redirect after payment.
	 * @param string $reference       Merchant reference (order ID or similar).
	 * @param array  $items           Optional line items array.
	 * @param bool   $is_subscription True if this is a recurring subscription payment.
	 * @param string $abandon_url     Optional URL for the shopper if they abandon the payment.
	 *
	 * @return array|false Response array from Straumur on success, false otherwise.
	 */
	public function create_session(
		int $amount,
		string $currency,
		string $return_url,
		string $reference,
		array $items,
		bool $is_subscription = false,
		string $abandon_url = ''
	) {
		$endpoint   = 'hostedcheckout/';
		$expires_at = gmdate('Y-m-d\\TH:i:s.v\\Z', time() + (int) ($this->checkout_expiry * HOUR_IN_SECONDS));

		$body = array(
			'amount'             => $amount,
			'currency'           => $currency,
			'returnUrl'          => $return_url,
			'reference'          => $reference,
			'terminalIdentifier' => $this->terminal_identifier,
			'expiresAt'          => $expires_at,
		);

		// Include line items if requested.
		if ($this->send_items) {
			$body['items'] = $items;
		}

		// Use the theme key only in production mode.
		if (! $this->test_mode && ! empty($this->theme_key)) {
			$body['themeKey'] = $this->theme_key;
		}

		// If manual capture is requested.
		if ($this->authorize_only) {
			$body['isManualCapture'] = true;
		}

		// If subscription, set recurringProcessingModel.
		if ($is_subscription) {
			$body['recurringProcessingModel'] = 'Subscription';
		}

		// Include abandon URL if provided.
		if (! empty($abandon_url)) {
			$body['abandonUrl'] = $abandon_url;
		}

		return $this->send_request($endpoint, $body);
	}

	/**
	 * Retrieve the session status by checkout reference.
	 *
	 * @since 1.0.0
	 *
	 * @param string $checkout_reference The Straumur checkout reference ID.
	 * @return array|false Array on success, false otherwise.
	 */
	public function get_session_status(string $checkout_reference)
	{
		$endpoint = "hostedcheckout/status/{$checkout_reference}";

		return $this->send_request($endpoint, array(), 'GET');
	}

	/**
	 * Capture an authorized payment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $payfac_reference Payfac reference from Straumur.
	 * @param string $reference        Your internal (merchant) reference.
	 * @param int    $amount           Amount to capture (minor units).
	 * @param string $currency         Currency code.
	 * @return array|false Array on success, or false on failure.
	 */
	public function capture(string $payfac_reference, string $reference, int $amount, string $currency)
	{
		$body = array(
			'reference'       => $reference,
			'payfacReference' => $payfac_reference,
			'amount'          => $amount,
			'currency'        => $currency,
		);

		return $this->send_request('modification/capture', $body);
	}

	/**
	 * Reverse an authorization or transaction (partial or full).
	 *
	 * @since 1.0.0
	 *
	 * @param string $reference        Your internal reference.
	 * @param string $payfac_reference Payfac reference from Straumur.
	 * @return bool True if the request was successful, false otherwise.
	 */
	public function reverse(string $reference, string $payfac_reference): bool
	{
		$body = array(
			'reference'       => $reference,
			'payfacReference' => $payfac_reference,
		);

		$response = $this->send_request('modification/reverse', $body);
		return (bool) $response;
	}

	/**
	 * Process a token-based subscription payment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token_value Token value stored in WooCommerce.
	 * @param int    $amount      Amount in minor units.
	 * @param string $currency    ISO currency code.
	 * @param string $reference   Unique order/merchant reference.
	 * @param string $shopper_ip  Shopper's IP address.
	 * @param string $origin      Origin URL (store domain).
	 * @param string $channel     Payment channel (e.g., 'Web').
	 * @param string $return_url  URL to handle 3DS or additional steps.
	 * @return array The Straumur API response as an associative array.
	 */
	public function process_token_payment(
		string $token_value,
		int $amount,
		string $currency,
		string $reference,
		string $shopper_ip,
		string $origin,
		string $channel,
		string $return_url
	): array {
		$body = array(
			'terminalIdentifier' => $this->gateway_terminal_identifier,
			'amount'             => $amount,
			'currency'           => $currency,
			'reference'          => $reference,
			'shopperIp'          => $shopper_ip,
			'origin'             => $origin,
			'channel'            => $channel,
			'returnUrl'          => $return_url,
			'tokenDetails'       => array(
				'tokenValue'               => $token_value,
				'recurringProcessingModel' => 'Subscription',
			),
		);

		$this->log(
			"Processing token payment for reference {$reference} and amount {$amount}",
			'info'
		);

		$endpoint = 'payment';
		$result   = $this->send_request($endpoint, $body);

		if (isset($result['resultCode']) && 'Authorised' === $result['resultCode']) {
			// Process and normalize payment references for future operations like refunds
			$result = $this->normalize_payment_references($result, $reference);
			$this->log("Token payment authorised for reference {$reference}", 'info');
		} elseif (isset($result['resultCode']) && 'RedirectShopper' === $result['resultCode']) {
			$this->log("Token payment requires redirect for reference {$reference}", 'info');
		} else {
			$this->log(
				"Token payment failed for reference {$reference} with response: " . wp_json_encode($result),
				'error'
			);
		}

		return is_array($result) ? $result : array();
	}
	private function log_json(string $title, array $data, string $level = 'info'): void
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$message = sprintf(
				"%s:\n%s",
				$title,
				wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
			);
			if (method_exists($this->logger, $level)) {
				$this->logger->{$level}($message, $this->context);
			} else {
				$this->logger->info($message, $this->context);
			}
		}
	}
	/**
	 * Common function to send an API request to Straumur.
	 *
	 * @param string $endpoint
	 * @param array  $body
	 * @param string $method
	 * @return array|false
	 */
	private function send_request(string $endpoint, array $body = array(), string $method = 'POST')
	{
		$url = $this->base_url . $endpoint;

		$args = array(
			'headers' => $this->get_request_headers(),
			'method'  => $method,
			'timeout' => $this->timeout,
		);

		if ('GET' === $method && !empty($body)) {
			$url = add_query_arg($body, $url);
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
		} elseif ('POST' === $method && !empty($body)) {
			$args['body'] = wp_json_encode($body);
		}

		$this->log_json('Straumur API Request', [
			'method'  => $method,
			'url'     => $url,
			'headers' => $args['headers'],
			'body'    => $body,
		]);

		$response = wp_remote_request($url, $args);

		return $this->handle_response($response, $url);
	}


	/**
	 * Handle the raw response from wp_remote_request().
	 *
	 * @since 1.0.0
	 *
	 * @param array|WP_Error $response The result of wp_remote_request().
	 * @param string         $url      The requested URL.
	 * @return array|false Decoded response on success, false on failure.
	 */
	private function handle_response($response, string $url)
	{
		if (is_wp_error($response)) {
			$this->log_json('Straumur API Response Error', [
				'url'   => $url,
				'error' => $response->get_error_message(),
			], 'error');
			return false;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		$response_data = json_decode($response_body, true);

		$this->log_json('Straumur API Response', [
			'url'      => $url,
			'httpCode' => $response_code,
			'body'     => $response_data,
		], $response_code >= 200 && $response_code < 300 ? 'info' : 'error');

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->log('JSON decode error: ' . json_last_error_msg(), 'error');
			return false;
		}

		if ($response_code >= 200 && $response_code < 300) {
			return $response_data;
		}

		return false;
	}

	/**
	 * Build request headers, including the X-API-key.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of headers.
	 */
	private function get_request_headers(): array
	{
		return array(
			'Content-Type' => 'application/json',
			'X-API-key'    => $this->api_key,
		);
	}

	/**
	 * Normalize payment references in the API response.
	 *
	 * This function ensures that payfacReference is available in the response,
	 * which is required for refunding subscription payments.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $response  The API response.
	 * @param string $reference The merchant reference.
	 * @return array The normalized response.
	 */
	private function normalize_payment_references(array $response, string $reference): array
	{
		// Some API responses use pspReference instead of payfacReference
		if (! isset($response['payfacReference']) && isset($response['pspReference'])) {
			$response['payfacReference'] = $response['pspReference'];
			$this->log(
				"Normalized pspReference to payfacReference ({$response['payfacReference']}) for reference {$reference}",
				'info'
			);
		}

		if (! isset($response['payfacReference']) && isset($response['additionalData']['payfacReference'])) {
			$response['payfacReference'] = $response['additionalData']['payfacReference'];
			$this->log(
				"Extracted payfacReference ({$response['payfacReference']}) from additionalData for reference {$reference}",
				'info'
			);
		}

		if (isset($response['payfacReference'])) {
			$this->log(
				"Payment has payfacReference: {$response['payfacReference']} for merchant reference {$reference}",
				'info'
			);
		} else {
			$this->log(
				"No payfacReference found in response for reference {$reference}. Refunds may not be possible.",
				'warning'
			);
		}

		return $response;
	}

	/**
	 * Log a message using WooCommerce's logger if WP_DEBUG is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (e.g., 'info', 'error').
	 * @return void
	 */
	private function log(string $message, string $level = 'info'): void
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			if (method_exists($this->logger, $level)) {
				$this->logger->{$level}($message, $this->context);
			} else {
				$this->logger->info($message, $this->context);
			}
		}
	}
}
```

## File: includes/class-wc-straumur-order-handler.php
```php
<?php

/**
 * Straumur Order Handler Class
 *
 * Handles order status transitions for Straumur payments.
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

use WC_Order;
use WP_Error;
use WC_Logger;

use function wc_get_logger;
use function wc_get_order;
use function round;
use function get_woocommerce_currency;

class WC_Straumur_Order_Handler
{

	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger
	 */
	private WC_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->logger = wc_get_logger();
	}

	/**
	 * Handle cancellation (On-hold -> Cancelled).
	 *
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function handle_cancellation(int $order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_Error('no_order', __('Invalid order.', 'straumur-payments-for-woocommerce'));
		}

		if ($order->get_payment_method() !== 'straumur') {
			return new WP_Error('not_straumur', __('Order not paid with Straumur.', 'straumur-payments-for-woocommerce'));
		}

		$payfac_reference = $order->get_meta('_straumur_payfac_reference');
		$reference        = $order->get_order_number();

		if (empty($payfac_reference) || empty($reference)) {
			return new WP_Error('no_reference', __('Cannot cancel: missing payment references.', 'straumur-payments-for-woocommerce'));
		}

		$api = new WC_Straumur_API();
		$this->logger->info("Attempting Straumur payment cancellation for order #{$order_id}", array('source' => 'straumur-payments-for-woocommerce'));

		$response = $api->reverse($reference, $payfac_reference);
		if ($response) {
			// Mark as "cancel requested" so that the webhook can recognize it as a cancellation event
			$order->update_meta_data('_straumur_cancel_requested', 'yes');
			$order->save();

			$order->add_order_note(__('Straumur payment cancellation request sent', 'straumur-payments-for-woocommerce'));
			return true;
		}

		$this->logger->error("Cancellation request failed for order #{$order_id}", array('source' => 'straumur-payments'));
		return new WP_Error('cancellation_failed', __('Failed to send cancellation request to Straumur.', 'straumur-payments-for-woocommerce'));
	}

	public function handle_capture(int $order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_Error('no_order', __('Invalid order.', 'straumur-payments-for-woocommerce'));
		}

		if ($order->get_payment_method() !== 'straumur') {
			return new WP_Error('not_straumur', __('Order not paid with Straumur.', 'straumur-payments-for-woocommerce'));
		}

		$payfac_reference = $order->get_meta('_straumur_payfac_reference');
		$reference        = $order->get_order_number();
		if (empty($payfac_reference) || empty($reference)) {
			return new WP_Error('no_reference', __('Cannot capture: missing payment references.', 'straumur-payments-for-woocommerce'));
		}

		// Call Straumur's API to request the capture
		$amount       = (float) $order->get_total();
		$amount_minor = (int) round($amount * 100);
		$currency     = get_woocommerce_currency();
		$api          = new WC_Straumur_API();

		$response = $api->capture($payfac_reference, $reference, $amount_minor, $currency);
		if ($response) {
			// Instead of payment_complete(), just add meta that capture was requested
			$order->update_meta_data('_straumur_capture_requested', 'yes');
			$order->add_order_note(__('Straumur capture request sent. Awaiting Straumur confirmation via webhook.', 'straumur'));
			$order->save();

			return true;
		}

		$this->logger->error("Capture request failed for order #{$order_id}", array('source' => 'straumur-payments'));
		return new WP_Error('capture_failed', __('Failed to send capture request to Straumur.', 'straumur-payments-for-woocommerce'));
	}

	/**
	 * Handle refund (Processing -> Refunded).
	 *
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function handle_refund(int $order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_Error('no_order', __('Invalid order.', 'straumur-payments-for-woocommerce'));
		}

		// Ensure order was paid with Straumur
		if ($order->get_payment_method() !== 'straumur') {
			return new WP_Error('not_straumur', __('Order not paid with Straumur.', 'straumur-payments-for-woocommerce'));
		}

		$payfac_reference = $order->get_meta('_straumur_payfac_reference');
		$reference        = $order->get_order_number();

		if (empty($payfac_reference) || empty($reference)) {
			return new WP_Error('no_reference', __('Cannot refund: missing payment references.', 'straumur-payments-for-woocommerce'));
		}

		$api = new WC_Straumur_API();
		$this->logger->info("Attempting Straumur payment refund for order #{$order_id}", array('source' => 'straumur-payments'));

		// Based on instructions, refund also uses reverse call
		$response = $api->reverse($reference, $payfac_reference);
		if ($response) {
			// Mark as "refund requested" so that the webhook can recognize it as a refund event
			$order->update_meta_data('_straumur_refund_requested', 'yes');
			$order->save();

			$order->add_order_note(__('Straumur refund request has been sent to Straumur.', 'straumur-payments-for-woocommerce'));
			$this->logger->info("Refund request successful for order #{$order_id}", array('source' => 'straumur-payments'));

			// Check if this order is related to a subscription and cancel it
			if (function_exists('wcs_order_contains_subscription') || function_exists('wcs_get_subscriptions_for_order')) {
				$subscriptions = function_exists('wcs_get_subscriptions_for_order') ?
					wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any')) : array();

				if (! empty($subscriptions)) {
					foreach ($subscriptions as $subscription) {
						if ($subscription->has_status('active')) {
							$subscription->update_status('cancelled', __('Subscription cancelled due to refunded payment.', 'straumur-payments-for-woocommerce'));
							$this->logger->info("Subscription #{$subscription->get_id()} cancelled due to refund of order #{$order_id}", array('source' => 'straumur-payments'));
						}
					}
				}
			}

			return true;
		}

		$this->logger->error("Refund request failed for order #{$order_id}", array('source' => 'straumur-payments'));
		return new WP_Error('refund_failed', __('Failed to send refund request to Straumur.', 'straumur-payments-for-woocommerce'));
	}
}
```

## File: includes/class-wc-straumur-payment-gateway.php
```php
<?php

/**
 * Straumur Payment Gateway Class.
 *
 * Integrates Straumur Hosted Checkout into WooCommerce, handling payment sessions,
 * return callbacks, and optional subscription payments.
 *
 * WC Subscriptions Support: yes
 * WC tested up to: 9.7
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

use WC_Payment_Gateway;
use WC_Order;
use WC_Logger_Interface;
use WP_Error;

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WC_ABSPATH')) {
	define('WC_ABSPATH', WP_PLUGIN_DIR . '/woocommerce/');
}

if (! defined('STRAUMUR_PAYMENTS_PLUGIN_URL')) {
	define('STRAUMUR_PAYMENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

class WC_Straumur_Payment_Gateway extends WC_Payment_Gateway
{

	private WC_Logger_Interface $logger;

	private string $terminal_identifier;
	private string $gateway_terminal_identifier;
	private string $api_key;
	private string $theme_key;
	private bool $authorize_only;
	private string $hmac_key;
	private bool $send_items;
	private string $abandon_url;
	private string $custom_success_url;

	private array $context = array('source' => 'straumur');

	public function __construct()
	{
		$this->logger = wc_get_logger();

		$this->id                 = 'straumur';
		$this->method_title       = esc_html__('Straumur Payments', 'straumur-payments-for-woocommerce');
		$this->method_description = esc_html__('Accept payments via Straumur Hosted Checkout.', 'straumur-payments-for-woocommerce');
		$this->has_fields         = false;
		$this->icon               = STRAUMUR_PAYMENTS_PLUGIN_URL . 'assets/images/straumur-28x28.png';

		$this->supports = array(
			'products',
			'subscriptions',
			'refunds',
			'wc-blocks',
			'wc-orders',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
		);

		$this->init_form_fields();
		$this->init_settings_values();

		add_action('woocommerce_api_' . $this->id, array($this, 'process_return'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action('woocommerce_scheduled_subscription_payment_straumur', array($this, 'process_subscription_payment'), 10, 2);
		add_action('woocommerce_subscription_payment_method_updated_to_straumur', array($this, 'process_subscription_payment_method_change'));
	}

	public function init_form_fields(): void
	{
		$this->form_fields = WC_Straumur_Settings::get_form_fields();
	}

	private function init_settings_values(): void
	{
		$this->title                       = WC_Straumur_Settings::get_title();
		$this->description                 = WC_Straumur_Settings::get_description();
		$this->enabled                     = WC_Straumur_Settings::is_enabled() ? 'yes' : 'no';
		$this->terminal_identifier         = WC_Straumur_Settings::get_terminal_identifier();
		$this->gateway_terminal_identifier = WC_Straumur_Settings::get_gateway_terminal_identifier();
		$this->api_key                     = WC_Straumur_Settings::get_api_key();
		$this->theme_key                   = WC_Straumur_Settings::get_theme_key();
		$this->authorize_only              = WC_Straumur_Settings::is_authorize_only();
		$this->hmac_key                    = WC_Straumur_Settings::get_hmac_key();
		$this->send_items                  = WC_Straumur_Settings::send_items();
		$this->abandon_url                 = WC_Straumur_Settings::get_abandon_url();
		$this->custom_success_url          = WC_Straumur_Settings::get_custom_success_url();
	}

	/**
	 * Returns an instance of the Straumur API class.
	 *
	 * @return WC_Straumur_API
	 */
	private function get_api(): WC_Straumur_API
	{
		// ADDED
		// Pass $this->authorize_only if you need manual-capture logic at the API level:
		return new WC_Straumur_API($this->authorize_only);
	}

	private function is_subscription_renewal_checkout(int $order_id): bool
	{
		if (! class_exists('WC_Subscriptions')) {
			return false;
		}

		return wcs_order_contains_renewal($order_id) || wcs_cart_contains_renewal();
	}

	private function get_order_items(WC_Order $order, int $expected_amount): array
	{
		$items            = array();
		$calculated_total = 0;

		foreach ($order->get_items() as $item) {
			/** @var \WC_Order_Item_Product $item */
			$line_total        = (int) round(($item->get_total() + $item->get_total_tax()) * 100);
			$items[]           = array(
				'Name'   => $item->get_name(),
				'Amount' => $line_total,
			);
			$calculated_total += $line_total;
		}

		if ($order->get_shipping_total() > 0) {
			$shipping_cost     = (int) round(($order->get_shipping_total() + $order->get_shipping_tax()) * 100);
			$items[]           = array(
				'Name'   => esc_html__('Delivery', 'straumur-payments-for-woocommerce'),
				'Amount' => $shipping_cost,
			);
			$calculated_total += $shipping_cost;
		}

		$difference = $expected_amount - $calculated_total;
		if ($difference !== 0 && ! empty($items)) {
			$items[count($items) - 1]['Amount'] += $difference;
		}

		return $items;
	}

	/**
	 * Handle the payment process and return a redirect URL to Straumur's hosted checkout.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return array|WP_Error
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			wc_add_notice(
				esc_html__('Invalid order.', 'straumur-payments-for-woocommerce'),
				'error'
			);
			$this->logger->error('Invalid order: ' . $order_id, $this->context);

			return array('result' => 'failure');
		}

		// Force status to 'pending' if the order is still in a draft state.
		// This ensures WooCommerce reserves stock (provided "Hold stock" is enabled).
		if (
			'auto-draft' === $order->get_status()
			|| 'draft' === $order->get_status()
		) {
			$order->update_status(
				'pending',
				__('Awaiting Straumur payment', 'straumur-payments-for-woocommerce')
			);

			// Add a direct note so you can see it in the order notes timeline.
			$order->add_order_note(
				__('Stock reserved by setting order to pending payment.', 'straumur-payments-for-woocommerce')
			);
			$order->save();
		}

		// Use get_api() to obtain the API instance
		$api = $this->get_api();

		// Whether manual capture is active
		$order->update_meta_data(
			'_straumur_is_manual_capture',
			$this->authorize_only ? 'yes' : 'no'
		);
		$order->save();

		// Convert order total to minor units.
		$amount    = (int) round($order->get_total() * 100);
		$currency  = get_woocommerce_currency();
		$reference = $order->get_order_number();

		// Build line items if needed.
		$items = $this->get_order_items($order, $amount);

		// Build return URL
		$return_url = add_query_arg(
			[
				'wc-api'         => $this->id,
				'order_id'       => $order->get_id(),
				'straumur_nonce' => wp_create_nonce('straumur_process_return'),
			],
			home_url('/')
		);

		// Check if the order is a subscription
		$is_subscription = function_exists('wcs_order_contains_subscription')
			&& wcs_order_contains_subscription($order_id);

		// Create session with Straumur
		$session = $api->create_session(
			$amount,
			$currency,
			$return_url,
			$reference,
			$items,
			$is_subscription,
			$this->abandon_url
		);

		if (! $session || ! isset($session['url'])) {
			wc_add_notice(
				esc_html__('Payment error: Unable to initiate payment session.', 'straumur-payments-for-woocommerce'),
				'error'
			);
			$this->logger->error(
				'Payment error: Unable to initiate payment session for order ' . $order_id,
				$this->context
			);

			return [
				'result' => 'failure'
			];
		}

		// Straumur returns the Hosted Checkout URL
		$redirect_url = $session['url'];

		return [
			'result'   => 'success',
			'redirect' => $redirect_url,
		];
	}

	/**
	 * Process a subscription payment (renewal) using the saved token.
	 *
	 * Uses the stored token to process automatic renewal payments for subscriptions.
	 * Handles different response scenarios including authorization, redirects, and failures.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount The amount to be charged.
	 * @param WC_Order $order  The order object (renewal order).
	 *
	 * @return array Response with result status and redirect URL if needed.
	 */
	public function process_subscription_payment($amount, WC_Order $order): array
	{
		$this->logger->info(
			sprintf(
				'Processing subscription payment of %s for order %d',
				$amount,
				$order->get_id()
			),
			$this->context
		);

		// Retrieve the customer's default payment token for this gateway.
		$tokens        = \WC_Payment_Tokens::get_customer_tokens($order->get_user_id(), $this->id);
		$default_token = null;

		if (! empty($tokens)) {
			foreach ($tokens as $token) {
				if (method_exists($token, 'is_default') && $token->is_default()) {
					$default_token = $token;
					break;
				}
			}
		}

		if (! $default_token) {
			$this->logger->error(
				'No default token found for order ' . $order->get_id(),
				$this->context
			);

			return array('result' => 'failure');
		}
		$token_value = $default_token->get_token();

		// Convert amount to minor units.
		$amount_minor = (int) round($amount * 100);
		$currency     = get_woocommerce_currency();
		$reference    = $order->get_order_number();

		// Retrieve shopper IP (if available) and origin.
		$shopper_ip = method_exists($order, 'get_customer_ip_address') ? $order->get_customer_ip_address() : '';
		$origin     = home_url('/');

		// Build return URL in case additional action is required.
		$return_url = add_query_arg(
			array(
				'wc-api'         => $this->id,
				'order_id'       => $order->get_id(),
				'straumur_nonce' => wp_create_nonce('straumur_process_return'),
			),
			home_url('/')
		);

		// Process token payment via API (uses get_api()).
		$api      = $this->get_api();
		$response = $api->process_token_payment(
			$token_value,
			$amount_minor,
			$currency,
			$reference,
			$shopper_ip,
			$origin,
			'Web',
			$return_url
		);

		if (isset($response['resultCode']) && 'Authorised' === $response['resultCode']) {
			// Save the payfacReference for future refund/capture operations
			if (! empty($response['payfacReference'])) {
				$payfac_reference = sanitize_text_field($response['payfacReference']);
				$order->update_meta_data('_straumur_payfac_reference', $payfac_reference);
				$order->save();

				$this->logger->info(
					sprintf(
						'Saved payfacReference %s for subscription payment on order %d',
						$payfac_reference,
						$order->get_id()
					),
					$this->context
				);
			} else {
				$this->logger->warning(
					sprintf(
						'No payfacReference received for authorized subscription payment on order %d',
						$order->get_id()
					),
					$this->context
				);
			}

			// Determine if the order should be marked completed or just payment complete.
			$mark_as_complete = WC_Straumur_Settings::is_complete_order_on_payment();
			if (! $order->needs_processing()) {
				$mark_as_complete = true;
			}

			if ($mark_as_complete) {
				$order->update_status(
					'completed',
					esc_html__('Subscription renewal authorized via token payment.', 'straumur-payments-for-woocommerce')
				);
			} else {
				$order->payment_complete();
				$order->add_order_note(
					esc_html__('Subscription renewal authorized via token payment.', 'straumur-payments-for-woocommerce')
				);
			}

			$this->logger->info(
				'Subscription payment authorized for order ' . $order->get_id(),
				$this->context
			);

			return array('result' => 'success');
		} elseif (isset($response['resultCode']) && 'RedirectShopper' === $response['resultCode']) {
			$redirect_url = $response['redirect']['url'] ?? '';
			$order->add_order_note(
				sprintf(
					/* translators: %s: redirect URL for additional payment steps. */
					esc_html__('Subscription renewal requires redirect: %s', 'straumur-payments-for-woocommerce'),
					$redirect_url
				)
			);
			$this->logger->info(
				'Subscription payment requires redirect for order ' . $order->get_id(),
				$this->context
			);

			return array(
				'result'   => 'success',
				'redirect' => $redirect_url,
			);
		} else {
			$order->update_status(
				'failed',
				esc_html__('Token payment failed.', 'straumur-payments-for-woocommerce')
			);
			$this->logger->error(
				'Subscription payment failed for order ' . $order->get_id()
					. '. Response: ' . wp_json_encode($response),
				$this->context
			);

			return array('result' => 'failure');
		}
	}

	/**
	 * Save the payment method (tokenization) for auto-renewals, if needed.
	 *
	 * Creates and stores a payment token for future subscription renewal payments.
	 * Always marks tokens as subscription_only since they're only used for recurring payments.
	 *
	 * @since 1.0.0
	 *
	 * @param int|WC_Order $order The completed order object or order ID.
	 *
	 * @return void
	 */
	public function save_payment_method($order): void
	{
		if (! $order instanceof WC_Order) {
			$order = wc_get_order($order);
		}
		if (! $order) {
			return;
		}

		if (class_exists('WC_Payment_Token_CC')) {
			$token = new \WC_Payment_Token_CC();
		} else {
			$this->logger->error('WC_Payment_Token_CC class does not exist.', $this->context);
			return;
		}

		$token->set_gateway_id($this->id);
		$token->set_token('SAVED_TOKEN_FROM_STRAUMUR');
		$token->set_user_id($order->get_user_id());
		$token->set_default(true);
		$token->update_meta_data('subscription_only', 'yes');

		$token->save();
	}

	/**
	 * Handle the return from Straumur's payment gateway
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_return(): void
	{
		if (
			! isset($_GET['straumur_nonce'])
			|| ! wp_verify_nonce(
				sanitize_text_field(wp_unslash($_GET['straumur_nonce'])),
				'straumur_process_return'
			)
		) {
			wp_die(esc_html__('Nonce verification failed.', 'straumur-payments-for-woocommerce'));
		}

		$order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
		$order    = wc_get_order($order_id);

		if (! $order) {
			wp_safe_redirect(wc_get_cart_url());
			exit;
		}

		$checkout_reference = isset($_GET['checkoutReference'])
			? sanitize_text_field(wp_unslash($_GET['checkoutReference']))
			: $order->get_meta('_straumur_checkout_reference');

		if (empty($checkout_reference)) {
			$checkout_url = $order->get_checkout_payment_url();
			wp_safe_redirect($checkout_url ? $checkout_url : wc_get_cart_url());
			exit;
		}

		if (! $order->get_meta('_straumur_checkout_reference')) {
			$order->update_meta_data('_straumur_checkout_reference', $checkout_reference);
			$order->save();
		}

		$api             = $this->get_api();
		$status_response = $api->get_session_status($checkout_reference);

		if (! $status_response) {
			wc_add_notice(
				esc_html__('Unable to verify payment status. Please try again.', 'straumur-payments-for-woocommerce'),
				'error'
			);
			$checkout_url = $order->get_checkout_payment_url();
			wp_safe_redirect($checkout_url ? $checkout_url : wc_get_cart_url());
			exit;
		}

		if (isset($status_response['payfacReference'])) {
			$payfac_ref = sanitize_text_field($status_response['payfacReference']);
			$order->update_meta_data('_straumur_payfac_reference', $payfac_ref);
			$order->save();

			wc_add_notice(
				esc_html__('Thank you for your order! Your payment is being processed.', 'straumur-payments-for-woocommerce'),
				'success'
			);

			$redirect_url = ! empty($this->custom_success_url)
				? $this->custom_success_url
				: $this->get_return_url($order);

			wp_safe_redirect($redirect_url);
			exit;
		} else {
			wc_add_notice(
				esc_html__('Your payment session was not completed. Please try again.', 'straumur-payments-for-woocommerce'),
				'error'
			);
			$checkout_url = $order->get_checkout_payment_url();
			wp_safe_redirect($checkout_url ? $checkout_url : wc_get_cart_url());
			exit;
		}
	}
}
```

## File: includes/class-wc-straumur-webhook-handler.php
```php
<?php

/**
 * Straumur Webhook Handler Class
 *
 * Handles incoming webhooks from Straumur's payment system and updates orders accordingly.
 *
 * @package Straumur\Payments
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Straumur\Payments;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WC_Order;

use function esc_html__;
use function esc_html;
use function sanitize_text_field;
use function sprintf;
use function wp_json_encode;
use function number_format;
use function absint;


// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

// Ensure the settings class is available.
if (! class_exists('Straumur\Payments\WC_Straumur_Settings')) {
	require_once __DIR__ . '/class-wc-straumur-settings.php';
}

/**
 * Class WC_Straumur_Webhook_Handler
 *
 * Manages registration of the webhook REST route and processes the incoming Straumur payloads.
 *
 * @since 1.0.0
 */
class WC_Straumur_Webhook_Handler
{

	/**
	 * Webhook event types
	 */
	public const EVENT_AUTHORIZATION = 'authorization';
	public const EVENT_CAPTURE       = 'capture';
	public const EVENT_REFUND        = 'refund';
	public const EVENT_TOKENIZATION  = 'tokenization';

	/**
	 * Initialize the webhook routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void
	{
		add_action('rest_api_init', array(self::class, 'register_routes'));
	}

	/**
	 * Register custom REST API routes for Straumur webhooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_routes(): void
	{
		register_rest_route(
			'straumur/v1',
			'/payment-callback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(self::class, 'handle_payment_callback'),
				'permission_callback' => array(self::class, 'check_webhook_hmac'),
			)
		);
	}

	/**
	 * Permission callback for verifying the HMAC signature.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return true|WP_Error True if signature is valid, WP_Error otherwise.
	 */
	public static function check_webhook_hmac(WP_REST_Request $request)
	{
		$body = $request->get_body();

		// Only log a redacted version of the payload without sensitive data
		$log_data = json_decode($body, true);
		if (is_array($log_data)) {
			if (isset($log_data['additionalData']['cardNumber'])) {
				$log_data['additionalData']['cardNumber'] = '[REDACTED]';
			}
			if (isset($log_data['additionalData']['token'])) {
				$log_data['additionalData']['token'] = '[REDACTED]';
			}
			wc_get_logger()->info("Incoming webhook:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		} else {
			self::log_message('Incoming webhook: Invalid JSON payload');
		}

		// Parse JSON payload
		$data = json_decode($body, true);
		if (! is_array($data)) {
			self::log_message(esc_html__('Invalid JSON payload.', 'straumur-payments-for-woocommerce'));
			return new WP_Error(
				'straumur_invalid_json',
				esc_html__('Invalid JSON payload.', 'straumur-payments-for-woocommerce'),
				array('status' => 400)
			);
		}

		// Verify required fields exist
		$required_fields = array('hmacSignature', 'checkoutReference', 'payfacReference', 'merchantReference');
		foreach ($required_fields as $field) {
			if (empty($data[$field])) {
				self::log_message(
					sprintf(
						/* translators: %s: field name */
						esc_html__('Required field missing: %s.', 'straumur-payments-for-woocommerce'),
						$field
					)
				);
				return new WP_Error(
					'straumur_missing_field',
					sprintf(
						/* translators: %s: field name */
						esc_html__('Required field missing: %s.', 'straumur-payments-for-woocommerce'),
						$field
					),
					array('status' => 400)
				);
			}
		}

		$signature = $data['hmacSignature'];

		// Validate the HMAC signature
		$is_valid = self::validate_hmac_signature($data, $signature);

		if (! $is_valid) {
			return new WP_Error(
				'straumur_invalid_signature',
				esc_html__('Invalid HMAC signature.', 'straumur-payments-for-woocommerce'),
				array('status' => 403)
			);
		}

		return true;
	}

	/**
	 * Process the Straumur payment callback after HMAC validation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request {
	 *     The incoming REST request. Expects a JSON body with:
	 *     @type string $hmacSignature      Base64-encoded signature.
	 *     @type string $merchantReference  The WooCommerce order ID.
	 *     @type bool   $success            Indicates if the payment succeeded.
	 *     @type array  $additionalData     Additional event data from Straumur.
	 * }
	 * @return WP_REST_Response|WP_Error Response with appropriate status.
	 */
	public static function handle_payment_callback(WP_REST_Request $request)
	{
		$body = $request->get_body();
		$data = json_decode($body, true);

		// Process in a try-catch block to ensure we catch all exceptions
		try {
			// Process with a database transaction if available
			global $wpdb;
			$transaction_started = false;

			if (method_exists($wpdb, 'query') && ! defined('WP_DEBUG') && function_exists('wc_transaction_query')) {
				wc_transaction_query('start');
				$transaction_started = true;
			}

			// If success is explicitly false, treat it as a failed transaction
			if (isset($data['success']) && ('false' === $data['success'] || false === $data['success'])) {
				$result = self::handle_failed_webhook($data);

				if (is_wp_error($result)) {
					if ($transaction_started) {
						wc_transaction_query('rollback');
					}
					return $result;
				}

				if ($transaction_started) {
					wc_transaction_query('commit');
				}

				return new WP_REST_Response(
					array('message' => esc_html__('Failed payment processed successfully.', 'straumur-payments-for-woocommerce')),
					200
				);
			}

			// Process the webhook for successful payments
			$result = self::process_webhook_data($data);

			if (is_wp_error($result)) {
				if ($transaction_started) {
					wc_transaction_query('rollback');
				}
				return $result;
			}

			if ($transaction_started) {
				wc_transaction_query('commit');
			}

			return new WP_REST_Response(
				array('message' => esc_html__('Webhook processed successfully.', 'straumur-payments-for-woocommerce')),
				200
			);
		} catch (\Exception $e) {
			self::log_message('Exception processing webhook: ' . $e->getMessage());

			if (isset($transaction_started) && $transaction_started) {
				wc_transaction_query('rollback');
			}

			return new WP_Error(
				'straumur_processing_error',
				esc_html__('Error processing webhook.', 'straumur-payments-for-woocommerce'),
				array('status' => 500)
			);
		}
	}

	/**
	 * Handle a failed transaction event from Straumur.
	 *
	 * Expects $data to contain at least 'merchantReference', 'payfacReference', and 'reason'.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Decoded JSON payload from Straumur.
	 *
	 *     @type string $merchantReference WooCommerce order ID.
	 *     @type string $payfacReference   Payfac reference for the transaction.
	 *     @type string $reason            Reason for failure, e.g. 'Refused', 'Expired Card'.
	 *     @type mixed  $additionalData    Additional optional data.
	 * }
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function handle_failed_webhook(array $data)
	{
		// Validate the merchant reference (order ID)
		$order_id = isset($data['merchantReference']) ? absint($data['merchantReference']) : 0;
		if ($order_id <= 0) {
			self::log_message(esc_html__('Invalid or missing order ID in failed webhook.', 'straumur-payments-for-woocommerce'));
			return new WP_Error(
				'straumur_invalid_order',
				esc_html__('Invalid or missing order ID.', 'straumur-payments-for-woocommerce'),
				array('status' => 400)
			);
		}

		// Get the order
		$order = wc_get_order($order_id);
		if (! $order) {
			self::log_message(
				sprintf(
					/* translators: %d: order ID */
					esc_html__('Order %d not found for failed webhook.', 'straumur-payments-for-woocommerce'),
					$order_id
				)
			);
			return new WP_Error(
				'straumur_order_not_found',
				esc_html__('Order not found.', 'straumur-payments-for-woocommerce'),
				array('status' => 404)
			);
		}

		// Get payment details
		$payfac_reference = isset($data['payfacReference']) ? sanitize_text_field($data['payfacReference']) : '';
		$reason           = isset($data['reason']) ? sanitize_text_field($data['reason']) : esc_html__('Transaction failed', 'straumur-payments-for-woocommerce');
		$additional_data  = isset($data['additionalData']) && is_array($data['additionalData']) ? $data['additionalData'] : array();
		$event_type       = isset($additional_data['eventType']) ? sanitize_text_field(strtolower($additional_data['eventType'])) : 'unknown';

		// Create failure note based on reason
		if (0 === strcasecmp('Refused', $reason)) {
			$note = esc_html__('Payment declined: The card was refused (declined).', 'straumur-payments-for-woocommerce');
		} elseif (0 === strcasecmp('Expired Card', $reason)) {
			$note = esc_html__('Payment failed: The card has expired.', 'straumur-payments-for-woocommerce');
		} elseif (0 === strcasecmp('3D Not Authenticated', $reason)) {
			$note = esc_html__('Payment failed: 3D Secure verification failed.', 'straumur-payments-for-woocommerce');
		} else {
			/* translators: 1: event type, 2: reason text, 3: payfac reference */
			$note = sprintf(
				/* translators: 1: event type, 2: reason text, 3: payfac reference */
				esc_html__('Straumur %1$s failed: %2$s. Reference: %3$s', 'straumur-payments-for-woocommerce'),
				esc_html(ucfirst($event_type)),
				esc_html($reason),
				esc_html($payfac_reference)
			);
		}

		// Store fail data as order meta for future reference
		$order->update_meta_data(
			'_straumur_last_failure',
			wp_json_encode(
				array(
					'timestamp'        => current_time('mysql'),
					'reason'           => $reason,
					'payfac_reference' => $payfac_reference,
					'event_type'       => $event_type,
				)
			)
		);

		// Add the failure note to the order
		$order->add_order_note($note);
		$order->save();

		self::log_message(sprintf('Handled failed webhook for order %d: %s', $order_id, $note));

		return true;
	}

	/**
	 * Process a successful (or partially successful) Straumur webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Decoded JSON payload.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function process_webhook_data(array $data)
	{
		// Validate the merchant reference (order ID)
		$order_id = isset($data['merchantReference']) ? absint($data['merchantReference']) : 0;
		if ($order_id <= 0) {
			self::log_message(esc_html__('No merchantReference or invalid.', 'straumur-payments-for-woocommerce'));
			return new WP_Error(
				'straumur_invalid_order',
				esc_html__('Invalid or missing order ID.', 'straumur-payments-for-woocommerce'),
				array('status' => 400)
			);
		}

		// Get the order
		$order = wc_get_order($order_id);
		if (! $order) {
			self::log_message(
				sprintf(
					/* translators: %d: order ID */
					esc_html__('No order found for merchantReference: %d', 'straumur-payments-for-woocommerce'),
					$order_id
				)
			);
			return new WP_Error(
				'straumur_order_not_found',
				esc_html__('Order not found.', 'straumur-payments-for-woocommerce'),
				array('status' => 404)
			);
		}

		// Get payment details
		$additional_data  = isset($data['additionalData']) && is_array($data['additionalData']) ? $data['additionalData'] : array();
		$event_type       = isset($additional_data['eventType']) ? sanitize_text_field(strtolower($additional_data['eventType'])) : 'unknown';
		$payfac_reference = isset($data['payfacReference']) ? sanitize_text_field($data['payfacReference']) : '';
		$original_payfac  = isset($additional_data['originalPayfacReference']) ? sanitize_text_field($additional_data['originalPayfacReference']) : '';
		$raw_amount       = isset($data['amount']) ? absint($data['amount']) : 0;

		// Create a unique event identifier using SHA-256 hash of key components
		$event_data = array(
			'payfacReference'         => $payfac_reference,
			'eventType'               => $event_type,
			'originalPayfacReference' => $original_payfac,
			'amount'                  => $raw_amount,
			'timestamp'               => isset($data['timestamp']) ? sanitize_text_field($data['timestamp']) : '',
		);
		$event_key  = hash('sha256', wp_json_encode($event_data));

		// Check if this event was already processed
		if (self::is_already_processed($order, $event_key)) {
			self::log_message(sprintf('Duplicate webhook event key "%s" - skipping', $event_key));
			return true; // Successfully ignored duplicate
		}

		// If it's a tokenization event, handle the payment token save
		if (self::EVENT_TOKENIZATION === $event_type && ! empty($additional_data['token'])) {
			$token_result = self::save_token_data($order, $data);
			if (is_wp_error($token_result)) {
				return $token_result;
			}
		}

		// Mark as processed and store webhook data
		self::mark_as_processed($order, $event_key);

		// Store a sanitized version of the webhook data
		$sanitized_data = self::sanitize_webhook_data($data);
		$order->update_meta_data('_straumur_last_webhook', wp_json_encode($sanitized_data));

		// Format amount for display
		$currency       = isset($data['currency']) ? sanitize_text_field($data['currency']) : '';
		$display_amount = self::format_amount($raw_amount, $currency);

		// Process based on event type
		switch ($event_type) {
			case self::EVENT_AUTHORIZATION:
				return self::handle_authorization_event($order, $data, $display_amount, $payfac_reference);

			case self::EVENT_REFUND:
				return self::handle_refund_event($order, $data, $display_amount, $payfac_reference);

			case self::EVENT_CAPTURE:
				return self::handle_capture_event($order, $display_amount, $payfac_reference);

			case self::EVENT_TOKENIZATION:
				return self::handle_tokenization_event($order, $data);

			default:
				$order->add_order_note(
					sprintf(
						/* translators: %s: event type */
						esc_html__('Unknown Straumur event type received: %s.', 'straumur-payments-for-woocommerce'),
						esc_html($event_type)
					)
				);
				$order->save();

				self::log_message(
					sprintf(
						'Unknown event type "%s" for order %d',
						$event_type,
						$order->get_id()
					)
				);
				return true;
		}
	}

	/**
	 * Handle an authorization event from Straumur.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order          The order object.
	 * @param array     $data           Webhook data.
	 * @param string    $display_amount Formatted amount for display.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function handle_authorization_event(
		\WC_Order $order,
		array    $data,
		string   $display_amount,
		string   $payfac_reference
	): bool {
		// 1. Extract additional data
		$additional_data = is_array($data['additionalData'] ?? null)
			? $data['additionalData'] : [];

		$card_number  = sanitize_text_field($additional_data['cardNumber'] ?? '');
		$auth_code    = sanitize_text_field($additional_data['authCode'] ?? '');
		$three_d_auth = sanitize_text_field($additional_data['threeDAuthenticated'] ?? 'false');
		$three_d_text = 'true' === $three_d_auth
			? esc_html__('verified by 3D Secure', 'straumur-payments-for-woocommerce')
			: esc_html__('not verified by 3-D Secure', 'straumur-payments-for-woocommerce');

		// 2. Persist the Straumur reference for later capture (no save here)
		$order->update_meta_data('_straumur_payfac_reference', $payfac_reference);

		// 3. Branch to auto vs manual capture (these both call save())
		$manual_capture = 'yes' === $order->get_meta('_straumur_is_manual_capture');

		if (! $manual_capture) {
			return self::handle_authorization_auto_capture(
				$order,
				$display_amount,
				$card_number,
				$three_d_text,
				$auth_code,
				$payfac_reference
			);
		}

		return self::handle_authorization_manual_capture(
			$order,
			$display_amount,
			$card_number,
			$three_d_text,
			$auth_code,
			$payfac_reference
		);
	}


	/**
	 * Handle an AUTHORISATION event when Straumur will auto-capture.
	 * We log the auth and keep the order unpaid (on-hold) until the CAPTURE
	 * webhook arrives; that second webhook will call maybe_mark_order_paid().
	 *
	 * @since 1.2.0
	 *
	 * @param \WC_Order $order            The WooCommerce order.
	 * @param string    $display_amount   Human-readable authorised amount.
	 * @param string    $card_number      Masked card number (e.g. •••• 1234).
	 * @param string    $three_d_text     “verified by 3-D Secure” / “not verified…”.
	 * @param string    $auth_code        Authorisation code from Straumur.
	 * @param string    $payfac_reference Straumur/PSP transaction-id (optional).
	 *
	 * @return bool Always true – any WP_Error bubbles up earlier.
	 */
	private static function handle_authorization_auto_capture(
		\WC_Order $order,
		string   $display_amount,
		string   $card_number,
		string   $three_d_text,
		string   $auth_code,
		string   $payfac_reference = ''
	): bool {

		// Mark order as paid/completed
		$order->payment_complete($payfac_reference);

		// Add an informative note to the order
		$note = sprintf(
			/* translators: 1: amount, 2: masked card, 3: 3-D Secure text, 4: auth code */
			esc_html__(
				'%1$s was authorised to card %2$s, %3$s. Auth code: %4$s.Payment has been captured',
				'straumur-payments-for-woocommerce'
			),
			esc_html($display_amount),
			esc_html($card_number),
			esc_html($three_d_text),
			esc_html($auth_code)
		);

		$order->add_order_note($note);
		$order->save();

		return true;
	}

	/**
	 * Handle an authorization event set for manual capture.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order          The order object.
	 * @param string    $display_amount Formatted authorized amount.
	 * @param string    $card_number    Masked card number.
	 * @param string    $three_d_text   Text describing 3D Secure state.
	 * @param string    $auth_code      Authorization code.
	 * @return true True on success.
	 */
	private static function handle_authorization_manual_capture(
		$order,
		string $display_amount,
		string $card_number,
		string $three_d_text,
		string $auth_code
	): bool {
		$note = sprintf(
			/* translators: 1: authorized amount, 2: masked card number, 3: 3D Secure text, 4: auth code */
			esc_html__('%1$s was authorized to card %2$s, %3$s. Auth code: %4$s. Awaiting manual capture.', 'straumur-payments-for-woocommerce'),
			esc_html($display_amount),
			esc_html($card_number),
			esc_html($three_d_text),
			esc_html($auth_code)
		);

		$order->update_status('on-hold', $note);
		$order->save();

		return true;
	}

	/**
	 * Handle a refund event from Straumur.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order            The order object.
	 * @param array     $data             Webhook data.
	 * @param string    $display_amount   Formatted amount.
	 * @param string    $payfac_reference Payfac reference ID.
	 * @return true True on success.
	 */
	private static function handle_refund_event($order, array $data, string $display_amount, string $payfac_reference): bool
	{
		if ('yes' === $order->get_meta('_straumur_refund_requested')) {
			return self::handle_refund($order, $display_amount, $payfac_reference);
		} elseif ('yes' === $order->get_meta('_straumur_cancel_requested')) {
			return self::handle_cancellation($order, $payfac_reference);
		} else {
			/* translators: %s: formatted refund or cancellation amount */
			$order->add_order_note(
				sprintf(
					/* translators: %s: formatted refund or cancellation amount */
					esc_html__('Straumur refund/cancellation %s (unknown type)', 'straumur-payments-for-woocommerce'),
					esc_html($display_amount)
				)
			);
			$order->save();

			return true;
		}
	}

	/**
	 * Handle a confirmed refund event from Straumur.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order            The order object.
	 * @param string    $display_amount   Formatted refunded amount.
	 * @param string    $payfac_reference Payfac reference.
	 * @return true True on success.
	 */
	private static function handle_refund($order, string $display_amount, string $payfac_reference): bool
	{
		$note = sprintf(
			/* translators: 1: refunded amount, 2: payfac reference ID */
			esc_html__('A refund amount of %1$s has been processed by Straumur. Reference: %2$s.', 'straumur-payments-for-woocommerce'),
			esc_html($display_amount),
			esc_html($payfac_reference)
		);

		$order->add_order_note($note);
		$order->delete_meta_data('_straumur_refund_requested');
		$order->save();

		self::log_message(
			sprintf(
				'Refund processed for order %d: %s',
				$order->get_id(),
				$display_amount
			)
		);

		return true;
	}

	/**
	 * Handle a confirmed cancellation event from Straumur.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order            The order object.
	 * @param string    $payfac_reference Payfac reference.
	 * @return true True on success.
	 */
	private static function handle_cancellation($order, string $payfac_reference): bool
	{
		$note = sprintf(
			/* translators: %s: payfac reference */
			esc_html__('Cancellation confirmed by Straumur. Reference: %s.', 'straumur-payments-for-woocommerce'),
			esc_html($payfac_reference)
		);

		$order->add_order_note($note);
		$order->delete_meta_data('_straumur_cancel_requested');
		$order->save();

		self::log_message(
			sprintf(
				'Cancellation processed for order %d',
				$order->get_id()
			)
		);

		return true;
	}

	/**
	 * Handle a capture event from Straumur (after manual capture).
	 *
	 * @param \WC_Order $order            The WooCommerce order object.
	 * @param string    $display_amount   Display-friendly capture amount.
	 * @param string    $payfac_reference Transaction/PSP reference from Straumur.
	 * @return bool Always true on success.
	 */
	private static function handle_capture_event($order, string $display_amount, string $payfac_reference): bool
	{
		// If the merchant requested a capture earlier, finalize the order now.
		if ('yes' === $order->get_meta('_straumur_capture_requested')) {
			// Instead of $order->payment_complete(), call the helper.
			self::maybe_mark_order_as_paid($order, $payfac_reference);

			// Clear the capture flag to avoid re-running on duplicate webhooks.
			$order->delete_meta_data('_straumur_capture_requested');
		}

		// Add a note about the capture completion.
		$note = sprintf(
			esc_html__(
				'Manual capture completed for %1$s via Straumur (reference: %2$s).',
				'straumur-payments-for-woocommerce'
			),
			esc_html($display_amount),
			esc_html($payfac_reference)
		);
		$order->add_order_note($note);
		$order->save();

		return true;
	}

	/**
	 * Handle a tokenization event from Straumur.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The order object.
	 * @param array     $data  Webhook data.
	 * @return true True on success.
	 */
	private static function handle_tokenization_event($order, array $data): bool
	{
		$additional_data = isset($data['additionalData']) && is_array($data['additionalData'])
			? $data['additionalData'] : array();

		$card_summary = isset($additional_data['cardSummary'])
			? sanitize_text_field($additional_data['cardSummary']) : '';

		$auth_code = isset($additional_data['authCode'])
			? sanitize_text_field($additional_data['authCode']) : '';

		$three_d_auth = isset($additional_data['threeDAuthenticated'])
			? sanitize_text_field($additional_data['threeDAuthenticated']) : 'false';

		$three_d_text = ('true' === $three_d_auth)
			? esc_html__('verified by 3D Secure', 'straumur-payments-for-woocommerce')
			: esc_html__('not verified by 3D Secure', 'straumur-payments-for-woocommerce');

		$note = sprintf(
			/* translators: 1: last four digits of the card, 2: 3D Secure status text, 3: authorization code */
			esc_html__('Card ending in %1$s has been saved for automatic subscription payments, %2$s (Auth code: %3$s).', 'straumur-payments-for-woocommerce'),
			esc_html($card_summary),
			esc_html($three_d_text),
			esc_html($auth_code)
		);

		$order->add_order_note($note);
		$order->save();

		return true;
	}

	/**
	 * Save the token data from a tokenization event.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The completed order object.
	 * @param array     $data  Decoded JSON payload.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private static function save_token_data($order, array $data)
	{
		$additional_data = isset($data['additionalData']) && is_array($data['additionalData'])
			? $data['additionalData'] : array();

		if (empty($additional_data['token'])) {
			return new WP_Error(
				'straumur_missing_token',
				esc_html__('Token value is missing in tokenization event.', 'straumur-payments-for-woocommerce'),
				array('status' => 400)
			);
		}

		$token_value = sanitize_text_field($additional_data['token']);
		$user_id     = $order->get_user_id();

		if (! $user_id) {
			self::log_message(
				sprintf(
					'Cannot save token for order %d: No user associated with order',
					$order->get_id()
				)
			);
			return true; // Not an error, just skip token saving
		}

		try {
			if (class_exists('\WC_Payment_Token_CC')) {
				$token = new \WC_Payment_Token_CC();

				$card_type = isset($additional_data['paymentMethod'])
					? sanitize_text_field($additional_data['paymentMethod']) : '';
				$token->set_card_type($card_type);

				$last4 = isset($additional_data['cardSummary'])
					? sanitize_text_field($additional_data['cardSummary']) : '';
				$token->set_last4($last4);

				// Attempt to parse the card expiry from the "reason" field if present
				$expiry_month = '00';
				$expiry_year  = '00';

				if (! empty($data['reason'])) {
					$parts = explode(':', $data['reason']);
					if (count($parts) >= 3) {
						$expiry_parts = explode('/', $parts[2]);
						if (2 === count($expiry_parts)) {
							$expiry_month = sanitize_text_field(trim($expiry_parts[0]));
							$expiry_year  = sanitize_text_field(trim($expiry_parts[1]));
						}
					}
				}

				$token->set_expiry_month($expiry_month);
				$token->set_expiry_year($expiry_year);
			} else {
				// Fallback if \WC_Payment_Token_CC is not available
				$token = new \WC_Payment_Token();
			}

			$token->set_gateway_id('straumur');
			$token->set_token($token_value);
			$token->set_user_id($user_id);
			$token->set_default(true);
			$token->update_meta_data('subscription_only', 'yes');
			$token->save();

			update_user_meta($user_id, '_straumur_payment_token', $token->get_id());

			// Log with partial token for security
			$masked_token = substr($token_value, 0, 4) . '...' . substr($token_value, -4);
			self::log_message(
				sprintf(
					'Saved token for user %d: %s (Exp: %s/%s)',
					$user_id,
					$masked_token,
					$token->get_expiry_month(),
					$token->get_expiry_year()
				)
			);

			return true;
		} catch (\Exception $e) {
			self::log_message(
				sprintf(
					'Error saving token for order %d: %s',
					$order->get_id(),
					$e->getMessage()
				)
			);

			return new WP_Error(
				'straumur_token_save_error',
				esc_html__('Error saving payment token.', 'straumur-payments-for-woocommerce'),
				array('status' => 500)
			);
		}
	}
	/**
	 * Safely mark an order as paid if it isn't already, and manually trigger
	 * the WooCommerce payment-complete hooks.
	 *
	 * @param \WC_Order   $order          The WooCommerce order object.
	 * @param string|null $transaction_id Transaction ID from Straumur (optional).
	 */
	private static function maybe_mark_order_as_paid(\WC_Order $order, ?string $transaction_id = null): void
	{
		// 1. Check if the order is already marked paid in WooCommerce.
		if ($order->is_paid()) {
			self::log_message(
				sprintf(
					'maybe_mark_order_as_paid: Order #%d is already paid; skipping.',
					$order->get_id()
				),
				'info'
			);
			return;
		}

		// 2. Ensure a paid date is set (if none).
		if (! $order->get_date_paid()) {
			self::log_message(
				sprintf(
					'maybe_mark_order_as_paid: Setting paid date for order #%d.',
					$order->get_id()
				),
				'info'
			);
			$order->set_date_paid(time()); // or gmdate('Y-m-d H:i:s')
		}

		// 3. Store the transaction ID if provided.
		if ($transaction_id) {
			self::log_message(
				sprintf(
					'maybe_mark_order_as_paid: Setting transaction ID "%s" on order #%d.',
					$transaction_id,
					$order->get_id()
				),
				'info'
			);
			$order->set_transaction_id($transaction_id);
		}

		// Persist changes to the order.
		$order->save();

		// For clarity, log just before firing the hooks.
		self::log_message(
			sprintf(
				'maybe_mark_order_as_paid: Firing woocommerce_payment_complete hooks for order #%d.',
				$order->get_id()
			),
			'info'
		);

		// These are the correct, standard hooks that WooCommerce fires after payment_complete().
		do_action('woocommerce_payment_complete', $order->get_id());
		do_action('woocommerce_payment_complete_order_status_' . $order->get_status(), $order->get_id());
		do_action('woocommerce_payment_complete_order_id', $order->get_id());
	}
	/**
	 * Check if an event key has already been processed for this order.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order     The order object.
	 * @param string    $event_key A unique string for this event.
	 * @return bool True if it was already processed, false otherwise.
	 */
	private static function is_already_processed($order, string $event_key): bool
	{
		$processed = $order->get_meta('_straumur_processed_webhooks', true);
		if (! is_array($processed)) {
			$processed = array();
		}

		return in_array($event_key, $processed, true);
	}

	/**
	 * Mark this webhook event as processed to avoid duplicates.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order     The order object.
	 * @param string    $event_key A unique string for this event.
	 * @return void
	 */
	private static function mark_as_processed($order, string $event_key): void
	{
		$processed = $order->get_meta('_straumur_processed_webhooks', true);
		if (! is_array($processed)) {
			$processed = array();
		}

		// Add the event key to the processed list
		$processed[] = $event_key;

		// Keep only the last 20 events to prevent meta data from growing too large
		if (count($processed) > 20) {
			$processed = array_slice($processed, -20);
		}

		$order->update_meta_data('_straumur_processed_webhooks', $processed);
	}

	/**
	 * Sanitize webhook data for storage, removing sensitive information.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The raw webhook data.
	 * @return array Sanitized data.
	 */
	private static function sanitize_webhook_data(array $data): array
	{
		$sanitized = array();

		// Safe fields to copy directly
		$safe_fields = array(
			'merchantReference',
			'payfacReference',
			'checkoutReference',
			'amount',
			'currency',
			'reason',
			'success',
			'timestamp',
		);

		foreach ($safe_fields as $field) {
			if (isset($data[$field])) {
				$sanitized[$field] = sanitize_text_field($data[$field]);
			}
		}

		if (isset($data['additionalData']) && is_array($data['additionalData'])) {
			$sanitized['additionalData'] = array();

			foreach ($data['additionalData'] as $key => $value) {
				if (in_array($key, array('token', 'cardNumber'), true)) {
					continue;
				}

				$sanitized['additionalData'][$key] = sanitize_text_field($value);
			}

			if (isset($data['additionalData']['cardNumber'])) {
				$card                                      = sanitize_text_field($data['additionalData']['cardNumber']);
				$sanitized['additionalData']['cardNumber'] = preg_replace('/\d(?=\d{4})/', '*', $card);
			}
		}

		return $sanitized;
	}





	/**
	 * Format a numeric amount (in minor units) with currency.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $raw_amount Minor units.
	 * @param string $currency   Currency code (e.g., "ISK", "USD").
	 * @return string Readable amount with currency code, or "N/A" if invalid.
	 */
	private static function format_amount(int $raw_amount, string $currency): string
	{
		if ($raw_amount <= 0) {
			return esc_html__('N/A', 'straumur-payments-for-woocommerce');
		}

		if ('ISK' === $currency) {
			// ISK typically has zero decimal places
			return number_format($raw_amount / 100, 0, ',', '.') . ' ISK';
		}

		// Default to two decimal places for other currencies
		return number_format($raw_amount / 100, 2, '.', '') . ' ' . $currency;
	}

	/**
	 * Validate the HMAC signature in the webhook payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data      The decoded JSON payload.
	 * @param string $signature The base64-encoded signature.
	 * @return bool True if valid, false otherwise.
	 */
	private static function validate_hmac_signature(array $data, string $signature): bool
	{
		$hmac_key = WC_Straumur_Settings::get_hmac_key();

		if (empty($hmac_key)) {
			self::log_message(esc_html__('No HMAC secret configured in settings.', 'straumur-payments-for-woocommerce'));
			return false;
		}

		// Validate the HMAC key is a valid hex string
		if (! ctype_xdigit($hmac_key)) {
			self::log_message(esc_html__('Invalid HMAC key format. Must be a hexadecimal string.', 'straumur-payments-for-woocommerce'));
			return false;
		}

		// Prepare values for HMAC calculation
		$values = array(
			$data['checkoutReference'] ?? '',
			$data['payfacReference'] ?? '',
			$data['merchantReference'] ?? '',
			$data['amount'] ?? '',
			$data['currency'] ?? '',
			$data['reason'] ?? '',
			$data['success'] ?? '',
		);

		$payload = implode(':', $values);

		// Convert hex key to binary safely
		try {
			$binary_key = hex2bin($hmac_key);
			if (false === $binary_key) {
				throw new \Exception('Invalid hex value');
			}
		} catch (\Exception $e) {
			self::log_message(esc_html__('Invalid HMAC key configured in settings.', 'straumur-payments-for-woocommerce'));
			return false;
		}

		// Calculate HMAC
		$computed_hash = hash_hmac('sha256', $payload, $binary_key, true);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Needed for API data encoding, not for obfuscation
		$computed_signature = base64_encode($computed_hash);

		// Compare signatures
		$result = hash_equals($computed_signature, $signature);

		if (! $result) {
			self::log_message(esc_html__('HMAC signature validation failed.', 'straumur-payments-for-woocommerce'));
		}

		return $result;
	}

	/**
	 * Log a message if conditions are met, using WooCommerce's logger.
	 *
	 * - Errors are always logged.
	 * - Warnings (and below) are only logged if WP_DEBUG is true.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Debug or error message to log.
	 * @param string $level   Log level (info, warning, error).
	 * @return void
	 */
	private static function log_message(string $message, string $level = 'info'): void
	{
		if (! function_exists('wc_get_logger')) {
			return;
		}

		$logger = wc_get_logger();

		// Always log errors. Warnings/info are only logged if WP_DEBUG is on.
		if (
			'error' === $level
			|| (defined('WP_DEBUG') && WP_DEBUG && in_array($level, array('warning', 'info'), true))
		) {
			if (! method_exists($logger, $level)) {
				$level = 'info';
			}
			$logger->{$level}($message, array('source' => 'straumur_webhook'));
		}
	}
}

// Initialize the handler.
WC_Straumur_Webhook_Handler::init();
```
