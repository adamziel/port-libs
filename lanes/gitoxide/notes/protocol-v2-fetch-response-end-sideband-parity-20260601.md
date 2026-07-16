# Protocol v2 fetch response-end sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T030406Z`

Base accepted HEAD: `0940b3464f3d7fe9344aca8d5b95a60e30c2d2c9`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/lib.rs`: `PacketLineRef::ResponseEnd` is packet-line `0002`.
- `gix-transport/src/client/non_io_types.rs`: transport-level `MessageKind::ResponseEnd` represents that stop packet.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband readers deliver channel-2/channel-3 text to progress handlers and stop cleanly when the underlying packet-line reader reaches a stop packet.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 fetch response parsing treats non-delimiter section stops as the end of the response, while `packfile` hands following bytes to the sideband reader.

## Native Behavior

- Added focused `FetchResponseTest` coverage for protocol v2 `0002` response-end terminators after an acknowledgement-only response.
- Added packfile-sideband coverage where response-end terminates channel-1 pack bytes without requiring a flush packet.
- Added sideband-all coverage where channel-2 progress and channel-3 error/advisory text are delivered to the caller handler before response-end terminates the pack stream.
- Extended the WordPress protocol-v2 fetch response fixture/example to expose the same stateless response-end boundary for deployment fetch diagnostics.

## Verification

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 296 assertions, 0 failures`.
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 315 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 7106 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`.
- PHP lint passed for `lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`, `lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`, and `lanes/gitoxide/tests/FetchResponseTest.php`.
- Expected mapped movement: `1713 / 2886` to `1714 / 2886`.
- Expected PHP pass movement: `7087` to `7106`.

## Dependency Closure

No new support component is needed. This reuses the lane-local packet-line, protocol-v2 fetch response, sideband, sideband-all, progress handler, and WordPress example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section sideband fixture parsing, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, truncated missing-flush rejection, SHA-256 fetch IDs, smart HTTP upload-pack body validation, progress-handler cancellation, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to response-end stop-packet handling around protocol v2 fetch sections and sidebanded pack bodies.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
