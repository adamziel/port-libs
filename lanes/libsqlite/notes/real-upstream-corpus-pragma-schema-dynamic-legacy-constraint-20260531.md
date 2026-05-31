# real-upstream-corpus-pragma-schema-dynamic-20260531T071507Z-0

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
  - `schema5-1.1` and `schema5-1.2`: `PRIMARY KEY(a) UNIQUE(a)` adjacent table constraints without a comma.
  - `schema5-1.3` and `schema5-1.4`: named `CONSTRAINT` wrappers with adjacent `CHECK(b<10)` and `UNIQUE(b)`.
  - `schema5-1.5` through `schema5-1.7`: `UNIQUE(a)` plus composite `PRIMARY KEY(b,c)` automatic index shape.

Behavior ported:

- Added a focused real-upstream PRAGMA/schema corpus test for SQLite legacy CREATE TABLE constraint syntax.
- The test verifies that the existing schema parser and PRAGMA catalog expose the same `table_info`, `index_list`, automatic-index metadata, and `index_xinfo` key columns for the legacy adjacent-constraint forms and comma-separated baseline forms.
- No production source change was needed because `SQLiteCreateTable` and `SQLitePragmaSchemaCatalog` already model the required adjacent-constraint behavior.

Focused coverage:

- `SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintTest.php`
- Focused PASS cases: `1001`
- Behavior assertions: `5505`

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintTest.php` -> `1 test files, 5505 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `2628093 -> 2629094` (`+1001` focused PASS lines)
- Mapped coverage remains `1589 / 1589`; this is behavior-backed PHP PASS-line growth over already mapped upstream inventory.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteCreateTable` automatic-index parsing and `SQLitePragmaSchemaCatalog` PRAGMA metadata rows.

Non-overlap:

- This does not repeat accepted `pragma.test` table/index metadata, `pragma3` data-version, `pragma4`/`pragma5` table-valued PRAGMA joins, `schema3` schema-cache refresh, `schema4` object namespace/rename behavior, `schema6` schema equivalence rows, trusted-schema policy, PRAGMA store-mode/schema3, JSON, B-tree, WAL, VFS, SELECT, or source-neutral cleanup clusters.
