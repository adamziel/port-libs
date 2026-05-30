# JSON Table Indexed Constraint Corpus Next5

- Micro-slice: `yield-sqlite-json-table-indexed-constraint-corpus-next5`.
- Behavior: parser-level `json_each` / `json_tree` hidden indexed constraints now accept commuted equality, so `literal = json` and `literal = root` are extracted the same way as `json = literal` and `root = literal`.
- Focused test delta: `+30` TestRunner PASS cases in `SQLiteJsonTableIndexedConstraintCorpusTest.php`.
- Expected `phpPass` delta: `+30`, from `1684` to `1714`.
- `benchmarkDenominator.mapped` unchanged; this is focused PHP executor/planner behavior, not a newly mapped upstream inventory unit.
- Non-overlap: avoids accepted JSON visible-constraint pushdown, hidden-constraint extraction in the normal direction, JSON table SELECT source/cursor behavior, JSON host joins, malformed JSONB join/source work, and accepted VFS/WAL/B-tree clusters.
- Dependency closure: no new support component needed; the slice reuses the existing SELECT parser, JSON table planner, JSONB validation, and row-array executor.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCorpusTest.php

php -l lanes/libsqlite/examples/application-json-table-commuted-hidden-constraints.php
No syntax errors detected in lanes/libsqlite/examples/application-json-table-commuted-hidden-constraints.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
30 PASS lines
1 test files, 48 assertions, 0 failures

php lanes/libsqlite/examples/application-json-table-commuted-hidden-constraints.php
{
    "scenario": "application-json-table-commuted-hidden-constraints",
    "priority_count": 3,
    "priorities": [
        7,
        4,
        2
    ],
    "top_priority_path": "$.plugin.rules[1].priority",
    "flags": [
        "beta",
        "network"
    ]
}
```
