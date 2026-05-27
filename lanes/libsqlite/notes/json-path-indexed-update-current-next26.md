# JSON Path Indexed Update Current/Next

This slice adds `SQLiteJsonPathIndexedUpdatePlan`, a bounded native PHP planner
for UPDATEs that mutate `wp_options.option_value` JSON paths while maintaining
expression-index current/next keys.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathIndexedUpdateTest.php`
- Verified focused delta: 62 new TestRunner PASS cases in one new focused file.

Non-overlap:

- Does not repeat accepted JSON table cursor/source/hidden/visible constraint
  work.
- Does not repeat accepted JSON path mutation corpus helpers; this slice
  consumes those helpers to produce expression-index delete/insert current-next
  records for UPDATE behavior.
- Does not repeat UPDATE FROM current-conflict behavior; row mutation input is
  bounded and keyed by rowid.

Dependency closure:

- No new support component is required. The planner reuses existing JSON
  mutation, JSONB, JSON extraction, and JSON path validation components.
