# Send-Pack Response-End Terminator Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T212833Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/src/lib.rs` models packet-line `0002` as `PacketLineRef::ResponseEnd`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/src/decode.rs` maps the `0002` hex prefix to that response-end packet before data-line decoding.
- Git `v2.54.0` `send-pack.c::receive_status()` reads normal `unpack` and ref status lines, then exits the status loop when packet reading returns a non-normal packet. This is the compatibility boundary for treating response-end as a terminator after valid receive-status data.

## Implementation

- `PushResponse::parseReportStatus()` now accepts `response-end` alongside `flush` as a terminating packet after valid report-status lines.
- `PushResponse::fromSidebandPacketLines()` now accepts an outer `response-end` as a terminating packet for sidebanded push responses.
- The WordPress protocol-v1 push-response fixture/example now records a response-end terminated deployment status response.

## Verification

- Red-first focused check before implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  - Result: `1 test files, 137 assertions, 2 failures`
  - Failure: `push response: unexpected response-end packet in report-status stream`
- Focused parser check after implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  - Result: `1 test files, 167 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 5795 assertions, 0 failures`
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

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP packet-line reader, sideband decoder, `PushResponse`, and WordPress push-response fixture/example. No live network, SSH process, credential store, provider, external Git command, or shared support-library activation is needed.

## Non-Overlap

This extends the accepted send-pack receive-status clusters without repeating report-status-v2 object option parsing, proc-receive fall-through parsing, expected-ref filtering, missing-ref handling, unrequested-option rejection, line-feed trimming, packet-length/empty-line guards, sideband fatality, smart HTTP redirect/cookie/proxy behavior, SSH receive-pack boundaries, or protocol-v2 fetch sideband parsing. The new behavior is bounded to packet-line `response-end` termination for send-pack receive-status bytes.
