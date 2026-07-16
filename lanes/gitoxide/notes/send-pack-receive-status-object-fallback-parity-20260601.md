# Send-Pack Receive-Status Object Fallback Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T164434Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  receive-status boundary where sideband channel 1 carries nested packet-line
  encoded `unpack` and ref status bytes.
- Git `send-pack.c::receive_status()` plus `transport.c::print_ok_ref_status()`
  at source commit `866e6a391f466baeeb98bc585845ea638322c04b` keep
  report-status-v2 report entries even when `old-oid` or `new-oid` is omitted,
  then fall back to the originally requested update objects while displaying or
  tracking that report.

## Behavior Added

- `PushResponse::forExpectedUpdates()` now filters parsed receive-status
  records with full `PushUpdate` context instead of only expected ref names.
- `ReceivePackClient::send()` uses that contextual path, so report-status-v2
  records with options but no object-id options inherit the requested old/new
  object IDs.
- The raw `PushResponse` parser remains conservative: standalone parser calls
  still expose only remote-reported object IDs and leave omitted options as
  `null`.
- The WordPress receive-pack transport fixture/example now covers a deployment
  hook that reports `option forced-update` without object options while the
  client summary preserves the requested old/new objects.

## Evidence

- Red-first focused probe after adding the test and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 1298 assertions, 1 failures`; the failing value
  was expected old object `58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a` versus
  actual `NULL`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1305 assertions, 0 failures`.
- Parser regression verification:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 359 assertions, 0 failures`.
- Full Gitoxide lane verification:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  10205 assertions, 0 failures`.
- Changed PHP lint passed for `PushRefStatus.php`, `PushResponse.php`,
  `ReceivePackClient.php`, `ReceivePackTransportTest.php`,
  `wordpress-receive-pack-transport.php` fixture, and matching example.
- Lane JSON parse check passed for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide` passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  `0`.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband receive-status parser, `PushResponse`, `PushRefStatus`,
`PushUpdate`, `ReceivePackClient`, and WordPress receive-pack fixture/example.
No live provider, credential store, SSH process, Git binary, Cargo workspace, or
shared support-library activation gate was used.

## Non-Overlap

This extends the accepted send-pack receive-status cluster without repeating
object-format prefix parsing, malformed object-option tolerance, valueless or
empty refname options, expected-ref filtering, missing/unpack-only fallback,
unrequested-option rejection, duplicate rejection reports, sideband fatality,
empty progress keepalives, delimiter/response-end terminators, smart HTTP
redirect/cookie/proxy behavior, SSH receive-pack boundaries, or protocol-v2
fetch sideband parsing. It is bounded to client-context fallback object IDs for
matched report-status-v2 report records that omit `old-oid` and/or `new-oid`.

Root harness status: `not run - isolated micro-slice`.
