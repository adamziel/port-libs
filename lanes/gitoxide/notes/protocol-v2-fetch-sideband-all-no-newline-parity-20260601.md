# Protocol v2 fetch sideband-all no-newline parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T151646Z`

Base accepted HEAD: `47ce92cb06fe604b95309ac683d21ead062958bb`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/blocking_io/sidebands.rs`: `WithSidebands::read_line_to_string()` forwards a single decoded sideband channel-1 data packet as a string without requiring a trailing linefeed.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 fetch response headers and section rows are matched after `trim_end()`, so newline-less sideband-all channel-1 protocol text remains valid response text.
- `gix-packetline/tests/read/sideband.rs::read_line_trait_method_reads_one_packet_line_at_a_time`: sideband readers return a decoded data packet exactly once, even when the packet payload has no LF.

## Native Behavior

- Added focused coverage for protocol v2 sideband-all fetch response section headers and rows that omit trailing LF bytes.
- The response still parses acknowledgements, shallow updates, wanted refs, progress, and channel-1 pack bytes.
- The WordPress fetch-response fixture/example now records compact no-newline sideband-all response parsing before pack import.

## Verification

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 426 assertions, 0 failures`.
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 443 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 9930 assertions, 0 failures`.
- PHP lint passed for `lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`, `lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`, and `lanes/gitoxide/tests/FetchResponseTest.php`.
- Example smoke: `php -r '$summary = require "lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php"; if (($summary["noNewlineSidebandAllParsed"] ?? false) !== true) { fwrite(STDERR, "no-newline sideband-all response was not parsed\n"); exit(1); } if (($summary["noNewlineSidebandAllPackTrailer"] ?? "") !== "3b4b12f4cf6262d95e165b4517d71d0b9df20789") { fwrite(STDERR, "unexpected no-newline sideband-all pack trailer\n"); exit(1); } echo "example ok: no-newline sideband-all parsed\n";'` -> `example ok: no-newline sideband-all parsed`.
- JSON validation passed for `lanes/gitoxide/lane-status.json`.
- Diff check passed: `git diff --check -- lanes/gitoxide`.
- Focused assertion delta: `+17`.
- Expected PHP pass movement: `9913 -> 9930`.
- Conservative mapped coverage remains `1799 / 2886`; this deepens the represented protocol-v2 fetch sideband response cluster.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, sideband-all, protocol-v2 fetch response, remote-progress, WordPress fixture, and example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, response-end or delimiter stop-state handling, progress-handler cancellation, empty error sideband handling, raw upload-pack `ERR` boundaries, packet-line length bounds, trailing ASCII/Unicode trim-end behavior, invalid UTF-8 line rejection, SHA-256 fetch IDs, smart HTTP upload-pack body validation, protocol-v2 exchange sideband-all capability inference, send-pack status parsing, or receive-pack transport work. It is bounded to Gitoxide sideband-all response text that is packet-line delimited but not LF-terminated.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
