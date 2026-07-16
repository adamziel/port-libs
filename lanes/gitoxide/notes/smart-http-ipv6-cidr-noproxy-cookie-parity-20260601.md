# Smart HTTP IPv6 CIDR No-Proxy Cookie Parity - 2026-06-01

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T152621Z`

Accepted base: `7efc5758f2e7f4a69f5e8d831691075050e5a2fd`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`
- Pinned upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`

Implemented behavior:

- The focused smart HTTP receive-pack path now verifies a bracketed IPv6 CIDR
  `noProxy` entry (`[2001:db8::]/32`) against an IPv6 literal repository URL.
- Matching the upstream Gitoxide handoff from config to curl `noproxy`, the
  request bypasses proxy stream options and does not consult the proxy
  credential helper.
- Origin `Set-Cookie` state from the bypassed discovery request is still
  scoped into the receive-pack POST request.
- The WordPress smart HTTP proxy fixture and example now expose the same
  bracketed IPv6 CIDR bypass and cookie-carryover behavior.

Verification:

- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`
  passed with no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1291 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited successfully.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy option normalization, proxy credential helper
  callbacks, cookie jar, and WordPress proxy fixture/example.

Non-overlap:

- This does not repeat accepted IPv4 CIDR noProxy fixture coverage,
  advertisement-only IPv6 CIDR matching, IPv6 literal noProxy bypass,
  wildcard noProxy matching, port-qualified noProxy literals, trailing-dot
  host/domain cookie behavior, HTTPS/all-proxy fallback, protocol-relative
  redirect handling, or 304 discovery cookie handling. This slice is bounded
  to receive-pack POST cookie preservation and WordPress fixture/example
  evidence for bracketed IPv6 CIDR noProxy ranges.
