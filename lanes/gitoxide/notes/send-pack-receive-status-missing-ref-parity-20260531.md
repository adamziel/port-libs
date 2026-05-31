# Send-Pack Receive-Status Missing-Ref Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T183608Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the Gitoxide
  transport boundary where send-pack receives receive-pack status as nested
  packet lines on sideband channel 1.
- Git `send-pack.c::receive_status()` at v2.54.0 reads remote `ok`/`ng`
  status lines into refs whose status is `REF_STATUS_EXPECTING_REPORT`, while
  Git `transport.c::print_ref_status()` reports any ref still in that state as
  `[remote failure] remote failed to report status`.
- Git `gitprotocol-pack` documents that report-status and report-status-v2
  list the unpack status and then status for each reference the receiver tried
  to update.

Behavior added:

- `PushResponse::forExpectedRefNames()` now preserves expected command order
  and adds a rejected `PushRefStatus` with message `remote failed to report
  status` for any requested send-pack ref that is absent from the remote
  report.
- `ReceivePackClient::send()` inherits the behavior because it already filters
  parsed responses through the command's requested refs. A remote that reports
  only an unrequested ghost ref now yields an unsuccessful response for the
  requested deployment ref instead of a false success.
- The WordPress protocol-v1 push-response fixture/example now records the
  missing expected status case as `missingExpectedStatusRejected`.

Verification evidence:

- Red-first focused probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(...)->forExpectedRefNames(["refs/heads/main"]) ...'`
  printed `accepted` for a response that contained only
  `ok refs/heads/ghost ignored by send-pack`.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 133 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 486 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 5065 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited 0.

Dependency closure:

- No new support component is needed. This reuses the existing native
  packet-line parser, `PushResponse`, `PushRefStatus`, `ReceivePackClient`,
  and the existing WordPress push-response fixture/example. No live service,
  credential store, external Git process, or shared dependency activation is
  needed.

Non-overlap:

- This extends the accepted send-pack receive-status requested-ref filtering
  and unrequested-option slices without repeating report-status-v2 SHA-1/SHA-256
  option parsing, proc-receive fall-through parsing, repeated option overwrite
  behavior, fatal sideband errors, packet-line bound guards, line-feed trimming,
  smart HTTP redirect/cookie/proxy behavior, SSH receive-pack argument safety,
  or receive-pack advertisement parsing. It is bounded to the upstream
  `REF_STATUS_EXPECTING_REPORT` case for requested refs that never receive a
  remote status line.
