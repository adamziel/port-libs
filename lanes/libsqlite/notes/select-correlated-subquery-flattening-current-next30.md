# SELECT Correlated Subquery Flattening Current Next30

Status delta: added parser-level `SQLiteSelectSql` support for correlated derived-table subqueries whose derived body is a compound SELECT. Compound arms now receive the current outer row before execution, so `UNION`, `UNION ALL`, `INTERSECT`, and `EXCEPT` arms can read qualified outer columns while the derived alias remains visible to the parent subquery.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCorrelatedSubqueryFlatteningCurrentNext30Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 42 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-correlated-subquery-flattening-current-next30.php
[
    {
        "option_name": "siteurl"
    },
    {
        "option_name": "home"
    },
    {
        "option_name": "blogname"
    }
]
```

Non-overlap: this avoids accepted parser-level SELECT SQL subqueries, scalar subqueries, JOIN text dispatch, GROUP BY/HAVING text, expression ORDER BY, derived-table materialization, correlated derived-table current-next25, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, and Unicode GLOB. The new behavior is the missing current-row yield into compound SELECT arms inside a correlated derived-table subquery.

Dependency closure: no new support component is needed. The patch reuses `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectPredicate`, and `SQLiteSelectCompound`.

Next task: broaden parser-level SELECT execution around non-flattenable correlated subqueries only after the accepted source includes this compound-arm current-row propagation.
