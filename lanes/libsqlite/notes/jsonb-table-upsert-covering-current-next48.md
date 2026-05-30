# JSONB Table UPSERT Covering Current Next48

2026-05-27 isolated slice `yield-sqlite-jsonb-table-upsert-covering-current-next48`.

## Status Delta

- Added `SQLiteJsonbPatchGeneratedIndexPlan::planUpsertCoveringTable()` to compose the existing bounded UPSERT executor with JSONB generated/path index maintenance.
- Covers current conflicting row deletes, next updated inserts, inserted-row index entries, partial-index activation/deactivation, `DO UPDATE WHERE` skips, secondary unique-conflict rejection, malformed JSON guards, non-covering fallback skips, and covering RETURNING-style projection of generated JSONB values.
- Added 62 focused `TestRunner` PASS cases in `SQLiteJsonbTableUpsertCoveringCurrentNext48Test.php`.
- Added `application-jsonb-table-upsert-covering-current-next48.php`, a copied `wp_options` smoke for JSONB `option_value` imports feeding covering generated/path indexes.
- Updated `lane-status.json` `phpPass` from 17373 to 17435 by the verified focused PASS-line delta.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbTableUpsertCoveringCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-jsonb-table-upsert-covering-current-next48.php
{
    "scenario": "application-jsonb-table-upsert-covering-current-next48",
    "coveringIndex": "idx_wp_options_jsonb_channel_covering",
    "changes": 2,
    "updatedOptionIds": [101],
    "insertedOptionIds": [103]
}
```

## Non-Overlap

This slice does not repeat accepted JSONB generated UPDATE index next37, JSON visible/hidden constraints, JSON table cursor/source/host joins, UPSERT trigger/FK yield, UPSERT RETURNING trigger/current-conflict work, expression ORDER BY, VFS/WAL, or B-tree clusters. The new behavior is JSONB table UPSERT current/next covering-index maintenance and covering projection.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP JSONB codec, JSON5/canonical JSON parser, JSON patch/extract helpers, generated/path index planner, and bounded UPSERT row-array executor.
