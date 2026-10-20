#!/usr/bin/env python3
"""
Parsna Landing — Secure Development Server
Blocks dotfiles (.htaccess, .git, etc.)
"""

import http.server
import os
import sys

class SecureHandler(http.server.SimpleHTTPRequestHandler):
    """Handler that blocks access to dotfiles and sensitive paths."""

    BLOCKED_PREFIXES = ('.', '__')
    BLOCKED_FILES = ('.htaccess', '.git', '.env', '.DS_Store')

    def do_GET(self):
        basename = os.path.basename(self.path.rstrip('/'))

        # Block dotfiles
        if basename.startswith('.') or basename.startswith('__'):
            self.send_error(403, 'Forbidden')
            return

        # Block sensitive files
        if basename.lower() in self.BLOCKED_FILES:
            self.send_error(403, 'Forbidden')
            return

        # Add security headers
        super().do_GET()

    def end_headers(self):
        self.send_header('X-Frame-Options', 'SAMEORIGIN')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('X-XSS-Protection', '1; mode=block')
        self.send_header('Referrer-Policy', 'strict-origin-when-cross-origin')
        self.send_header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        self.send_header('Content-Security-Policy',
            "default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; "
            "img-src 'self' data:; connect-src 'self'; "
            "frame-ancestors 'self'; base-uri 'self'; form-action 'self';")
        super().end_headers()

    def log_message(self, format, *args):
        # Suppress noisy logs in dev
        pass

if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    server = http.server.HTTPServer(('0.0.0.0', port), SecureHandler)
    print(f'🔒 Parsna secure server on http://0.0.0.0:{port}')
    print(f'   Dotfiles blocked, security headers active')
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print('\n👋 Stopped.')
        server.server_close()