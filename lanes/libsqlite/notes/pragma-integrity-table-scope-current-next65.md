# PRAGMA integrity table scope current-next65

Slice: `pragma-integrity-current-next65`.

This slice adds bounded native PHP support for upstream-style table-scoped
`PRAGMA integrity_check(table)` and `PRAGMA quick_check(table)` planning. It
collects the target table plus associated index root pages from the parsed
`sqlite_schema`, filters existing integrity diagnostics down to that table
scope, and exposes current/next pagination for Application repair screens that
need to inspect `wp_options` without surfacing unrelated table, freelist, or
database-wide root-limit diagnostics.

Behavior covered:

- parses schema-qualified, quoted, and single-quoted table-scoped integrity
  PRAGMAs;
- returns `ok` for clean table/index root scopes;
- reports target table/index root-page and pointer-map diagnostics;
- ignores unrelated `wp_posts` root failures and freelist/header diagnostics
  for a `wp_options` scoped check while preserving the global diagnostic path;
- paginates target root rows plus scoped integrity rows with current/next
  metadata.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityTableScopeCurrentNext65Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 101 assertions, 0 failures
```

Smoke evidence:

```text
$ php lanes/libsqlite/examples/application-pragma-integrity-table-scope-current-next65.php
```

Non-overlap:

This avoids accepted/queued pointer-map/freelist integrity pagination,
foreign-key integrity yields, autoindex/root pagination, `index_xinfo` plus
integrity root diagnostics, deep b-tree integrity walking, schema catalog DDL
planning/reparse, VFS/WAL transaction application, B-tree mutation/freeblock
work, JSON planner work, and accepted SQL executor batches. The new surface is
specifically the table-argument form of `integrity_check` / `quick_check` and
its current/next table-root pagination.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP
SQLite database image parser, `sqlite_schema` record decoding, b-tree page
helpers, pointer-map checks, and integrity checker.
