# Protocol V2 ls-refs SHA-256 Advertisement Parity

Micro-slice: `gitoxide-protocol-v2-ls-refs-advertisement-parity-20260531T090234Z`

## Upstream Source Truth

- Inspected upstream `gix-protocol/src/handshake/refs/shared.rs`, where v2 `ls-refs` lines parse direct, symbolic, unborn, `(null)` symref, peeled, and symbolic peeled refs through `gix_hash::ObjectId::from_hex()`.
- Inspected upstream `gix-hash/src/object_id.rs`, where `ObjectId::from_hex()` accepts 40-hex SHA-1 IDs and 64-hex SHA-256 IDs when the hash kind is enabled.
- Inspected upstream `gix-protocol/tests/protocol/handshake.rs` for the existing v2 ref-line shape, and kept the previous SHA-1 fixture parity intact.

## Native PHP Delta

- `RemoteRef` now accepts both SHA-1 and SHA-256 object IDs while preserving lowercase normalization.
- `LsRefsCommand::parseV2RefLine()` now accepts SHA-256 IDs for direct, symbolic, and peeled v2 advertisement lines and still rejects invalid object-id lengths.
- The WordPress protocol-v2 ls-refs fixture now advertises `object-format=sha256` and uses SHA-256 branch/tag object IDs.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 44 assertions, 2 failures` at the SHA-1-only protocol object-id guard.
- Focused test after implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 62 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` => `36 test files, 3283 assertions, 0 failures`.
- PHP lint: `php -l lanes/gitoxide/src/LsRefsCommand.php`, `php -l lanes/gitoxide/src/RemoteRef.php`, `php -l lanes/gitoxide/tests/ProtocolV2Test.php`, and `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-ls-refs.php` => no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => exit `0`.
- Lane diff check: `git diff --check -- lanes/gitoxide` => exit `0`.
- JSON check: `lanes/gitoxide/lane-status.json` and `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` decode successfully.

## Dependency Closure

No new support component is needed. This reuses native PHP packet-line parsing, existing protocol capability parsing, and string object-id validation already present in the Gitoxide lane.

## Non-Overlap

This does not repeat accepted protocol-v2 request-byte parsing, protocol-v2 fetch sideband-all response handling, send-pack report-status-v2 SHA-256 handling, smart HTTP redirect/cookie transport work, SSH auth-boundary handling, reflog SHA-256 parsing, or object database integrity slices. It is bounded to SHA-256 object IDs in `ls-refs` remote-reference advertisements.
