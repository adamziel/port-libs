# Send-Pack Receive-Status Valueless Option Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T015918Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  receive-status boundary where sideband channel 1 carries nested packet-line
  encoded `unpack` and ref status bytes.
- Git `send-pack.c::receive_status()` at source
  `2be606a3bd1c916fcc14435556a807c6f5b5ce14` treats an `option` line without a
  value as a valid report-status-v2 option record: missing `refname`,
  `old-oid`, and `new-oid` values leave the current report data absent instead
  of aborting the status stream.

## Behavior Added

- `PushRefStatus::withOption()` now accepts valueless `refname`, `old-oid`,
  and `new-oid` option lines, records that a report-status-v2 option was seen,
  and leaves the effective ref plus object ids unchanged.
- Non-empty malformed object-id option values remain rejected, preserving the
  accepted contiguous malformed-hex guard.
- The WordPress push-response fixture/example now covers a deployment hook that
  reports an accepted pseudo-ref with omitted rewrite details.

## Evidence

- Red-first probe before the fix:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(... "option refname\n" ...) ...'`
  printed `InvalidArgumentException: push response: refname option requires a
  value`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  passed with `1 test files, 220 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited `0`.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband accumulator, `PushResponse`, `PushRefStatus`, and WordPress
push-response fixture/example. It does not shell out to Git, run live provider
tests, read credentials, or require a shared support-library activation gate.

## Non-Overlap

This extends the accepted send-pack receive-status option parsing cluster
without repeating report-status-v2 object-id trailing diagnostics, unrequested
option rejection, expected-ref filtering, unpack-only fallback, response-end or
delimiter terminators, packet-line bounds, fatal sideband errors, smart HTTP
redirect/cookie/proxy behavior, SSH receive-pack boundaries, or protocol-v2
fetch sideband parsing. It is bounded to valueless report-status-v2 options
after a matched successful ref status.
