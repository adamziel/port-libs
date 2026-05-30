# JSON table generated hidden path current-source next144

Status: focused PHP behavior growth for `json-table-generated-hidden-path-current-source-next144`.

This slice adds `SQLiteJsonTablePlan::currentSourceGeneratedHiddenPathNext144()`. It composes the accepted generated-hidden residual planner with a generated hidden path/source profile so a current `json_tree()` cursor stays pinned to the current generated path while the next source can reprepare for a different generated subtree, JSON source kind, rowset, residual value tape, and cost class.

Application smoke: `application-json-table-generated-hidden-path-current-source-next144.php` covers copied `wp_options` plugin settings where an `active_path` generated column moves diagnostics from the core rules subtree to the commerce rules subtree while usable generated predicates and residual slug checks stay explicit.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenPathCurrentSourceNext144Test.php`
- Result: `1 test files, 54 assertions, 0 failures`.

Expected dashboard movement: `phpPass` +54 from focused PASS lines (`63412 -> 63466`). Mapped coverage is unchanged; this is PHP behavior coverage over an already mapped JSON table current-source family and does not claim a fresh upstream runner row.

Non-overlap: avoids accepted JSON visible constraints, hidden constraints, generated hidden cost/residual cost next136/next141, hidden path rowid next140, parser-level JSON table SELECT sources, cursor behavior, lateral host joins, malformed JSONB planner, and JSON aggregate/window clusters. The new behavior is specifically generated hidden path/source fingerprinting and current/next reprepare reasons over the generated path column.

Dependency closure: no new support component is needed; the slice reuses native JSON path composition, JSON source validation, `json_tree()` rows, and generated hidden residual costing.

Next task: continue with non-overlapping JSON planner behavior such as dynamic source join edges or malformed JSONB planner admission outside hidden/generated path current-source coverage.
