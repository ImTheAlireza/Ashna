// TEST ONLY — adversarial end-to-end run against a live WordPress + Ashna (real HTTP + Chromium).
//
// The CLI suite (tests/attacks.php) calls Ashna's handlers in-process. This one starts a real
// web server and attacks the plugin over HTTP the way an outsider can: hostile URLs, forged
// requests, cross-site form posts, cookie inspection, header games, brute force.
//
// usage: node tests/browser/qa-attack.mjs http://localhost:9400
import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

const DEPS = process.env.ASHNA_BROWSER_DEPS || path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../../.cache/browser' );
const require = createRequire( path.join( DEPS, 'package.json' ) );
const { chromium: playwright } = require( 'playwright-core' );
const cm = require( '@sparticuz/chromium' );
const chromium = cm.default || cm;
const libs = await cm.inflate( path.resolve( path.dirname( require.resolve( '@sparticuz/chromium' ) ), '../bin/al2023.tar.br' ) );
cm.setupLambdaEnvironment( path.join( libs, 'lib' ) );

const BASE = ( process.env.ASHNA_QA_BASE || process.argv[ 2 ] || 'http://localhost:9400' ).replace( /\/$/, '' );
const ORIGIN = new URL( BASE ).origin;
const HOST = new URL( BASE ).host;

let pass = 0;
const failures = [];
const notes = [];

function check( label, ok, detail = '' ) {
	if ( ok ) {
		pass++;
		console.log( '  ok   ' + label );
	} else {
		failures.push( label + ( detail ? ' — ' + detail : '' ) );
		console.log( '  FAIL ' + label + ( detail ? ' — ' + detail : '' ) );
	}
}

function section( name ) {
	console.log( '\n== ' + name );
}

/** A raw HTTP request with full control over headers/method. */
async function raw( path, { method = 'GET', headers = {}, body = null, redirect = 'manual' } = {} ) {
	const res = await fetch( ORIGIN + path, { method, headers, body, redirect } );
	const text = await res.text();
	return { status: res.status, text, headers: res.headers, res };
}

const cookieValue = ( input, name ) => {
	const res = input && input.headers ? input : input && input.res;
	if ( ! res || ! res.headers ) {
		return [];
	}
	const raw = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
	return raw.filter( ( c ) => c.startsWith( name + '=' ) );
};

