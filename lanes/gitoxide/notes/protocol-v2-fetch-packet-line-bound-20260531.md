# Protocol v2 fetch packet-line bound parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T092236Z`

Accepted base: `c1dc98580d69cabeea0ebb72a1c7e33f357eaf2c`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/lib.rs`
- `gix-packetline/src/blocking_io/sidebands.rs`
- `gix-packetline/tests/read/sideband.rs`
- `gix-protocol/src/fetch/response/blocking_io.rs`

## Behavior Added

- `FetchResponse` now enforces Gitoxide's packet-line maximum before protocol v2 fetch sideband decoding.
- The exact upstream maximum remains accepted: 65,520 bytes total packet-line length, including the 4-byte hex length header and 65,516 bytes of payload.
- A packet-line larger than that maximum is rejected before channel-1 pack bytes, channel-2 progress, or channel-3 errors are interpreted.
- The WordPress protocol v2 fetch response fixture/example now records the 64k packet-line boundary used by deployment fetch diagnostics.

## Evidence

- Red-first focused check before implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` failed `caps fetch response packet lines at the gix-packetline 64k maximum` because the over-limit packet-line was accepted.
- Focused after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` passed `1 test files, 99 assertions, 0 failures`.
- Full lane after implementation: `php tools/run-tests.php lanes/gitoxide/tests` passed `38 test files, 3800 assertions, 0 failures`.
- Syntax checks passed for `lanes/gitoxide/src/FetchResponse.php`, `lanes/gitoxide/tests/FetchResponseTest.php`, `lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`, and `lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`.
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` exited `0`.
- `git diff --check -- lanes/gitoxide` exited `0`.

## Status Delta

- PHP lane evidence moves from `3796` to `3800` assertions, a `+4` assertion delta.
- Mapped Gitoxide coverage moves from `1513 / 2886` to `1514 / 2886` for the focused protocol v2 fetch packet-line bound.

## Dependency Closure

No new support component is needed. This slice reuses the existing packet-line and sideband parser boundary in native PHP and aligns its maximum length guard with upstream `gix-packetline`. No live network, credential store, SSH process, provider, or full Cargo workspace runner was used.

## Exclusions

Root harness status: `not run - isolated micro-slice`.

Full upstream Cargo workspace parity remains open; this slice is bounded to protocol v2 fetch response packet-line length parity before sideband decoding.
