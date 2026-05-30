# Trigger Recursion Conflict Edge Next11

Status delta 2026-05-27 isolated libsqlite slice: `SQLiteDmlTriggerRecursionPlan`
now applies SQLite-style outer statement conflict precedence for recursive
trigger inserts. A non-default outer `OR IGNORE`, `OR REPLACE`, `OR FAIL`, or
`OR ROLLBACK` policy overrides a trigger-body conflict action, while the
default outer `ABORT` still lets trigger-local `IGNORE`, `REPLACE`, or `FAIL`
handle duplicate recursive rows.

Focused verification:

- Before focused recursion corpus: 53 PASS lines.
- After focused recursion corpus: 77 PASS lines.
- Delta recorded in `lane-status.json`: `phpPass` +24, from 3796 to 3820.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDmlTriggerRecursionCorpusTest.php`
  reported `1 test files, 77 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-dml-trigger-recursion-corpus.php`
  passed and reports copied `wp_options` recursive trigger rows plus outer
  statement conflict precedence.

Non-overlap: this avoids accepted trigger order update/delete and trigger/FK
interaction clusters by covering only recursive INSERT trigger conflict
precedence. It also avoids accepted INSERT/UPDATE conflict-current SQL text
execution by staying inside recursive trigger row production.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive trigger row-array executor and pure PHP Application option
smoke fixture.
