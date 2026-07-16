# Trigger Recursive View RETURNING Current Source Next158

## Behavior

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext158Plan`
for an upstream-style trigger/view/RETURNING current-source edge:

- `INSTEAD OF` view rows are projected into the underlying option table shape.
- recursive trigger side effects produce their own `RETURNING` rows with
  source, event, trigger depth, and trigger name metadata.
- the current source may yield rows and then roll back before the next source
  is admitted, so only admitted-source `RETURNING` rows are visible.
- releasing the current source feeds the current phase output into the next
  phase; rolling back the next source suppresses next `RETURNING` rows while
  retaining the current phase.

The Application smoke models copied `wp_options` imports where a view-trigger
`siteurl` update recursively refreshes `home` and `rewrite_rules`, while a
next-source import remains the visible source after the current source is
rolled back.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext158Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 76 assertions, 0 failures
```

Expected dashboard movement: `phpPass` `69549 -> 69625` (`+76`) if accepted.
Mapped coverage is unchanged because this is focused PHP behavior coverage,
not a fresh upstream manifest row.

## Non-Overlap

This does not repeat accepted next143 recursive view RETURNING savepoint source
switching, next144 UPSERT RETURNING view source retention, or next148 recursive
view UPSERT current-source retention. The new surface is explicit recursive
trigger side-effect `RETURNING` admission/suppression across current and next
view sources.

## Dependency Closure

No new support component is needed. The slice reuses native PHP trigger,
recursive side-effect, view projection, savepoint/source-retention, and
RETURNING projection behavior already present in the libsqlite lane.
