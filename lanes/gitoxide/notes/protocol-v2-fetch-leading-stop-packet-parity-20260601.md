# Protocol v2 fetch leading stop-packet parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T232410Z`

Base accepted HEAD: `e92015d5f1d2545bb6a0e1bbacb4f4ca2f995a63`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 fetch response parsing calls `readline_str()` for a message headline and returns `Could not read message headline` when no data line is read.
- `gix-transport/src/client/blocking_io/bufread_ext.rs`: protocol v2 readers stop at delimiter and flush packets, so a leading stop packet produces no headline.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband-all channel-1 empty data packets are skipped, while channel-2 progress is delivered to the handler before the next packet is read.

## Native Behavior

- `FetchResponse::fromV2PacketLines()` now rejects leading `flush`, `delimiter`, and `response-end` packets before any response section headline.
- The same guard applies after sideband-all progress and empty channel-1 keepalives if the first meaningful packet is a stop packet.
- Existing valid no-pack responses remain accepted when an `acknowledgments` section is followed by `flush` or `response-end`.
- The WordPress protocol-v2 fetch response fixture/example now records the rejected sideband-all progress-before-empty-response boundary.

## Evidence

- Red-first probe before implementation: `FetchResponse::fromV2PacketLines("0000")` returned `hasPack=false` and `terminator=flush` instead of rejecting the missing headline.
- `php -l lanes/gitoxide/src/FetchResponse.php`
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`
- `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
- `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 492 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`
- `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 10811 assertions, 0 failures`
- `git diff --check -- lanes/gitoxide` -> clean

Focused/full lane assertion delta: `phpPass` moves `10803 -> 10811` (`+8`). Conservative mapped coverage remains `1819 / 2886`; this deepens the represented protocol-v2 fetch sideband response cluster.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, sideband-all, fetch-response, progress-handler, and WordPress fixture/example plumbing. No live network, credential store, provider, SSH process, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section fixture parsing, raw upload-pack `ERR` handling, decoded sideband-all `ERR` boundaries, empty channel-3 handler behavior, response-end/delimiter stopped-at parsing after sections, consumed-byte cursor behavior, packet-line bounds, truncated sideband rejection, SHA-256 fetch IDs, smart HTTP upload-pack validation, send-pack status parsing, smart HTTP/SSH transport, pack/index, or reference transaction slices. It is bounded to rejecting stop packets before any protocol-v2 fetch response headline.

Root harness status: `not run - isolated micro-slice`.
