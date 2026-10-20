// TEST ONLY — load test: thousands of virtual users signing in through Ashna at once.
//
// Each virtual user is a real browser-like client on its own IP (X-Forwarded-For, which the
// plugin trusts from the loopback peer): it fetches a nonce, asks for an OTP, reads the sandbox
// gateway's debug code, verifies it, and then proves the session it got belongs to *that* user.
// A hostile fraction can be mixed in to see whether abuse starves the legitimate crowd.
//
// usage: node tests/load/load-login.mjs [--users=1000] [--concurrency=25] [--hostile=0]
//                                       [--mode=otp|password|mixed] [--label=baseline]
//                                       [--base=http://localhost:9400] [--repeat=1]
import fs from 'fs';
import path from 'path';

const arg = ( name, fallback ) => {
	const hit = process.argv.find( ( a ) => a.startsWith( '--' + name + '=' ) );
	return hit ? hit.split( '=' ).slice( 1 ).join( '=' ) : fallback;
};

const BASE = String( arg( 'base', process.env.ASHNA_QA_BASE || 'http://localhost:9400' ) ).replace( /\/$/, '' );
const USERS = Number( arg( 'users', 1000 ) );
const CONCURRENCY = Number( arg( 'concurrency', 25 ) );
const HOSTILE = Number( arg( 'hostile', 0 ) ); // percent of requests that are abusive
const MODE = String( arg( 'mode', 'otp' ) );
const LABEL = String( arg( 'label', 'run' ) );
const REPEAT = Number( arg( 'repeat', 1 ) ); // rounds per virtual user (repeat logins)
const OUT = String( arg( 'out', '' ) );
// Guests share one nonce in WordPress (it is derived from the session token and the user id, and
// a logged-out visitor has neither), so one fetched nonce can serve the whole crowd — exactly what
// a cached login page does. Saves a third of the requests.
const SHARED_NONCE = '1' === String( arg( 'shared-nonce', '1' ) );
const PASSWORD = 'Load-Pass-1!';

const fa = ( s ) => String( s ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );
const phoneFor = ( i ) => '0999' + String( i % 10000000 ).padStart( 7, '0' );
const loginFor = ( i ) => 'loaduser' + i;
const ipFor = ( i ) => '203.' + ( 8 + ( i % 200 ) ) + '.' + ( i % 251 ) + '.' + ( 1 + ( i % 250 ) );

const stats = {
	label: LABEL,
	base: BASE,
	users: USERS,
	concurrency: CONCURRENCY,
	hostilePercent: HOSTILE,
	mode: MODE,
	repeat: REPEAT,
	requests: 0,
	byEndpoint: {},
	errors: [],
	hostileSent: 0,
	hostileWronglyAccepted: 0,
	legitFailures: 0,
	sessionMismatch: 0,
	timeouts: 0,
	server5xx: 0,
	startedAt: new Date().toISOString(),
};

/** Latency samples per endpoint. */
const samples = {};
/** Server-side JSON errors that are not a plain rate-limit refusal. */
const unexpected = [];

function record( endpoint, ms, status, ok ) {
	stats.requests++;
	stats.byEndpoint[ endpoint ] = stats.byEndpoint[ endpoint ] || { n: 0, ok: 0, fail: 0, ms: [] };
	const bucket = stats.byEndpoint[ endpoint ];
	bucket.n++;
	ok ? bucket.ok++ : bucket.fail++;
	bucket.ms.push( ms );
	if ( status >= 500 ) {
		stats.server5xx++;
	}
	( samples[ endpoint ] = samples[ endpoint ] || [] ).push( ms );
}

function percentile( values, p ) {
	if ( ! values.length ) {
		return 0;
	}
	const sorted = [ ...values ].sort( ( a, b ) => a - b );
	return sorted[ Math.min( sorted.length - 1, Math.floor( ( p / 100 ) * sorted.length ) ) ];
}

