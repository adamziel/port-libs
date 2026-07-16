# Receive-Pack Packet-Line Boundary Parity - 2026-05-31

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260531T223721Z`

Accepted base: `457d8df75c82fef3de304d8652d979a0fd3d1346`

Upstream source truth:

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `.upstream-cache/gitoxide/gix-packetline/src/lib.rs`
- `.upstream-cache/gitoxide/gix-packetline/src/decode.rs`
- `.upstream-cache/gitoxide/gix-transport/src/client/git/blocking_io.rs`

Implemented behavior:

- `StreamReceivePackTransport` now rejects packet lines whose total length is
  greater than 65,520 bytes before reading advertisement or response payloads.
- `ReceivePackAdvertisement::fromV1PacketLines()` now rejects the same
  over-limit packet length before calculating payload size or parsing refs.
- The limit matches upstream `gix-packetline`: 65,516 bytes of payload plus
  the 4-byte hexadecimal length header.
- The WordPress receive-pack fixture/example now records that oversized
  packet-line advertisements are rejected before deployment ref discovery.

Verification:

- Red-first focused check before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed `receive-pack transport rejects packet lines beyond upstream
  gix-packetline limit`; the stream path tried to read the over-limit payload
  and reported `receive-pack transport ended while reading advertisement packet
  payload`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed, `1 test files, 630 assertions, 0 failures`.
- Full lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed, `39 test files, 6024
  assertions, 0 failures`.
- Syntax checks passed for the changed PHP files.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- `git diff --check -- lanes/gitoxide` exited 0.

Status delta:

- PHP lane evidence moves from `6017` to `6024` assertions, a `+7` assertion
  delta.
- Conservative mapped Gitoxide coverage moves from `1653 / 2886` to
  `1654 / 2886` for receive-pack packet-line max-length boundary parity.

Dependency closure:

- No new support component is needed. This slice reuses the existing native PHP
  stream transport, receive-pack advertisement parser, packet-line helpers, and
  WordPress receive-pack fixture. No live network, credential store, SSH
  process, provider, or full upstream Cargo workspace runner was used.

Non-overlap and exclusions:

- This does not repeat accepted protocol v2 fetch packet-line bounds,
  send-pack response packet-line bounds, smart HTTP redirect/proxy/cookie
  behavior, SSH argument/auth boundaries, git-daemon request preflight, or
  receive-pack optional service-announcement handling.
- Root harness status: `not run - isolated micro-slice`.
