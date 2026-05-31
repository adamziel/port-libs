# Protocol v2 fetch response SHA-256 sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T212739Z`

Accepted base: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-protocol/src/fetch/response/mod.rs`: `Acknowledgement::from_line`, `shallow_update_from_line`, and `WantedRef::from_line` parse object IDs via `gix_hash::ObjectId::from_hex`.
- `gix-hash/src/object_id.rs`: `ObjectId::from_hex` accepts 40-byte SHA-1 hex and 64-byte SHA-256 hex object IDs.
- `gix-protocol/src/fetch/response/blocking_io.rs`: protocol v2 section parsing collects acknowledgements, shallow updates, and wanted refs before the `packfile` section.
- `gix-packetline/src/blocking_io/sidebands.rs`: sideband channel 1 carries pack bytes while channel 2 carries remote progress.

## Native Behavior Covered

- `FetchAcknowledgement`, `FetchShallowUpdate`, and `FetchWantedRef` now accept and normalize both SHA-1 and SHA-256 object IDs.
- Protocol v2 fetch responses can parse 64-hex acknowledgements, shallow updates, and wanted refs before preserving sideband channel-1 pack bytes.
- The WordPress protocol-v2 fetch-response fixture/example now includes a SHA-256 object-format fetch response with sideband progress and a 32-byte pack trailer.

## Evidence

- Baseline focused check before the slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 221 assertions, 0 failures`
- Red check after adding the SHA-256 fixture/test before implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 214 assertions, 2 failures` at the 64-hex ACK parser
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 238 assertions, 0 failures`
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `39 test files, 5800 assertions, 0 failures`
- Focused assertion delta: `+17`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, protocol v2 response-section, sideband, and remote progress parsers. No live network, credential store, provider, SSH process, or full Cargo workspace runner was used.

## Non-Overlap

This does not repeat accepted protocol v2 `ls-refs` SHA-256 advertisements, send-pack report-status-v2 SHA-256/proc-receive parsing, sideband-all synthetic response parsing, upstream section fixture parsing, packet-line maximum bounds, raw ERR response handling, or clone exchange parsing. It is bounded to fetch response section object IDs in SHA-256 object-format repositories.

## Root Status

Root harness not run - isolated Gitoxide micro-slice.
