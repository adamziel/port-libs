# DML Trigger Recursion Corpus Next

Slice: `yield-sqlite-trigger-recursion-corpus-next`

## Behavior

- Added `SQLiteDmlTriggerRecursionPlan`, a bounded row-array executor for recursive `INSERT` triggers on the target table.
- Covers upstream-style trigger recursion behaviors: `PRAGMA recursive_triggers` enabled/disabled behavior, recursion depth limits, `WHEN` guards, `BEFORE` versus `AFTER` timing, recursive `NEW.column` substitution, recursive conflict `IGNORE`/`REPLACE`/`FAIL`, and malformed trigger guardrails.
- Added `application-dml-trigger-recursion-corpus.php` for copied `wp_options` import/audit rows where recursive plugin option rows are generated without requiring `ext/sqlite`.

## Focused Verification

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteDmlTriggerRecursionCorpusTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures
```

Command:

```bash
php lanes/libsqlite/examples/application-dml-trigger-recursion-corpus.php
```

Result:

```text
{
    "applicationUse": "Preview bounded recursive INSERT triggers for copied wp_options import/audit rows without requiring ext/sqlite.",
    "optionNames": [
        "plugin_seed",
        "plugin_seed:child",
        "plugin_seed:child:child",
        "plugin_seed:child:child:child"
    ],
    "levels": [
        1,
        2,
        3,
        4
    ],
    "changes": 4,
    "recursiveTriggers": true,
    "maxDepth": 8,
    "triggerResults": [
        "inserted",
        "fired",
        "inserted",
        "fired",
        "inserted",
        "fired",
        "inserted",
        "when-skipped"
    ]
}
```

Additional required verification:

- `php -l lanes/libsqlite/src/SQLiteDmlTriggerRecursionPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteDmlTriggerRecursionCorpusTest.php`
- `php -l lanes/libsqlite/examples/application-dml-trigger-recursion-corpus.php`
- `git diff --check -- lanes/libsqlite`

## Dashboard Delta

- `phpPass`: `1336 -> 1389` from 53 verified focused PASS lines.
- `benchmarkDenominator.mapped`: `451 -> 452` for one newly mapped focused DML trigger recursion inventory row.

## Non-Overlap

- Avoids the accepted DML trigger conflict inheritance corpus by focusing on recursive trigger execution, recursion suppression, recursion limits, `WHEN` recursion guards, and recursive same-table inserts.
- Avoids accepted trigger `OLD`/`NEW` diagnostics and prior conflict-policy work except where recursive child inserts need conflict handling to prove termination and failure behavior.

## Dependency Closure

No new support component is required. The slice reuses existing lane-local row-array execution patterns and stays under native PHP libsqlite code.

## Next Task

Extend parser/VDBE DML execution after this bounded recursion corpus is integrated, preferably with statement-text trigger body parsing or UPDATE/DELETE trigger recursion over copied Application import rows.
