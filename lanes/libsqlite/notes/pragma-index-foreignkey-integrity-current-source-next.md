# pragma-index-foreignkey-integrity-current-source-next

This slice adds a current-source pagination bridge for copied Application
`wp_options` PRAGMA diagnostics: `index_list` / `index_xinfo` / index rootpage
integrity rows are emitted in the same resumable cursor as foreign-key parent
index admissions and `foreign_key_check` violations.

Behavior covered:

- one stable `source_id` spans the catalog image, database bytes, schema
  records, foreign-key declarations, table rows, source labels, normalized
  `index_list` SQL, and integrity SQL;
- mixed pages resume only when the current source still matches, and reject
  stale database/catalog/table-row cursors;
- blocking state distinguishes index rootpage integrity failures,
  missing/invalid FK parent unique indexes, and FK row violations;
- clean copied `wp_options` rows and valid index root pages report `ok`.

Focused verification:

```bash
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 82 assertions, 0 failures
```

PASS-line delta: `+74` focused PHP PASS lines.

Application smoke:

```bash
$ php lanes/libsqlite/examples/application-pragma-index-foreignkey-integrity-current-source-next.php --self-test
application-pragma-index-foreignkey-integrity-current-source-next self-test passed
```

Dependency closure: no new support component is needed. This reuses existing
native PHP schema catalog PRAGMA cursors, index rootpage integrity checks, FK
parent-index admission checks, and FK row comparison helpers.

Non-overlap: avoids accepted next133 index integrity cursor mechanics, next131
FK/index integrity rows, next132 quickcheck FK rootpage behavior, accepted
PRAGMA index_xinfo/rootpage/pointer-map pagination slices, and current
B-tree/WAL/JSON/SELECT worker surfaces. The new behavior is the combined
current-source cursor and blocking summary across index PRAGMA integrity rows
and FK integrity rows.
