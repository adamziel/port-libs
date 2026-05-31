# Protocol v2 fetch ref-in-want sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T183434Z`

Accepted base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

## Upstream source truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/tests/fixtures/v2/clone-only.response`
- `gix-protocol/tests/fixtures/v2/clone-ref-in-want.response`
- `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::clone`
- `gix-protocol/tests/protocol/fetch/v2.rs::ref_in_want`
- `gix-protocol/src/fetch/response/blocking_io.rs`
- `gix-protocol/src/fetch/response/mod.rs`
- `gix-packetline/src/blocking_io/sidebands.rs`

## Behavior added

- Added exact lane-local upstream `clone-only.response` bytes so the clone sideband fixture set now covers both no-keepalive and keepalive variants from the upstream `clone` test loop.
- Added exact upstream `clone-ref-in-want.response` fetch response body after the capability advertisement flush, covering a real `wanted-refs` section followed by sideband channel-1 pack data.
- Extended the WordPress protocol-v2 fetch response fixture/example with a ref-in-want branch response that preserves wanted-ref metadata and pack bytes for native deployment imports.

## Evidence

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 174 assertions, 0 failures`
- Focused check after this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 199 assertions, 0 failures`
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `39 test files, 5072 assertions, 0 failures`
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`
- PHP lint: changed/new PHP files under this slice report no syntax errors.
- Diff check: `git diff --check -- lanes/gitoxide` -> clean.

## Status delta

- Focused assertion delta: `+25`.
- Full Gitoxide PHP evidence moves from `5047` to `5072 pass / 0 fail`.
- Mapped Gitoxide coverage moves from `1605 / 2886` to `1607 / 2886`.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP packet-line, sideband, protocol-v2 section, wanted-ref, and pack-byte parsing code. No live network, SSH, credential store, provider, or full upstream Cargo workspace test was used.

## Non-overlap

This does not repeat accepted sideband-all synthetic response parsing, upstream `fetch.response` suffixless ACK fixture coverage, fetch-unshallow/deepen section fixtures, raw upload-pack `ERR` sideband handling, packet-line bounds, ls-refs, send-pack status parsing, smart HTTP, or SSH transport slices. It is bounded to the remaining exact upstream clone-only response fixture and the ref-in-want `wanted-refs` response fixture followed by sideband pack data.

Root harness status: `not run - isolated micro-slice`.
