# Real Upstream Corpus: B-tree Index Dynamic Wide Schema

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T165205Z-0`

Base accepted HEAD: `9dc20dce32143ddf9ade7c84c6244ce48fb3e470`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`

Scenarios ported:

- `index2-1.1` through `index2-1.5`: 1000-column table creation, insert, projection, row count, and `c1000` sum behavior.
- `index2-2.1` through `index2-2.2`: 1000-column index creation and covering index order scan for `ORDER BY c1, c2, c3, c4, c5, c6 LIMIT 5`.
- `index3-1.1` through `index3-1.4`: failed UNIQUE index build over duplicate rows must leave no index residue and allow commit/integrity to stay clean.
- `index3-2.1` through `index3-2.5`: backwards-compatible string/quoted identifiers in primary key, unique, and ordinary index definitions.

Patch summary:

- Added `SQLiteIndexSchemaPlan` for bounded native planning of wide-column index keys, unique-index build rollback diagnostics, and quoted identifier index catalog output.
- Added `SQLiteRealUpstreamBtreeIndexDynamicWideSchemaTest.php`.
- The test uses generic table/index names from upstream SQLite (`t1`, `t1i1`, `i1`) and does not add domain-specific APIs, fixtures, or examples.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexDynamicWideSchemaTest.php`
- Result: `1 test files, 719 assertions, 0 failures`
- PASS-line growth: `73` focused PASS cases.
- Expected `phpPass`: `198691 -> 198764`.
- Mapped denominator: unchanged at `958 / 1589`; this handoff ports behavior assertions and does not claim new manifest rows.

Non-overlap:

- Does not repeat prior `index.test` duplicate-key delete/mixed-order coverage, `indexedby.test` access-path planning, `index6.test` partial-index admission, expression-index range costs, B-tree page relocation/root-collapse/interior merge, overflow freelist release, JSON, WAL, VFS, grouped SELECT, or expression ORDER BY clusters.
- The narrower surface is real upstream `index2.test` wide CREATE INDEX behavior and `index3.test` UNIQUE rollback/quoted identifier schema behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP test runner and adds one bounded generic planner component under `lanes/libsqlite/src`.
