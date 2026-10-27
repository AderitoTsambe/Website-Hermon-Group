<?php
/**
 * Limited-time Sale Admin Notice for Sticky Header Effects for Elementor.
 *
 * Shows a dismissible banner to admins that do not have Pro active, promoting
 * the current 20% off Pro offer. The discount is applied automatically at
 * checkout, so the notice sends people to the pricing page without a code.
 *
 * @link  https://posimyth.com/
 * @since 2.2.3
 *
 * @package she-header
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'She_Sale_Notice' ) ) {

	/**
	 * Shows a dismissible 20% off Pro banner in the WP admin.
	 *
	 * @since 2.2.3
	 */
	class She_Sale_Notice {

		/**
		 * Singleton instance.
		 *
		 * @since 2.2.3
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * User-meta key used to track per-user dismissal.
		 *
		 * @since 2.2.3
		 * @var string
		 */
		public $db_key = 'she_sale_notice_dismissed';

		/**
		 * Pro plugin slug (folder/file).
		 *
		 * @since 2.2.3
		 * @var string
		 */
		public $pro_slug = 'sticky-header-effects-for-elementor-pro/sticky-header-effects-for-elementor-pro.php';

		/**
		 * Returns (and lazy-creates) the singleton.
		 *
		 * @since 2.2.3
		 * @return self
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor, registers hooks.
		 *
		 * @since 2.2.3
		 */
		public function __construct() {
			add_action( 'admin_notices', array( $this, 'render' ) );
			add_action( 'wp_ajax_she_dismiss_sale_notice', array( $this, 'dismiss' ) );
		}

		/**
		 * Outputs the admin banner markup and inline dismiss script.
		 *
		 * @since 2.2.3
		 */
		public function render() {

			// Hide for anyone who already has Pro. The sale is a Pro upsell, so it
			// must not show to Pro users, and not to someone who has Pro installed
			// but deactivated either, they already own it. Checking the file on
			// disk covers both cases, where is_plugin_active() would miss the
			// installed-but-inactive one.
			if ( file_exists( WP_PLUGIN_DIR . '/' . $this->pro_slug ) ) {
				return;
			}

			// Hide once the current user has dismissed it.
			if ( get_user_meta( get_current_user_id(), $this->db_key, true ) ) {
				return;
			}

			// Only show on the admin sections our other notices use (same page
			// gate as the Join Community notice): the dashboard, Elementor, the
			// Appearance and post-edit screens, the Plugins screen, and once the
			// user has been onboarded, the SHE dashboard itself.
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$allowed_parents = array( 'index', 'elementor', 'themes', 'edit', 'plugins' );

			if ( get_option( 'she_onboarding_setup' ) ) {
				$allowed_parents[] = 'she-header';
			}

			$parent_base = ! empty( $screen->parent_base ) && in_array( $screen->parent_base, $allowed_parents, true );

			if ( ! $parent_base ) {
				return;
			}

			$nonce       = wp_create_nonce( 'she_sale_notice_nonce' );
			$pricing_url = 'https://stickyheadereffects.com/?ref=plugin&utm_source=plugin&utm_medium=admin&utm_campaign=gopro&utm_content=sale-notice-pricing#pricing';
			$claim_url   = 'https://store.posimyth.com/checkout/?edd_action=add_to_cart&download_id=464447&edd_options[price_id]=1&discount=SHE20&utm_source=plugin&utm_medium=admin&utm_campaign=gopro&utm_content=sale-notice-claim';
			$logo_img    = SHE_HEADER_URL . 'assets/images/banner/she-notice-benner.png';
			$crown_img   = SHE_HEADER_URL . 'assets/svg/premium_icon.svg';

			?>
			<style>
				/* Make WP's native dismiss button visible against the dark background */
				#she-sale-notice.notice-is-dark .notice-dismiss::before {
					color: rgba(255, 255, 255, 0.55);
				}
				#she-sale-notice.notice-is-dark .notice-dismiss:hover::before {
					color: #ffffff;
				}
				#she-sale-notice .she-btn-primary {
					transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
				}
				#she-sale-notice .she-btn-primary:hover {
					background: #bf0069 !important;
					border-color: #bf0069 !important;
					color: #fff !important;
					box-shadow: 0 4px 12px rgba(230, 1, 126, 0.45);
				}
				#she-sale-notice .she-btn-secondary {
					transition: background-color 0.2s ease, border-color 0.2s ease;
				}
				#she-sale-notice .she-btn-secondary:hover {
					background: rgba(255, 255, 255, 0.12) !important;
					border-color: rgba(255, 255, 255, 0.75) !important;
					color: #fff !important;
				}
			</style>

			<div id="she-sale-notice"
				class="notice is-dismissible she-sale-notice notice-is-dark"
				style="background:linear-gradient(135deg,#190A12 0%,#2d0d20 60%,#3a0f29 100%);border-left-color:#E6017E;margin-left:0;border-radius:6px;">

				<div class="she-sale-notice-inner"
					style="display:flex;column-gap:12px;align-items:flex-start;padding:15px 10px;position:relative;margin-left:0;">

					<?php /* Left: product logo */ ?>
					<div style="display:flex;padding-top:14px;flex-shrink:0;">
						<img src="<?php echo esc_url( $logo_img ); ?>"
							alt="<?php esc_attr_e( 'Sticky Header Effects', 'she-header' ); ?>"
							style="max-width:44px;max-height:44px;border-radius:6px;" />
					</div>

					<?php /* Right: headline, description, button */ ?>
					<div style="margin:0 10px;color:#fff;">

						<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:10px 0 7px;">
							<h3 style="margin:0;color:#ffffff;font-size:1.25em;font-weight:700;line-height:1.3;">
								<?php esc_html_e( 'Get 20% Off Sticky Header Effects Pro', 'she-header' ); ?>
							</h3>
							<span style="display:inline-flex;align-items:center;gap:5px;background:#E6017E;color:#fff;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:3px 9px;border-radius:20px;line-height:1.6;">
								<img src="<?php echo esc_url( $crown_img ); ?>" alt="" width="14" height="14" style="display:block;" />
								<?php esc_html_e( '20% Off', 'she-header' ); ?>
							</span>
						</div>

						<p style="margin:0;color:rgba(255,255,255,0.72);font-size:13px;line-height:1.5;">
							<?php esc_html_e( 'Unlock 12 advanced scroll effects and 50+ ready-made headers on top of the free plugin. Save 20% on any Pro plan.', 'she-header' ); ?>
						</p>

						<div class="she-sale-notice-buttons" style="display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap;">

							<?php /* Primary: straight to checkout with the discount pre-applied */ ?>
							<a href="<?php echo esc_url( $claim_url ); ?>"
								target="_blank" rel="noopener noreferrer"
								class="button she-btn-primary"
								style="background:#E6017E;border-color:#E6017E;color:#fff;text-decoration:none;white-space:nowrap;">
								<?php esc_html_e( 'Claim 20% Off', 'she-header' ); ?> &rarr;
							</a>

							<?php /* Secondary: the pricing page, to compare Pro and Agency */ ?>
							<a href="<?php echo esc_url( $pricing_url ); ?>"
								target="_blank" rel="noopener noreferrer"
								class="button she-btn-secondary"
								style="background:transparent;border-color:rgba(255,255,255,0.45);color:#fff;text-decoration:none;white-space:nowrap;">
								<?php esc_html_e( 'View Pricing', 'she-header' ); ?>
							</a>

						</div>

					</div>

				</div>

			</div>

			<script>
				jQuery( document ).ready( function( $ ) {
					$( '#she-sale-notice' ).on( 'click', '.notice-dismiss', function() {
						$.ajax( {
							type: 'POST',
							url:  ajaxurl,
							data: {
								action: 'she_dismiss_sale_notice',
								nonce:  '<?php echo esc_js( $nonce ); ?>',
							},
						} );
					} );
				} );
			</script>
			<?php
		}

		/**
		 * AJAX handler, saves dismissal to user meta so the notice stays hidden.
		 *
		 * @since 2.2.3
		 */
		public function dismiss() {

			if ( ! check_ajax_referer( 'she_sale_notice_nonce', 'nonce', false ) ) {
				wp_send_json( array(
					'status'  => false,
					'message' => esc_html__( 'Security check failed.', 'she-header' ),
				) );
				wp_die();
			}

			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				wp_send_json( array(
					'status'  => false,
					'message' => esc_html__( 'Insufficient permissions.', 'she-header' ),
				) );
				wp_die();
			}

			update_user_meta( get_current_user_id(), $this->db_key, 1 );

			wp_send_json( array(
				'status'  => true,
				'message' => esc_html__( 'Notice dismissed.', 'she-header' ),
			) );
		}
	}

	She_Sale_Notice::instance();
}
