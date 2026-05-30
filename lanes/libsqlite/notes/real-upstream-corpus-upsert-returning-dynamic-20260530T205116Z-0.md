# real-upstream-corpus-upsert-returning-dynamic-20260530T205116Z-0

Added a non-overlapping real upstream UPSERT dynamic alias matrix under
`SQLiteRealUpstreamUpsertReturningDynamicAliasMatrixTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-7.1` through `upsert4-7.4`: excluded/current target qualifier
    behavior over rowid and WITHOUT ROWID composite primary-key layouts.
  - `upsert4-8.1` through `upsert4-8.5`: a table named `excluded`, quoted
    composite conflict columns, and invalid predicate/target rejection.
  - `upsert4-5.0`: mismatched expression/collation-style conflict target
    rejection.

Focused coverage:

- 1,009 focused TestRunner assertions/PASS cases.
- The matrix varies 42 source images across rowid and WITHOUT ROWID layouts,
  composite key conflicts, secondary `z` conflicts, pseudo-table `excluded`
  assignment, current target row assignment, skipped `WHERE` updates, and
  malformed target rejection.

Non-overlap:

- Avoids the already accepted `upsert5` catch-all/tail matrix,
  `returning1` recursive/subquery cases, `upsert2` SELECT-source input, and the
  prior `upsert1` target/tail batches.
- This slice focuses on `upsert4.test` alias and table-named-`excluded`
  behavior using existing generic UPSERT conflict-arm helpers.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicAliasMatrixTest.php`
  - `1 test files, 1009 assertions, 0 failures`
- Related UPSERT/RETURNING real-upstream family check:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicAliasMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTailTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPriorityMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicRedundantConflictTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCatchAllMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php`
  - `8 test files, 19109 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  UPSERT conflict-arm executor and RETURNING row image helpers.
