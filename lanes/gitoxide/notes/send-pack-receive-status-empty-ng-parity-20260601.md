# Send-Pack Empty NG Receive-Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T030531Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  response boundary where sideband channel 1 carries nested receive-status
  packet-line data.
- Git `v2.54.0` `send-pack.c::receive_status()` preserves the remote status
  bytes after `ng <ref> `. A bare `ng <ref>` falls back to `failed`, but a
  trailing separator with no bytes after it is an empty rejection message.

## Behavior

- `PushRefStatus` now allows an explicit empty rejection message while still
  rejecting rejected statuses that have no message field at all.
- `PushResponse` keeps the existing bare `ng <ref>` fallback to `failed`, and
  now preserves `ng <ref> ` as `message === ''` for direct and sidebanded
  receive-status streams.
- The WordPress protocol-v1 push-response fixture/example now records a
  deployment hook rejection that sent an empty status string.

## Evidence

- Red-first focused check before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  failed with `1 test files, 220 assertions, 1 failures` at
  `push response: rejected ref status requires an error message`.
- Focused parser check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  passed with `1 test files, 231 assertions, 0 failures`.
- Focused parser/transport gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `2 test files, 988 assertions, 0 failures`.
- Full lane check:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed with `40 test files, 7108 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited `0`.

## Dependency Closure

No new support component is needed. This reuses the native packet-line reader,
sideband accumulator, `PushResponse`, `PushRefStatus`, `ReceivePackClient`, and
the WordPress push-response fixture/example. No live network, SSH process,
credential store, provider test, external Git command, or shared support
activation gate is needed.

## Non-Overlap

This extends the accepted send-pack receive-status compatibility cluster
without repeating report-status-v2 object-option parsing, valueless option
handling, unrequested-option rejection, expected-ref filtering, unpack-only
fallbacks, response-end or delimiter terminators, packet-line bounds, fatal
sideband errors, smart HTTP redirect/cookie/proxy behavior, SSH receive-pack
boundaries, or protocol-v2 fetch sideband parsing. It is bounded to preserving
the empty remote rejection text in `ng <ref> ` status lines.
