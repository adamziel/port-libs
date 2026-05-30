# Trigger/view TEMP name resolution current-next31

Status delta: added bounded schema-qualified trigger target parsing and inferred
trigger/target schema reporting for `SQLiteViewTriggerNameResolution`.

Behavior covered:

- TEMP triggers on unqualified view/table names resolve TEMP shadows first.
- TEMP triggers with `ON main.name` or `ON archive.name` resolve that exact
  schema instead of the TEMP shadow.
- Non-TEMP triggers skip TEMP shadows for unqualified names.
- Double-quoted and bracket-quoted schema qualifiers are accepted.
- Pseudo-column diagnostics stay tied to the resolved target schema, so a TEMP
  trigger qualified to `main.view` does not accept columns that only exist on
  `temp.view`.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerViewTempNameResolutionCurrentNext31Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteViewTriggerNameResolutionTest.php lanes/libsqlite/tests/SQLiteTriggerViewTempNameResolutionCurrentNext31Test.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 111 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-trigger-view-temp-name-resolution-current-next31.php --self-test
application-trigger-view-temp-name-resolution-current-next31 self-test passed
```

Expected dashboard movement: `phpPass` +55, from 10687 to 10742, with no mapped
upstream denominator change.

Non-overlap: this slice avoids accepted batch23 trigger/FK yield behavior,
accepted attach/temp view-trigger resolution, batch26 UPSERT trigger returning,
and accepted JSON/VFS/WAL/B-tree/SELECT clusters. The new behavior is narrower:
current-source TEMP trigger/view/table name resolution when the trigger target
is schema-qualified or unqualified.

Dependency closure: no new support component is needed. The slice reuses
lane-local `SQLiteSchemaRecord` and `SQLiteViewTriggerNameResolution` parsing;
it does not require ext/sqlite, upstream binaries, network access, or provider
credentials.
