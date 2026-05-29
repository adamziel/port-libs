# compound-union-limit-affinity-current-source-next145

Status: focused PHP behavior growth for parser-level compound `UNION` duplicate elimination before final `ORDER BY` / `LIMIT` / `OFFSET` at a WordPress copied `wp_options` current/next boundary.

This slice adds `SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan`.

- `UNION` removes duplicate rows using SQLite compound row keys: integer `1` and real `1.0` compare as the same numeric value.
- Text values such as `'1'` stay distinct from numeric `1`; no column-affinity coercion is applied to compound duplicate checks.
- The final compound `ORDER BY rank_value, payload LIMIT 4 OFFSET 1` is applied after duplicate elimination, so a next-source numeric row can enter the limited page while a text row is truncated after the limit.
- Diagnostics expose skipped duplicate rows, storage classes, limit boundary rows, changed signatures, and replan reasons.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundUnionLimitAffinityCurrentSourceNext145Test.php
```

Expected dashboard movement: `phpPass +53` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this reuses already mapped compound SELECT, affinity, ORDER BY, LIMIT/OFFSET, and current-source inventory rather than claiming a fresh upstream manifest row.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-compound-union-limit-affinity-current-source-next145.php
```

Non-overlap: avoids accepted compound row composition, compound ORDER/LIMIT next110, recursive LIMIT next117, recursive affinity next120, compound values/name-resolution next121/123/127, correlated/window compound next124/126/128/129/131/132/133/134/135/136/137/138/139/140/141/142, SELECT SQL text/JOIN/GROUP/subquery/expression ORDER/comma LIMIT, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, and encoding-only LIKE/GLOB/collation work. The narrower surface is non-recursive `UNION` numeric-vs-text duplicate affinity feeding a post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level `SQLiteSelectSql`, `SQLiteSelectCompound`, result ordering, and LIMIT/OFFSET machinery.

Next task: continue compound SELECT only on a non-overlapping current-source behavior, preferably parser/executor behavior that raises focused tests without repeating recursive/window/order clusters.
