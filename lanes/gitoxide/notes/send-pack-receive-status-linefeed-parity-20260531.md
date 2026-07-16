# Send-Pack Receive-Status Linefeed Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T103334Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/src/lib.rs`
  documents `PacketLineRef::as_text()` as truncating a trailing newline only.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/tests/decode/mod.rs`
  covers that packet-line text removes LF explicitly and does not remove line
  feeds automatically while decoding raw packet data.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  remains the upstream send-pack fixture boundary: receive-status lines are
  packet-line text nested in sideband data.

Behavior added:

- `PushResponse` now trims receive-status text with the same single-LF boundary
  as upstream packet-line text decoding instead of stripping arbitrary trailing
  CR/LF bytes.
- Raw `ERR ...` receive-status packet messages are normalized through that same
  single-LF boundary.
- CR bytes in reported ref names are no longer silently normalized away, so
  deployment tooling rejects CR-polluted `ok <ref>` and `option refname <ref>`
  status lines while preserving CR in hook rejection messages.
- The WordPress protocol-v1 push-response fixture/example now includes a
  CR-polluted ref status smoke and reports it as rejected.

Verification evidence:

- Red-first focused check after adding assertions and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` failed
  with `1 test files, 72 assertions, 1 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 77 assertions, 0 failures`.
- Full lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `38 test files,
  4189 assertions, 0 failures`.
- PHP lint passed for changed PHP files:
  `lanes/gitoxide/src/PushResponse.php`,
  `lanes/gitoxide/tests/PushResponseTest.php`,
  `lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`, and
  `lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`.
- Example smoke passed:
  `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php` exited
  `0`.
- JSON metadata validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- Diff check passed: `git diff --check -- lanes/gitoxide`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  packet-line receive-status parser, `PushRefStatus`, and reference-name
  validation. No shell-out, live service, credential store, or shared support
  activation gate is needed.

Non-overlap:

- This does not repeat accepted send-pack fatal receive-status parsing,
  proc-receive fall-through status parsing, receive-status packet-line bounds,
  report-status-v2 SHA-1/SHA-256 object options, protocol-v2 fetch sideband
  parsing, smart HTTP redirect/cookie behavior, or transport advertisement ERR
  parsing. It is bounded to packet-line text trimming parity for receive-pack
  status bytes after a send-pack request.
