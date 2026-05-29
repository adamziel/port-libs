# JSON table generated-path rowid cost current-source next210

This isolated slice adds current-source `OFFSET` / `LIMIT` costing after the
existing generated-path rowid range admission profile.

Behavior covered:

- `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext210()`
  layers an offset-cost profile over next209 range admission without changing
  hidden/visible constraint extraction or parser-level `json_each()` /
  `json_tree()` source wiring.
- Current-source plans record skipped rowids, yielded rowids, rows blocked by
  `LIMIT`, estimated rows/cost, opcodes, cost classes, and transition reasons.
- Stable current/next sources reuse the offset profile; changed source/range
  fingerprints force next-source reprepare.
- Negative offsets are rejected before planning.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext210Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next210.php --self-test
wordpress-json-table-generated-path-rowid-cost-current-source-next210 self-test passed
```

Non-overlap:

- Avoids accepted JSON hidden/visible constraints, JSON table cursor/source
  execution, and next209 range admission.
- The new behavior starts only after range rowids are admitted and models the
  current-source offset skip/yield cost boundary.

Dependency closure:

No new support component is needed; this reuses the native JSON table
generated-path rowid range planner and current-source cost profiles.
