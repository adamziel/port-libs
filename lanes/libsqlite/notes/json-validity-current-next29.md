# JSON validity current next29

## Behavior

- Extends `json_valid()` to accept SQLite-current SQL scalar JSON inputs: integers, finite reals, and booleans are converted to their SQL text form before JSON validity classification.
- Adds SQLite-style `json_valid(X,Y)` flag coercion for string, numeric, boolean, and BLOB flag values. Prefix numeric text such as `'1abc'`, decimal values such as `1.9`, and digit BLOBs are coerced through SQLite integer semantics before the existing `1..15` flag validation.
- Wires the same behavior through `SQLiteSelectSql` expression dispatch so copied `wp_options` rows can validate JSON text, JSON5, JSONB BLOBs, JSON subtype values, numeric scalars, and row-supplied flags in projection, WHERE, and ORDER BY contexts.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonValidityCurrentNext29Test.php
Focused test run: 1 selected test files (root lock skipped)
44 PASS lines
1 test files, 44 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-json-validity-current-next29.php
[
    {
        "option_name": "plugin_json5_settings",
        "valid": 1
    },
    {
        "option_name": "plugin_jsonb_settings",
        "valid": 1
    },
    {
        "option_name": "plugin_generated_settings",
        "valid": 1
    },
    {
        "option_name": "plugin_numeric_setting",
        "valid": 1
    }
]
```

## Status delta

- `phpPass`: `10028 -> 10072` (`+44` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.
- Dependency closure: no new support component is needed; the slice reuses existing JSON validity, JSONB, JSON subtype, and SELECT expression machinery.
- Non-overlap: avoids accepted JSON validity subtype admission from `next20`, JSON table cursor/source/hidden/visible-constraint clusters, malformed JSONB planner diagnostics, JSONB NULL-path behavior, and accepted SELECT SQL subquery/GROUP/ORDER-expression work. This slice is limited to SQL-scalar input validity and current SQLite flag coercion.
