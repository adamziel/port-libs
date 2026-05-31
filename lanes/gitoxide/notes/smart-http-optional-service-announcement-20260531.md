# Smart HTTP Optional Service Announcement Parity - 2026-05-31

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260531T113052Z`

Accepted base: `c46d51851f90edc636ae7332660f056b95f53fd6`

Upstream source truth:

- `gix-transport/src/client/blocking_io/http/mod.rs`
- `gix-transport/src/client/blocking_io/http/traits.rs`

Implemented behavior:

- `SmartHttpReceivePackTransport` now accepts a receive-pack advertisement
  whose first pkt-line is already a ref/capability line.
- A `# service=git-receive-pack` announcement is still stripped when present.
- A `# service=` announcement for another service is still rejected before ref
  parsing.
- The WordPress receive-pack fixture/example records that deployment discovery
  works with servers that omit the optional service announcement.

Verification:

- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - passed, `1 test files, 415 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  - passed, `39 test files, 4423 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
  - passed.

Dependency closure:

- No new support component is needed. This slice reuses the existing smart HTTP
  requester boundary, receive-pack packet parser, native request builder, and
  WordPress fixture plumbing. Full cargo workspace and live provider tests were
  not run.

Non-overlap:

- This does not repeat accepted smart HTTP redirect/cookie/proxy/SOCKS work,
  SSH protocol-v2 auth context, git-daemon preflight, send-pack status parsing,
  fetch sideband parsing, or protocol v2 `ls-refs` advertisement slices. It is
  bounded to the optional receive-pack service-announcement boundary in the
  smart HTTP handshake.
