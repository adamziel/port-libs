# Protocol v2 fetch ref-in-want exchange sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T084922Z`

Base accepted HEAD: `6c5f68290192c5bf57e0f3c2cca80b604bf38511`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/tests/fixtures/v2/clone-ref-in-want.response`: full protocol v2 response bytes contain a capability advertisement followed directly by `wanted-refs`, a delimiter, `packfile`, sideband channel-1 pack bytes, `0000`, and a final newline byte.
- `gix-protocol/tests/protocol/fetch/v2.rs::ref_in_want`: ref-in-want fetch skips the `ls-refs` advertisement, records wanted refs from the fetch response, and receives 641 pack bytes.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 `wanted-refs` and `packfile` sections are parsed by the same response reader used after capability negotiation.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband readers hand channel-1 bytes to the pack reader and stop at packet-line stop markers.

## Native Behavior

- `ProtocolV2FetchExchange::fromPacketLines()` now accepts a two-message exchange: capabilities plus fetch response, with no intervening `ls-refs` advertisement.
- The split helper ignores only trailing CR/LF bytes after a complete stop-packet-bounded exchange, matching the exact upstream `clone-ref-in-want.response` fixture boundary without accepting arbitrary trailing data.
- Added focused native coverage for the upstream-shaped ref-in-want exchange: no remote refs, empty `lsRefsAdvertisementBytes()`, wanted-ref preservation, 641 sideband-stripped pack bytes, pack trailer, and final `flush` terminator.
- Extended the WordPress protocol-v2 fetch response fixture/example with a ref-in-want exchange where deployment tooling can import wanted refs and pack bytes without shelling out to `git` or requiring a separate `ls-refs` response.

## Verification

- Red precheck for exact upstream full fixture before implementation: `php -r 'require "tools/bootstrap.php"; $full=file_get_contents("/home/claude/port-libs/.upstream-cache/gitoxide/gix-protocol/tests/fixtures/v2/clone-ref-in-want.response"); try { \PortLibs\Gitoxide\ProtocolV2FetchExchange::fromPacketLines($full); echo "unexpected ok\n"; } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage()."\n"; }'` -> `InvalidArgumentException: protocol v2 fetch exchange: truncated packet line length`
- Baseline focused test before implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 366 assertions, 0 failures`.
- PHP lint: `php -l lanes/gitoxide/src/ProtocolV2FetchExchange.php && php -l lanes/gitoxide/tests/FetchResponseTest.php && php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php && php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> no syntax errors.
- Focused test after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 379 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 8337 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit `0`.
- Expected PHP pass movement: `8324` to `8337`.
- Conservative mapped movement: no denominator increase; this deepens the already represented ref-in-want/fetch-sideband upstream cluster with full-exchange parsing.

## Dependency Closure

No new support component is needed. This reuses the native packet-line splitter, protocol-v2 capability parser, fetch-response parser, sideband decoder, wanted-ref parser, and WordPress example plumbing. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted exact fetch response body parsing, clone/ref-in-want response body sideband fixtures, section sideband fixture parsing, sideband progress-handler cancellation, response-end/delimiter stopped-at parsing, raw upload-pack `ERR` handling, packet-line maximum bounds, SHA-256 fetch IDs, smart HTTP upload-pack body validation, send-pack status parsing, or protocol-v2 `ls-refs` work. It is bounded to the full protocol-v2 ref-in-want exchange shape where no `ls-refs` advertisement appears before the sidebanded fetch response.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
