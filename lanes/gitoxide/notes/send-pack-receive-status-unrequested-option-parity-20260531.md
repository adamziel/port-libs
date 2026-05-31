# Send-Pack Receive-Status Unrequested-Option Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T175851Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the Gitoxide
  transport boundary where send-pack receives nested report-status packet lines
  on sideband channel 1.
- Git `send-pack.c::receive_status()` at source `866e6a391f466baeeb98bc585845ea638322c04b`
  ignores status lines for refs that were not part of the pending send-pack
  command, but an `option` line is valid only when a matching `ok/ng` directive
  was found. This means report-status-v2 options after an unknown remote ref
  must not be silently accepted while applying status to requested refs.
- Git `gitprotocol-pack` keeps the baseline report-status-v2 grammar for
  successful command status lines followed by optional `option` directives.

Behavior added:

- `PushRefStatus` now records that a report-status-v2 option line was seen,
  including ignored future extension options. Existing known options still
  update the parsed status as before.
- `PushResponse::forExpectedRefNames()` continues to ignore unrequested remote
  status refs without options, but now rejects an unrequested ref if the remote
  attaches any report-status-v2 option to it.
- `ReceivePackClient::send()` inherits this guard at the send-pack command
  boundary, so transport clients fail the same malformed unrequested-option
  response that raw `PushResponse` can still parse for diagnostics.
- The WordPress protocol-v1 push-response fixture/example now exposes this
  malformed deployment-hook status report as `unrequestedOptionRejected`.

Verification evidence:

- Red-first focused probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(...)->forExpectedRefNames(["refs/heads/main"]) ...'`
  printed `accepted` for an unrequested `refs/heads/ghost` status followed by
  `option refname refs/heads/other`.
- Focused parser check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 122 assertions, 0 failures`.
- Focused transport/client check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 457 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  5025 assertions, 0 failures`.
- Final lane checks: `php -l` passed on changed PHP files, `jq empty` passed
  for changed JSON metadata, `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited 0, and `git diff --check -- lanes/gitoxide` exited 0.

Dependency closure:

- No new support component is needed. This reuses the existing native
  packet-line parser, `PushResponse`, `PushRefStatus`, `ReceivePackClient`, and
  WordPress push-response fixture/example. No live service, credential store,
  external Git process, or shared dependency activation is needed.

Non-overlap:

- This extends the accepted send-pack receive-status requested-ref filtering
  without repeating optional OK text, bare NG fallback text, ignored future
  options on matched refs, repeated option overwrite behavior, expected-ref
  last-status-wins behavior, fatal status parsing, line-feed trimming,
  packet-bound guards, SHA-1/SHA-256 report-status-v2 object parsing,
  proc-receive fall-through parsing, smart HTTP redirect/cookie behavior, or
  receive-pack advertisement ERR handling. It is bounded to rejecting
  report-status-v2 options attached to status refs that were not requested by
  the outgoing send-pack command.
