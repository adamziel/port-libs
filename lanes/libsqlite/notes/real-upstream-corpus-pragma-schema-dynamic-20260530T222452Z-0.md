# real-upstream-corpus-pragma-schema-dynamic-20260530T222452Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T222452Z-0`

Base accepted HEAD: `9f789d799d368a95f9314c9ed366646dd5d17143`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`
  - `pragma6-1.0`: deserializes a database with ordinary and generated-column schema records.
  - `pragma6-1.1`: attempts a temp `WITHOUT ROWID` table with default expressions and redundant `UNIQUE` terms.
  - `pragma6-1.2`: verifies both `PRAGMA integrity_check` and `PRAGMA quick_check` complete without schema corruption output.

## PHP Coverage

- Added `SQLiteRealUpstreamPragmaSchemaDynamicPragma6Test.php`.
- Focused result: `1 test files, 4004 assertions, 0 failures`.
- PASS-line movement: 1,001 distinct focused TestRunner PASS cases.
- Mapped denominator movement: none; mapped inventory is already complete at `1589 / 1589`.

## Non-Overlap

This does not repeat the accepted `pragma.test` table-info/index-info, `pragma3.test` data-version, `pragma4.test` table-valued PRAGMA, `pragma5.test` runtime-list, `schema2.test`, `schema3.test`, `schema4.test`, `schema5.test`, or `schema6.test` dynamic batches. The new surface is upstream `pragma6.test` generated-column schema integrity/quick-check acceptance over varied generic database images.

## Dependency Closure

No new support component is needed. The batch reuses existing native PHP page assembly, record encoding, schema parsing, and `SQLitePragmaIntegrityCheck` behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicPragma6Test.php`
  - `1 test files, 4004 assertions, 0 failures`
