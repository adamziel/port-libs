# Smart HTTP port-qualified noProxy cookie/proxy parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T005207Z`
Base accepted HEAD: `21ac2341908d8036647334639cc353ff11f0d89f`

## Source truth

- Upstream `gix` uses curl/libcurl for blocking smart HTTP transport:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
  and
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`.
- Upstream transport config forwards `http.proxy` and `http.noProxy` into the
  transport options:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`.
- Local curl 8.18.0 probes showed `--noproxy git.example.test` bypasses a
  dead proxy for `http://git.example.test/`, while
  `--noproxy git.example.test:80`,
  `--noproxy git.example.test:443`, and `.example.test:443` continue through
  the proxy and fail with the proxy connection. That matches treating
  port-qualified tokens as literal non-bypass patterns for these host-only
  requests.

## Implemented evidence

- Added focused smart HTTP receive-pack assertions that
  `noProxy=git.example.test:443,.example.test:443` does not bypass
  `https://git.example.test/wp-content.git`.
- Verified the transport still:
  - sends both discovery and POST through the configured proxy;
  - retrieves proxy credentials for discovery and POST;
  - keeps `Proxy-Authorization` in HTTP transport options rather than origin
    request headers;
  - preserves the discovery `Set-Cookie` into the receive-pack POST.
- Extended the WordPress proxy fixture/example with the same port-qualified
  noProxy path.
- No production source change was needed; the existing matcher already follows
  the curl behavior for port-qualified tokens.

## Verification

- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - No syntax errors detected.
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`
  - No syntax errors detected.
- `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  - No syntax errors detected.
- `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  - Exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - 1 test files, 709 assertions, 0 failures.
- `php tools/run-tests.php lanes/gitoxide/tests`
  - 40 test files, 6532 assertions, 0 failures.
- `git diff --check -- lanes/gitoxide`
  - Exited 0.

## Non-overlap

This does not repeat the accepted CIDR noProxy, bare-star noProxy, literal
wildcard noProxy, trailing-dot noProxy, 304 proxy-cookie, proxy username,
credential URL override, redirect credential, cleartext credential rejection,
SOCKS/TLS, or optional service-announcement slices.

## Dependency closure

No new support component is needed. The slice reuses the existing smart HTTP
transport, in-memory receive-pack requester, proxy credential helper callbacks,
and WordPress proxy fixture/example.
