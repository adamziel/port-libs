# PRAGMA Integrity Pointer-map Index Current-source Next85

Slice: `pragma-integrity-pointermap-index-current-source-next85`.

Adds a bounded current-source PRAGMA integrity yield for index-owned
pointer-map diagnostics. The helper reuses the existing native PHP
`integrity_check` pointer-map/freelist collector, enriches each diagnostic with
the launcher accepted source, next source, pointer-map entry type/parent, and
schema index/root ownership, then paginates with current/next85 metadata.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield.php
php -l lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapIndexCurrentSourceNext85Test.php
php -l lanes/libsqlite/examples/application-pragma-integrity-pointermap-index-current-source-next85.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapIndexCurrentSourceNext85Test.php
php lanes/libsqlite/examples/application-pragma-integrity-pointermap-index-current-source-next85.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
71 PASS lines
1 test files, 71 assertions, 0 failures
```

Application smoke:

The smoke reports copied `wp_options` index repair preflight rows with the
accepted base source, next-source label, nine pointer-map diagnostics, and five
index-owned pointer-map blockers over the `autoload` and `option_name` indexes.

Non-overlap:

This avoids accepted pointer-map/freelist integrity pagination, FK/index
combined admission, table-scoped integrity checks, b-tree page ordering,
autoindex root pagination, and batch82 PRAGMA integrity/FK index current-source
pagination. The new surface is specifically index-owned pointer-map diagnostic
annotation with current-source/next-source resumability for repair UIs.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP
SQLite header, database page, schema-record, pointer-map, and PRAGMA integrity
primitives.
