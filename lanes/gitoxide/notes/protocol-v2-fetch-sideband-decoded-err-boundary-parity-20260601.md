# Protocol v2 fetch sideband decoded ERR boundary parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T124444Z`

Base accepted HEAD: `687c594e4d06eca0127679aada46331adea32e3c`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-packetline/src/blocking_io/read.rs`: `fail_on_err_lines` classifies raw packet-line `ERR ` records before sideband decoding.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband-all channel 1 is unwrapped into response text before `read_line_to_string()` returns it to protocol parsing.
- `gix-protocol/src/fetch/response/blocking_io.rs`: decoded protocol v2 response lines are matched as section headers or parsed as section rows.

## Native Behavior

- Raw protocol v2 `ERR ...` packet-lines still surface as upload-pack errors before sideband decoding.
- Sideband-all channel-1 data that starts with `ERR ` is no longer reclassified as a raw upload-pack error after unwrapping.
- Such decoded channel-1 text now flows into the normal protocol response parser and is rejected as an unknown section header or unknown section row.
- The WordPress protocol-v2 fetch response fixture/example records this boundary so deployment tooling distinguishes raw server errors from malformed sideband-all protocol text.

## Verification

- Focused test before status/manifest updates: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 407 assertions, 0 failures`.
- Full lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 9337 assertions, 0 failures`.
- PHP lint: `php -l` on `FetchResponse.php`, `FetchResponseTest.php`, `wordpress-protocol-v2-fetch-response.php` fixture, and `wordpress-protocol-v2-fetch-response.php` example -> no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode with `JSON_THROW_ON_ERROR`.
- Diff check: `git diff --check -- lanes/gitoxide` -> clean.
- Focused assertion delta: `+3` over the accepted `FetchResponseTest.php` baseline.
- Conservative mapped coverage remains `1794 / 2886`; this deepens the represented protocol-v2 fetch sideband response cluster.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line, sideband-all, protocol-v2 fetch response, progress/error, and WordPress fixture/example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact `fetch.response` fixture parsing, clone/ref-in-want sideband fixtures, section fixture parsing, raw upload-pack ERR packet handling, empty channel-3 handler behavior, response-end/delimiter stopped-at handling, packet-line bounds, truncated sideband rejection, SHA-256 fetch IDs, smart HTTP upload-pack validation, send-pack status parsing, smart HTTP/SSH transport, pack/index, or reference transaction slices. It is bounded to the ordering boundary between raw `ERR ` packet classification and sideband-all channel-1 protocol text parsing.

Root harness status: `not run - isolated micro-slice`.
