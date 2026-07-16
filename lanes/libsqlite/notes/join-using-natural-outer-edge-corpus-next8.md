# JOIN USING/NATURAL/Outer Edge Corpus Next8

Slice: `yield-sqlite-join-using-natural-outer-edge-corpus-next8`

Status delta: added parser-level `SQLiteSelectSql` support for qualified
`JOIN ... USING`, multi-column `USING`, `NATURAL JOIN`,
`NATURAL LEFT OUTER JOIN`, `RIGHT JOIN`, and `FULL OUTER JOIN` over bounded
row-array sources. `SQLiteSelectQuery` now materializes RIGHT/FULL null
extension for parser-built plans.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJoinUsingNaturalOuterEdgeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS executes qualified join using equality over copied option ids
PASS executes left join using with null-extended right side
PASS executes multi-column using without matching partial keys
PASS executes natural join from common column names
PASS executes natural left outer join with unmatched option rows
PASS executes right join using with unmatched metadata rows
PASS executes full outer join using with left and right unmatched rows
PASS executes chained using join after natural join
PASS rejects invalid using and natural cross forms

1 test files, 48 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-sql-join-using-natural-outer.php
{
    "scenario": "application-select-sql-join-using-natural-outer",
    "rowCount": 5,
    "optionNames": [
        null,
        "blogname",
        "home",
        "network_admin_email",
        "siteurl"
    ],
    "sources": [
        "orphan_meta",
        "theme",
        "core",
        null,
        "core"
    ]
}
```

Non-overlap: this does not repeat accepted JOIN text dispatch, JSON table
host/source/cursor joins, expression ORDER BY, GROUP BY text, comma LIMIT,
subquery execution, or VFS/WAL/B-tree clusters. It narrows the remaining JOIN
surface to upstream-style `USING`, `NATURAL`, and RIGHT/FULL outer null
extension edges.

Dependency closure: no new support component is needed; the patch reuses the
existing parser, predicate, projection, and row-array SELECT executor.

Next task: continue SQL executor/planner parity with non-overlapping join-order
costing, derived table sources, or additional VDBE-style result semantics.
