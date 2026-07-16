# pragma-index-xinfo-foreignkey-current-source-next177

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, an additive
current-source PRAGMA helper over the accepted `index_xinfo` plus
catalog-derived foreign-key path.

The new behavior records foreign-key constraint identity that SQLite exposes
through DDL, not through `PRAGMA foreign_key_list` rows:

- named column-level constraints such as `blog_id CONSTRAINT fk_option_site
  REFERENCES ...`;
- named table-level composite constraints such as `CONSTRAINT
  fk_option_name_locale FOREIGN KEY(option_name, locale) REFERENCES ...`;
- unnamed table-level FK clauses;
- table-vs-column origin, child-column lists, parent table names, source-fresh
  current/next summaries, counts, row decoration, pagination, and stale cursor
  rejection.

This lets copied Application `wp_options` import diagnostics keep
`PRAGMA index_xinfo` and `foreign_key_check` pages tied to stable FK names and
clause origins when schema DDL changes between current and next sources.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 81 assertions, 0 failures
```

The focused run emitted 73 PASS lines. `lane-status.json` `phpPass` moves from
82455 to 82528. Mapped upstream coverage remains `613 / 1589`; this is focused
PHP behavior over already mapped PRAGMA `index_xinfo`, `foreign_key_list`, and
`foreign_key_check` inventory rather than a fresh manifest row.

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next177.php --self-test
application-pragma-index-xinfo-foreignkey-current-source-next177 self-test passed
```

Non-overlap: this avoids accepted next156/159/161/164/165/166/167/169/170/171/
172/173 PRAGMA FK/index surfaces. The added behavior is only DDL-derived FK
constraint names and column/table-level clause origin beside the accepted
current-source `index_xinfo`, action, deferral, timing, targeted
`foreign_key_check`, and pagination behavior. It also avoids accepted
quickcheck/rootpage, index_list, PRAGMA optimize/index_xinfo/table_info
analysis, pointer-map, and recursive FK catalog clusters.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`,
parent-index admission, `foreign_key_check`, action, and deferral helpers.
