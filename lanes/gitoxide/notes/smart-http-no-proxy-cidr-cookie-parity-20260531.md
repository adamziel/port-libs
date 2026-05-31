# Smart HTTP No-Proxy CIDR Cookie Parity - 2026-05-31

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260531T203750Z`

Accepted base: `91b42fe7029899440b4b46f38b3f903a76f3b322`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/transport_options.rs`

Implemented behavior:

- `SmartHttpReceivePackTransport` now accepts curl-style IPv4 and IPv6 CIDR
  `noProxy` entries such as `192.168.0.0/16` and `[2001:db8::]/32`.
- Matching CIDR entries bypass proxy stream options and do not call the proxy
  credential helper, matching the upstream handoff from `gitoxide.http.noProxy`
  to curl `noproxy`.
- Origin `Set-Cookie` state from a bypassed smart HTTP discovery request is
  still scoped into the receive-pack POST request, and proxy credentials remain
  absent from origin headers.
- Invalid slash-delimited `noProxy` values, such as `example.test/24` or
  impossible prefix lengths, still fail before requester handoff.

Verification:

- Red-first check after adding CIDR assertions and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 479 assertions, 2 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 582 assertions, 0 failures`.
- Full lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 5430 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy option normalization, proxy credential helper
  callbacks, cookie jar, and WordPress proxy credential fixture/example.

Non-overlap:

- This does not repeat accepted smart HTTP redirect cookie scoping,
  followRedirects, SOCKS proxy handshakes, HTTPS-through-SOCKS, cleartext URL
  credential rejection, optional service-announcement handling, proxy
  credential store/erase lifecycle, username-only proxy helper activation, or
  proxy credential/header scoping. It is bounded to Gitoxide's upstream
  `noProxy` transport behavior where curl accepts CIDR bypass patterns while
  the origin cookie jar continues to operate normally.
