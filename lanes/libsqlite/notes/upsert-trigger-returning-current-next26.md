# UPSERT Trigger RETURNING Current Next26

Slice: `yield-sqlite-trigger-upsert-returning-current-next26`

This slice extends the accepted UPSERT trigger/FK yield helper with an optional
RETURNING projection image. The new behavior is the current/next row image
captured for the top-level UPSERT row after assignment and BEFORE triggers,
before AFTER-trigger target mutations. Skipped `DO UPDATE WHERE` rows still
yield diagnostic metadata but no RETURNING row.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertTriggerReturningCurrentNext26Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures

php lanes/libsqlite/examples/application-upsert-trigger-returning-current-next26.php
changes: 2; yieldedStatuses: changed, changed, skipped; returning rows capture
siteurl and fresh_plugin before the AFTER trigger changes the final siteurl row.
```

Expected dashboard movement:

- `phpPass`: `8739 -> 8792` from 53 newly verified focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged. This is a focused native extension
  to the already mapped batch23 UPSERT trigger/FK behavior rather than a new
  upstream inventory row.

Non-overlap:

- Avoids accepted batch23 UPSERT trigger/FK yield behavior by adding RETURNING
  projection images and before/after trigger row-image timing instead of
  re-covering child-row FK yield admission.
- Avoids accepted UPSERT RETURNING conflict-current, multi-conflict, trigger
  current-next18, and savepoint/trigger/FK slices by focusing only on the
  top-level RETURNING image around triggers.

Dependency closure:

No new support component is needed. The slice reuses the existing bounded
native PHP UPSERT trigger/FK row-array executor and adds projection handling
inside that helper.
