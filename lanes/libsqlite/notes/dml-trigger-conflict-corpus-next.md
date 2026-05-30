# DML Trigger Conflict Corpus Next

Date: 2026-05-27

Slice: `yield-sqlite-dml-trigger-conflict-corpus-next`

Behavior added:

- Added `SQLiteDmlTriggerConflictPlan`, a bounded row-array executor for INSERT-trigger side-table conflict behavior.
- Covers statement-level conflict policy inheritance into trigger body DML, trigger-local `IGNORE` / `REPLACE` / `FAIL`, `BEFORE` and `AFTER` trigger timing, `NEW.column` substitution, and malformed trigger guardrails.
- Added `application-dml-trigger-conflict-corpus.php` for copied `wp_options` import-audit rows where outer `INSERT OR REPLACE` controls trigger side-table conflicts without `ext/sqlite`.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteDmlTriggerConflictCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS dml trigger conflict corpus before insert adds side row
PASS dml trigger conflict corpus before insert records inserted effect
PASS dml trigger conflict corpus statement ignore overrides trigger abort
PASS dml trigger conflict corpus ignore still inserts outer row
PASS dml trigger conflict corpus ignore keeps existing side row
PASS dml trigger conflict corpus ignore reports two outer changes
PASS dml trigger conflict corpus replace overrides trigger abort
PASS dml trigger conflict corpus replace updates conflicting side row
PASS dml trigger conflict corpus replace preserves side row count
PASS dml trigger conflict corpus trigger replace applies without statement override
PASS dml trigger conflict corpus trigger ignore applies without statement override
PASS dml trigger conflict corpus trigger fail skips conflicted outer row
PASS dml trigger conflict corpus trigger fail records ignored outer row
PASS dml trigger conflict corpus trigger fail keeps prior side effects
PASS dml trigger conflict corpus trigger fail changes exclude skipped row
PASS dml trigger conflict corpus abort raises on conflict
PASS dml trigger conflict corpus rollback raises like abort in bounded executor
PASS dml trigger conflict corpus after trigger sees inserted row
PASS dml trigger conflict corpus after trigger ignores side conflict
PASS dml trigger conflict corpus after trigger replace side row
PASS dml trigger conflict corpus after trigger fail does not undo target row
PASS dml trigger conflict corpus after trigger fail marks conflict effect
PASS dml trigger conflict corpus multiple triggers fire in order
PASS dml trigger conflict corpus new column substitution uses input value
PASS dml trigger conflict corpus literal trigger row value is preserved
PASS dml trigger conflict corpus malformed statement conflict rejected
PASS dml trigger conflict corpus malformed trigger conflict rejected
PASS dml trigger conflict corpus unsupported trigger target rejected
PASS dml trigger conflict corpus unsupported trigger action rejected
PASS dml trigger conflict corpus missing new column rejected
PASS dml trigger conflict corpus empty unique columns rejected
PASS dml trigger conflict corpus malformed unique column rejected

1 test files, 32 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `933 -> 965` from 32 verified new PASS lines.
- `benchmarkDenominator.mapped`: `450 -> 451` for one newly mapped focused DML trigger conflict inventory row.

Non-overlap:

- Avoids accepted `UPDATE FROM` current duplicate-source / `OR REPLACE` unique-conflict behavior.
- Avoids accepted `INSERT OR REPLACE` current-row delete-before-insert planning.
- Avoids accepted trigger `OLD` / `NEW` diagnostics by focusing specifically on conflict-policy inheritance into trigger body DML.

Dependency closure:

- No new support component is needed. The slice reuses existing row-array execution patterns and native PHP arrays only.

Next:

- Extend parser/VDBE DML execution only after this bounded trigger conflict behavior is integrated, preferably with DELETE/UPDATE trigger body actions or real statement text parsing.