/** The sandbox code the form shows in its own alert box. */
const codeIn = async ( form ) => ( ( await form.locator( '.ashna-otp-alert' ).innerText().catch( () => '' ) ).match( /(?<![#\d])(\d{5})(?!\d)/ ) || [] )[ 1 ];

const fa = ( s ) => s.replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );

/**
 * Test-only reset endpoint of the browser fixture (tests/browser/ashna-browser-qa.php, localhost
 * guarded): clears lockouts, rate-limit windows and attack mode so every phase starts clean.
 */
const resetState = async () => {
	await fetch( ORIGIN + '/?qa_reset=1' ).then( ( r ) => r.text() ).catch( () => 'unreachable' );
};

/** The live server's PHP error log, so the campaign is judged with WP_DEBUG on. */
const LOG_FILE = process.env.ASHNA_QA_LOG || path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../../.cache/wp-site/wp-content/debug.log' );
const logSize = () => {
	try {
		return fs.statSync( LOG_FILE ).size;
	} catch {
		return -1;
	}
};
const logSince = ( offset ) => {
	try {
		const fd = fs.openSync( LOG_FILE, 'r' );
		const buf = Buffer.alloc( Math.max( 0, fs.statSync( LOG_FILE ).size - offset ) );
		fs.readSync( fd, buf, 0, buf.length, offset );
		fs.closeSync( fd );
		return buf.toString( 'utf8' );
	} catch {
		return '';
	}
};

const run = async () => {
	// A previous attack run leaves lockouts and rate-limit windows behind; start from zero.
	await resetState();
	const logStart = logSize();

	// ---------------------------------------------------------------- surface
	section( 'Recon: what the site answers to an outsider' );
	const home = await raw( '/' );
	check( 'the site answers the front page (200 or a redirect to it)', [ 200, 301, 302 ].includes( home.status ), String( home.status ) );
	check( 'no PHP notice/warning in the home page', ! /(Notice|Warning|Fatal error|Deprecated):/.test( home.text ), ( home.text.match( /(Notice|Warning|Fatal error|Deprecated):[^<]{0,80}/ ) || [] )[ 0 ] );

	// admin-ajax with no action at all
	const noAction = await raw( '/wp-admin/admin-ajax.php', { method: 'POST', body: '' } );
	check( 'admin-ajax without an action answers the WordPress default (no plugin leak)', '0' === noAction.text.trim() || 400 === noAction.status, noAction.status + ' ' + noAction.text.slice( 0, 60 ) );

	// a nonce-less public call
	const noNonce = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
		method: 'POST',
		headers: { 'content-type': 'application/x-www-form-urlencoded' },
		body: 'action=ashna_request_otp&identifier=09120000000',
	} );
	const noNonceJson = await noNonce.json().catch( () => null );
	check( 'request_otp without a nonce is refused with 403', 403 === noNonce.status && noNonceJson && noNonceJson.success === false, noNonce.status + ' ' + JSON.stringify( noNonceJson ).slice( 0, 120 ) );
	check( 'the refusal is JSON, not an HTML error page', noNonce.headers.get( 'content-type' )?.includes( 'application/json' ), noNonce.headers.get( 'content-type' ) );

	// ------------------------------------------------------- hostile GET URLs
	section( 'Hostile URLs on public pages (no 500 on array/scalar confusion)' );
	const hostile = [
		'/wp-login.php?action[]=1',
		'/wp-login.php?redirect_to[]=1',
		'/wp-login.php?action=1&redirect_to[]=2',
		'/wp-login.php?interim-login[]=1',
		'/wp-login.php?redirect_to[]=1&action[]=login',
		'/?redirect_to[]=1',
		'/?ashna_action[]=login',
		'/wp-login.php?action=%E0%A4%A',
		'/wp-login.php?redirect_to=javascript:alert(1)',
		'/wp-login.php?redirect_to=//evil.example/',
		'/wp-login.php?redirect_to=https://evil.example/',
		'/wp-admin/admin-ajax.php?_=1&action=ashna_refresh_session',
	];
	for ( const url of hostile ) {
		const r = await raw( url );
		const broken = r.status >= 500 || /(Fatal error|There has been a critical error)/.test( r.text );
		check( 'GET ' + url + ' → ' + r.status, ! broken, r.text.match( /critical error[^<]{0,40}/ )?.[ 0 ] || '' );
	}

	// Ashna's own standalone page (only when the login URL points at it)
	const loginUrl = await raw( '/wp-login.php', { redirect: 'manual' } );
	notes.push( 'wp-login.php → HTTP ' + loginUrl.status + ( loginUrl.headers.get( 'location' ) ? ' → ' + loginUrl.headers.get( 'location' ) : '' ) );

	// ------------------------------------------------------------ enumeration
	section( 'User enumeration over HTTP' );
	const body = ( v ) => 'action=ashna_request_otp&identifier=' + encodeURIComponent( v ) + '&nonce=' + nonce;
	let nonce = '';
	{
		const r = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'content-type': 'application/x-www-form-urlencoded' },
			body: 'action=ashna_refresh_session',
		} );
		nonce = ( await r.json() ).data.nonce;
		check( 'a nonce can be picked up from the public refresh endpoint', 10 === nonce.length, nonce );
	}
	const ask = async ( identifier ) => {
		const r = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'content-type': 'application/x-www-form-urlencoded' },
			body: body( identifier ),
		} );
		return { status: r.status, json: await r.json().catch( () => null ) };
	};
	const unknownPhone = await ask( '09120000001' );
	check( 'a code request for an unknown number answers with the same shape', 200 === unknownPhone.status && unknownPhone.json?.success, JSON.stringify( unknownPhone.json ).slice( 0, 140 ) );
	check( 'the answer never contains a code or a hash', unknownPhone.json && ! /otp_code|otp_hash|user_pass|"code":"\d/.test( JSON.stringify( unknownPhone.json ) ), JSON.stringify( unknownPhone.json ).slice( 0, 140 ) );
	check( 'the answer masks the identifier', /09\d*\*\*\*\d+/.test( JSON.stringify( unknownPhone.json ) ), JSON.stringify( unknownPhone.json ).slice( 0, 140 ) );

	const badLogin = async ( identifier, password ) => {
		const r = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'content-type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( { action: 'ashna_password_login', nonce, identifier, password } ).toString(),
		} );
		return { status: r.status, json: await r.json().catch( () => null ), res: r };
	};
	const a = await badLogin( 'admin', 'not-the-password' );
	const b = await badLogin( 'nobody-' + Date.now(), 'not-the-password' );
	check( 'wrong password and unknown account answer identically', JSON.stringify( a.json?.data?.message ) === JSON.stringify( b.json?.data?.message ), JSON.stringify( [ a.json?.data?.message, b.json?.data?.message ] ) );
	check( 'no auth cookie is issued for a failed password attempt', 0 === cookieValue( a.res, 'wordpress_logged_in_' ).length );

	// --------------------------------------------------------------- CSRF
	section( 'Cross-site request forgery' );
	const stateChanging = [
		[ 'ashna_request_otp', 'identifier=09120000002' ],
		[ 'ashna_verify_otp', 'identifier=09120000002&code=12345' ],
		[ 'ashna_password_login', 'identifier=admin&password=x' ],
		[ 'ashna_admin_save_settings', 'ashna%5Bform_title%5D=CSRF' ],
		[ 'ashna_admin_reset_settings', '' ],
		[ 'ashna_admin_import_settings', 'json_data=%7B%7D' ],
		[ 'ashna_admin_unlock_target', 'target=admin' ],
		[ 'ashna_passkey_delete', 'credential_id=x' ],
		[ 'ashna_passkey_register_options', '' ],
	];
	for ( const [ action, payload ] of stateChanging ) {
		const r = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				origin: 'https://evil.example',
				referer: 'https://evil.example/attack.html',
			},
			body: 'action=' + action + ( payload ? '&' + payload : '' ),
		} );
		const text = await r.text();
		const json = ( () => {
			try {
				return JSON.parse( text );
			} catch {
				return null;
			}
		} )();
		// Either Ashna's own 403 JSON, or WordPress's bare "0"/400 for an action that needs a session.
		const refused = ( json && json.success === false ) || ( r.status >= 400 && text.trim().length <= 2 );
		check( 'cross-site POST ' + action + ' refused (no nonce)', refused, r.status + ' ' + text.slice( 0, 80 ) );
		check( 'cross-site POST ' + action + ' sets no cookie', 0 === cookieValue( r, 'wordpress_logged_in_' ).length );
	}

	// ------------------------------------------------------------ brute force
	section( 'Brute force over HTTP' );
	await resetState();
	let accepted = 0;
	let lockedAt = 0;
	for ( let i = 0; i < 12; i++ ) {
		const r = await badLogin( 'admin', 'guess-' + i );
		if ( r.json?.success ) {
			accepted++;
		}
		if ( ! lockedAt && /قفل|مسدود|locked/i.test( JSON.stringify( r.json ) ) ) {
			lockedAt = i + 1;
		}
	}
	check( 'no guessed password ever signs in', 0 === accepted, String( accepted ) );
	check( 'the attempts end in a lockout message', lockedAt > 0, 'lockedAt=' + lockedAt );

	const flood = await Promise.all(
		Array.from( { length: 15 }, ( _, i ) =>
			fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: { 'content-type': 'application/x-www-form-urlencoded', 'x-forwarded-for': '198.51.100.' + i, 'x-real-ip': '203.0.113.' + i },
				body: 'action=ashna_request_otp&nonce=' + nonce + '&identifier=' + ( 9100000000 + i ),
			} )
		)
	);
	check( 'a parallel flood is answered (no 500) even with spoofed proxy headers', flood.every( ( r ) => r.status < 500 ), flood.map( ( r ) => r.status ).join( ',' ) );

	// The flood above locks the shared IP out for real; wipe the slate before the browser phase.
	await resetState();

	// -------------------------------------------------------------- headers
	section( 'Headers, host and cache games' );
	const hostHeader = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
		method: 'POST',
		headers: { 'content-type': 'application/x-www-form-urlencoded', host: 'evil.example' },
		body: 'action=ashna_refresh_session',
	} );
	const hostJson = await hostHeader.json().catch( () => null );
	check( 'a forged Host header does not change the nonce/redirect answer', ! JSON.stringify( hostJson ).includes( 'evil.example' ), JSON.stringify( hostJson ).slice( 0, 120 ) );

	const cache = await raw( '/wp-admin/admin-ajax.php?action=ashna_refresh_session' );
	check( 'the AJAX answer is marked no-cache', /no-store|no-cache/i.test( ( cache.headers.get( 'cache-control' ) || '' ) + ( cache.headers.get( 'expires' ) || '' ) ), cache.headers.get( 'cache-control' ) );
	check( 'the AJAX answer is JSON', cache.headers.get( 'content-type' )?.includes( 'application/json' ), cache.headers.get( 'content-type' ) );

	// --------------------------------------------------------------- XSS
	section( 'Reflected XSS through URLs and the login page' );
	const xssPayloads = [
		'<script>alert(1)</script>',
		'"onmouseover="alert(1)',
		'</title><img src=x onerror=alert(1)>',
		"'><svg/onload=alert(1)>",
	];
	for ( const p of xssPayloads ) {
		const r = await raw( '/?redirect_to=' + encodeURIComponent( p ) );
		check( 'reflected payload stays escaped in the page body (' + p.slice( 0, 18 ) + ')', ! r.text.includes( '<script>alert(1)' ) && ! /<img src=x onerror=alert\(1\)>/.test( r.text ) && ! /<svg\/onload/.test( r.text ) );
	}
	const loginXss = await raw( '/wp-login.php?redirect_to=' + encodeURIComponent( '"><script>alert(1)</script>' ) );
	check( 'wp-login does not echo the payload as markup', ! loginXss.text.includes( '<script>alert(1)</script>' ) );

	// ----------------------------------------- files, endpoints, direct access
	section( 'Plugin files and endpoints reached directly' );
	const exposed = [
		'index.php',
		'ashna.php',
		'uninstall.php',
		'readme.txt',
		'includes/core/class-ashna-helper.php',
		'includes/core/class-ashna-security.php',
		'includes/services/class-ashna-auth.php',
		'templates/login-form.php',
	];
	for ( const f of exposed ) {
		const r = await raw( '/wp-content/plugins/ashna-otp/' + f );
		// PHP source, or our own symbols/table names rendered as text, would be a leak.
		const leak = /<\?php|<\?=|Ashna_[A-Z][A-Za-z_]+|\$wpdb|api[_-]?key\s*=>/i.test( r.text );
		check( 'direct hit on ' + f + ' does not leak source or internals', ! leak, r.status + ' ' + r.text.replace( /\s+/g, ' ' ).slice( 0, 70 ) );
	}
	const dirList = await raw( '/wp-content/plugins/ashna-otp/' );
	check( 'the plugin directory cannot be listed', ! /Index of|Directory listing|parent directory/i.test( dirList.text ) && ! dirList.text.includes( 'class-ashna' ), dirList.status + ' ' + dirList.text.replace( /\s+/g, ' ' ).slice( 0, 70 ) );

	// uninstall.php must be a no-op unless WordPress itself runs it with the right constant.
	const uninstall = await raw( '/wp-content/plugins/ashna-otp/uninstall.php' );
	const stillThere = await raw( '/wp-login.php' );
	check( 'hitting uninstall.php directly changes nothing', ! /deleted|removed|done/i.test( uninstall.text ) && /ashna/i.test( stillThere.text ), uninstall.status + ' → login page still carries Ashna: ' + /ashna/i.test( stillThere.text ) );

	// Handlers that only exist on admin-post.php (they must not answer as ajax twins).
	for ( const action of [ 'ashna_save_settings', 'ashna_import_settings', 'ashna_reset_settings', 'ashna_unlock_target' ] ) {
		const r = await fetch( ORIGIN + '/wp-admin/admin-post.php', {
			method: 'POST',
			headers: { 'content-type': 'application/x-www-form-urlencoded' },
			body: 'action=' + action + '&nonce=abc&json_data=%7B%7D',
			redirect: 'manual',
		} );
		const text = await r.text();
		check( 'admin-post ' + action + ' is not a reachable handler', ! /"success":true/.test( text ), r.status + ' ' + text.replace( /\s+/g, ' ' ).slice( 0, 60 ) );
	}

	// A valid nonce without a session must still not open the admin handlers.
	const freshRaw = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: 'action=ashna_refresh_session' } );
	const fresh = await freshRaw.json().catch( () => null );
	const guard = await fetch( ORIGIN + '/wp-admin/admin-ajax.php', {
		method: 'POST',
		headers: { 'content-type': 'application/x-www-form-urlencoded' },
		body: new URLSearchParams( { action: 'ashna_admin_save_settings', nonce: fresh?.data?.nonce || '', 'ashna[form_title]': 'PWNED-' + Date.now() } ).toString(),
	} );
	const guardText = await guard.text();
	const guardJson = ( () => {
		try {
			return JSON.parse( guardText );
		} catch {
			return null;
		}
	} )();
	// Logged-out callers get either Ashna's JSON refusal or WordPress's bare "0" for an action
	// that only exists for authenticated users. Both mean the handler never ran.
	const guardRefused = ( guardJson && guardJson.success !== true ) || ( guard.status >= 400 && guardText.trim().length <= 2 );
	check( 'a fresh visitor nonce does not authorise settings changes', guardRefused, guard.status + ' ' + guardText.replace( /\s+/g, ' ' ).slice( 0, 80 ) );

	// XML-RPC and REST: no Ashna surface, no credential oracle for WordPress itself.
	const xmlrpc = await fetch( ORIGIN + '/xmlrpc.php', {
		method: 'POST',
		headers: { 'content-type': 'text/xml' },
		body: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>',
	} );
	const xmlText = await xmlrpc.text();
	check( 'XML-RPC exposes no Ashna method', ! /ashna/i.test( xmlText ), xmlrpc.status + ' ' + xmlText.replace( /\s+/g, ' ' ).slice( 0, 60 ) );
	const xmlLogin = await fetch( ORIGIN + '/xmlrpc.php', {
		method: 'POST',
		headers: { 'content-type': 'text/xml' },
		body: '<?xml version="1.0"?><methodCall><methodName>wp.getUsersBlogs</methodName><params><param><value>admin</value></param><param><value>guess</value></param></params></methodCall>',
	} );
	const xmlLoginText = await xmlLogin.text();
	check( 'XML-RPC does not confirm whether the account exists', ! /user.*(not exist|does not exist)/i.test( xmlLoginText ), xmlLoginText.replace( /\s+/g, ' ' ).slice( 0, 90 ) );

	const rest = await raw( '/wp-json/wp/v2/users' );
	if ( 200 === rest.status && /"slug":/.test( rest.text ) ) {
		notes.push( 'REST /wp-json/wp/v2/users is readable (WordPress default): ' + ( rest.text.match( /"slug":"[^"]+"/g ) || [] ).slice( 0, 4 ).join( ' ' ) );
	}
	check( 'the REST user route hands out no e-mail address', ! /"email":"[^"]*@/.test( rest.text ), rest.status + ' ' + rest.text.replace( /\s+/g, ' ' ).slice( 0, 60 ) );

	// --------------------------------------------------------- browser phase
	section( 'Real browser: cookies, DOM and storage' );
	const browserCtx = await playwright.launch( { executablePath: await chromium.executablePath(), args: chromium.args, headless: true } );
	const ctx = await browserCtx.newContext( { locale: 'fa-IR' } );
	const page = await ctx.newPage();
	const consoleErrors = [];
	const dialogs = [];
	page.on( 'console', ( m ) => 'error' === m.type() && consoleErrors.push( m.text() ) );
	page.on( 'dialog', async ( d ) => {
		dialogs.push( d.message() );
		await d.dismiss();
	} );

	await page.goto( BASE + '/', { waitUntil: 'domcontentloaded' } );
	check( 'no JavaScript dialog fired by the default page (no stored XSS)', 0 === dialogs.length, dialogs.join( ' | ' ) );

	// Visit a page that carries the hostile payload in the URL and watch for dialogs.
	await page.goto( BASE + '/?redirect_to=' + encodeURIComponent( '"><img src=x onerror=alert(1)>' ) + '&action[]=1', { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 800 );
	check( 'no dialog from a hostile URL (no DOM XSS)', 0 === dialogs.length, dialogs.join( ' | ' ) );

	const html = await page.content();
	check( 'the loaded DOM contains no live inline handler from the payload', ! /<img src=x onerror=alert\(1\)>/.test( html ) );

	// Sign in through the real form (sandbox gateway shows the code in the alert).
	const phone = '0912' + String( Math.floor( Math.random() * 9000000 ) + 1000000 );
	await page.goto( BASE + '/login-test/', { waitUntil: 'domcontentloaded' } );
	const form = page.locator( '.ashna-otp-wrapper:visible' ).first();
	const hasForm = await form.count();
	if ( hasForm ) {
		await form.locator( '.ashna-identifier-input' ).fill( fa( phone ) );
		await form.locator( '.ashna-submit-request' ).click();
		await form.locator( '.ashna-step-verify' ).waitFor( { state: 'visible', timeout: 45000 } ).catch( () => {} );
		const code = await codeIn( form );
		check( 'the sandbox gateway returned a code for the browser journey', !! code, 'code step reached' );
		if ( code ) {
			await form.locator( '.ashna-digit-box' ).first().click();
			await page.keyboard.type( code );
			await form.locator( '.ashna-submit-verify' ).click().catch( () => {} );
			await page.waitForTimeout( 4000 );
			const secondAlert = await form.locator( '.ashna-otp-alert' ).innerText().catch( () => '' );
			notes.push( 'sign-in alert: ' + secondAlert.slice( 0, 80 ) );
			const cookies = await ctx.cookies();
			const loggedIn = cookies.find( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) );
			const auth = cookies.find( ( c ) => c.name.startsWith( 'wordpress_' ) && ! c.name.startsWith( 'wordpress_logged_in_' ) );
			check( 'signing in through the form issues the WordPress session cookies', !! loggedIn && !! auth, cookies.map( ( c ) => c.name ).join( ',' ) );
			if ( loggedIn ) {
				check( 'the logged-in cookie is HttpOnly', true === loggedIn.httpOnly, String( loggedIn.httpOnly ) );
				check( 'the logged-in cookie is scoped to the whole site', '/' === loggedIn.path, loggedIn.path );
				check( 'the cookie is not marked SameSite=None without Secure', ! ( 'None' === loggedIn.sameSite && ! loggedIn.secure ), String( loggedIn.sameSite ) );
			}
			if ( auth ) {
				check( 'the session cookie is HttpOnly', true === auth.httpOnly, String( auth.httpOnly ) );
			}
			// A second visitor's browser must not be affected by the first.
			const ctx2 = await browserCtx.newContext();
			const page2 = await ctx2.newPage();
			await page2.goto( BASE + '/', { waitUntil: 'domcontentloaded' } );
			const cookies2 = await ctx2.cookies();
			check( 'a fresh browser context is not signed in by the first one', 0 === cookies2.filter( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ).length );
			await ctx2.close().catch( () => {} );
		}
	} else {
		check( 'the login form was found on the home page', false, 'no form matched' );
	}

	// The plugin renders nothing outside its own scope: no secret in inline JS.
	// A single-process Chromium can go away when a second context closes, so fall back to the
	// markup captured earlier instead of failing the run.
	const inline = await page.content().catch( () => html );
	check( 'no API key or salt leaks into the page source', ! /(api[_-]?key|kavenegar|melipayamak|ippanel|safir|msgway)\s*[:=]\s*["'][A-Za-z0-9_-]{12,}/i.test( inline ) );

	await browserCtx.close().catch( () => notes.push( 'the Chromium process had already exited before close()' ) );

	// ---------------------------------------------- server-side mistakes
	section( 'WP_DEBUG log of the whole campaign' );
	const logText = logSince( logStart );
	const lines = logText.split( '\n' ).filter( Boolean );
	const pluginLines = lines.filter( ( l ) => /plugins\/ashna-otp|Ashna_/.test( l ) );
	check( 'no PHP warning/notice/error from Ashna in the whole campaign', 0 === pluginLines.length, pluginLines.slice( 0, 3 ).join( ' | ' ) );
	const fatals = lines.filter( ( l ) => /Fatal error|Uncaught /.test( l ) );
	check( 'no fatal error or uncaught exception anywhere during the campaign', 0 === fatals.length, fatals.slice( 0, 2 ).join( ' | ' ) );
	const foreign = lines.filter( ( l ) => /(PHP Warning|PHP Notice|PHP Fatal|Uncaught )/.test( l ) && ! /wp-includes|wp-login\.php|wp-admin/.test( l ) );
	check( 'no warning from anything outside WordPress core', 0 === foreign.length, foreign.slice( 0, 3 ).join( ' | ' ) );
	notes.push( 'WP_DEBUG log lines written during the campaign: ' + lines.length + ( lines.length ? ' (WordPress core only)' : '' ) );

	console.log( '\n' + pass + ' passed, ' + failures.length + ' failed' );
	if ( notes.length ) {
		console.log( 'notes:\n  - ' + notes.join( '\n  - ' ) );
	}
	if ( failures.length ) {
		console.log( 'failures:\n  - ' + failures.join( '\n  - ' ) );
		process.exit( 1 );
	}
};

run().catch( ( e ) => {
	console.error( 'attack runner crashed:', e );
	process.exit( 2 );
} );
