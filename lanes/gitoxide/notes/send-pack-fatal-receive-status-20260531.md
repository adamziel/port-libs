# Send-Pack Fatal Receive-Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T092732Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/fixtures/v1/push.response`
- Git `gitprotocol-pack` side-band and report-status grammar: side-band channel 3 carries fatal errors, and receive-pack may send ERR pkt-lines before normal status.

Behavior added:

- `PushResponse::fromSidebandPacketLines()` now reports raw `ERR ...` pkt-lines as receive-pack runtime errors before trying to interpret the payload as a sideband channel byte.
- Fatal sideband channel 3 responses without any channel 1 report-status now surface as runtime sideband errors instead of falling through to a generic missing report-status parse error.
- The WordPress protocol-v1 push-response fixture/example now records a pre-receive hook decline sideband response and exposes a boolean smoke result for deployment tooling.

Verification evidence:

- Red-first focused check before the fix: `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` failed with `1 test files, 53 assertions, 2 failures`.
- Focused check after the fix: `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed with `1 test files, 56 assertions, 0 failures`.
- Full lane check after the fix: `php tools/run-tests.php lanes/gitoxide/tests` passed with `38 test files, 3800 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing packet-line receive-status parser, sideband decoder, and native PHP exception boundary. No shared support-library row or activation gate is proposed.

Non-overlap:

- This does not repeat the accepted report-status-v2 SHA-1/SHA-256 `old-oid`/`new-oid` proc-receive parsing, receive-status packet-line maximum guard, protocol-v2 fetch sideband parsing, smart HTTP redirect/cookie behavior, or advertisement ERR parsing. It is bounded to fatal receive-pack response bytes returned after a send-pack request.
