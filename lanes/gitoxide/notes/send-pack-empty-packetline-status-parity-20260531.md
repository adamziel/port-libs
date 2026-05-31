# Send-Pack Empty Packet-Line Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T110938Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/src/decode.rs`
  rejects a packet-line length of `0004` as an invalid empty line before
  returning `PacketLineRef::Data`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-packetline/tests/decode/mod.rs`
  covers that invalid empty-line boundary.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the send-pack
  response boundary where receive-status bytes are nested packet lines inside
  sideband channel 1.

Behavior added:

- `PushResponse` now rejects `0004` packet lines at the packet decoder layer
  instead of accepting an empty data packet and failing later as a malformed
  status line.
- The guard applies to both direct report-status streams and sideband channel
  1 nested report-status streams.
- The WordPress protocol-v1 push-response fixture/example now exposes an
  empty packet-line rejection smoke for deployment tooling.

Verification evidence:

- Red-first focused check after adding assertions and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` failed
  with `1 test files, 79 assertions, 2 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 80 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  4341 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  packet-line receive-status parser, sideband decoder, `PushResponse`, and
  WordPress push-response fixture/example.

Non-overlap:

- This does not repeat accepted send-pack fatal receive-status parsing,
  proc-receive fall-through status parsing, packet-line maximum-length bounds,
  linefeed-only trimming, report-status-v2 object options, protocol-v2 fetch
  sideband parsing, smart HTTP redirect/cookie behavior, or transport
  advertisement ERR parsing. It is bounded to the upstream invalid-empty
  packet-line boundary for receive-pack status bytes after a send-pack request.
