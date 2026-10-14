#!/usr/bin/env python3
"""Static-preview HTTP regression checks, not a penetration/security certification.
Run serve.py separately. Override LANDING_URL to test another preview URL.
"""
import os
import sys
import urllib.request
import urllib.error

BASE = os.environ.get('LANDING_URL', 'http://127.0.0.1:8080').rstrip('/')

def fetch(path, method='GET'):
    request = urllib.request.Request(BASE + path, method=method)
    try:
        with urllib.request.urlopen(request, timeout=10) as response:
            return response.status, response.headers, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.headers, error.read()

try:
    status, headers, body = fetch('/')
    assert status == 200
    assert 'آشنا'.encode() in body
    assert headers['X-Content-Type-Options'] == 'nosniff'
    assert headers['Referrer-Policy'] == 'strict-origin-when-cross-origin'
    assert "connect-src 'none'" in headers['Content-Security-Policy']
    assert "font-src 'self'" in headers['Content-Security-Policy']
    # Dev preview intentionally allows Arena embedding; production .htaccess protects framing.
    assert 'X-Frame-Options' not in headers
    for path in ['/.htaccess', '/assets/.hidden/file.webp', '/assets/%2e%2e/index.html',
                 '/%2e%2e/.git/config', '/serve.py', '/tests/security_test.py',
                 '/parsna-landing.zip', '/assets/', '/missing-page']:
        assert fetch(path)[0] in (403, 404), path
        assert fetch(path, 'HEAD')[0] in (403, 404), 'HEAD ' + path
    for path in ['/css/style.css', '/js/main.js', '/assets/ashna-icon.svg',
                 '/assets/fonts/estedad-400.woff2', '/assets/settings/01-routing.webp']:
        code, metadata, content = fetch(path)
        assert code == 200 and content, path
        code, head_metadata, empty = fetch(path, 'HEAD')
        assert code == 200 and not empty
        assert metadata['Content-Length'] == head_metadata['Content-Length']
    try:
        urllib.request.urlopen(urllib.request.Request(BASE + '/', data=b'demo=1'), timeout=10)
        raise AssertionError('POST was accepted')
    except urllib.error.HTTPError as error:
        assert error.code == 501
except (AssertionError, OSError) as error:
    print('FAIL:', error)
    sys.exit(1)
print('PASS: preview headers, public assets, GET/HEAD access controls, hidden paths, listing and POST rejection. No production security certification implied.')
