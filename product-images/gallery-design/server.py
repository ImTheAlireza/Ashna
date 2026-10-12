"""Serve the gallery and local brand/font assets; no WordPress runtime required."""
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import argparse
from urllib.parse import urlsplit, unquote

ROOT = Path(__file__).resolve().parents[2]

class GalleryHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def do_GET(self):
        if self.path == "/":
            self.send_response(302)
            self.send_header("Location", "/product-images/gallery-design/index.html")
            self.end_headers()
            return
        # Never expose repository internals through the public preview.
        requested = (ROOT / unquote(urlsplit(self.path).path).lstrip('/')).resolve()
        allowed = [ROOT / 'product-images', ROOT / 'assets/fonts', ROOT / 'assets/images']
        if not any(requested.is_relative_to(folder) for folder in allowed):
            self.send_error(404)
            return
        if not requested.is_file():
            self.send_error(404)
            return
        return super().do_GET()

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--port", type=int, default=8000)
    args = parser.parse_args()
    ThreadingHTTPServer(("0.0.0.0", args.port), GalleryHandler).serve_forever()
