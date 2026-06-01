# Protocol v2 fetch sideband Unicode trim-end parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T073420Z`

Base accepted HEAD: `0e6b89c861545d2e8159ac2fd07a33034e44e234`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 fetch response section headers are matched with Rust `str::trim_end()`.
- `gix-protocol/src/fetch/response/mod.rs`: `Acknowledgement::from_line()`, `shallow_update_from_line()`, and `WantedRef::from_line()` all trim with Rust `str::trim_end()` before splitting response lines.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband-all readers unwrap channel-1 data before protocol v2 response parsing, so the same trim-end boundary applies to sidebanded section lines.

## Native Behavior

- Added `ProtocolLine::trimEnd()` to apply Unicode-aware trailing whitespace trimming for Gitoxide protocol lines while falling back to the existing ASCII trim behavior for invalid UTF-8 byte strings.
- `FetchResponse`, `FetchAcknowledgement`, `FetchShallowUpdate`, and `FetchWantedRef` now use the shared trim helper before matching or splitting protocol v2 fetch response lines.
- Added focused `FetchResponseTest` coverage for sideband-all protocol v2 responses whose section headers, ACK, `ready`, shallow update, wanted-ref, and `packfile` header lines end in UTF-8 whitespace bytes.
- Extended the WordPress protocol-v2 fetch response fixture/example so deployment fetch diagnostics cover this sideband-all Unicode trim boundary before importing channel-1 pack bytes.

## Verification

- Red-first probe before implementation: a sideband-all `acknowledgments` header ending in `\xE2\x80\x83` failed with `fetch response: unknown or unsupported section header acknowledgments`.
- Focused test: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 366 assertions, 0 failures`.
- Full Gitoxide lane test: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 8067 assertions, 0 failures`.
- Focused assertion delta: `+13` over the accepted lane baseline of `8054` full-lane assertions.
- PHP lint passed for `ProtocolLine.php`, `FetchResponse.php`, `FetchAcknowledgement.php`, `FetchShallowUpdate.php`, `FetchWantedRef.php`, `FetchResponseTest.php`, the WordPress fetch-response fixture, and the WordPress fetch-response example.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- Diff check: `git diff --check -- lanes/gitoxide` -> exit `0`.

## Dependency Closure

No new support component is needed. This reuses native PHP packet-line, sideband-all, protocol v2 fetch response, remote-progress, and WordPress fixture/example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section sideband fixture parsing, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, response-end or delimiter stopped-at handling, progress-handler cancellation, SHA-256 fetch IDs, smart HTTP upload-pack body validation, ASCII-only trailing whitespace trimming, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to Gitoxide `trim_end()` parity for Unicode trailing whitespace on protocol v2 fetch sideband-all response lines.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
