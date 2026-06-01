# Protocol v2 fetch sideband trim-end parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T052124Z`

Accepted base: `0c7896344a507c700afebaf2695a682269f3a18a`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/src/fetch/response/blocking_io.rs`
- `gix-protocol/src/fetch/response/async_io.rs`
- `gix-protocol/src/fetch/response/mod.rs`
- `gix-packetline/src/blocking_io/sidebands.rs`

## Behavior Verified

- Gitoxide uses `trim_end()` when matching protocol v2 fetch response section headers and when parsing `ACK`, `shallow`, `unshallow`, and `wanted-refs` lines.
- The PHP parser now trims trailing protocol whitespace before matching sideband-all section headers and section payload lines.
- A WordPress deployment fetch fixture covers sideband-all `acknowledgments`, `shallow-info`, `wanted-refs`, and `packfile` section lines with trailing spaces/tabs before CRLF while preserving the following channel-1 pack bytes and parsed remote progress.

## Evidence

- Baseline focused run before edits: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` => `1 test files, 333 assertions, 0 failures`
- Focused run after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` => `1 test files, 346 assertions, 0 failures`
- Full lane run after implementation: `php tools/run-tests.php lanes/gitoxide/tests` => `40 test files, 7589 assertions, 0 failures`
- PHP lint: `php -l` on `FetchResponse.php`, `FetchAcknowledgement.php`, `FetchShallowUpdate.php`, `FetchWantedRef.php`, `FetchResponseTest.php`, `wordpress-protocol-v2-fetch-response.php` fixture, and `wordpress-protocol-v2-fetch-response.php` example => no syntax errors
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` => exit `0`
- Diff check: `git diff --check -- lanes/gitoxide` => exit `0`
- Focused assertion delta: `+13`

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line, protocol v2 fetch response, sideband, wanted-ref, shallow-update, acknowledgement, and remote-progress parsers. No live network, credential store, provider, SSH process, or full upstream Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section sideband fixture parsing, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, response-end or delimiter stopped-at handling, progress-handler cancellation, SHA-256 fetch IDs, smart HTTP upload-pack body validation, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to upstream `trim_end()` parity for protocol v2 fetch response headers and section lines before sideband pack byte preservation.

Root harness status: `not run - isolated micro-slice`.
