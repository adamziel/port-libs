# Smart HTTP Trailing-Dot NoProxy Cookie Parity - 2026-05-31

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260531T234756Z`

Accepted base: `fb7d06d53486b39f2451378154d78e6da27eae83`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/transport_options.rs`

Implemented behavior:

- `SmartHttpReceivePackTransport` now canonicalizes DNS trailing dots before
  matching configured `noProxy` entries, mirroring Gitoxide's handoff of
  `gitoxide.http.noProxy` to libcurl `noproxy`.
- `example.test` now bypasses a proxy for `https://git.example.test./...`,
  and `.example.test.` bypasses `https://git.example.test/...`.
- Matching trailing-dot bypasses skip proxy stream options and proxy credential
  helper calls, while origin `Set-Cookie` state from receive-pack discovery is
  still preserved into the receive-pack POST.
- Literal asterisk-bearing noProxy entries, CIDR entries, and bare `*`
  bypass-all semantics are unchanged.

Verification:

- Local curl parity probe:
  `curl --noproxy example.test --proxy http://127.0.0.1:9 --resolve git.example.test.:80:127.0.0.1 http://git.example.test./`
  attempted the origin connection, while `--noproxy '*example.test'` and
  `--noproxy '*.example.test'` attempted the proxy.
- Red-first check after adding trailing-dot assertions and before the source
  fix: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 565 assertions, 2 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 669 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 6320 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`,
  `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`,
  and `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  all reported no syntax errors.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`.
- Lane hygiene:
  `git diff --check -- lanes/gitoxide` exited `0`.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy option normalization, proxy credential helper
  callbacks, cookie jar, packet/request builders, and WordPress proxy
  credential fixture/example.

Non-overlap:

- This does not repeat accepted 304 proxy-cookie discovery, noProxy CIDR,
  bare-star/literal-star noProxy semantics, proxy credential store/erase
  lifecycle, username-only proxy helper activation, redirect cookie scoping,
  SOCKS proxy handshakes, HTTPS-through-SOCKS, cleartext URL credential
  rejection, or optional service-announcement handling. It is bounded to
  curl/libcurl's DNS trailing-dot noProxy matching and cookie preservation on
  the bypassed receive-pack flow.
