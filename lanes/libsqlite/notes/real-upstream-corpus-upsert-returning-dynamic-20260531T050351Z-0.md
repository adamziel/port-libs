# real-upstream-corpus-upsert-returning-dynamic-20260531T050351Z-0

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - Ported section `upsert4-8.$tn.1` through `upsert4-8.$tn.4` around a real table named `excluded`, INSERT target aliasing, quoted conflict-target column `[a b]`, and `excluded.*` resolution in `DO UPDATE`.
  - Ported `upsert4-8.$tn.5` unresolved conflict-target `WHERE y=1` rejection.

Behavior:

- Added `SQLiteUpstreamUpsertReturningExcludedAliasDynamicTest.php` with 1000 seeded variants of the `upsert4-8` table-named-`excluded` cases.
- The test distinguishes unaliased `INSERT INTO excluded... DO UPDATE SET w=excluded.w`, where `excluded.w` resolves to the target table row, from aliased `INSERT INTO excluded AS x1...`, where `excluded.w` resolves to the incoming pseudo-row.
- The dynamic matrix also checks aliased `excluded` predicates that either skip the update and yield no `RETURNING` row or admit the current row update and yield the post-update image.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningExcludedAliasDynamicTest.php`
- Result: `1 test files, 4001 assertions, 0 failures`

Non-overlap:

- This does not repeat accepted `upsert2` repeated-source gates, `upsert3-200` literal excluded-table behavior, `upsert4` general conflict/DO NOTHING matrix, `upsert5` conflict-arm ordering, `returning1-17` duplicate source streams, autoincrement UPSERT RETURNING, or parser-level SELECT input UPSERT.
- This slice owns the upstream `upsert4.test` section 8 alias-sensitive `excluded.*` resolution behavior, extended through the existing native UPSERT RETURNING executor with generic row names.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `SQLiteUpsertReturningSql` parser/executor and its conflict-target validation.
