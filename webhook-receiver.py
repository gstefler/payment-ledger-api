import json
from http.server import HTTPServer, BaseHTTPRequestHandler
from datetime import datetime, timezone


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        body = self.rfile.read(int(self.headers.get("Content-Length", 0)))
        try:
            data = json.loads(body)
        except Exception:
            data = body.decode()
        print(f"\n[{datetime.now(timezone.utc).isoformat()}] {self.path}", flush=True)
        print(json.dumps(data, indent=2), flush=True)
        self.send_response(200)
        self.end_headers()

    def log_message(self, *a):
        pass


print("Webhook receiver listening on :4000", flush=True)
HTTPServer(("", 4000), Handler).serve_forever()
