# Git Daemon Receive-Pack Virtual Host Boundary Parity - 2026-06-01

Slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T125303Z`

Source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/git/blocking_io.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/git/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs`

Upstream `gix-transport` builds the git-daemon service-request `host=` field
from the target URL, but its blocking connector also supports a virtual-host
override before emitting the packet line. The PHP receive-pack transport now
models that transport boundary with explicit `virtualHost` / `virtualPort`
arguments instead of reading process environment, preserving isolated-worker
secret hygiene while keeping the socket target validation separate from the
advertised daemon host parameter.

Mapped behavior:

- `GitDaemonReceivePackTransport::serviceRequestBytes()` and
  `serviceRequestBytesForUrl()` can route a receive-pack request to one socket
  host while advertising a distinct virtual `host=` value.
- Virtual hosts share the same delimiter/control-byte guards as socket hosts.
- A virtual port is rejected unless a virtual host is supplied, matching the
  upstream boundary that the override is a complete virtual-host value.
- IPv6 virtual hosts are bracketed before a port is appended, matching the
  existing daemon `host=` normalization.
- The WordPress receive-pack fixture records a proxy-fronted git-daemon
  deployment path where `socket.example.test:9418` advertises
  `host=git.example.test:9440`.

Focused verification:

- `php -l lanes/gitoxide/src/GitDaemonReceivePackTransport.php`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed: 1 file / 1187 assertions / 0 failures.
- `php tools/run-tests.php lanes/gitoxide/tests` passed: 40 files / 9345
  assertions / 0 failures.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  successfully.
- Focused example payload smoke passed for the virtual-host service request and
  rejection guards.
- `git diff --check -- lanes/gitoxide` passed.

Dependency closure:

- No new support component is needed. The behavior reuses existing pkt-line,
  stream, URL parsing, and receive-pack transport helpers.
