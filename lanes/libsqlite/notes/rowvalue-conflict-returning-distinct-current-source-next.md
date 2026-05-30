# rowvalue-conflict-returning-distinct-current-source-next

Status: focused PHP behavior growth for row-value `IS DISTINCT FROM` predicates
combined with UPDATE conflict policy and `RETURNING` current-source behavior.

This slice adds `SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan`.
It composes the existing native UPDATE/DELETE RETURNING executor to model a
current-source sequence where:

- `UPDATE OR IGNORE` selects a drifted row through a row-value
  `IS DISTINCT FROM` predicate, detects a unique conflict, restores the source
  row, and emits no `RETURNING` row.
- `UPDATE OR REPLACE` selects a drifted row through the same row-value
  predicate, deletes the current conflicting row, and returns the replacement
  row image with row-value `IS NOT DISTINCT FROM` projection state.
- `DELETE ... RETURNING` removes clean rows selected by row-value
  `IS NOT DISTINCT FROM` after the replacement has changed the current source.
- A later `UPDATE OR ABORT` conflict stops the batch and preserves the completed
  current-source prefix rather than applying the failed statement image.

Application smoke:
`lanes/libsqlite/examples/application-rowvalue-conflict-returning-distinct-current-source-next.php`
models copied `wp_options` drift repair for import cleanup and option-key
deduplication.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueConflictReturningDistinctCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteRowValueConflictReturningDistinctCurrentSourceNextTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueConflictReturningDistinctCurrentSourceNextTest.php

php -l lanes/libsqlite/examples/application-rowvalue-conflict-returning-distinct-current-source-next.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-conflict-returning-distinct-current-source-next.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueConflictReturningDistinctCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-conflict-returning-distinct-current-source-next.php
"status": "stopped-after-conflict"
"returningCount": 4
"ignoredCount": 1
"deletedConflictCount": 1
"conflictCount": 3

git diff --check -- lanes/libsqlite
no output
```

Expected dashboard delta: `phpPass` moves from `64992` to `65060` from 68 newly
passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this
is focused current-source PHP behavior over already mapped row-value,
RETURNING, and conflict-policy primitives rather than a fresh upstream
denominator row.

Non-overlap: avoids accepted next142 row-value DISTINCT UPDATE/DELETE
RETURNING as a standalone predicate slice, next143 row-value conflict savepoint
retry, next140 abort savepoint RETURNING, next138 conflict RETURNING savepoint,
next134 UPSERT conflict RETURNING, trigger RETURNING conflict/deferred
clusters, WAL/pager/B-tree/JSON/encoding/PRAGMA/compound SELECT clusters, and
the batch142 row-value conflict savepoint RETURNING behavior. The new surface is
the direct current-source composition of row-value DISTINCT selection with
UPDATE `OR IGNORE`/`OR REPLACE`/`OR ABORT` conflict policy and RETURNING stream
admission.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP row-value predicates, UPDATE/DELETE RETURNING execution,
and unique conflict-policy handling.
