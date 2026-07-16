# PRAGMA index_xinfo / foreign-key implicit parent current-source next162

Slice: `pragma-index-xinfo-foreignkey-current-source-next162`.

This slice extends the catalog-derived `PRAGMA foreign_key_list(...)` bridge
used by the index_xinfo/FK current-source cursor. When SQLite reports a
foreign-key parent column as NULL because the DDL omitted the parent column
list, the bridge now resolves the parent table primary key and feeds those
columns into parent-index admission and `foreign_key_check` comparison.

Behavior covered:

- column-level `REFERENCES parent` resolves to the parent INTEGER PRIMARY KEY;
- table-level composite `FOREIGN KEY(a,b) REFERENCES parent` resolves to the
  parent composite PRIMARY KEY in pk order;
- parent affinity/collation enrichment is preserved after implicit resolution;
- current/next source hashes change when implicit parent DDL or row data
  changes;
- cursor pagination and stale-cursor rejection still cover the derived source;
- arity mismatches remain explicit blockers instead of silently guessing.

Focused verification:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyImplicitParentCurrentSourceNext162Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

Application smoke:

```sh
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162.php --self-test
application-pragma-index-xinfo-foreignkey-implicit-parent-current-source-next162 self-test passed
```

PASS-line delta: +61 focused PASS lines.

Non-overlap: this builds on next159 catalog-derived FK rows, but covers the
previously rejected implicit parent primary-key path. It avoids accepted
index_xinfo expression metadata, FK recursive output, quickcheck/rootpage,
PRAGMA optimize/index_xinfo, visible rootpage, pointer-map, and FK pagination
clusters.

Dependency closure: no new support component is needed. The slice reuses the
existing PRAGMA schema catalog, table_info primary-key metadata, index_xinfo
rows, parent-index admission, and foreign_key_check helpers.
