# Protocol v2 fetch sideband stopped-at parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T041533Z`

Base accepted HEAD: `431362468a9b0d67073256297cf9e0acadb56383`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-packetline/src/read.rs`: `StreamingPeekableIterState::stopped_at()` preserves the packet-line that stopped iteration.
- `gix-packetline/src/blocking_io/sidebands.rs`: `WithSidebands::stopped_at()` forwards that stop-packet state after sideband reads.
- `gix-transport/src/client/blocking_io/bufread_ext.rs`: protocol v2 readers reset with delimiter and flush stop packets.
- `gix-packetline/tests/read/mod.rs::peek_non_data`: packet readers distinguish configured stop packets from unexpected EOF/non-data states.

## Native Behavior

- `FetchResponse` now preserves the stop packet that ended a parsed response: `flush`, `delimiter`, or `response-end`.
- Existing flush and response-end responses expose the same terminator state without changing existing pack, progress, or error parsing.
- Added focused delimiter-terminated protocol v2 sideband pack coverage, including `sideband-all` channel-2 progress, channel-3 advisory text, empty channel-1 keepalive, and channel-1 pack-byte preservation.
- Extended the WordPress protocol-v2 fetch response fixture/example so deployment diagnostics can distinguish a delimiter section boundary from a flush-only response end.

## Verification

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 315 assertions, 0 failures`.
- PHP lint passed for `lanes/gitoxide/src/FetchResponse.php`, `lanes/gitoxide/tests/FetchResponseTest.php`, `lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`, and `lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`.
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 333 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 7320 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- Diff check passed: `git diff --check -- lanes/gitoxide`.
- Expected PHP pass movement: `7302` to `7320`.

## Dependency Closure

No new support component is needed. This reuses the native packet-line, protocol-v2 fetch response, sideband, sideband-all, progress handler, and WordPress example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, response-end stop-packet parsing, progress-handler cancellation, truncated missing-flush rejection, raw upload-pack `ERR` handling, packet-line maximum bounds, clone/ref-in-want sideband fixtures, smart HTTP upload-pack body validation, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to retaining the sideband reader stop-packet kind and adding delimiter-terminated sideband pack parity.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
