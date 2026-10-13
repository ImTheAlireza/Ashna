<?php
/** Remove ONLY this diagnostic tool's reports, never Signa settings. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_metadata( 'user', 0, '_signa_activation_diagnostics_activate', '', true );
delete_metadata( 'user', 0, '_signa_activation_diagnostics_error_scrape', '', true );
