# real-upstream-corpus-pragma-schema-dynamic-20260531T052015Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T052015Z-0`

Base accepted HEAD: `597c96169f44cb49bb577675ba5900812102b596`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Ported scenario: `pragma.test 25.0`

`pragma.test 25.0` creates a generated-column table, a TEMP WITHOUT ROWID
table with primary-key and unique constraints, a temp unique index on `(c,b)`,
then runs `PRAGMA integrity_check`.

## PHP Coverage

- Added `SQLiteRealUpstreamPragmaSchemaDynamicIntegrityTempTest.php`
- 600 dynamic schema variants plus one source-citation case
- Focused run: `1 test files / 9605 assertions / 0 failures / 601 PASS lines`

The test exercises:

- `PRAGMA table_xinfo` generated-column hidden/not-null metadata.
- `PRAGMA table_list` `wr` and `strict` flags for WITHOUT ROWID temp-style
  schema records.
- `PRAGMA index_list` origin/unique rows for autoindex and created unique
  indexes.
- `PRAGMA index_xinfo` key/auxiliary column shape for a temp unique index and
  primary-key autoindex.
- `PRAGMA integrity_check` and `PRAGMA quick_check` over the generated-column
  database image.

## Non-Overlap

This does not repeat the accepted `pragma6.test` generated-schema-only
integrity batch, `schema6.test` rowid/WITHOUT ROWID equivalence batches,
`schema5.test` legacy constraint parser batches, `schema4.test` name-collision
batches, `pragma.test` 23 reload/index-xinfo batches, or PRAGMA table-valued
join/shadowing coverage. The new surface is the upstream `pragma.test 25.0`
combination of generated-column integrity with TEMP WITHOUT ROWID unique-index
PRAGMA metadata.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntegrityTempTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntegrityTempTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses existing lane-local
`SQLitePragmaSchemaCatalog`, schema record parsing, B-tree leaf page assembly,
and `SQLitePragmaIntegrityCheck` behavior.
