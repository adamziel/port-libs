# real-upstream-corpus-upsert-returning-dynamic-20260531T234209Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Ported behavior:

- `returning1.test` section 12 result-column naming for RETURNING:
  - `returning1-12.1` `RETURNING "x"` dequotes the result column name to `x`;
  - `returning1-12.2` `RETURNING [x]` dequotes the result column name to `x`;
  - `returning1-12.3` `RETURNING x AS [xyz]` dequotes the result alias to `xyz`;
  - `returning1-12.4` `RETURNING "x"+"y"` evaluates the expression while preserving the raw expression text as the result column name.

Implementation:

- `SQLiteUpsertReturningSql::returningProjection()` now accepts unaliased RETURNING expressions and stores the original expression text as the output alias, while keeping the existing rejection for `excluded.*` / `excluded.column` references in RETURNING.

Red-first evidence:

```text
php -r 'require "lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php"; require "lanes/libsqlite/src/SQLiteUpsertReturningSql.php"; ... RETURNING "x"+"y" ...'
InvalidArgumentException: SQLite UPSERT RETURNING only supports columns, aliases, expressions with aliases, literals, and *
```

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningColumnNameDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 12008 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS libsqlite source has no WordPress-named text
PASS libsqlite filenames have no WordPress-specific names
PASS libsqlite php declarations have no WordPress-specific class or method names

1 test files, 3 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicExcludedAliasTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 1246 assertions, 0 failures
```

PASS-line movement:

- Added `4003` focused TestRunner PASS cases:
  - 1 upstream source citation case;
  - 1000 deterministic `returning1-12.1` quoted-identifier dequoting cases;
  - 1000 deterministic `returning1-12.2` bracket-identifier dequoting cases;
  - 1000 deterministic `returning1-12.3` bracket-alias dequoting cases;
  - 1000 deterministic `returning1-12.4` unaliased expression result-name preservation cases;
  - 1 non-overlap/source-coverage case;
  - 1 dependency-closure case.
- `lane-status.json` `phpPass` moves from `4406566` to `4410569` if accepted.

Non-overlap:

- This slice ports upstream `returning1.test` section 12 result-column naming.
- It does not repeat accepted UPSERT conflict priority, target-alias/excluded-table binding, trigger histograms, prepared changes counters, JSON table source wiring, or QRF list formatting.

Dependency closure:

- No new support component needed.
- Reuses the native row-array `SQLiteUpsertReturningSql` parser/executor and `SQLiteUpsertDoUpdateWherePlan::returningRows()` projection plumbing.

Root harness:

- Not run; isolated micro-slice.
