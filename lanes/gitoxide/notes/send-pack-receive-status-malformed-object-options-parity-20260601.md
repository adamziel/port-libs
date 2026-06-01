# Send-Pack Receive-Status Malformed Object Options Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T052151Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the Gitoxide
  transport boundary where send-pack receives nested report-status packet lines
  on sideband channel 1.
- Git `send-pack.c::receive_status()` at
  `2be606a3bd1c916fcc14435556a807c6f5b5ce14` only assigns `old-oid` and
  `new-oid` report fields when `parse_oid_hex_algop()` succeeds; malformed
  option values remain ignored option records rather than fatal receive-status
  errors.

## Behavior Added

- `PushRefStatus::withOption()` now treats malformed non-empty `old-oid` and
  `new-oid` values as ignored report-status-v2 option records. The status still
  records that an option was seen, valid prior values remain intact, and valid
  later object IDs still overwrite earlier values.
- Missing and empty object option values keep the accepted valueless-option
  behavior.
- Direct `PushRefStatus` construction still validates explicit object IDs, so
  this leniency is bounded to remote report-status-v2 stream parsing.
- The WordPress protocol-v1 push-response fixture/example now covers a
  deployment hook that emits malformed object-option diagnostics before a valid
  later object ID.

## Evidence

- Red-first probe before the fix:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(... "option old-oid " . str_repeat("f", 63) ...) ...'`
  failed with `InvalidArgumentException: push response: option object id must be
  a 40- or 64-character hex object id`.
- After the fix, the same probe returns a successful response with `oldObject`
  equal to `null` and `hasReportOption()` equal to `true`.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 259 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 795 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited
  `0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  7590 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband accumulator, `PushResponse`, `PushRefStatus`, receive-pack
client flow, and WordPress push-response fixture/example. It does not shell out
to Git, run live provider tests, read credentials, or require a shared
support-library activation gate.

## Non-Overlap

This extends the accepted send-pack receive-status option parsing cluster
without repeating optional OK text, bare NG fallback, proc-receive
fall-through, valueless options, object-id trailing diagnostics, missing
expected refs, unrequested option rejection, unpack-only fallback,
response-end/delimiter terminators, packet-line bounds, fatal sideband errors,
smart HTTP redirect/cookie/proxy behavior, SSH receive-pack boundaries,
protocol-v2 fetch sideband parsing, pack/index behavior, reference
transactions, or object database integrity checks.
