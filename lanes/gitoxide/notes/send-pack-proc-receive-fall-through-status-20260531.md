# Send-Pack Proc-Receive Fall-Through Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T095617Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/fixtures/v1/push.response`
- Git `githooks` proc-receive documentation: a proc-receive status report can return `ok <ref>` followed by `option fall-through` to let receive-pack execute that command.
- Git `protocol-capabilities`/`gitprotocol-pack` report-status-v2 documentation: receive-pack/send-pack report-status-v2 extends report-status with option directives for proc-receive rewritten references.

Behavior added:

- `PushRefStatus` now carries a `fallThrough` flag for `option fall-through` status reports.
- `PushResponse` accepts `option fall-through` after an `ok` ref status, rejects duplicate fall-through options, rejects values on fall-through, and still refuses options after rejected refs.
- The WordPress protocol-v1 push-response fixture/example now includes a proc-receive fall-through pseudo-ref status for deployment tooling.

Verification evidence:

- Focused check: `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed with `1 test files, 71 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` passed with `38 test files, 4013 assertions, 0 failures`.
- PHP lint passed for changed PHP files.
- Example smoke passed: `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited `0`.
- Diff check passed: `git diff --check -- lanes/gitoxide`.

Dependency closure:

- No new support component is needed. This reuses the existing packet-line report-status parser and PushRefStatus option model.

Non-overlap:

- This does not repeat accepted send-pack fatal receive-status parsing, receive-status packet-line bounds, report-status-v2 SHA-1/SHA-256 `old-oid`/`new-oid`/`forced-update` parsing, protocol-v2 fetch sideband parsing, or transport redirect/cookie behavior. It is bounded to proc-receive `option fall-through` status parity.
