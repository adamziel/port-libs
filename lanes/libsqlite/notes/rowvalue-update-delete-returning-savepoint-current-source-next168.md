# rowvalue-update-delete-returning-savepoint-current-source-next168

Status: focused PHP behavior growth for nested row-value `UPDATE`/`DELETE`
`RETURNING` savepoints.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext168Plan`.
It models a copied Application `wp_options` import batch with an outer savepoint,
an inner savepoint that attempts `UPDATE OR IGNORE`, `UPDATE OR REPLACE`, and
`DELETE ... RETURNING`, then `ROLLBACK TO` the inner image before retrying. The
outer row-value `UPDATE RETURNING` rows stay yielded and current, while the
rolled-back inner RETURNING stream is discarded. The retry statements read the
restored inner savepoint image, preserving outer changes but undoing the inner
replace/delete side effects.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext168Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 81 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-rowvalue-nested-savepoint-current-source-next168.php
application-rowvalue-nested-savepoint-current-source-next168 self-test passed
```

Syntax/diff evidence:

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext168Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext168Test.php
php -l lanes/libsqlite/examples/application-rowvalue-nested-savepoint-current-source-next168.php
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: `phpPass` moves from `75459` to `75540` from 81 new
focused PASS lines. Mapped upstream coverage remains `611 / 1589`; this is
focused PHP behavior over already mapped row-value DML/savepoint inventory.

Non-overlap: this avoids accepted row-value next158/next161/next164 rollback
retry and transaction rollback clusters, accepted DELETE RETURNING nested
savepoint next144, accepted VFS/WAL/pager savepoint application clusters,
SELECT SQL text/order/group/subquery clusters, B-tree, JSON, PRAGMA, planner,
and encoding surfaces. The new surface is nested `ROLLBACK TO` current-source
handling where outer row-value RETURNING rows survive and only inner
UPDATE/DELETE RETURNING side effects are discarded before retry.

Dependency closure: no new support component is needed. The slice reuses the
lane-local `SQLiteUpdateDeleteReturningSql` row-value DML executor and adds
bounded nested savepoint orchestration for copied Application `wp_options`
cleanup/import rows.

Next task: continue with broader SQL executor/planner correctness or another
non-overlapping current-source DML/savepoint behavior; avoid repeating accepted
row-value rollback, VFS savepoint writer, and WAL byte-truncation paths.
