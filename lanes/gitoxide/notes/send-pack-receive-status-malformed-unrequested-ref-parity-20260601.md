# Send-Pack Receive-Status Malformed Unrequested-Ref Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T175829Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  transport boundary where sideband channel 1 carries nested receive-status
  packet lines.
- Git `v2.54.0` `send-pack.c::receive_status()` splits the status ref name,
  searches only the pending push refs, ignores status lines for unknown refs,
  and treats a following `option` as an error when no matching `ok` report was
  active.

## Behavior Added

- `PushResponse` now exposes expected-ref-aware direct and sideband
  receive-status parsers.
- `ReceivePackClient::send()` uses the expected refs from the outgoing
  `PushCommand` while parsing the response, before applying object fallbacks.
- Malformed or empty remote status refs that are not part of the outgoing push
  no longer abort a client push if a later expected ref reports success.
- A report-status-v2 `option` after a skipped unrequested status still raises an
  error, preserving the accepted unrequested-option guard.
- The raw `PushResponse::fromReportStatusPacketLines()` parser remains strict:
  standalone parsing of malformed status refs still fails.
- The WordPress protocol-v1 push-response fixture/example now records this
  deployment boundary.

## Evidence

- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(...)->forExpectedRefNames(["refs/heads/main"]); ...'`
  reported `Reference name contains an invalid byte`.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`:
  `1 test files, 373 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`:
  `1 test files, 1326 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited
  `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 10398 assertions, 0 failures`.
- `php -l` passed for all changed PHP source, test, fixture, and example files.
- `php -r 'foreach (["lanes/gitoxide/lane-status.json", "lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  passed.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the native packet-line reader,
sideband receive-status parser, `PushCommand` update inventory,
`PushResponse`, `PushRefStatus`, `ReceivePackClient`, and WordPress
push-response fixture/example. No live provider, credential store, Git binary,
Cargo workspace, or shared support-library activation gate was used.

## Non-Overlap

This extends the accepted send-pack receive-status cluster without repeating
object-format prefix parsing, malformed object-option tolerance, valueless or
empty refname options, expected-ref filtering, missing/unpack-only fallback,
unrequested-option rejection, duplicate rejection reports, sideband fatality,
empty progress keepalives, delimiter/response-end terminators, smart HTTP
redirect/cookie/proxy behavior, SSH receive-pack boundaries, protocol-v2 fetch
sideband parsing, pack/index behavior, reference transactions, or object
database integrity checks. It is bounded to contextual parsing of malformed or
empty unrequested remote status refs before matched expected refs.

Root harness status: `not run - isolated micro-slice`.
