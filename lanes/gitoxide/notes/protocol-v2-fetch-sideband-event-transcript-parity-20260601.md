# Protocol v2 fetch sideband event transcript parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T163357Z`

## Upstream Source Truth

- `gix-packetline/src/blocking_io/sidebands.rs`: `WithSidebands` decodes sideband channel 1 as data, routes channel 2 progress and channel 3 error text through `handle_progress(is_error, text)`, and continues reading packet lines unless the handler breaks.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 response parsing keeps reading `acknowledgments`, `shallow-info`, `wanted-refs`, and `packfile` sections while the sideband reader filters progress/error sidebands.
- `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::fetch_acks_and_pack`: fetch response parsing installs the sideband progress handler before pack bytes are read.

## Native PHP Delta

- `FetchResponse` now retains an ordered `sidebandEvents()` transcript containing every sideband progress/error callback-equivalent event observed while parsing the response.
- Existing `progressMessages()` and `errorMessages()` stay intact for separated channel views. Empty channel-3 keepalives still do not create blank `errorMessages()` entries, but they are represented in `sidebandEvents()` to match the handler boundary.
- The WordPress protocol-v2 fetch response fixture/example now covers `sideband-all` progress and advisory/error packets interleaved between response sections before the following pack bytes.

## Verification

- Baseline before edits: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 443 assertions, 0 failures`.
- Changed PHP lint: `php -l lanes/gitoxide/src/FetchResponse.php && php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php && php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php && php -l lanes/gitoxide/tests/FetchResponseTest.php` -> no syntax errors.
- Focused tests: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 462 assertions, 0 failures`.
- Example smoke: `php -r '$summary = require "lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php"; if (($summary["interleavedSidebandAllParsed"] ?? false) !== true) { fwrite(STDERR, "interleaved sideband-all response was not parsed\n"); exit(1); } if (($summary["interleavedSidebandAllEvents"] ?? []) !== ($summary["interleavedSidebandAllMessages"] ?? null)) { fwrite(STDERR, "interleaved sideband-all transcript did not match callback order\n"); exit(1); } if (($summary["interleavedSidebandAllPackTrailer"] ?? "") !== "3b4b12f4cf6262d95e165b4517d71d0b9df20789") { fwrite(STDERR, "unexpected interleaved sideband-all pack trailer\n"); exit(1); } echo "example ok: interleaved sideband-all transcript parsed\n";'` -> `example ok: interleaved sideband-all transcript parsed`.
- Diff check: `git diff --check -- lanes/gitoxide` -> passed.

## Coverage And Non-Overlap

- Focused assertion delta: `+19` assertions in `FetchResponseTest.php`.
- Projected lane status delta: `phpPass` `10170 -> 10189`.
- Conservative mapped coverage remains `1807 / 2886`; this deepens the represented protocol-v2 fetch sideband cluster rather than claiming a new denominator row.
- This does not repeat exact upstream fetch.response fixture parsing, clone/ref-in-want sideband fixture imports, packet-line maximum bounds, raw upload-pack `ERR` classification, response-end/delimiter stopped-at handling, sideband-all capability inference, no-newline/trim-end behavior, invalid UTF-8 protocol-line rejection, SHA-256 fetch IDs, smart HTTP upload-pack parsing, send-pack status parsing, smart HTTP/SSH transport, pack/index, object database, or reference transaction slices. It is bounded to preserving the ordered Gitoxide sideband callback transcript through protocol v2 fetch response parsing.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, sideband, protocol-v2 fetch response, remote progress, fixture, and example plumbing. No live network, credential store, provider, SSH process, tmux subagent, or upstream Cargo workspace runner was used.
