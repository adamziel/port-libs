# Protocol v2 fetch truncated sideband terminator parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T004349Z`

Accepted base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Upstream source truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/blocking_io/read.rs`: packet-line reads use `read_exact()`, so EOF before a complete packet or stop packet propagates as an I/O error.
- `gix-packetline/src/blocking_io/sidebands.rs`: the sideband reader only reaches clean EOF through the parent packet-line reader stopping at a configured flush/delimiter.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 fetch response parsing reads sections through the packet-line reader before handing the following `packfile` bytes to the sideband reader.
- `gix-packetline/tests/read/sideband.rs` and `gix-packetline/tests/read/mod.rs`: delimiter/flush stops are natural, but raw EOF/error state is not silently reinterpreted as a complete data-line stream.

## Native behavior covered

- `FetchResponse::fromV2PacketLines()` now rejects EOF before a protocol v2 section terminator with `fetch response: missing section terminator`.
- Sideband pack streams now reject EOF before a flush/terminator with `fetch response: missing sideband flush packet`.
- The guard applies to ordinary sideband streams and sideband-all section decoding before WordPress deployment tooling can import partial pack bytes.
- The WordPress protocol-v2 fetch response fixture/example now exposes a truncated-pack diagnostic.

## Evidence

- Red-first focused check after adding the truncation assertions before implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 276 assertions, 1 failures`; failure was `Expected RuntimeException was not thrown`.
- Focused check after implementation and WordPress fixture/example update: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 282 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 6491 assertions, 0 failures`.
- Expected mapped movement: `1680 / 2886` to `1681 / 2886`.
- Expected PHP pass movement: `6485` to `6491`.

## Dependency closure

No new support component is needed. This slice reuses the existing native PHP packet-line, protocol-v2 fetch-response, sideband, and remote-progress parsing code. No live network, credential store, provider, SSH process, or full upstream Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted exact `fetch.response` fixture parsing, sideband-all synthetic response parsing, raw upload-pack `ERR` packet handling, empty packet-line rejection, packet-line maximum bounds, smart HTTP upload-pack body parsing, SHA-256 fetch response IDs, clone/ref-in-want fixtures, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to rejecting truncated protocol-v2 fetch response sections and sideband pack streams that end before a packet-line terminator.

## Root status

Root harness not run - isolated Gitoxide micro-slice.
