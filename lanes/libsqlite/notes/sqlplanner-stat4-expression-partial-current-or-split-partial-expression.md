# sqlplanner-stat4-expression-partial-current-or-split-partial-expression

Status: ready for integration as a focused current-source planner behavior
slice.

Behavior:

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
- Models a stale prepared `wp_options` statement whose OR-split
  `lower(option_name)` probes can use a current partial STAT4 expression index
  only when every OR arm independently proves the index partial predicate.
- Fences schema cookie, STAT4 generation, source/index/row signatures, OR-arm
  signatures, STAT4 samples, and the resulting row stream before admitting the
  current covering cursor.
- Rejects missing partial proof, missing STAT4 samples, malformed samples, bad
  rowids, and underspecified OR arms.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentOrSplitPartialExpressionTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-or-split-partial-expression.php --self-test
```

Result:

```text
wordpress-sqlplanner-stat4-expression-partial-current-or-split-partial-expression self-test passed
```

Expected dashboard movement: `phpPass` +58, from `72234` to `72292` in this
worktree. Mapped upstream coverage is unchanged because this composes existing
expression-index, partial-predicate, current-source, and STAT4 manifest
surfaces.

Non-overlap:

This avoids accepted next154 equality/IN/BETWEEN row stream behavior, next158
range-window stale-row exclusion, next151/158 STAT4 expression partial accepted
cluster, expression ORDER BY, range-cost, JSON table, WAL/VFS, and B-tree
clusters. The new surface is OR-split STAT4 partial expression probe admission
where every OR branch must independently imply the current partial predicate.

Dependency closure:

No new support component is needed. The slice reuses lane-local expression term
matching, partial predicate proof, STAT4 sample fences, and current-source row
diagnostics.
