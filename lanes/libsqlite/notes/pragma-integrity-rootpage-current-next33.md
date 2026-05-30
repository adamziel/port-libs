# PRAGMA Integrity Rootpage Current Next33

This slice tightens `PRAGMA integrity_check` / `quick_check` rootpage handling
for auto-vacuum databases. The checker now derives current table/index root
pages from `sqlite_schema` instead of treating every page number up to
`largestRootBtreePage` as a root.

Behavior covered:

- Validates `sqlite_schema` table/index rootpage values against the database
  image and freelist.
- Compares the auto-vacuum largest-root header field to the current
  `sqlite_schema` maximum rootpage.
- Checks pointer-map root-page entries only for actual current table/index
  roots, while ordinary b-tree pages below the largest root remain btree-page.
- Keeps view/trigger zero rootpages ignored for root-page integrity.
- Preserves `quick_check` row shape and limit metadata while still reporting
  rootpage metadata errors.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityRootpageCurrentNext33Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-integrity-rootpage-current-next33.php
{
    "scenario": "copied wp_options PRAGMA integrity_check rootpage current metadata",
    "valid": {
        "rows": [
            {
                "integrity_check": "ok"
            }
        ],
        "errors": []
    },
    "stalePointerMapRoot": {
        "firstError": "pointer-map type root-page for page 3 does not match expected btree-page"
    },
    "dependencyTags": [
        "sqlite-pragma-integrity-check",
        "sqlite-schema-rootpage-current",
        "sqlite-auto-vacuum-pointer-map"
    ]
}
```

Non-overlap: this avoids accepted deep PRAGMA integrity page-structure,
foreign-key integrity, PRAGMA auto-vacuum/page-count, B-tree page move/root
collapse/overflow freelist release, VFS writer/sync/rollback, JSON table, and
SELECT SQL text clusters. It is limited to current `sqlite_schema` rootpage
semantics inside integrity checking.

Dependency closure: no new support component is needed. The slice reuses the
lane-local SQLite header, schema-record, b-tree page, pointer-map, and
freelist primitives.
