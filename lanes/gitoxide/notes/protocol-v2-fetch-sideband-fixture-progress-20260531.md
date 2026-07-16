# Protocol v2 fetch sideband fixture progress parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T085147Z`

Accepted base: `3a431abadd85098a3180a1d68669384b82d27fff`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/tests/fixtures/v2/clone-only-with-keepalive.response`
- `gix-protocol/tests/fixtures/v2/clone-only-2.response`
- `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::{clone,clone_with_sidebands}`
- `gix-protocol/src/remote_progress.rs`

## Behavior Added

- Added `RemoteProgress::fromText()` to map gix-protocol sideband progress chunks into action, percent, step, and max fields.
- Added exact lane-local upstream v2 fetch sideband fixtures for clone pack responses, including the empty channel-1 keepalive packet from `clone-only-with-keepalive.response`.
- Added parser assertions for upstream pack prefixes, byte counts, trailers, progress chunk counts, empty keepalive handling, and sideband-all empty error keepalive omission.
- Updated the WordPress protocol v2 fetch response example to expose pack trailer and structured sideband progress fields for deployment diagnostics.

## Evidence

- `php -l lanes/gitoxide/src/FetchResponse.php`
- `php -l lanes/gitoxide/src/RemoteProgress.php`
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`
- `php -l lanes/gitoxide/fixtures/upstream-gix-protocol-v2-fetch-sideband.php`
- `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
- `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php`: `1 test files, 95 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `34 test files, 3165 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`: exit `0`

## Status Delta

- PHP lane evidence moves from `3124` to `3165` assertions, a `+41` assertion delta.
- Mapped Gitoxide coverage moves from `1498 / 2886` to `1499 / 2886` for the focused upstream fixture/progress sideband parity unit.

## Dependency Closure

No new support component is needed. This slice reuses existing packet-line, sideband, and fetch-response parsing and adds a small native PHP progress parser from upstream `gix-protocol` source semantics. No live network, credential store, provider, SSH process, or full Cargo workspace runner was used.

## Exclusions

Root harness status: `not run - isolated micro-slice`.

Full upstream Cargo workspace parity remains open; this slice is bounded to protocol v2 fetch sideband response fixture/progress behavior.
