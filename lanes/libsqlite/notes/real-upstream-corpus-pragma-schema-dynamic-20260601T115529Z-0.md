# real-upstream-corpus-pragma-schema-dynamic-20260601T115529Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Upstream sections ported: `pragma-3.40` and `pragma-3.41`.

## Handoff Delta

- Extended `SQLitePragmaWritableSchemaIntegrityPlan` with index-rootpage swap integrity diagnostics for writable-schema reset.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexRootSwap20260601T115529ZTest.php`.
- Adds 1,003 focused TestRunner PASS cases and 26,013 focused assertions.
- Behavior covered: after two index rootpages are swapped under `writable_schema`, `pragma_integrity_check` reports row 2 as missing from both indexes because a BINARY index column differs, while rows 3 through 5 report byte-value mismatches because NOCASE keys compare equal but the stored bytes differ.
- Non-overlap: this owns only `pragma.test` `pragma-3.40`/`pragma-3.41`; it avoids accepted `pragma-3.20..3.25` writable-schema constraint checks, malformed leaf checks, generated/temp integrity, PRAGMA catalog reload, schema runtime expiry, data-version, cache-spill, page-count, JSON, WAL, VFS, B-tree, and SELECT clusters.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaWritableSchemaIntegrityPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaWritableSchemaIntegrityPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexRootSwap20260601T115529ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexRootSwap20260601T115529ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexRootSwap20260601T115529ZTest.php`
  - `1 test files, 26013 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaWritableIntegrityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexRootSwap20260601T115529ZTest.php`
  - `2 test files, 61024 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`

## Dependency Closure

No new support component needed; the slice extends the existing lane-local writable-schema PRAGMA integrity model.

## Root

Not run - isolated micro-slice.
