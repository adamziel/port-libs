# pragma-index-xinfo-foreignkey-current-source-next198

This slice adds current-source PRAGMA/FK diagnostics for `WITHOUT ROWID`
composite parent primary keys. It layers on the accepted `index_xinfo` and
foreign-key parent-key helpers, then treats a matching `WITHOUT ROWID` table
primary key as the FK parent key when no separate `sqlite_schema` index record
is present.

Behavior covered:

- `PRAGMA table_info` primary-key order is used to identify a `WITHOUT ROWID`
  parent table primary key for `PRAGMA foreign_key_list` rows.
- `foreign_key_parent_key` rows that were previously missing are decorated as
  covered by `without-rowid-primary-key`.
- The next-state `foreign_key_parent_unique_index` blocker is removed only
  when all next-side missing parent-key rows are covered by the
  `WITHOUT ROWID` primary key.
- Mismatched composite primary-key arity stays blocked, while an ordinary
  separate UNIQUE index remains accepted through the existing path.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
54 PASS lines
1 test files, 61 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next198.php --self-test
wordpress-pragma-index-xinfo-foreignkey-current-source-next198 self-test passed
```

Non-overlap: avoids accepted next195 permuted UNIQUE parent-index diagnostics,
next193 order mismatch, next191 superset, next190 expression parent indexes,
next188 partial UNIQUE, next185 NULL child-key exemption, next182 collation,
and accepted PRAGMA quickcheck/integrity/FK pagination clusters. The new
surface is specifically `WITHOUT ROWID` table primary-key parent eligibility
when `PRAGMA index_xinfo` has no separate parent index row to inspect.

Dependency closure: no new support component is needed. This reuses
`SQLitePragmaSchemaCatalog`, `PRAGMA table_info`, `PRAGMA foreign_key_list`,
and the existing current/next PRAGMA page helpers.
