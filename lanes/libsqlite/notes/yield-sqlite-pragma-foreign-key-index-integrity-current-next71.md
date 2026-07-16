# yield-sqlite-pragma-foreign-key-index-integrity-current-next71

Scope: bounded PRAGMA foreign-key/index integrity current-next admission for
parent-key indexes. This slice covers upstream-shaped foreign-key parent key
eligibility when a parent key is backed by a named UNIQUE index, a rowid
INTEGER PRIMARY KEY alias, a non-unique index, a partial UNIQUE index, or a
UNIQUE index whose collation does not match the parent column declaration.

Behavior added:

- Adds `SQLitePragmaForeignKeyIndexIntegrityYield` to collect index-admission
  rows and live `foreign_key_check` violations in one resumable current/next
  page.
- Accepts named UNIQUE indexes with matching parent columns and declared
  collations, not just `sqlite_autoindex_*` parent-key coverage.
- Blocks non-unique indexes, partial UNIQUE indexes, and UNIQUE indexes whose
  collation does not match the parent key collation.
- Keeps SQL NULL child-key behavior delegated to existing foreign-key check
  semantics and preserves rowid-primary-key parent admission.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIndexIntegrityCurrentNext71Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 69 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pragma-foreign-key-index-integrity-current-next71.php
```

The smoke reports copied `wp_options` parent-key integrity for option-name and
plugin-code metadata imports, including a named `NOCASE` UNIQUE parent index,
a non-unique plugin-code parent index blocker, and live FK violations.

Non-overlap: this does not repeat accepted PRAGMA foreign_key_check schema
resolution, pointer-map/FK yield pagination, autoindex-only FK preflight,
PRAGMA b-tree order integrity, PRAGMA index_xinfo root diagnostics, or batch68
PRAGMA integrity b-tree page ordering. The new surface is named UNIQUE
parent-key index admission with collation/partial-index gating plus FK
violation pagination.

Dependency closure: no new support component is needed. The patch reuses
existing lane-local schema catalog, PRAGMA index_list/index_xinfo metadata, and
foreign-key check execution.
