# Protocol v2 fetch remote-progress u32 parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T223026Z`

Base accepted HEAD: `457d8df75c82fef3de304d8652d979a0fd3d1346`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/src/remote_progress.rs`: `next_optional_percentage()` parses a `usize` and then accepts it only if it fits `u32`.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband channel 2 carries progress text and channel 1 carries pack bytes.
- `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::fetch_acks_and_pack`: protocol v2 fetch responses parse sideband progress separately from pack data.

## Native Behavior Covered

- `RemoteProgress::fromText()` now matches Gitoxide's `u32` percentage boundary: `4294967295%` is retained, while `4294967296%` is ignored as a percentage.
- Step and maximum counters after an overflowing percentage are still parsed, matching the upstream parser's consumed-percent cursor behavior.
- The WordPress protocol-v2 fetch-response fixture/example now includes sideband progress at and above the u32 percentage bound while preserving channel-1 pack bytes.

## Evidence

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $progress = PortLibs\Gitoxide\RemoteProgress::fromText("Counting objects: 4294967296% (5/10)\r"); var_export([$progress?->percent, $progress?->step, $progress?->max]);'`
  - Result before fix: `[4294967296, 5, 10]`, which kept an out-of-range percentage.
- `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php`: `1 test files, 249 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 6028 assertions, 0 failures`
- `php -l lanes/gitoxide/src/RemoteProgress.php`
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`
- `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`: exit `0`
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`: `json ok`
- `git diff --check -- lanes/gitoxide`: clean

Focused assertion delta: `+11` over the accepted `FetchResponseTest.php` baseline of `238` assertions. Full Gitoxide lane evidence moves from `6017` to `6028` PHP assertions. Conservative mapped coverage remains `1653 / 2886`; this deepens the already represented protocol v2 fetch sideband remote-progress cluster.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, sideband, fetch-response, and remote-progress parsers. No live network, credential store, SSH process, provider, or full upstream Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted exact `v2/fetch.response` fixture coverage, section fixture coverage, clone/ref-in-want fixture coverage, SHA-256 object-format response coverage, raw upload-pack `ERR` handling, empty sideband keepalive handling, packet-line maximum bounds, send-pack status parsing, smart HTTP, SSH transport, pack/index, or reference transaction slices. It is bounded to Gitoxide remote-progress percentage overflow behavior observed through protocol v2 fetch sideband progress chunks.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
