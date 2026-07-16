# Git-Daemon Receive-Pack Value-Only Extra Parameter Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260531T151842Z`

Accepted base: `4678f572bda3b3437f0480f42476c787d671be75`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/git/mod.rs`
  defines generic git service-request construction for `Service::ReceivePack`
  and `Service::UploadPack`.
- `message::connect()` emits extra parameters as NUL-delimited `key=value`
  strings when a value exists and as bare protocol keys when the value is
  absent.
- The upstream tests `version_2_without_host_and_version_and_extra_parameters`
  and `with_host_without_port_and_extra_parameters` cover the `value-only`
  form alongside `key=value`.

Behavior added:

- `GitDaemonReceivePackTransport::serviceRequestBytes()` now accepts
  upstream-style value-only protocol keys such as `session-id` in addition to
  `key=value` parameters.
- Empty keys, empty values, leading-digit keys, whitespace-delimited
  parameters, and control bytes remain rejected before service-request pkt-line
  construction, socket connection, or stream writes.
- The WordPress receive-pack fixture/example now records a git-daemon
  deployment service request with `version=2`, `session-id`, and
  `object-format=sha1` extra parameters.

Verification:

- `php -l lanes/gitoxide/src/GitDaemonReceivePackTransport.php` - passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` - passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` - passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` - passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - passed, `1 test files, 435 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` - passed,
  `39 test files, 4744 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` - passed.

Dependency closure:

- No new support component is needed. This reuses the existing native
  git-daemon receive-pack service-request constructor, pkt-line writer, and
  WordPress transport fixture/example. Live git-daemon/network provider tests
  remain excluded from this isolated lane slice.

Non-overlap:

- This does not repeat accepted smart HTTP optional service announcements,
  redirect/cookie/proxy behavior, SSH protocol-v2 or legacy-scheme boundaries,
  git-daemon URL control-byte/host-delimiter validation, or receive-pack
  stream watchdog/status parsing. It is bounded to upstream value-only
  extra-parameter admission for git-daemon receive-pack service requests.
