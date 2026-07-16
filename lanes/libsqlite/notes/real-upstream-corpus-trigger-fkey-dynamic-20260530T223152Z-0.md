# real-upstream-corpus-trigger-fkey-dynamic-20260530T223152Z-0

Status: focused real-upstream trigger/FK corpus growth on accepted base
`9f789d799d368a95f9314c9ed366646dd5d17143`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-4.*` recursive FK cascades when recursive triggers are disabled
  - `fkey2-11.1.1` parent key `ON UPDATE CASCADE`
  - `fkey2-12.1.*` deferred `RESTRICT`
  - `fkey2-12.2.*` NOCASE parent delete repaired by an AFTER DELETE trigger
  - `fkey2-12.3.*` composite FK cascade and restrict behavior
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`
  - `3.3.1..3.3.4` `PRAGMA defer_foreign_keys` delaying `RESTRICT` so an
    AFTER DELETE trigger can repair the parent row before commit
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
  - `trigger1-1.10..1.11` trigger body DELETE does not corrupt the outer
    DELETE/UPDATE statement
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-1.1..1.3` BEFORE/AFTER row trigger order for UPDATE, DELETE,
    and INSERT

Patch:

- Added `SQLiteRealUpstreamTriggerFkeyDynamicActionMatrixTest.php`.
- The new file contributes 3,524 focused TestRunner PASS cases over 130
  generated generic application rowsets plus source-citation guards.
- It reuses existing lane-local native PHP trigger/FK helpers and does not add
  source APIs, metadata-only denominator rows, fake upstream names, examples, or
  WordPress-specific scenarios.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicActionMatrixTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicActionMatrixTest.php`
  - `1 test files, 3524 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - required generic API guard
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement:

- `phpPass +3524` from the new focused PHP TestRunner cases.
- `benchmarkDenominator.mapped` remains `1589 / 1589`; this is behavior-backed
  real-upstream PHP corpus growth over already hydrated upstream files.

Non-overlap:

- This does not repeat the earlier NOCASE-only
  `SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepairTest.php`; it adds a
  broader action matrix covering deferred `RESTRICT`, composite cascade,
  trigger body statement preservation, and row-trigger order.
- It avoids accepted UPSERT/RETURNING, PRAGMA foreign-key catalog, schema
  reparse, WAL/VFS, B-tree, JSON, expression, date, window, and suite-evidence
  clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing
  `SQLiteDynamicTriggerForeignKeyPlan` behavior for native PHP trigger/FK
  action modeling.
