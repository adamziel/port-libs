# Partial Index ORDER Current Source Next 85

This slice adds `SQLitePartialIndexOrderCurrentSourcePlan`, a bounded planner
wrapper for current-source partial index ORDER BY decisions. It chooses an
implied partial index when the current index cursor can satisfy ORDER BY,
keeps next-source residual predicates/table lookups explicit, and reports when
an external temp sort is still required.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialIndexOrderCurrentSourceNext85Test.php
Focused test run: 1 selected test files (root lock skipped)
63 PASS lines / 63 assertions / 0 failures

php lanes/libsqlite/examples/application-planner-partial-index-order-current-source-next85.php --self-test
application-planner-partial-index-order-current-source-next85 self-test passed
```

Dashboard-visible focused PASS delta: `+63` PHP PASS lines, from `32160` to
`32223`. Mapped upstream coverage is unchanged because this is a focused native
planner behavior slice, not a newly mapped upstream inventory row.

Dependency closure: no new support component is needed. This reuses the
existing native PHP CREATE INDEX parser, partial predicate implication, and
multicolumn range planner.

Non-overlap: this avoids accepted STAT4 partial skip-scan ORDER current/next52,
expression-index range cost, SQL expression ORDER BY, JSON table source and
constraint work, B-tree page/root/overflow clusters, WAL savepoint/checkpoint
clusters, and VFS writer/lock/sync clusters. The new behavior is current-source
partial index ORDER BY selection with explicit next-source residual/table
lookup diagnostics.
