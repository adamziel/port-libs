# Protocol v2 fetch sideband progress handler parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T015813Z`

Base accepted HEAD: `dc8bb5cac377111467dc403c9b9c75704db62cd4`

## Source Truth

- `gix-packetline/src/blocking_io/sidebands.rs`: `WithSidebands::with_progress_handler()` delivers progress and error channel text to the caller and returns an `interrupted by user` I/O error when the handler breaks.
- `gix-protocol/src/fetch/function.rs`: protocol v2 fetch installs the sideband progress handler before parsing `sideband-all` responses and again before reading ordinary sideband pack bytes.
- `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::fetch_acks_and_pack`: fetch response parsing hands the following pack body to the sideband reader after the `packfile` section header.

## Implementation

- Added an optional progress/error sideband handler to `FetchResponse::fromV2PacketLines()` and `FetchResponse::fromSmartHttpUploadPackResult()`.
- The handler receives trimmed sideband progress/error text and returns `false` to abort with `fetch response: interrupted by user`, matching Gitoxide's progress-handler break boundary.
- The handler path covers ordinary `packfile` sidebands, `sideband-all` progress before response sections, and smart HTTP upload-pack result bodies while preserving existing parsed `progressMessages()` and `errorMessages()`.
- The WordPress protocol-v2 fetch response fixture/example now records cancellation before sideband pack import.

## Verification

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 282 assertions, 0 failures`.
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 296 assertions, 0 failures`.
- Full Gitoxide lane check after implementation: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 6803 assertions, 0 failures`.
- PHP lint passed for `lanes/gitoxide/src/FetchResponse.php`, `lanes/gitoxide/tests/FetchResponseTest.php`, `lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`, and `lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP packet-line, sideband, protocol-v2 fetch-response, smart HTTP upload-pack body, and remote-progress parsing code. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section sideband fixture parsing, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, truncated sideband flush rejection, SHA-256 fetch IDs, smart HTTP upload-pack body validation, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to caller-visible sideband progress/error handling and interruption parity while reading fetch responses.