/** One HTTP call with a per-user cookie jar; returns { status, ms, text, json, setCookies }. */
async function call( jar, endpoint, { method = 'POST', body = null, query = '', ip = '127.0.0.1', timeout = 120000 } = {} ) {
	const url = BASE + '/wp-admin/admin-ajax.php' + ( query || '' );
	const headers = {
		'content-type': 'application/x-www-form-urlencoded',
		'x-forwarded-for': ip,
		'user-agent': 'AshnaLoadTest/1.0',
	};
	if ( jar && jar.length ) {
		headers.cookie = jar.join( '; ' );
	}
	const started = process.hrtime.bigint();
	let res;
	try {
		res = await fetch( url, {
			method,
			headers,
			body: 'GET' === method ? undefined : body,
			signal: AbortSignal.timeout( timeout ),
			redirect: 'manual',
		} );
	} catch ( e ) {
		const ms = Number( process.hrtime.bigint() - started ) / 1e6;
		const timedOut = 'TimeoutError' === e.name || /timeout/i.test( String( e.message ) );
		timedOut ? stats.timeouts++ : null;
		record( endpoint, ms, 0, false );
		unexpected.push( endpoint + ' → ' + ( timedOut ? 'TIMEOUT' : e.message ) );
		return { status: 0, ms, text: '', json: null, setCookies: [], failed: true };
	}
	const text = await res.text();
	const ms = Number( process.hrtime.bigint() - started ) / 1e6;
	let json = null;
	try {
		json = JSON.parse( text );
	} catch {
		/* HTML or bare "0" */
	}
	const setCookies = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
	if ( setCookies.length && jar ) {
		for ( const raw of setCookies ) {
			const pair = raw.split( ';' )[ 0 ];
			const name = pair.split( '=' )[ 0 ];
			const at = jar.findIndex( ( c ) => c.startsWith( name + '=' ) );
			at >= 0 ? ( jar[ at ] = pair ) : jar.push( pair );
		}
	}
	return { status: res.status, ms, text, json, setCookies, failed: false };
}

const nonceOf = ( json ) => ( json && json.data && json.data.nonce ? json.data.nonce : '' );
const codeOf = ( json ) => ( json && json.data ? json.data.code : '' );
const messageOf = ( json ) => ( json && json.data ? String( json.data.message || '' ).slice( 0, 60 ) : '' );
const solveCaptcha = ( json ) => {
	const q = json && json.data && json.data.new_captcha ? json.data.new_captcha : null;
	if ( ! q ) {
		return null;
	}
	const digits = String( q.question ).match( /\d+/g ) || [];
	if ( digits.length < 2 ) {
		return null;
	}
	return { answer: String( Number( digits[ 0 ] ) + Number( digits[ 1 ] ) ), token: q.token };
};

/** One complete, legitimate OTP sign-in for virtual user $index. */
async function signInWithOtp( index, jar ) {
	const phone = phoneFor( index );
	const ip = ipFor( index );

	let nonce = SHARED_NONCE && stats.sharedNonce ? stats.sharedNonce : '';
	if ( ! nonce ) {
		const refresh = await call( jar, 'refresh', { method: 'POST', body: 'action=ashna_refresh_session', ip } );
		record( 'refresh_session', refresh.ms, refresh.status, !! nonceOf( refresh.json ) || !! refresh.json );
		if ( ! nonceOf( refresh.json ) ) {
			stats.legitFailures++;
			unexpected.push( 'no nonce for ' + loginFor( index ) + ': ' + refresh.status + ' ' + refresh.text.slice( 0, 80 ) );
			return false;
		}
		nonce = nonceOf( refresh.json );
		if ( SHARED_NONCE ) {
			stats.sharedNonce = nonce;
		}
	}

	let ask = await call( jar, 'request_otp', {
		body: new URLSearchParams( { action: 'ashna_request_otp', nonce, identifier: phone } ).toString(),
		ip,
	} );
	record( 'request_otp', ask.ms, ask.status, true );
	if ( ask.json && ask.json.success === false ) {
		const captcha = solveCaptcha( ask.json );
		if ( ! captcha ) {
			stats.legitFailures++;
			unexpected.push( 'request_otp refused for ' + loginFor( index ) + ': ' + codeOf( ask.json ) + ' ' + messageOf( ask.json ) );
			return false;
		}
		ask = await call( jar, 'request_otp_captcha', {
			body: new URLSearchParams( {
				action: 'ashna_request_otp',
				nonce,
				identifier: phone,
				captcha_answer: captcha.answer,
				captcha_token: captcha.token,
			} ).toString(),
			ip,
		} );
		record( 'request_otp_after_captcha', ask.ms, ask.status, true );
		if ( ask.json && ask.json.success === false ) {
			stats.legitFailures++;
			unexpected.push( 'captcha retry still refused for ' + loginFor( index ) + ': ' + codeOf( ask.json ) );
			return false;
		}
	}
	const code = ask.json && ask.json.data ? ask.json.data.debug_code : '';
	if ( ! code ) {
		stats.legitFailures++;
		unexpected.push( 'no debug code for ' + loginFor( index ) + ': ' + ask.text.slice( 0, 100 ) );
		return false;
	}

	const verify = await call( jar, 'verify_otp', {
		body: new URLSearchParams( { action: 'ashna_verify_otp', nonce, identifier: phone, code } ).toString(),
		ip,
	} );
	record( 'verify_otp', verify.ms, verify.status, !!( verify.json && verify.json.success ) );
	if ( ! verify.json || verify.json.success !== true ) {
		stats.legitFailures++;
		unexpected.push( 'verify refused for ' + loginFor( index ) + ': ' + codeOf( verify.json ) + ' ' + verify.text.slice( 0, 100 ) );
		return false;
	}
	return true;
}

