# JSON validity subtype current next20

## Behavior

- Allows `json_valid()` to accept `SQLiteJsonSubtypeValue` inputs directly and through bounded `SQLiteSelectSql` expression dispatch.
- Treats JSON subtype inputs as already-produced canonical JSON text for text-validity flags (`1`, `2`, or mixed text/JSONB flags), while JSONB-only flags (`4`, `8`) do not classify subtype text as a BLOB.
- Allows SELECT predicate row admission to carry JSON subtype values so copied `wp_options` rows can be filtered/projected by `json_valid(option_value)`.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonValiditySubtypeCurrentNext20Test.php
Focused test run: 1 selected test files (root lock skipped)
20 PASS lines
1 test files, 20 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-json-validity-subtype-current-next20.php
strict subtype row: strict_ok=1, json5_ok=1, jsonb_ok=0
JSON5 text row: strict_ok=0, json5_ok=1, jsonb_ok=0
JSONB row: strict_ok=0, json5_ok=0, jsonb_ok=1
```

## Status delta

- `phpPass`: `6957 -> 6977` (`+20` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.
- Dependency closure: no new support component is needed; this reuses existing JSON subtype, JSONB, and SELECT expression components.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible-constraint clusters, JSONB NULL-path behavior, and accepted SELECT SQL subquery/GROUP/ORDER expression work.
