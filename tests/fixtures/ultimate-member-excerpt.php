<?php
/**
 * Ultimate Member stand-in for Ashna's integration tests.
 *
 * Source: https://github.com/ultimatemember/ultimatemember (master), GPL-2.0-or-later.
 * The method names and status values match includes/common/class-users.php
 * (get_status(), approve(), set_as_pending()) and um-short-functions.php (um_fetch_user(),
 * um_user(), um_user_profile_url()); storage is simplified: the status lives in the same
 * `account_status` user meta, role settings come from the test, e-mails are recorded.
 * The [ultimatemember] shortcode prints a marker form.
 */

// phpcs:ignoreFile

class Ashna_Fixture_UM_Users {
	public function get_status( $user_id, $format = 'raw' ) {
		return get_user_meta( $user_id, 'account_status', true );
	}
	public function set_status( $user_id, $status ) {
		return update_user_meta( $user_id, 'account_status', $status );
	}
	public function approve( $user_id, $force = false, $silent = false ) {
		if ( 'approved' === $this->get_status( $user_id ) || 'inactive' === $this->get_status( $user_id ) ) {
			return false;
		}
		$old = $this->get_status( $user_id );
		$this->set_status( $user_id, 'approved' );
		if ( ! $silent ) {
			$GLOBALS['ashna_fixture_um_mails'][] = 'awaiting_admin_review' === $old ? 'approved_email' : 'welcome_email';
		}
		return true;
	}
	public function set_as_pending( $user_id, $force = false ) {
		$this->set_status( $user_id, 'awaiting_admin_review' );
		$GLOBALS['ashna_fixture_um_mails'][] = 'pending_email';
		return true;
	}
}

class Ashna_Fixture_UM_Common {
	private $users;
	public function users() {
		if ( null === $this->users ) {
			$this->users = new Ashna_Fixture_UM_Users();
		}
		return $this->users;
	}
}

class Ashna_Fixture_UM {
	private $common;
	public function common() {
		if ( null === $this->common ) {
			$this->common = new Ashna_Fixture_UM_Common();
		}
		return $this->common;
	}
}

function UM() {
	static $um = null;
	if ( null === $um ) {
		$um = new Ashna_Fixture_UM();
	}
	return $um;
}

function um_fetch_user( $user_id ) {
	$GLOBALS['ashna_fixture_um_user'] = (int) $user_id;
}

/** Role settings of the fetched user (UM keeps them per role: status, after_login, login_redirect_url). */
function um_user( $data, $attrs = null ) {
	$user = get_user_by( 'id', isset( $GLOBALS['ashna_fixture_um_user'] ) ? $GLOBALS['ashna_fixture_um_user'] : 0 );
	if ( ! $user ) {
		return '';
	}
	foreach ( (array) $user->roles as $role ) {
		if ( isset( $GLOBALS['ashna_fixture_um_roles'][ $role ][ $data ] ) ) {
			return $GLOBALS['ashna_fixture_um_roles'][ $role ][ $data ];
		}
	}
	return '';
}

function um_user_profile_url( $user_id = null ) {
	$user = get_user_by( 'id', $user_id ? $user_id : $GLOBALS['ashna_fixture_um_user'] );
	return home_url( '/user/' . ( $user ? $user->user_nicename : '' ) . '/' );
}

if ( ! shortcode_exists( 'ultimatemember' ) ) {
	add_shortcode(
		'ultimatemember',
		function ( $atts ) {
			return '<form class="um-form">UM-FORM-' . ( isset( $atts['form_id'] ) ? (int) $atts['form_id'] : 0 ) . '</form>';
		}
	);
}
