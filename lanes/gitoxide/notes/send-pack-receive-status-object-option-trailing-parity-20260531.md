# Send-Pack Receive-Status Object Option Trailing Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T223228Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the Gitoxide
  transport boundary where send-pack receives nested report-status packet
  lines on sideband channel 1.
- Git `send-pack.c::receive_status()` at source
  `866e6a391f466baeeb98bc585845ea638322c04b` parses `old-oid` and `new-oid`
  with `parse_oid_hex_algop()` and does not require the option value to end
  immediately after the valid object id.

Behavior added:

- `PushRefStatus` now normalizes `old-oid` and `new-oid` option values by
  taking a leading 40- or 64-hex object id before trailing whitespace-delimited
  hook diagnostics.
- Contiguous malformed hex remains rejected, preserving the existing
  report-status-v2 object-id guard while matching the send-pack leniency for
  remote diagnostic suffixes.
- The WordPress protocol-v1 push-response fixture/example now covers
  proc-receive object-id options followed by stale/current deployment-hook
  diagnostics and CRLF-shaped object-id status text.

Verification evidence:

- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 172 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 623 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  6022 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited
  `0`.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  packet-line parser, sideband decoder, `PushResponse`, `PushRefStatus`,
  `ReceivePackClient`, and WordPress push-response fixture/example. No live
  network service, credential store, external Git process, or shared
  support-library activation is needed.

Non-overlap:

- This extends the accepted send-pack receive-status compatibility and
  expected-ref slices without repeating optional OK text, bare NG fallback,
  fall-through, missing expected refs, unrequested option rejection, packet
  length/empty-line guards, sideband fatal errors, response-end termination,
  smart HTTP redirect/cookie behavior, SSH transport behavior, pack/index
  behavior, reference transactions, or object database integrity checks.
