# JSON Table Generated Path Rowid Cost Current Source Next162

- Lane/session: `libsqlite` / `port-dev-sqlite-yield-jsonvt162`.
- Base accepted HEAD: `4f3992aac5565e56bf8760474f9f96db90489d93`.
- Behavior: added a next162 current-source `json_tree()` / `json_each()` planning layer for generated-path plus rowid alias constraints. It records `_rowid_` / `rowid` / `oid` provenance, xBestIndex-style `idxNum` / `idxStr`, generated-path and rowid argv positions, ORDER BY consumption, omit/residual columns, stable source keys, and row/cost estimates on top of the accepted next160 generated-path rowid-cost source profile.
- Focused tests: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext162Test.php` => `1 test files, 52 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next162.php --self-test` passed.
- Expected dashboard movement: `phpPass` +52 if accepted; mapped upstream coverage unchanged because this is planner/runtime coverage without a new manifest denominator row.
- Non-overlap: avoids accepted JSON table generated-path rowid-cost next145, current-source next158/next160, generated-path rowid seek-cost next159, hidden/visible constraint pushdown, parser-level JSON table SELECT/FROM source wiring, JSON cursor behavior, and JSON aggregate/window surfaces. This slice only adds rowid-alias/xBestIndex provenance and stable-source costing for the current-source generated-path rowid-cost profile.
- Dependency closure: no new support component needed; reuses native JSON table planning, JSON1/JSONB input classification, path validation, rowid alias normalization, and current-source fingerprint helpers.