/** One complete password sign-in (for the password-limit paths). */
async function signInWithPassword( index, jar ) {
	let nonce = SHARED_NONCE && stats.sharedNonce ? stats.sharedNonce : '';
	if ( ! nonce ) {
		const refresh = await call( jar, 'refresh', { method: 'POST', body: 'action=ashna_refresh_session', ip: ipFor( index ) } );
		record( 'refresh_session', refresh.ms, refresh.status, !! refresh.json );
		nonce = nonceOf( refresh.json );
		if ( SHARED_NONCE ) {
			stats.sharedNonce = nonce;
		}
	}
	const res = await call( jar, 'password_login', {
		body: new URLSearchParams( {
			action: 'ashna_password_login',
			nonce,
			identifier: loginFor( index ),
			password: PASSWORD,
		} ).toString(),
		ip: ipFor( index ),
	} );
	record( 'password_login', res.ms, res.status, !!( res.json && res.json.success ) );
	if ( ! res.json || res.json.success !== true ) {
		stats.legitFailures++;
		unexpected.push( 'password refused for ' + loginFor( index ) + ': ' + codeOf( res.json ) + ' ' + res.text.slice( 0, 90 ) );
		return false;
	}
	return true;
}

/** Prove the session belongs to the expected account (a session mix-up is the worst failure). */
async function whoami( jar, index, ip ) {
	const url = BASE + '/?qa_whoami=1';
	const started = process.hrtime.bigint();
	let res;
	try {
		res = await fetch( url, { headers: { cookie: jar.join( '; ' ), 'x-forwarded-for': ip, 'user-agent': 'AshnaLoadTest/1.0' }, signal: AbortSignal.timeout( 120000 ) } );
	} catch ( e ) {
		const ms = Number( process.hrtime.bigint() - started ) / 1e6;
		record( 'whoami', ms, 0, false );
		unexpected.push( 'whoami failed for ' + loginFor( index ) + ': ' + e.message );
		return { ok: false };
	}
	const ms = Number( process.hrtime.bigint() - started ) / 1e6;
	const json = await res.json().catch( () => null );
	const expected = loginFor( index );
	const ok = !! json && json.signed_in && json.login === expected;
	record( 'whoami', ms, res.status, ok );
	if ( ! ok ) {
		if ( json && json.signed_in ) {
			stats.sessionMismatch++;
			unexpected.push( 'SESSION MIX-UP: expected ' + expected + ' got ' + json.login );
		} else {
			stats.legitFailures++;
			unexpected.push( 'not signed in as ' + expected + ': ' + JSON.stringify( json ).slice( 0, 90 ) );
		}
	}
	return { ok, json };
}

const HOSTILE_CASES = [
	[ 'bad nonce', { action: 'ashna_request_otp', nonce: 'deadbeef', identifier: '09120000001' } ],
	[ 'array params', { action: 'ashna_request_otp', nonce: 'x' } ],
	[ 'unknown phone', { action: 'ashna_request_otp', identifier: '09120000002' } ],
	[ 'array identifier', { action: 'ashna_verify_otp', identifier: 'x', code: '12345' } ],
	[ 'absurd identifier', { action: 'ashna_request_otp', identifier: '%E0%A4%A'.repeat( 20 ) } ],
	[ 'wrong code', { action: 'ashna_verify_otp', identifier: '09120000003', code: '00000' } ],
	[ 'passkey forgery', { action: 'ashna_passkey_login_verify', credential_id: 'x', client_data_json: 'x', authenticator_data: 'x', signature: 'x' } ],
];

async function hostile( jar, nonce ) {
	const which = HOSTILE_CASES[ Math.floor( Math.random() * HOSTILE_CASES.length ) ];
	const params = new URLSearchParams( { ...which[ 1 ] } );
	if ( nonce && 'bad nonce' !== which[ 0 ] ) {
		params.set( 'nonce', nonce );
	}
	if ( 'array params' === which[ 0 ] ) {
		for ( const key of [ 'identifier', 'code', 'redirect_to' ] ) {
			params.set( key + '[]', 'x' );
		}
	}
	if ( 'array identifier' === which[ 0 ] ) {
		params.set( 'identifier[]', 'x' );
		params.set( 'nonce', nonce || 'x' );
	}
	stats.hostileSent++;
	const res = await call( jar, 'hostile:' + which[ 0 ], {
		body: params.toString(),
		ip: '198.51.100.' + ( 1 + Math.floor( Math.random() * 250 ) ),
	} );
	// A hostile request may legitimately get "sent" (an unknown number must not be an oracle), but
	// a wrong code, a forged assertion or an array identifier must never come back as success.
	if ( res.json && res.json.success === true && [ 'wrong code', 'passkey forgery', 'array identifier' ].includes( which[ 0 ] ) ) {
		stats.hostileWronglyAccepted++;
		unexpected.push( 'hostile accepted: ' + which[ 0 ] + ' → ' + res.text.slice( 0, 90 ) );
	}
	if ( res.status >= 500 ) {
		unexpected.push( 'hostile 5xx: ' + which[ 0 ] + ' → ' + res.status );
	}
	return res;
}

