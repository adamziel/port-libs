# Protocol v2 fetch empty sideband packet parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T113005Z`

Base accepted HEAD: `cbea9b200f61d4e2d7924f8e87acdd9ed09af27f`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/decode.rs`
- `gix-packetline/src/blocking_io/sidebands.rs`
- `gix-protocol/src/remote_progress.rs`
- `gix-protocol/src/fetch/response/blocking_io.rs`

## Behavior Added

- `FetchResponse::fromV2PacketLines()` now rejects `0004` empty data packet-lines at the packet-line reader boundary, matching `gix-packetline` `DataIsEmpty` behavior before protocol v2 section or sideband parsing can reinterpret the packet.
- Empty channel-3 sideband payloads are ignored in ordinary sideband pack streams, matching Gitoxide remote-progress handling that treats empty error text as a keepalive/no-op instead of surfacing a blank remote error.
- Non-empty channel-3 error text remains captured, channel-2 progress remains separated from pack bytes, and channel-1 pack bytes continue to stream unchanged.
- The WordPress protocol v2 fetch-response fixture/example now proves that an empty channel-3 sideband keepalive does not create a blank deployment error while preserving the pack payload.

## Evidence

- Red-first probe before implementation:
  - `FetchResponse::fromV2PacketLines('0004')` produced `fetch response: unknown or unsupported section header ` instead of a packet-line decode error.
  - A `packfile` response containing `\x03` produced `['']` in `errorMessages()`.
- `php -l lanes/gitoxide/src/FetchResponse.php`: no syntax errors
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`: no syntax errors
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`: no syntax errors
- `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`: no syntax errors
- `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php`: `1 test files, 148 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4420 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`: exit `0`

## Status Delta

- Focused `FetchResponseTest.php` evidence moves from `142` to `148` assertions, a `+6` assertion delta.
- Full Gitoxide lane evidence moves from `4414` to `4420 pass / 0 fail`.
- Mapped Gitoxide coverage moves from `1558 / 2886` to `1559 / 2886` for the focused empty packet-line and empty sideband error keepalive parity boundary.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP packet-line and sideband parser and aligns edge-case behavior with upstream `gix-packetline` and `gix-protocol` remote-progress handling. No live network, credential store, provider, SSH process, or full Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted protocol v2 sideband-all response parsing, section sideband fixture parity, packet-line maximum bounds, raw upload-pack `ERR` packet handling, ls-refs advertisement work, send-pack status parsing, or packed/object/ref slices. It is bounded to empty packet-line rejection and empty channel-3 sideband keepalive handling in fetch response parsing.

Root harness status: `not run - isolated micro-slice`.
