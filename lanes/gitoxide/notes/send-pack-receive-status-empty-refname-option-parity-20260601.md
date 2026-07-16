# Send-Pack Receive-Status Empty Refname Option Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T140401Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  response boundary where sideband channel 1 carries nested receive-status
  packet-line data.
- Git `v2.54.0` `send-pack.c::receive_status()` splits report-status-v2
  option keys at the first post-key space and stores `option refname <value>`
  with `xstrdup_or_null(val)`. That distinguishes a valueless
  `option refname` from `option refname `, whose value is an explicit empty
  string.

## Behavior

- `PushRefStatus::withOption('refname', ...)` now preserves an explicit empty
  refname option value as `reportedRefName === ''`.
- A truly valueless `option refname` still records that report-status-v2 data
  was seen while leaving the effective ref as the requested ref.
- Normal requested ref names and non-empty reported ref names still go through
  `ReferenceName` validation; the empty value leniency is bounded to the
  remote report-status-v2 `refname` option.
- The WordPress protocol-v1 push-response fixture/example now records a
  proc-receive deployment response that returned a blank rewrite refname while
  still reporting old/new object IDs.

## Evidence

- Red-first probe before implementation:
  `PushResponse::fromReportStatusPacketLines(... "option refname \n" ...)->forExpectedRefNames(...)`
  returned `reportedRefName === null` and `effectiveRefName() ===
  refs/for/main`.
- Focused parser check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 359 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  9687 assertions, 0 failures`.
- PHP lint passed for `PushRefStatus.php`, `PushResponseTest.php`, the
  WordPress push-response fixture, and the WordPress push-response example.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited
  `0`.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the native packet-line reader,
sideband accumulator, `PushResponse`, `PushRefStatus`, and WordPress
push-response fixture/example. It does not shell out to Git, run live provider
tests, read credentials, or require a shared support-library activation gate.

## Non-Overlap

This extends the existing send-pack receive-status option parsing cluster
without repeating valueless options, malformed object option tolerance,
object-id prefix diagnostics, expected-ref filtering, missing/unpack-only
fallbacks, duplicate reject report handling, empty unpack or empty `ng` status,
response-end/delimiter terminators, packet-line bounds, fatal sideband errors,
smart HTTP redirect/cookie/proxy behavior, SSH receive-pack boundaries,
protocol-v2 fetch sideband parsing, pack/index behavior, reference
transactions, or loose-object integrity checks. It is bounded to the explicit
empty value of `option refname `.
