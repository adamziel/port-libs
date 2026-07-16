# real-upstream-corpus-trigger-fkey-dynamic-20260531T004429Z-0

Base accepted HEAD: `ad16a572f80ccf85246d93f3ad58ce0402786c09`.

Owned upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-13.1.1..13.1.4`

Behavior ported:

- `REPLACE` performs FK processing when it deletes a referenced composite parent row.
- Failed `REPLACE` preserves original parent and child rows.
- Failed `REPLACE` inside an explicit transaction leaves the transaction open for follow-up work.
- `REPLACE` that preserves the referenced composite parent key commits, including the rowid-changing case from `fkey2-13.1.4`.
- FK violation accounting is per referencing child row.

Changed lane files:

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepairTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T004429Z-0.md`

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepairTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepairTest.php`
  - `1 test files, 6767 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Local selected assertion movement:

- Existing fkey2-12.2 dynamic file total before this slice was `2004` focused assertions.
- The focused file now has `6767` assertions.
- Local movement for this slice is `+4763` focused assertions.

Dependency closure:

- No new support component is needed. This reuses the existing trigger/FK dynamic planner helper and row-array validation machinery.

Non-overlap:

- This slice does not repeat accepted fkey2-12.2 NOCASE delete-trigger repair behavior. It ports adjacent upstream `fkey2-13.*` REPLACE/FK behavior and keeps the source-neutral API surface.
