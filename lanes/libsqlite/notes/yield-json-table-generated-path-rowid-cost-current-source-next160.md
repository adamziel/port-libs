# JSON Table Generated Path Rowid Cost Current Source Next160

- Lane/session: `libsqlite` / `port-dev-sqlite-yield-jsonvt160`.
- Base accepted HEAD: `a6b9d27bc90e696dc97f234875031ff0ed48f10f`.
- Behavior: added current-source admission profiling for `json_tree`/`json_each` generated-path plus rowid alias constraints. The planner now records JSON source identity, generated-path source column, root column, argv bindings, omit/residual columns, source/cost/plan fingerprints, and next-source replan reasons on top of the existing generated-path rowid-cost profile.
- Focused tests: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext160Test.php` => `1 test files, 67 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next160.php --self-test` passed.
- Expected dashboard movement: `phpPass` +67 if accepted; mapped upstream coverage unchanged because this adds runtime/planner coverage without a new manifest denominator row.
- Non-overlap: avoids accepted/queued JSON table hidden/generated rowid, generated-path cost next134, generated-path rowid-cost next145, hidden-generated rowid next157, visible/hidden constraint pushdown, parser-level JSON table SELECT source/cursor, and aggregate/window surfaces. This slice only adds the current-source vtab-filter admission/fingerprint layer for generated-path rowid-cost.
- Dependency closure: no new support component needed; reuses native JSON table planning, JSON1/JSONB input classification, path validation, and rowid alias constraint helpers.
