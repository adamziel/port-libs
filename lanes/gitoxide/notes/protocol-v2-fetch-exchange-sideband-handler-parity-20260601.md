# Protocol v2 fetch exchange sideband handler parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T062831Z`

Accepted base: `cc1b0ff669a7347b4e43610b8425ed481a9b7e5c`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/src/fetch/function.rs`
- `gix-protocol/src/fetch/response/blocking_io.rs`
- `gix-packetline/src/blocking_io/sidebands.rs`

## Behavior Verified

- Gitoxide installs a sideband progress handler while reading the fetch response for ordinary sideband pack data and for `sideband-all` negotiation streams.
- `ProtocolV2FetchExchange::fromPacketLines()` now accepts the same caller progress handler used by `FetchResponse::fromV2PacketLines()` and passes it into the parsed fetch response message.
- The WordPress protocol-v2 exchange fixture/example now confirms that a full exchange, from capability advertisement through ls-refs and fetch response, streams sideband progress to the caller handler and still preserves channel-1 pack bytes.
- A handler returning `false` while the exchange wrapper is reading fetch sideband progress aborts with `fetch response: interrupted by user`, matching the lower-level fetch response reader behavior.

## Evidence

- Baseline focused run before edits: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` => `1 test files, 346 assertions, 0 failures`
- Focused run after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` => `1 test files, 353 assertions, 0 failures`
- Full lane run after implementation: `php tools/run-tests.php lanes/gitoxide/tests` => `40 test files, 7765 assertions, 0 failures`
- PHP lint: `php -l` on `ProtocolV2FetchExchange.php`, `FetchResponseTest.php`, the WordPress protocol-v2 fetch-response fixture, and the WordPress protocol-v2 fetch-response example => no syntax errors
- Example smoke: `php -r '$summary = require "lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php"; if (($summary["cloneExchangeProgressHandled"] ?? false) !== true) { fwrite(STDERR, "clone exchange progress handler was not exercised\n"); exit(1); } echo "example ok: clone exchange progress handler exercised\n";'` => `example ok: clone exchange progress handler exercised`
- Diff check: `git diff --check -- lanes/gitoxide` => exit `0`
- Focused assertion delta: `+7`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, protocol-v2 exchange, fetch response, sideband, and remote-progress parsing components. No live network, credential store, provider, SSH process, tmux worker, or full upstream Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted direct `FetchResponse::fromV2PacketLines()` progress handler coverage, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, response-end or delimiter stopped-at handling, sideband-all section parsing, SHA-256 fetch IDs, smart HTTP upload-pack result parsing, suffixless ACK fixtures, ref-in-want fixtures, trim-end behavior, send-pack status parsing, receive-pack status parsing, smart HTTP transport, or protocol-v2 `ls-refs` work. It is bounded to preserving Gitoxide sideband progress callback parity through the higher-level protocol-v2 fetch exchange wrapper.

Root harness status: `not run - isolated micro-slice`.
