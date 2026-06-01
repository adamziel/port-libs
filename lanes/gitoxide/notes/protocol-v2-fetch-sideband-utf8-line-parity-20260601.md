# Protocol v2 fetch sideband UTF-8 line parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T101815Z`

Base accepted HEAD: `c6b272456fc7eec0f7044976d02c4a6a795d2d5c`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband channel 1 is unwrapped before protocol response parsing, while channel 2 and channel 3 are delivered as byte slices to the progress handler.
- `gix-packetline/src/blocking_io/sidebands.rs::read_line_to_string()`: protocol response lines are converted with `std::str::from_utf8()`.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 section headers and rows are parsed through `readline_str()`, so invalid UTF-8 in response text fails before ACK, shallow, wanted-ref, or packfile section handling.

## Native Behavior

- `FetchResponse::fromV2PacketLines()` now rejects invalid UTF-8 only for protocol v2 response headers and section rows.
- The guard applies to ordinary v2 response lines and sideband-all channel-1 response text before parsing wanted refs or trusting a following packfile.
- Sideband channel-2 progress, channel-3 advisory/error text, and channel-1 pack bytes remain binary-safe PHP strings and can contain non-UTF-8 bytes.
- The WordPress protocol-v2 fetch response fixture/example now records both sides of the boundary: invalid UTF-8 wanted-ref text is rejected, while binary progress/error/pack payloads are preserved.

## Evidence

- Red-first probe before implementation:
  - `FetchResponse::fromV2PacketLines()` accepted a `wanted-refs` row containing `refs/heads/wp-\xFF` and preserved the following binary pack bytes.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php`
  - `1 test files, 391 assertions, 0 failures`
- Full lane check after implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 8705 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/FetchResponse.php`
  - `php -l lanes/gitoxide/tests/FetchResponseTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`
  - `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
  - all reported no syntax errors
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
  - exited `0`
- JSON validation:
  - `lanes/gitoxide/lane-status.json ok`
  - `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json ok`
- Diff check:
  - `git diff --check -- lanes/gitoxide`
  - exited `0`
- Focused assertion delta: `+12`.
- Expected mapped movement: `1779 / 2886` to `1780 / 2886`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, protocol-v2 fetch response, sideband, sideband-all, progress/error, and WordPress fixture/example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section sideband fixture parsing, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, response-end or delimiter stopped-at handling, progress-handler cancellation, SHA-256 fetch IDs, smart HTTP upload-pack body validation, ASCII/Unicode trim-end behavior, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to Gitoxide `readline_str()` UTF-8 validation for protocol v2 fetch response text while preserving byte-oriented sideband payloads.

Root harness status: `not run - isolated micro-slice`.