async function runVirtualUser( index ) {
	const jar = [];
	const ip = ipFor( index );
	const roll = Math.random() * 100;
	const rounds = Math.max( 1, REPEAT );
	for ( let r = 0; r < rounds; r++ ) {
		if ( roll < HOSTILE ) {
			await hostile( jar, '' );
			continue;
		}
		const signedIn = 'password' === MODE
			? await signInWithPassword( index, jar )
			: await signInWithOtp( index, jar );
		if ( signedIn ) {
			await whoami( jar, index, ip );
		}
		// Half the hostile traffic also fires between honest users, from the same client.
		if ( HOSTILE > 0 && Math.random() * 100 < HOSTILE ) {
			await hostile( jar, '' );
		}
	}
}

async function main() {
	const indexes = [ ...Array( USERS ).keys() ];
	let cursor = 0;
	let inFlight = 0;
	let done = 0;
	const workers = [];

	const tick = () => {
		if ( done % 50 === 0 && done > 0 ) {
			process.stdout.write( `\r  ${ done }/${ USERS } users, ${ stats.requests } requests, ${ stats.legitFailures } legit failures, ${ stats.server5xx } server 5xx` );
		}
	};

	const worker = async () => {
		while ( true ) {
			const idx = cursor++;
			if ( idx >= indexes.length ) {
				return;
			}
			inFlight++;
			try {
				await runVirtualUser( indexes[ idx ] );
			} catch ( e ) {
				unexpected.push( 'virtual user ' + idx + ' threw: ' + e.message );
			}
			inFlight--;
			done++;
			tick();
		}
	};

	stats.wallStart = Date.now();
	for ( let i = 0; i < Math.max( 1, CONCURRENCY ); i++ ) {
		workers.push( worker() );
	}
	await Promise.all( workers );
	stats.wallMs = Date.now() - stats.wallStart;

	// Wait for the site to settle, then ask it what it thinks happened.
	await new Promise( ( r ) => setTimeout( r, 1500 ) );
	let report = null;
	try {
		const res = await fetch( BASE + '/?qa_load_report=1', { signal: AbortSignal.timeout( 120000 ) } );
		report = await res.json();
	} catch ( e ) {
		unexpected.push( 'final report failed: ' + e.message );
	}

	const endpoints = {};
	for ( const [ name, list ] of Object.entries( samples ) ) {
		endpoints[ name ] = {
			n: list.length,
			p50: Math.round( percentile( list, 50 ) ),
			p95: Math.round( percentile( list, 95 ) ),
			p99: Math.round( percentile( list, 99 ) ),
			max: Math.round( Math.max( ...list ) ),
			avg: Math.round( list.reduce( ( a, b ) => a + b, 0 ) / list.length ),
		};
	}
	const errors = Object.entries( stats.byEndpoint ).map( ( [ name, b ] ) => ( { endpoint: name, n: b.n, failed: b.fail } ) ).filter( ( e ) => e.failed );

	const result = {
		...stats,
		wallSeconds: Math.round( stats.wallMs / 100 ) / 10,
		requestsPerSecond: Math.round( ( stats.requests / ( stats.wallMs / 1000 ) ) * 10 ) / 10,
		usersPerSecond: Math.round( ( USERS / ( stats.wallMs / 1000 ) ) * 10 ) / 10,
		latency: endpoints,
		endpointErrors: errors,
		server: report,
		problems: unexpected.slice( 0, 40 ),
		problemCount: unexpected.length,
	};

	console.log( '\n' + JSON.stringify( result, null, 2 ) );
	if ( OUT ) {
		fs.mkdirSync( path.dirname( OUT ), { recursive: true } );
		fs.writeFileSync( OUT, JSON.stringify( result, null, 2 ) );
		console.log( '\nwritten to ' + OUT );
	}
	process.exit( 0 );
}

main().catch( ( e ) => {
	console.error( 'load runner crashed:', e );
	process.exit( 2 );
} );
