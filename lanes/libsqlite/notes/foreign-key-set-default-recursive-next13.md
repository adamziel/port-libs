## Foreign Key SET DEFAULT Recursive Next13

This slice adds `SQLiteForeignKeySetDefaultRecursivePlan`, a bounded native PHP
planner for SQLite-style recursive ON DELETE foreign-key actions where
`SET DEFAULT` rewrites child keys and queued cascading parent deletes can cause
later default rewrites in dependent tables. The focused Application-shaped fixture
uses copied category/post/postmeta rows so plugin import cleanup can delete a
parent category while preserving child rows by moving them to the configured
default key.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeySetDefaultRecursiveCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 40 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-fk-set-default-recursive.php --self-test
application-fk-set-default-recursive self-test passed
```

Dashboard movement: `phpPass` increases by the verified 40 focused PASS lines.
No upstream manifest denominator is changed because this is a lane-local focused
corpus slice, not a newly mapped upstream inventory unit.

Non-overlap: this does not repeat accepted FK ON UPDATE cascade/SET DEFAULT,
trigger/FK interaction, rollback-journal commit/apply, JSON table planning,
SELECT SQL text/subquery/order/grouping, VFS writer/sync/lock, or B-tree page
move/freeblock/overflow/root-collapse clusters. The new surface is recursive
delete action ordering over a multi-table FK graph with SET DEFAULT validation.

Dependency closure: no new support component is needed; the slice reuses native
PHP row-array planning and existing focused TestRunner infrastructure.
