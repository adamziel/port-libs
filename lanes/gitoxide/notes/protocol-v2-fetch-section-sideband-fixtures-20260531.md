# Protocol v2 fetch section sideband fixture parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T095610Z`

Accepted base: `633d868181ed471ba314711c0ee3aff27a79b97e`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/tests/fixtures/v2/fetch-unshallow.response`
- `gix-protocol/tests/fixtures/v2/clone-deepen-1.response`
- `gix-protocol/tests/fixtures/v2/clone-deepen-5.response`
- `gix-protocol/tests/fixtures/v2/fetch-no-pack.response`
- `gix-protocol/tests/fixtures/v2/fetch-err-line.response`
- `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader`
- `gix-packetline/src/blocking_io/sidebands.rs`

## Behavior Verified

- Added lane-local exact upstream v2 fetch response fixture bytes for unshallow, shallow clone, empty shallow clone, no-pack, and upload-pack `ERR` response cases.
- Verified `FetchResponse::fromV2PacketLines()` parses upstream `acknowledgments`, `shallow-info`, and `packfile` section ordering before sideband pack decoding.
- Verified sideband channel 1 pack bytes preserve upstream pack length/trailer, channel 2 progress counts remain separate from pack data, and the no-pack response terminates on flush with `hasPack=false`.
- Verified upstream upload-pack `ERR` packet-lines surface before section parsing as a runtime fetch response error.

## Evidence

- `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php`: `1 test files, 135 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `38 test files, 4034 assertions, 0 failures`
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`: no syntax errors
- `php -l lanes/gitoxide/fixtures/upstream-gix-protocol-v2-fetch-section-sideband.php`: no syntax errors
- `php -r 'json_decode(...)'` for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `json ok`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`: exit `0`
- `git diff --check -- lanes/gitoxide`: exit `0`
- Focused assertion delta: `+36` over the accepted `FetchResponseTest.php` baseline of `99` assertions.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, protocol v2 section, sideband, and remote progress parsers. No live network, credential store, provider, SSH process, or full Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted protocol v2 sideband-all synthetic response parsing, clone-only upstream sideband pack trailer fixtures, packet-line maximum bounds, fetch sideband packet-bound guards, send-pack fatal receive-status parsing, or ls-refs advertisement/refspec-prefix work. It is bounded to exact upstream v2 fetch response fixture cases that combine section parsing, sideband pack data, no-pack termination, and upload-pack `ERR` handling.

Root harness status: `not run - isolated micro-slice`.
