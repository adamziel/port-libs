# Smart HTTP HTTPS allProxy cookie/proxy parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T091010Z`
Base accepted HEAD: `8c8829e6ea966fa9e8e7ed89cc2696e6096ac93d`

## Source Truth

- Upstream `gix/src/repository/config/transport.rs` at pinned Gitoxide commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6` selects `gitoxide.http.allProxy`
  as the final fallback when neither the primary HTTP proxy nor an HTTPS
  specific proxy is selected.
- Upstream `gix/tests/gix/repository/config/transport_options.rs` verifies
  `all_proxy_only` for both HTTP and HTTPS remotes, and verifies that an empty
  `gitoxide.https.proxy` is a configured value that disables lower fallback.
- Upstream `gix-transport/src/client/blocking_io/http/curl/remote.rs` applies
  the selected proxy, proxy authentication, and no-proxy options through the
  curl-backed smart HTTP request path.

## Native Behavior Verified

- `SmartHttpReceivePackTransport` already selected `allProxy` for an HTTPS
  receive-pack remote when no primary or HTTPS-specific proxy was configured.
- The HTTPS all-proxy path preserves discovery `Set-Cookie` state into the
  generated receive-pack POST and keeps proxy authorization in transport
  options rather than origin headers.
- An explicitly empty `httpsProxy` disables lower `allProxy` fallback for
  HTTPS remotes, so proxy helpers are not called, both discovery and POST are
  direct, and origin cookies still reach the receive-pack POST.
- The WordPress proxy credential fixture/example exposes both behaviors for
  deployment tooling running behind shared-hosting proxy configuration.

## Verification

Before this slice, focused transport evidence was:

```text
php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php
1 test files, 958 assertions, 0 failures
```

After adding the focused assertions:

```text
php -l lanes/gitoxide/tests/ReceivePackTransportTest.php
No syntax errors detected

php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php
No syntax errors detected

php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php
No syntax errors detected

php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php
1 test files, 984 assertions, 0 failures

php tools/run-tests.php lanes/gitoxide/tests
40 test files, 8409 assertions, 0 failures

php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php
exit 0
```

## Expected Dashboard Movement

- `phpPass`: `8383 -> 8409`
- Conservative mapped coverage: `1778 / 2886 -> 1779 / 2886`

## Dependency Closure

No new support component is needed. The slice reuses the existing injected
smart HTTP requester, native proxy fallback selection, proxy credential helper
callbacks, cookie jar, receive-pack request builder, and WordPress proxy
fixture/example.

## Non-Overlap

This does not repeat accepted primary proxy lifecycle, username-only proxy
helper context, default-port proxy credential context, HTTPS proxy fallback,
primary-proxy-empty fallback disabling, HTTP allProxy fallback, CIDR/IPv6/
trailing-dot/port-qualified noProxy behavior, 304 proxy-cookie preservation,
upgrade redirect proxy selection, SOCKS/TLS proxy handling, or content-type
header/proxy behavior. It is bounded to HTTPS allProxy fallback plus empty
HTTPS-specific proxy disablement with receive-pack cookie preservation.
