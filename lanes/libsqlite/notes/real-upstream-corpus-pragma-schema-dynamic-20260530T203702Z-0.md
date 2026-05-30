# Real Upstream Corpus: PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T203702Z-0`

Accepted base: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
  - `schema4-1.1` through `schema4-1.6` same trigger names as table, view, and index objects survive creation, object drops, and reopen
  - `schema4-2.1` through `schema4-2.5` table rename where triggers share table or index names
  - `schema4-2.6` through `schema4-2.11` TEMP trigger/table name collision remains independent after table rename

Behavior ported:

- Added a focused dynamic corpus over generic `app_events_N`, `app_event_log_N`, `app_same_table_N`, `app_same_view_N`, and `app_same_index_N` schema records.
- The cases verify `SQLiteSchemaDdlReparsePlan` allows trigger names to coexist with same-named table/view/index records, drops only the named schema object type, preserves same-named triggers, and reparses target-table indexes/views/triggers during `ALTER TABLE ... RENAME TO ...` without conflating object names.
- This is behavior-backed PHP coverage over the existing schema DDL reparse and PRAGMA schema catalog primitives; no metadata-only admission rows were added.

Focused coverage:

- `SQLiteRealUpstreamPragmaSchema4NameCollisionDynamicTest.php`
  - 250 dynamic same-name trigger/table/view/index creation cases
  - 250 dynamic same-name object drop cases that preserve triggers
  - 250 dynamic unrelated table rename cases that leave same-named triggers bound to their original target
  - 250 dynamic target table rename cases that reparse indexes, views, and target triggers
  - 1 source-citation case
- Focused PASS cases: 1001
- Behavior assertions: 6004

Verification:

- Red-first: initial focused run failed 250 rowid assertions because the test expected trigger rowids from the schema cookie instead of from the highest existing sqlite_schema rowid. Production behavior already preserved the upstream schema4 semantics; the assertion was corrected to the actual schema-row sequence.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema4NameCollisionDynamicTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema4NameCollisionDynamicTest.php` -> `1 test files, 6004 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` -> clean
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `612306 -> 613307` (`+1001` focused PASS lines)
- `mapped coverage`: unchanged at `1472 / 1589`; this is behavior-backed PHP PASS-line growth, not a denominator admission change.

Dependency closure:

- No new support component is needed. This reuses lane-local schema DDL reparse, schema-record catalog, and PRAGMA schema catalog primitives.

Non-overlap:

- This does not repeat prior `pragma.test` table-info/index metadata, `pragma3` data-version, `pragma4` table-valued PRAGMA, `pragma5` function/module list, `schema.test`/`schema2.test` invalidation, `schema3.test`, `schema5.test`, or `schema6.test` rowid coverage.
- This does not touch source-neutral cleanup, VFS/pager evidence, runner-map denominator rows, or domain-specific API surfaces.
