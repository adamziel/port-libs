Send-pack receive-status duplicate rejection report parity
=========================================================

- Worker slice: `gitoxide-send-pack-receive-status-parity-20260601T102133Z`
  on accepted base `7bd413e4c22aac9f2c5a76765dae0d142cb048cb`.
- Source truth: upstream Gitoxide
  `gix-transport/tests/client/git.rs::push_v1_simulated` and
  `gix-transport/tests/fixtures/v1/push.response` provide the nested
  sideband receive-status stream shape. Git `send-pack.c::receive_status()` at
  `866e6a391f466baeeb98bc585845ea638322c04b` keeps `ref_push_report` entries
  accumulated after matched `ok` status lines; Git `transport.c` then prints a
  final remote rejection through those report records when a duplicate `ng`
  status later changes the command result.
- Native PHP delta: `PushResponse::forExpectedRefNames()` now converts earlier
  report-status-v2 rewrite records into rejected `PushRefStatus` records when
  the final duplicate status for the requested ref is `ng`, preserving each
  rewritten effective ref, object ids, and the final remote rejection message.
  This keeps WordPress deployment review pushes from collapsing rewritten
  proc-receive destinations back to the pseudo-ref when a late hook rejects.
- Red-first evidence: a direct PHP probe before the implementation returned
  one rejected status with effective ref `refs/for/wp-deploy`; after the
  implementation the focused tests preserve rewritten `refs/heads/site-a` and
  `refs/heads/site-b` rejection records.
- Verification:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  `1 test files, 322 assertions, 0 failures`.
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 1048 assertions, 0 failures`.
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 8721 assertions, 0 failures`.
- Dependency closure: no new support component is needed. The slice reuses the
  existing bounded receive-pack status parser, sideband packet-line parser,
  stream transport client, and WordPress push-response fixture/example.
- Root harness status: not run - isolated micro-slice.
