# Smart HTTP 304 Proxy Cookie Parity - 2026-05-31

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260531T224044Z`

Accepted base: `33a65237308053a0654b3629f3bffe8d77c73515`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/transport_options.rs`

Implemented behavior:

- `SmartHttpReceivePackTransport` now treats an accepted smart HTTP discovery
  `304 Not Modified` response as a successful proxy credential action, matching
  the upstream curl backend's accepted-status proxy authentication boundary.
- The existing 304 advertisement path now stores helper-returned proxy
  credentials instead of erasing them, while unexpected non-redirect statuses
  such as `204` still erase stale helper credentials before validation fails.
- `Set-Cookie` state returned with a 304 discovery response is preserved into
  the following receive-pack POST, and proxy credentials remain in HTTP options
  rather than origin headers.
- The WordPress proxy credential fixture/example now records this 304
  discovery path, the two helper/store callbacks for discovery and POST, the
  absence of erasures, and the POST cookie header.

Verification:

- Red-first check after adding 304 proxy/cookie assertions and before the
  source fix: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 601 assertions, 2 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 640 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 6068 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy credential helper/store/erase callbacks,
  cookie jar, packet/request builders, and WordPress proxy credential
  fixture/example.

Non-overlap:

- This does not repeat accepted username-only proxy helper activation, proxy
  credential/header scoping, proxy final-status store/erase for unexpected
  statuses, redirect cookie scoping, noProxy CIDR or wildcard semantics, SOCKS
  proxy handshakes, HTTPS-through-SOCKS, cleartext URL credential rejection, or
  optional service-announcement handling. It is bounded to accepted 304
  discovery responses and their combined proxy-credential plus cookie lifecycle.
