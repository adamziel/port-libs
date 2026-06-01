# Send-Pack Receive-Status Object Prefix Diagnostic Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T062946Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the Gitoxide
  transport boundary where send-pack receives nested report-status packet lines
  on sideband channel 1.
- Git `v2.54.0` `send-pack.c::receive_status()` parses `old-oid` and
  `new-oid` report fields through `parse_oid_hex_algop()` and does not require
  a whitespace separator after the object id. `hex.c::parse_oid_hex_algop_impl`
  advances the end pointer after the selected hash length, so a valid object-id
  prefix followed by a non-hex hook diagnostic suffix is still accepted.

## Behavior Added

- `PushRefStatus::withOption()` now accepts report-status-v2 `old-oid` and
  `new-oid` values when a valid 40- or 64-hex object-id prefix is followed by a
  non-hex diagnostic byte such as `#` or `:`.
- The parser still ignores short, malformed, or ambiguous all-hex option values,
  preserving the existing guard for incomplete object IDs.
- Direct `PushRefStatus` construction still validates explicit object IDs, so
  the leniency remains bounded to remote receive-status stream parsing.
- The WordPress protocol-v1 push-response fixture/example now covers a
  deployment hook that appends non-whitespace suffix diagnostics to valid
  object-id options.

## Evidence

- Red-first probe before the fix:
  `php -r 'require "tools/bootstrap.php"; ... "option old-oid " . str_repeat("a", 40) . "#hook diagnostic" ...'`
  returned `NULL` for `oldObject`.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 273 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited
  `0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  7812 assertions, 0 failures`.
- `php -l` passed for the changed source, test, fixture, and example PHP files.
- `php -r 'json_decode(...)'` validated the updated lane status and upstream
  manifest JSON files.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband accumulator, `PushResponse`, `PushRefStatus`, receive-pack
client flow, and WordPress push-response fixture/example. It does not shell out
to Git, run live provider tests, read credentials, or require a shared
support-library activation gate.

## Non-Overlap

This extends the accepted send-pack receive-status option parsing cluster
without repeating valueless options, malformed object-option ignores,
whitespace-delimited trailing diagnostics, proc-receive fall-through,
expected-ref filtering, missing expected refs, unpack-only fallback,
response-end/delimiter terminators, packet-line bounds, fatal sideband errors,
smart HTTP redirect/cookie/proxy behavior, SSH receive-pack boundaries,
protocol-v2 fetch sideband parsing, pack/index behavior, reference
transactions, or object database integrity checks.
