# SELECT Join Predicate Current Next56

Status delta: added parser-level `SQLiteSelectSql` behavior for chained
`JOIN ... USING` predicates after an earlier `RIGHT` or `FULL` join. The
current joined row now coalesces prior matching `USING` columns before the next
join predicate is evaluated, so NULL-extended left rows from a right/full join
can still match the next table through the right-side key.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJoinPredicateCurrentNext56Test.php`
- Result: `1 test files, 100 assertions, 0 failures`
- PASS-line delta: `+50`
- Application smoke: `php lanes/libsqlite/examples/application-select-join-predicate-current-next56.php`

Non-overlap: this avoids accepted SELECT SQL JOIN text dispatch, subqueries,
GROUP BY/HAVING text, expression ORDER BY, JSON table source/cursor/constraint
work, VFS writer/lock/sync/rollback clusters, WAL savepoint/checkpoint
clusters, B-tree page move/root-collapse/overflow/freelist clusters, and
Unicode GLOB behavior. The new surface is the current/next join predicate key
visible after an outer `USING` join, not another standalone join row-production
helper.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP SELECT parser, `SQLiteSelectQuery` join executor, and
`SQLiteSelectPredicate`; it does not require ext/sqlite, live services, or
shared-cache fixtures.

Next task: continue SQL executor/planner work on non-overlapping predicate,
range-cost, or SELECT pipeline behavior; avoid duplicate JOIN text and
expression ORDER BY clusters.
