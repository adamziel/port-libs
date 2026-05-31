# Gitoxide Pack Delta Header Outcome Parity - 2026-05-31

## Upstream Source Truth

- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/file/decode/header.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/file/decode/entry.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/data/file.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/malformed.rs`.
- Focused upstream behavior: `File::decode_header()` follows OFS_DELTA and REF_DELTA bases to report object kind, decoded result size, and `num_deltas` without requiring callers to materialize the final object body. The malformed large padded-delta regression also shows header probing can recover the result size while full entry decode still rejects the declared-size mismatch.

## Native PHP Delta

- `PackData` now has header-resolution APIs:
  - `readObjectHeader()`
  - `readObjectHeaderAtOffset()`
  - `readObjectHeaderWithExternalBases()`
  - `readObjectHeaderWithExternalBaseResolver()`
- Pack entry metadata parsing is separated from full payload inflation, so header resolution can walk delta chains and inspect the delta result-size header without constructing the final object.
- In-pack OFS_DELTA chains report the final base type, target result size, and accumulated delta count.
- Thin REF_DELTA headers can resolve from external base metadata and preserve an external base delta count when the resolver provides one.
- `ObjectDatabase::readHeader()` now uses the pack header path for packed objects instead of full object materialization.
- `examples/wordpress-pack-data.php` now reports `deltaHeaderProbe` for the compacted WordPress content-pack delta.

## Red-First Evidence

- Before the implementation, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new header tests because `PackData::readObjectHeader()` was undefined.
- After the implementation, `PackDataTest.php` reports packed two-link OFS_DELTA headers as `blob`, final result size, and `numDeltas = 2`.
- After the implementation, thin REF_DELTA headers resolve from external base metadata and add the external resolver's `numDeltas` value.
- After the implementation, a large padded delta can report the header result size while `readObjectWithExternalBases()` still rejects the full malformed pack entry with the declared-size mismatch.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` - passed, `1 test files, 95 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` - passed, `1 test files, 110 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PackBuilderTest.php` - passed, `1 test files, 146 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` - passed, `39 test files, 4269 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/PackData.php` - passed.
- `php -l lanes/gitoxide/src/ObjectDatabase.php` - passed.
- `php -l lanes/gitoxide/tests/PackDataTest.php` - passed.
- `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php` - passed.
- `php -l lanes/gitoxide/examples/wordpress-pack-data.php` - passed.
- `php lanes/gitoxide/examples/wordpress-pack-data.php` - passed.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` - passed.
- `git diff --check -- lanes/gitoxide` - passed.

## Non-Overlap

This slice does not repeat the accepted pack declared-size guards, oversized delta header guards, delta result-buffer guards, or pack-entry metadata canonicalization. It is bounded to Gitoxide's `decode_header()` outcome behavior for pack delta chains and the ObjectDatabase packed-header fast path.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib streaming inflate support, existing `PackData`, `PackIndex`, `GitObject`, `ObjectDatabase`, and WordPress pack fixtures. Full Cargo workspace parity remains excluded for this isolated worker because it would hydrate and build the large feature-heavy upstream workspace.
