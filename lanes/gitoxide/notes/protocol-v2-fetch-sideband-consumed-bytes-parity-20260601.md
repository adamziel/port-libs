# Protocol v2 fetch sideband consumed-byte parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T175440Z`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-packetline/src/blocking_io/read.rs`: `StreamingPeekableIter` stops on configured protocol delimiters while leaving the underlying reader positioned after the stop packet.
- `gix-packetline/src/blocking_io/sidebands.rs`: `WithSidebands::stopped_at()` forwards that stop-packet state after channel-1 data, channel-2 progress, and channel-3 error sidebands are decoded.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 fetch response parsing stops after `flush`/`delimiter` section boundaries and hands following bytes to the next protocol message instead of consuming the stream blindly.

## Native PHP Delta

- `FetchResponse` now records `consumedBytes()` at the flush, delimiter, or response-end boundary that ended parsing.
- The cursor is preserved for sideband pack streams and `sideband-all` channel-1 response/pack streams, including progress callbacks before the stop packet.
- The WordPress fetch-response fixture/example now demonstrates parsing a persistent upload-pack byte stream by slicing the next response at `consumedBytes()`.

## Verification

- Baseline focused check before edits: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 462 assertions, 0 failures`.
- Focused check after source/test/example changes: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 484 assertions, 0 failures`.
- Focused assertion delta: `+22`.
- Final lint/diff/example evidence is recorded in the worker handoff.

## Dependency Closure

No new support component is needed. This reuses the native packet-line, sideband, fetch-response, protocol-v2 exchange, fixture, and WordPress example plumbing. No live network, credential store, provider, SSH process, tmux subagent, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat exact upstream `fetch.response` fixture parsing, section/deepen fixtures, ref-in-want or clone exchange fixtures, raw upload-pack `ERR` classification, response-end/delimiter terminator detection, sideband progress-handler cancellation, sideband-all capability inference, trim-end/UTF-8 behavior, SHA-256 fetch IDs, smart HTTP upload-pack validation, send-pack status parsing, smart HTTP/SSH transport, pack/index, object database, sparse-checkout, URL/refspec, or reference transaction slices. It is bounded to exposing the stream cursor that Gitoxide's sideband reader naturally preserves after a protocol v2 fetch response stop packet.

Root harness status: `not run - isolated micro-slice`.
