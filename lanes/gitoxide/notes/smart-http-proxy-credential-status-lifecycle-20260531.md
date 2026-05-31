# Smart HTTP Proxy Credential Status Lifecycle - 2026-05-31

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260531T122052Z`

Accepted base: `82ffc15bcb109224eed304cd069ec63109a1767a`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/transport_options.rs`

Implemented behavior:

- `SmartHttpReceivePackTransport` now stores proxy-helper credentials only
  after an HTTP `200` final status, matching Gitoxide's curl backend.
- Unexpected non-redirect statuses such as `204` now run the
  `proxyCredentialErase` callback before receive-pack status validation fails.
- Redirect statuses still defer store/erase until a final response, and proxy
  credentials remain out of origin request headers.
- The WordPress proxy credential fixture/example now records that stale helper
  credentials are erased after an unexpected smart HTTP discovery status.

Verification:

- Red-first check after adding assertions and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 394 assertions, 1 failures`.
- Focused check after implementation and fixture update:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 421 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  4510 assertions, 0 failures`.
- Syntax, smoke, and lane hygiene checks:
  `php -l` passed for the changed source, test, fixture, and example PHP
  files; `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`; JSON decoding passed for the updated manifest/status files; and
  `git diff --check -- lanes/gitoxide` exited `0`.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy credential helper/store/erase callbacks, and
  WordPress proxy credential fixture/example.

Non-overlap:

- This does not repeat accepted smart HTTP redirect cookie scoping,
  followRedirects, SOCKS proxy handshakes, HTTPS-through-SOCKS, cleartext URL
  credential rejection, optional service-announcement handling, send-pack
  status parsing, or SSH receive-pack boundaries. It is bounded to the
  upstream proxy credential store/erase decision for final HTTP statuses.
