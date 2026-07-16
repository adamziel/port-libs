# Protocol v2 fetch raw ERR sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T102933Z`

Accepted base: `1681be96b403cae039655fef5cb4703982266b2d`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/blocking_io/read.rs`
- `gix-packetline/tests/read/sideband.rs::handling_of_err_lines`
- `gix-transport/src/client/capabilities.rs::Handshake::from_lines_with_version_detection`
- `gix-protocol/src/fetch/response/blocking_io.rs`

## Behavior Added

- `FetchResponse::fromV2PacketLines()` now treats raw `ERR ...` pkt-lines as upload-pack errors before sideband channel decoding, matching Gitoxide's persistent `fail_on_err_lines(true)` packet reader boundary.
- The guard applies both after a protocol v2 `packfile` header in ordinary sideband streams and before sideband-all section parsing.
- Valid sideband channel 1 data remains pack data even when the decoded pack chunk starts with `ERR `, so binary pack bytes are not confused with raw pkt-line errors.
- The WordPress protocol v2 fetch-response fixture/example now exposes a raw upload-pack error diagnostic for deployment fetch failures.

## Evidence

- Red-first check before implementation: raw `ERR backend died` after `packfile` returned `InvalidArgumentException: fetch response: invalid sideband 69`.
- `php -l lanes/gitoxide/src/FetchResponse.php`
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`
- `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
- `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php`: `1 test files, 142 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `38 test files, 4190 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`: exit `0`

## Status Delta

- Focused `FetchResponseTest.php` evidence moves from `135` to `142` assertions, a `+7` assertion delta.
- Full Gitoxide lane evidence moves from `4183` to `4190 pass / 0 fail`.
- Mapped Gitoxide coverage moves from `1539 / 2886` to `1540 / 2886` for the raw upload-pack ERR sideband boundary.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP packet-line and sideband parser and aligns the error boundary with upstream `gix-packetline`/`gix-protocol` behavior. No live network, credential store, provider, SSH process, or full Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted protocol v2 sideband-all response parsing, upstream clone pack trailer fixtures, protocol v2 fetch section sideband fixtures, packet-line maximum bounds, fetch sideband packet-bound guards, send-pack status parsing, or ls-refs advertisement/refspec-prefix work. It is bounded to raw upload-pack `ERR` pkt-line handling before sideband decoding.

Root harness status: `not run - isolated micro-slice`.
