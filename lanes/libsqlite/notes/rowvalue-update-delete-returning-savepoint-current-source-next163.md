# rowvalue-update-delete-returning-savepoint-current-source-next163

Status: focused PHP behavior growth for row-value `BETWEEN` / `NOT BETWEEN`
RETURNING expressions across UPDATE/DELETE savepoint rollback-to retry.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext163Plan`.
It models a copied `wp_options` import batch where draft UPDATE/DELETE
RETURNING streams are yielded from the attempted current source, `ROLLBACK TO`
restores the savepoint image and discards those streams, and the retry executes
the same row-value range predicates against the restored current source before
release.

Application smoke:
`application-rowvalue-between-returning-savepoint-current-source-next163.php`
covers plugin option retry cleanup using row-value `BETWEEN` in RETURNING
projections and DELETE cleanup over transient option-name ranges.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext163Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

Expected dashboard delta: update `phpPass` by `+64`, from `72664` to `72728`.
Mapped coverage remains unchanged at `609 / 1589`; this is current-source PHP
behavior over already mapped row-value/update/delete/RETURNING/savepoint
surfaces.

Non-overlap: avoids accepted next126 row-value savepoint rollback, next133
row-value `IS` / `IS NOT`, next142/next148/next149 DISTINCT row-value
RETURNING, next156 OR IGNORE/REPLACE conflict handling, next157 nested
savepoint rollback, next158 rollback-to retry, trigger/view RETURNING, WAL,
pager, B-tree, JSON, VFS, and encoding clusters. The new surface is row-value
`BETWEEN` / `NOT BETWEEN` RETURNING expression streams at the rollback-to retry
current-source boundary.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP UPDATE/DELETE RETURNING, row-value expression evaluation,
and savepoint current-source planning primitives.

Next task: wire the same RETURNING row-value range expression boundary into any
future parser-level VDBE savepoint executor once row-array plans are retired.
