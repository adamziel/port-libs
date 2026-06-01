# Receive-Pack Request Packet-Line Boundary Parity - 2026-05-31

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260531T234235Z`

Accepted base: `fb7d06d53486b39f2451378154d78e6da27eae83`

Upstream source truth:

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `.upstream-cache/gitoxide/gix-packetline/src/decode.rs`
- `.upstream-cache/gitoxide/gix-packetline/tests/encode/mod.rs`

Implemented behavior:

- `PushCommand` now uses the same request write boundary as upstream
  `gix-packetline`: 65,520 bytes total per pkt-line, or 65,516 bytes of
  payload after the 4-byte hexadecimal header.
- Receive-pack command lines and push-option lines accept the exact boundary
  and reject one byte over it before appending generated pack bytes.
- The WordPress receive-pack fixture/example records the max-payload boundary,
  the `fff0` max-frame header, and oversized command/push-option rejection for
  deployment push request generation.

Verification:

- `php -l lanes/gitoxide/src/PushCommand.php` passed.
- `php -l lanes/gitoxide/tests/PushCommandTest.php` passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/PushCommandTest.php` passed,
  `1 test files, 30 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed, `1 test files, 659 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed,
  `40 test files, 6315 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- `git diff --check -- lanes/gitoxide` exited 0.

Status delta:

- PHP lane evidence moves from `6306` to `6315` assertions, a `+9` assertion
  delta.
- Conservative mapped Gitoxide coverage moves from `1669 / 2886` to
  `1670 / 2886` for receive-pack request packet-line write-boundary parity.

Dependency closure:

- No new support component is needed. This slice reuses the existing native PHP
  receive-pack request encoder, protocol capability parser, packet-line
  framing, and WordPress receive-pack fixture. No live network, credentials,
  SSH process, provider, or full upstream Cargo workspace runner was used.

Non-overlap and exclusions:

- This does not repeat accepted receive-pack advertisement/read packet-line
  bounds, send-pack response packet-line bounds, protocol-v2 fetch packet-line
  bounds, smart HTTP redirect/proxy/cookie behavior, SSH argument/auth
  boundaries, git-daemon request preflight, or receive-pack optional
  service-announcement handling.
- Root harness status: `not run - isolated micro-slice`.
