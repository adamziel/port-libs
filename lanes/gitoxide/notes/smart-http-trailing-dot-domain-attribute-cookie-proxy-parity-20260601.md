# Smart HTTP trailing-dot Domain attribute cookie/proxy parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T103034Z`
Base accepted HEAD: `9fdbbaf081786bb1d6389d15e519a76f8a24a31c`

## Source truth

- Upstream `gix` smart HTTP repository transport forwards proxy/noProxy
  configuration into the curl-backed transport:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`.
- Upstream blocking smart HTTP applies those options and delegates cookie jar
  handling to libcurl:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
  and
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`.
- A local curl probe against `http://git.example.test.:PORT/...` showed
  `Set-Cookie: trail=ok; Domain=example.test.; Path=/` is accepted by libcurl
  and sent back as `Cookie: trail=ok` on the next request to the
  DNS-equivalent trailing-dot host.
- The same probe shape with `Domain=example.test..` produced no libcurl cookie
  jar entry, so the PHP normalization accepts exactly one trailing DNS dot and
  keeps double-dot attributes invalid.

## Implemented behavior

- `SmartHttpReceivePackTransport` now trims one trailing DNS dot from cookie
  `Domain` attributes before validating the attribute and storing the cookie
  scope, while rejecting attributes that still end in a dot afterward.
- `https://git.example.test./wp-content.git` with `noProxy=example.test`
  still bypasses the configured proxy and does not consult the proxy
  credential helper.
- A discovery response cookie
  `Set-Cookie: wp_domain_attr=trail; Domain=example.test.; Path=/; Secure` is
  stored and sent as `Cookie: wp_domain_attr=trail` on the receive-pack POST.
- `Domain=example.test..` remains rejected and is not sent on the
  receive-pack POST, matching the observed libcurl behavior.
- The WordPress smart HTTP proxy credential fixture/example exposes the same
  curl-compatible trailing-dot Domain-attribute path.

## Verification

- Red-first focused check after adding the assertions but before the source
  fix:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 836 assertions, 2 failures`.
- Red-first guard for over-broad normalization:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 915 assertions, 1 failures` while
  `Domain=example.test..` was incorrectly accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1055 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 8769 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`,
  `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`,
  and `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  all reported no syntax errors.
- JSON status parse:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  printed `json ok`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`.
- Lane hygiene:
  `git diff --check -- lanes/gitoxide` exited `0`.

## Non-overlap

This does not repeat the accepted trailing-dot request-host Domain cookie
slice, trailing-dot noProxy host-only cookie slice, CIDR noProxy, IPv6 literal
noProxy, port-qualified noProxy, HTTPS/allProxy fallback, upgrade redirect,
304 discovery proxy-cookie preservation, redirect credential/cookie scoping,
SOCKS/TLS proxy handling, cleartext credential rejection, or optional
service-announcement handling. It is bounded to curl-compatible normalization
of the `Domain` attribute itself when that attribute includes a trailing DNS
dot.

## Dependency closure

No new support component is needed. The slice reuses the existing smart HTTP
requester boundary, curl-compatible proxy/noProxy matcher, cookie jar, proxy
credential helper callbacks, receive-pack packet builders, and WordPress proxy
fixture/example.
