# Smart HTTP Proxy Username Helper Parity - 2026-05-31

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260531T175958Z`

Accepted base: `e83ba68ab62e3e93ee2dcf9fc87ea144ffeb366d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/transport_options.rs`

Implemented behavior:

- `SmartHttpReceivePackTransport` now treats a proxy URL with username but no
  password, such as `http://proxy-user@proxy.example.test:8080`, as a proxy
  credential-helper action instead of emitting Basic auth with an empty
  password.
- The credential helper receives the normalized proxy URL with the username
  preserved as context, returns username/password material, and the transport
  keeps `Proxy-Authorization` in requester HTTP options rather than origin
  headers.
- Existing explicit `user:password` proxy URLs and accepted SOCKS username
  handshakes remain supported.
- The WordPress proxy credential fixture/example now records username-only
  proxy credential helper lookup, store callback, and no origin-header leak.

Verification:

- Red-first check after adding assertions and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 421 assertions, 1 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 471 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy URL normalization, proxy credential
  helper/store callbacks, and WordPress proxy credential fixture/example.

Non-overlap:

- This does not repeat accepted smart HTTP redirect cookie scoping,
  followRedirects, SOCKS proxy handshakes, HTTPS-through-SOCKS, cleartext URL
  credential rejection, optional service-announcement handling, proxy
  credential final-status store/erase, send-pack status parsing, or SSH
  receive-pack boundaries. It is bounded to Gitoxide's upstream behavior where
  proxy URL usernames activate proxy credential helpers before HTTP transport
  requests.
