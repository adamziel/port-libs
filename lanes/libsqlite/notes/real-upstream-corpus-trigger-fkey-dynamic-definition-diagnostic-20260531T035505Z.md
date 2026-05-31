# real-upstream-corpus-trigger-fkey-dynamic-definition-diagnostic-20260531T035505Z

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T035025Z-0`

Accepted base: `1d87a6fc2cf9c016da25d4e727af365cff780442`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Sections `fkey2-10.1.*` and `fkey2-10.2.*`

Behavior added:

- Generic foreign-key definition diagnostics for missing parent tables,
  missing parent key columns, child `rowid` references without a declared
  child column, parent `rowid` references without a declared parent column,
  parent-key unique-index collation mismatch, and declared-`rowid` success.
- New focused PHP corpus:
  `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDefinitionDiagnosticTest.php`

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDefinitionDiagnosticTest.php`
- Result: `1 test files, 6126 assertions, 0 failures`

Expected movement:

- PASS-line/assertion growth only: `+6126` focused behavior assertions.
- Mapped denominator remains complete at `1589 / 1589`.

Dependency closure:

- No new support component needed. The slice reuses the existing generic
  trigger/FK dynamic corpus helper surface and hydrated upstream SQLite Tcl
  checkout.

Non-overlap:

- Does not repeat accepted trigger/FK deferred graph, statement counter reset,
  count_changes, REPLACE counter, nocase repair, PRAGMA foreign_keys toggle,
  triggerupfrom, triggerA, trigger5, trigger6, trigger8, trigger9, or deferred
  restrict coverage. This patch owns the `fkey2-10.*` definition-diagnostic
  matrix.
