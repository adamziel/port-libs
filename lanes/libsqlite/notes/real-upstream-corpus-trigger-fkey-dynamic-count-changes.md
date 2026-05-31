# real-upstream-corpus-trigger-fkey-dynamic-count-changes

Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`,
section `fkey2-17.1.1..17.1.6`.

This slice ports the `PRAGMA count_changes` / foreign-key boundary from the
hydrated SQLite corpus into `SQLiteDynamicTriggerForeignKeyPlan`:

- immediate FK violations report `SQLITE_CONSTRAINT` before returning a count
  row;
- deferred FK violations return the attempted row count before the commit/final
  constraint failure;
- FK action side effects are included in total-change style accounting but not
  in the statement `changes()` count.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCountChangesTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCountChangesTest.php`
  - `1 test files, 2091 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -type f | sort | rg 'SQLiteRealUpstream.*TriggerFkey.*Dynamic.*Test\.php$')`
  - `59 test files, 435589 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the
existing native dynamic trigger/FK planner and the hydrated upstream SQLite
corpus checkout as source evidence.

Non-overlap: this does not repeat accepted fkey action journal, deferred
counter, pragma toggle, trigger body, view trigger, nocase repair, or
drop-trigger dynamic sections. It owns the distinct upstream `fkey2-17.*`
`count_changes` statement-result boundary.
