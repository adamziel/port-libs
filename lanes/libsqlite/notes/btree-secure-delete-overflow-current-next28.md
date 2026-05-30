# B-tree secure-delete overflow current/next 28

## Scope

- Added `SQLiteOverflowPage::chainLinksFromChain()` and `chainLinksFromDatabase()` so overflow release code can expose the exact current-page to next-page links that were followed before secure-delete release.
- Added focused coverage for copied `wp_options` table and index overflow chains with sparse current/next page numbers `[20, 22, 106]` and `[107, 21]`.
- Reused the existing overflow freelist release and auto-vacuum pointer-map application path; this does not repeat accepted root-collapse, page-move, bulk freeblock, overflow freelist release, or VFS/WAL clusters.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeSecureDeleteOverflowCurrentNext28Test.php
Focused test run: 1 selected test files (root lock skipped)
41 PASS lines, 41 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-secure-delete-overflow-current-next28.php
```

## Dependency Closure

No new support component is needed. The slice uses existing native PHP SQLite page, overflow-chain, freelist, pointer-map, and test-runner primitives under `lanes/libsqlite`.
