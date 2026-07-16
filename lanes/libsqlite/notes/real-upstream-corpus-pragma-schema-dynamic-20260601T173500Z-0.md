# real-upstream-corpus-pragma-schema-dynamic-20260601T173500Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260601T173500Z-0`
Base accepted HEAD: `a25bbddc9233ed27761d6d1c0152bb434c9c08f2`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
  - `check_same_database_content 120`: WITHOUT ROWID primary-key/unique schema
    spellings stay equivalent across inline, table-level, redundant, and
    created-index forms.
  - `check_different_database_content 130`: rowid and WITHOUT ROWID table
    shapes remain observably distinct.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`
  - `pragma6-1.1`: TEMP WITHOUT ROWID table with column PRIMARY KEY, defaults,
    and UNIQUE constraints.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.8`: duplicate PRIMARY KEY terms keep rowid-table ordinal gaps.
  - `pragma-25.0`: TEMP WITHOUT ROWID table plus unique index is visible to
    PRAGMA integrity/schema paths.

## Behavior Ported

- `SQLitePragmaSchemaCatalog` now marks PRIMARY KEY columns on WITHOUT ROWID
  tables as implicit `notnull=1` in `PRAGMA table_info`,
  `PRAGMA table_xinfo`, and table-valued PRAGMA rowsets.
- Duplicate table-level PRIMARY KEY terms keep the existing rowid-table ordinal
  gap behavior from `pragma-6.8`, while WITHOUT ROWID metadata canonicalizes
  duplicate key terms to the stored key image.
- Explicit index `PRAGMA index_xinfo` rows on WITHOUT ROWID tables continue to
  append primary-key auxiliary columns and do not append rowid `cid=-1` terms.

## Focused Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicWithoutRowidPk20260601T173500ZTest.php`.
- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicWithoutRowidPk20260601T173500ZTest.php`
- Result: `1 test files, 18754 assertions, 0 failures`.
- New focused PASS cases: `1001`.
- Adjacent command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema6EquivalenceTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma6IntegrityGeneratedDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntegrityTempTest.php`
- Result: `4 test files, 29120 assertions, 0 failures`.

## Non-overlap

This does not repeat prior PRAGMA schema dynamic table/default/index/table-list
wide batches, schema6 equivalence coverage, generated integrity corpus, table
namespace/import admission, cache-spill/temp-store/application-id/version-state
PRAGMA slices, JSON/WAL/VFS/B-tree work, or source-neutral cleanup. The owned
surface is the PRAGMA-visible implicit NOT NULL metadata for WITHOUT ROWID
primary-key columns and duplicate-key canonicalization in that table shape.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and table-valued PRAGMA
helpers.
