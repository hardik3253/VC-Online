<?php
/**
 * HFE_Default_Compat setup
 *
 * @package header-footer-elementor
 */

namespace HFE\Themes;

/**
 * Astra theme compatibility.
 */
class HFE_Default_Compat {

	/**
	 *  Initiator
	 */
	public function __construct() {
		add_action( 'wp', [ $this, 'hooks' ] );
	}

	/**
	 * Run all the Actions / Filters.
	 *
	 * @return void
	 *
	 * // phpcs:ignore
	 */
	// phpcs:ignore
	public function hooks(): void {
		if ( hfe_header_enabled() ) {
			// Replace header.php template.
			add_action( 'get_header', [ $this, 'override_header' ] );

			// Display HFE's header in the replaced header.
			add_action( 'hfe_header', 'hfe_render_header' );
		}

		if ( hfe_footer_enabled() || hfe_is_before_footer_enabled() ) {
			// Replace footer.php template.
			add_action( 'get_footer', [ $this, 'override_footer' ] );
		}

		if ( hfe_footer_enabled() ) {
			// Display HFE's footer in the replaced header.
			add_action( 'hfe_footer', 'hfe_render_footer' );
		}

		if ( hfe_is_before_footer_enabled() ) {
			add_action( 'hfe_footer_before', [ 'Header_Footer_Elementor', 'get_before_footer_content' ] );
		}
	}

	/**
	 * Function for overriding the header in the elmentor way.
	 *
	 * @since 1.2.0
	 *
	 * // phpcs:ignore
	 * @return void
	 */ 
	// phpcs:ignore
	public function override_header(): void {
		require HFE_DIR . 'themes/default/hfe-header.php';
		$templates   = [];
		$templates[] = 'header.php';
		// Avoid running wp_head hooks again.
		remove_all_actions( 'wp_head' );
		// wp_body_open already fired in hfe-header.php; don't fire it again for the discarded theme header.
		remove_all_actions( 'wp_body_open' );
		$this->load_discarded_template( $templates );
	}

	/**
	 * Function for overriding the footer in the elmentor way.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 *
	 * // phpcs:ignore
	 */ 
	// phpcs:ignore
	public function override_footer(): void {
		require HFE_DIR . 'themes/default/hfe-footer.php';
		$templates   = [];
		$templates[] = 'footer.php';
		// Avoid running wp_footer hooks again.
		remove_all_actions( 'wp_footer' );
		$this->load_discarded_template( $templates );
	}

	/**
	 * Load the theme's own template once and discard its output.
	 *
	 * The template is required once so WordPress core's follow-up
	 * locate_template() call in get_header()/get_footer() does not load it
	 * again. Every removable output buffer opened while it runs is closed,
	 * including ones a third-party callback leaves open, so the theme's
	 * markup does not leak into the page. A buffer that is not removable
	 * stops the loop before ob_end_clean() is called on it, so PHP does not
	 * raise a "failed to delete buffer" notice into the page.
	 *
	 * @since 2.9.8
	 *
	 * @param string[] $templates Template file names to locate.
	 * @return void
	 */
	private function load_discarded_template( array $templates ): void {
		$level = ob_get_level();
		ob_start();
		locate_template( $templates, true );
		while ( ob_get_level() > $level ) {
			$status = ob_get_status();
			if ( ! isset( $status['flags'] ) || ! ( $status['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE ) ) {
				break;
			}
			if ( ! ob_end_clean() ) {
				break;
			}
		}
	}
}

new HFE_Default_Compat();
