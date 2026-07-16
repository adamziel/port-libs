# Send-Pack Receive-Status Delimiter Terminator Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T233833Z`

## Upstream Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  remains the Gitoxide receive-pack push boundary: sideband channel 1 carries
  nested packet-line encoded receive-status data, and channel 2 carries
  progress/advisory text.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/src/lib.rs`
  defines `PacketLineRef::Delimiter` for `0001`, alongside `Flush` and
  `ResponseEnd`, as a non-data packet-line kind.
- Existing lane source note
  `lanes/gitoxide/notes/send-pack-response-end-terminator-parity-20260531.md`
  records the Git `v2.54.0` send-pack boundary: `receive_status()` exits the
  ref-status loop when packet reading returns a non-normal packet. This slice
  applies the same already accepted response-end terminator rule to delimiter.

## Implementation

- `PushResponse::parseReportStatus()` now treats `delimiter` as a terminating
  non-data packet after valid `unpack` and ref status lines.
- `PushResponse::fromSidebandPacketLines()` already accumulates channel-1
  nested receive-status bytes; the new parser behavior applies to sidebanded
  receive-status streams as well.
- The WordPress push-response fixture/example now records a delimiter-ended
  deployment preview ref status.

## Evidence

- Red-first probe before the fix:
  - `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(... "0001") ...'`
  - Failure: `InvalidArgumentException: push response: unexpected delimiter packet in report-status stream`
- Focused after the fix:
  - `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  - Result: `1 test files, 182 assertions, 0 failures`
- Full lane check:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `40 test files, 6278 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/PushResponse.php`
  - `php -l lanes/gitoxide/tests/PushResponseTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`
  - `php -l lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  - Result: no syntax errors
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  - Result: exit 0
- Metadata validation:
  - `jq empty lanes/gitoxide/lane-status.json lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`
  - Result: exit 0
- Diff check:
  - `git diff --check -- lanes/gitoxide`
  - Result: exit 0

## Non-Overlap

This extends the accepted send-pack receive-status packet-line terminator
cluster without repeating report-status-v2 proc-receive option parsing,
expected-ref filtering, missing-ref handling, unrequested-option rejection,
line-feed trimming, packet-length/empty-line guards, sideband fatality,
response-end termination, smart HTTP redirect/cookie/proxy behavior, SSH
receive-pack boundaries, or protocol-v2 fetch sideband parsing. The new behavior
is bounded to `0001` delimiter termination for already valid receive-status
bytes.

## Dependency Closure

No new support component is needed. This reuses the lane-local packet-line
reader, sideband accumulator, receive-status parser, WordPress fixture/example,
and root test harness. It does not shell out to Git, run live provider tests,
read credentials, or require a shared support activation gate.
