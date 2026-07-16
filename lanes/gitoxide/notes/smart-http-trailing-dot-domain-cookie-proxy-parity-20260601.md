# Smart HTTP trailing-dot Domain cookie/proxy parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T031404Z`
Base accepted HEAD: `979af834e747cf8f00cd2e2b7b981cbc1e549c29`

## Source truth

- Upstream `gix` forwards `http.proxy`, `gitoxide.http.proxy`, and
  `gitoxide.http.noProxy` into curl-backed smart HTTP options:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`.
- Upstream blocking smart HTTP transport applies those options through
  libcurl in
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
  and
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`.
- The accepted 2026-05-31 trailing-dot noProxy slice already mapped curl-style
  DNS-equivalent trailing-dot proxy bypass. This slice closes the matching
  cookie scope: a trailing-dot request host is canonicalized before host-only
  or Domain cookie matching.

## Implemented behavior

- `SmartHttpReceivePackTransport` now canonicalizes request hosts for cookie
  matching by lowercasing, removing IPv6 brackets, and trimming a trailing DNS
  dot.
- `https://git.example.test./wp-content.git` with `noProxy=example.test`
  bypasses the configured proxy and does not call the proxy credential helper.
- A discovery response cookie
  `Set-Cookie: wp_domain=trail; Domain=example.test; Path=/; Secure` is stored
  and sent as `Cookie: wp_domain=trail` on the receive-pack POST to the
  DNS-equivalent trailing-dot host.
- The WordPress proxy credential fixture/example now exposes the same
  bypassed-proxy Domain-cookie path.

## Verification

- Red-first focused check before the source fix:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 620 assertions, 2 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 768 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 7145 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`,
  `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`,
  and `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  all reported no syntax errors.
- JSON status/manifest parse:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  printed `json ok`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`.
- Lane hygiene:
  `git diff --check -- lanes/gitoxide` exited `0`.

## Non-overlap

This does not repeat accepted trailing-dot noProxy host-only cookies, CIDR
noProxy, bare-star noProxy, literal-star noProxy, port-qualified noProxy,
HTTPS/allProxy fallback, 304 discovery proxy-cookie preservation, redirect
credential/cookie scoping, SOCKS/TLS proxy handling, cleartext credential
rejection, or optional service-announcement handling. It is bounded to
Domain-scoped cookie matching for DNS-equivalent trailing-dot smart HTTP hosts
that already bypass the proxy.

## Dependency closure

No new support component is needed. The slice reuses the existing smart HTTP
requester boundary, proxy/noProxy matcher, cookie jar, proxy credential helper
callbacks, receive-pack packet builders, and WordPress proxy fixture/example.
