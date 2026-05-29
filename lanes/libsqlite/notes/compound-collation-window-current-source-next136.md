# compound-select-collation-window-current-source-next136

Status: focused PHP behavior growth for compound SELECT set-operator collation plus per-arm window rows across current/next sources.

This slice adds `SQLiteCompoundCollationWindowCurrentSourceNextPlan`. It checks a current-source boundary where:

- the first compound arm supplies `COLLATE NOCASE` for distinct set-operator comparison;
- `row_number()` window values are evaluated in each arm before `UNION` duplicate elimination;
- the next copied `wp_options` source can suppress only rows whose collation key and window value both match.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-compound-collation-window-current-source-next136.php --self-test
```

Focused tests:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCollationWindowCurrentSourceNext136Test.php
```

Expected dashboard movement: `phpPass +61` from the new focused test file. `benchmarkDenominator.mapped` remains `606 / 1589`; this is current-source PHP behavior over already mapped compound SELECT, collation, and window inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound row composition, recursive collation/limit next132, compound window/EXCEPT affinity next133, window filter/current-source slices, SQL expression ORDER BY, grouped SELECT text, named-window subquery, VDBE sorter/window collation work, and encoding-only LIKE/GLOB/collation clusters. The new surface is left-arm set collation interacting with per-arm window row numbers at the compound current/next source boundary.

Dependency closure: no new support component is needed; this reuses lane-local SELECT SQL compound execution, collation keys, and window row-array execution.
