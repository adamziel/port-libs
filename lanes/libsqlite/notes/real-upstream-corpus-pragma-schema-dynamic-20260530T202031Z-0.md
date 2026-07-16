# Real Upstream Corpus: PRAGMA Schema Dynamic Schema6

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T202031Z-0`

Accepted base: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
  - `schema6-100` equivalent rowid `INTEGER PRIMARY KEY` plus UNIQUE forms
  - `schema6-110` equivalent reordered column constraints
  - `schema6-120` equivalent `WITHOUT ROWID` primary-key plus UNIQUE forms
  - same/different content checks for key-order and rowid-vs-without-rowid semantics

Behavior ported:

- Added a focused PHP corpus over the PRAGMA schema-catalog path for upstream
  `schema6.test` equivalence classes.
- The corpus compares schema-derived content signatures from
  `PRAGMA table_info`, `PRAGMA table_list`, `PRAGMA index_list`, and
  `PRAGMA index_info` across equivalent `CREATE TABLE` spellings, including
  identifier changes, whitespace/formatting changes, reordered table
  constraints, reordered column constraints, explicit unique indexes, and
  `WITHOUT ROWID` variants.
- The initial red run showed that a table/index-only signature did not
  distinguish rowid tables from `WITHOUT ROWID`; the final corpus includes the
  `PRAGMA table_list` `wr` bit so this upstream content-difference case is
  represented in the port behavior.

Focused coverage:

- New file: `SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php`
- Focused PASS cases: `1200`
- Focused assertions: `9400`
- No mapped-denominator claim; this is behavior-backed PHP PASS-line growth
  from a real upstream SQLite source section.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php`
- Red-first focused run:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php`
  - failed `200` rowid-vs-`WITHOUT ROWID` signature cases before adding the `table_list.wr` signature component
- Final focused run:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php`
  - `1 test files, 9400 assertions, 0 failures`

Non-overlap:

- This does not repeat prior real-upstream PRAGMA table-info, `pragma3`
  data-version, `pragma4` table-valued PRAGMA, `pragma5` function/module list,
  `schema5` legacy constraint, or `schema.test` invalidation coverage.
- This slice is specifically `schema6.test` content-equivalence behavior
  projected through the existing PRAGMA schema-catalog metadata path.
- No source-neutral cleanup, runner-map denominator, VFS/pager, JSON, or
  domain-specific API surfaces are touched.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` primitives.
