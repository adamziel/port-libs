# Send-Pack Receive-Status Expected-Ref Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T160213Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the Gitoxide
  transport boundary where receive-pack status data is delivered as nested
  packet lines on sideband channel 1.
- Git `send-pack.c::receive_status()` at upstream Git source commit
  `2be606a3bd1c916fcc14435556a807c6f5b5ce14` reads `ok`/`ng` status lines,
  searches for the reported ref among the pending command refs, ignores
  unknown or unexpected remote status refs after warning, and overwrites the
  previous remote status when a later report targets the same ref.
- The same function attaches report-status-v2 `option` lines to the matched
  successful status, so proc-receive rewrites remain keyed by the requested
  pseudo-ref while exposing the reported target ref through option data.

Behavior added:

- `PushResponse::forExpectedRefNames()` filters an already parsed response to
  the refs requested by the outgoing send-pack command. It preserves command
  ref order, ignores valid but unrequested remote status refs, and keeps the
  latest status for repeated requested refs.
- `ReceivePackClient::send()` now applies that filter with the refs from the
  `SendPackRequest` command, matching Git send-pack's response application
  boundary while leaving direct raw `PushResponse` parsing available.
- The WordPress protocol-v1 push-response fixture/example now covers an
  ignored unknown ref, a stale rejection followed by a later accepted report
  for the requested deployment branch, and a proc-receive-style rewrite whose
  effective ref remains available after filtering.

Verification evidence:

- Red-first focused probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(...) ...'`
  returned `2` statuses for a response containing one requested ref and one
  remote-only ghost ref.
- Focused checks after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 116 assertions, 0 failures`.
- Focused transport/client check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 456 assertions, 0 failures`.
- Full Gitoxide PHP lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 4957 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  packet-line parser, sideband receive-status parsing, `PushResponse`,
  `ReceivePackClient`, and WordPress protocol-v1 push-response fixture/example.
  No live service, credential store, external Git process, or shared dependency
  activation is needed.

Non-overlap:

- This extends the accepted send-pack receive-status compatibility slice
  without repeating optional OK text, bare NG fallback text, ignored future
  report-status-v2 options, repeated option overwrite behavior, fatal status
  parsing, line-feed trimming, packet-bound guards, SHA-1/SHA-256
  report-status-v2 object parsing, proc-receive fall-through parsing, smart
  HTTP redirect/cookie behavior, or receive-pack advertisement ERR handling.
  The new behavior is bounded to applying parsed receive-status reports to the
  refs requested by the send-pack command, including unknown-ref ignore and
  repeated requested-ref last-status-wins semantics.
