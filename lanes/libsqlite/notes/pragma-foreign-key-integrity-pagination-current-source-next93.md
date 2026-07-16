# PRAGMA FK Integrity Pagination Current Source Next93

## Scope

- Adds `SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield`, a
  current-source paginator for combined integrity-check and targeted
  `foreign_key_check` rows.
- Reuses the accepted integrity and FK-check executors while adding resume
  metadata: target schema, target source, schema generation, search order,
  page boundary rows, and pending-page/stale-cache blockers.
- Covers both table-valued `pragma_foreign_key_check(...)` and direct
  `PRAGMA schema.foreign_key_check(table)` forms without repeating the older
  next73/next86 row collection behavior.

## Verification

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceNext93Test.php
```

Result: `1 test files, 59 assertions, 0 failures` with 59 PASS lines.

## Application Smoke

```sh
php lanes/libsqlite/examples/application-pragma-foreign-key-integrity-pagination-current-source-next93.php
```

The smoke reports copied `wp_options` FK integrity pages resolving the current
TEMP source for unqualified checks and the attached `archive` source for
qualified checks, with resume metadata for import/repair loops.

## Non-Overlap

This avoids accepted PRAGMA integrity/FK pagination, table-valued
`foreign_key_check` current-source routing, FK/index integrity admission, and
batch89 autoindex pointer-map integrity checks. The new behavior is the
schema-generation-aware pagination/resume envelope around those accepted
sources.

## Dependency Closure

No new support component is needed. This reuses existing native PHP PRAGMA,
attached-schema catalog, pointer-map, and integrity-check components.
