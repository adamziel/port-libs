## Receive-Pack Transport Options Boundary Parity

Slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T074925Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`

The pinned upstream `gix-transport` HTTP options expose `connect_timeout`,
`low_speed_limit_bytes_per_second`, `low_speed_time_seconds`, `verbose`, and
`http_version`. The curl backend applies `verbose`, best-effort HTTP version,
connect timeout, and the paired low-speed limit/time only when both low-speed
values are non-zero.

Native PHP movement:

- `SmartHttpReceivePackTransport` now accepts `connectTimeout`,
  `lowSpeedLimit`, `lowSpeedTime`, `httpVersion`, and `verbose` in
  `httpOptions`.
- The options are validated at construction and propagated to every smart HTTP
  receive-pack request before proxy selection, so direct, proxied, redirected,
  advertisement, and POST request paths share the same transport boundary.
- Paired low-speed options are emitted only when both values are non-zero,
  matching upstream curl application.
- SOCKS connection setup uses `connectTimeout` for the proxy connection where
  the PHP stream backend can apply it.

Verification:

- Baseline before edit:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 917 assertions, 0 failures`.
- After edit:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 958 assertions, 0 failures`.
- Example smoke:
  `php -r '$summary = require "lanes/gitoxide/examples/wordpress-receive-pack-transport.php"; ...'`
  passed with `smart HTTP transport option smoke ok`.

Non-overlap:

- This slice avoids the already accepted smart HTTP proxy credential lifecycle,
  cookie scope, redirect preservation, optional service-announcement,
  content-type/header validation, noProxy CIDR/IPv6/trailing-dot behavior,
  port-qualified noProxy literal-token behavior, SOCKS handshake/TLS, and SSH
  receive-pack argv/auth boundary clusters.

Dependency closure:

- No new support component is needed. The existing injected smart HTTP
  requester remains the boundary for custom backends, and the native stream
  backend reuses its existing HTTP/SOCKS support.

Follow-up:

- Full upstream Cargo workspace execution remains outside this isolated
  micro-slice. A later transport slice can wire additional upstream HTTP
  options, such as SSL version ranges, if the lane needs that parity.
