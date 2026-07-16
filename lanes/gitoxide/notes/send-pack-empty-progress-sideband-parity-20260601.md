# Send-Pack Empty Progress Sideband Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T073843Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  boundary where sideband channel 1 carries nested report-status packet lines
  and channel 2 carries progress.
- Git `sideband.c::demultiplex_sideband()` ignores a channel-2 packet whose
  payload has no bytes after the sideband designator, while still preserving
  non-empty progress messages around primary channel status bytes.

## Behavior Added

- `PushResponse::fromSidebandPacketLines()` now ignores empty channel-2
  progress keepalives instead of recording an empty progress message.
- The existing empty channel-3 keepalive behavior remains unchanged.
- Sideband channel 1 receive-status bytes can still be fragmented across
  packets while empty progress keepalives are skipped.
- The WordPress protocol-v1 push-response fixture/example now records a
  deployment response with empty progress keepalives before and after the
  meaningful progress/status packets.

## Evidence

- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromSidebandPacketLines($packet("\x02") ...) ...'`
  returned `array (0 => '')` for `progressMessages()`.
- PHP lint passed for changed PHP files:
  `lanes/gitoxide/src/PushResponse.php`,
  `lanes/gitoxide/tests/PushResponseTest.php`,
  `lanes/gitoxide/tests/ReceivePackTransportTest.php`,
  `lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`, and
  `lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`.
- Focused receive-status/transport check:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `2 test files, 1197 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  8061 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband accumulator, `PushResponse`, receive-pack client flow, and
WordPress push-response fixture/example. It does not shell out to Git, run live
provider tests, read credentials, or require a shared support-library activation
gate.

## Non-Overlap

This extends the accepted send-pack receive-status sideband cluster without
repeating fatal channel-3 handling, unpack-only fallback, empty unpack status,
empty rejected `ng` text, response-end/delimiter termination, packet-line
bounds, report-status-v2 option parsing, expected-ref filtering, smart HTTP
redirect/cookie/proxy behavior, SSH receive-pack boundaries, protocol-v2 fetch
sideband parsing, pack/index behavior, reference transactions, or object
database integrity checks. It is bounded to empty channel-2 progress keepalives
around sidebanded send-pack receive-status bytes.
