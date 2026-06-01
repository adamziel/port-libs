# Protocol v2 fetch sideband-all capability exchange parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T140249Z`

Base accepted HEAD: `e887702f4d0ec0eb7033060d3f2943bfd7ebb562`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/src/command.rs`: protocol v2 fetch default features include `sideband-all` when the server advertises it.
- `gix-protocol/src/fetch/function.rs`: fetch checks those default features and installs remote-progress sideband handling before parsing a `sideband-all` response.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband readers unwrap channel 1 response/pack bytes and route channel 2/3 text through the caller handler.

## Native Behavior

- `ProtocolV2FetchExchange::fromPacketLines()` now derives sideband-all response decoding from the parsed protocol v2 `fetch` capability when it advertises `sideband-all`.
- The explicit `$sidebandAll` argument remains supported, but WordPress deployment exchange parsing no longer needs a separate caller flag for the Gitoxide default fetch path.
- The WordPress protocol-v2 fetch response fixture/example now covers a capability advertisement followed by sideband-all acknowledgements, shallow updates, wanted refs, progress, and channel-1 pack bytes.

## Evidence

- Red-first probe before implementation: a capability advertisement with `fetch=shallow sideband-all` followed by the existing sideband-all WordPress response failed with `InvalidArgumentException: fetch response: unknown or unsupported section header \x02remote: preparing WordPress blobless pack`.
- Baseline focused check before edits: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 407 assertions, 0 failures`.
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 426 assertions, 0 failures`.
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 9682 assertions, 0 failures`.
- PHP lint passed for `ProtocolV2FetchExchange.php`, `FetchResponseTest.php`, `wordpress-protocol-v2-fetch-response.php` fixture, and `wordpress-protocol-v2-fetch-response.php` example.
- Example smoke: `php -r '$summary = require "lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php"; if (($summary["sidebandAllCapabilityExchangeParsed"] ?? false) !== true) { fwrite(STDERR, "sideband-all capability exchange was not parsed\n"); exit(1); } if (($summary["sidebandAllCapabilityExchangeMessages"] ?? []) !== [["isError" => false, "text" => "remote: preparing WordPress blobless pack"], ["isError" => false, "text" => "Enumerating objects: 1, done."]]) { fwrite(STDERR, "sideband-all capability progress was not handled\n"); exit(1); } echo "example ok: sideband-all capability exchange parsed\n";'` -> `example ok: sideband-all capability exchange parsed`.
- Focused assertion delta: `+19`.

## Dependency Closure

No new support component is needed. This reuses the native PHP packet-line, protocol-v2 capabilities, fetch exchange, fetch response, sideband, and remote-progress parser components. No live network, provider, SSH process, credential store, or full upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat direct `FetchResponse::fromV2PacketLines()` sideband-all parsing, progress-handler cancellation, exchange handler forwarding, raw upload-pack `ERR` handling, empty packet-line rejection, packet-line maximum bounds, response-end/delimiter stopped-at handling, SHA-256 fetch IDs, smart HTTP upload-pack result parsing, suffixless ACK fixtures, ref-in-want fixtures, trim-end behavior, send-pack status parsing, receive-pack transport, or protocol-v2 `ls-refs` work. It is bounded to deriving sideband-all decoding from the advertised fetch capability at the higher-level protocol-v2 exchange boundary.

Root harness status: `not run - isolated micro-slice`.
