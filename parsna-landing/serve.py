#!/usr/bin/env python3
"""Preview-only static server. Production Apache policy is in .htaccess.
No WP, API, user storage, directory listing or installer downloads are served.
The dev preview permits embedding by Arena; production keeps frame protection.
"""
import http.server
import mimetypes
from pathlib import Path
import sys
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parent

class PreviewHandler(http.server.BaseHTTPRequestHandler):
    server_version = 'AshnaPreview'
    sys_version = ''

    def do_GET(self):
        self.serve(head=False)

    def do_HEAD(self):
        self.serve(head=True)

    def serve(self, head=False):
        path = unquote(urlsplit(self.path).path)
        parts = path.split('/')
        if any(part.startswith(('.', '__')) for part in parts if part):
            self.send_error(403); return
        name = path.lstrip('/') or 'index.html'
        allowed = name == 'index.html' or (
            name.startswith(('assets/', 'css/', 'js/')) and
            Path(name).suffix.lower() in {'.css', '.js', '.webp', '.png', '.svg', '.woff2', '.txt', '.json'}
        )
        target = (ROOT / name).resolve()
        if not target.is_relative_to(ROOT) or not allowed or not target.is_file():
            self.send_error(404); return
        content = target.read_bytes()
        self.send_response(200)
        self.send_header('Content-Type', mimetypes.guess_type(name)[0] or 'application/octet-stream')
        self.send_header('Content-Length', str(len(content)))
        self.end_headers()
        if not head: self.wfile.write(content)

    def end_headers(self):
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('Referrer-Policy', 'strict-origin-when-cross-origin')
        self.send_header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        self.send_header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self'; connect-src 'none'; base-uri 'none'; form-action 'none'; object-src 'none'")
        self.send_header('Cache-Control', 'no-cache')
        super().end_headers()

if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
    server = http.server.ThreadingHTTPServer(('0.0.0.0', port), PreviewHandler)
    print(f'Ashna product preview listening on 0.0.0.0:{port}', flush=True)
    try: server.serve_forever()
    except KeyboardInterrupt: pass
    finally: server.server_close()
