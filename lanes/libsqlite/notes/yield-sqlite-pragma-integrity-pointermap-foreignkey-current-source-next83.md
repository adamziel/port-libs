# PRAGMA integrity pointer-map/FK current-source next83

## Behavior

Adds `SQLitePragmaIntegritySourceCursor`, a source-bound wrapper around the
accepted PRAGMA integrity/FK current-next paginator. Resume cursors now carry a
stable `source_id` derived from the database image, integrity PRAGMA SQL,
targeted `foreign_key_check` SQL, and staged FK source rows. A next-page resume
is rejected if any of those inputs changed, preventing copied Application repair
preflights from combining pointer-map findings from one database image with FK
violations from a later source.

## Application Smoke

`lanes/libsqlite/examples/application-pragma-integrity-pointermap-foreignkey-current-source-next83.php`
models a copied `wp_options` integrity preflight where page 1 ends in
pointer-map findings and page 2 resumes with the same source cursor.

## Verification

Focused:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapForeignKeyCurrentSourceNext83Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

Smoke:

```text
php lanes/libsqlite/examples/application-pragma-integrity-pointermap-foreignkey-current-source-next83.php --self-test
application-pragma-integrity-pointermap-foreignkey-current-source-next83 self-test passed
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLitePragmaIntegritySourceCursor.php
php -l lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapForeignKeyCurrentSourceNext83Test.php
php -l lanes/libsqlite/examples/application-pragma-integrity-pointermap-foreignkey-current-source-next83.php
No syntax errors detected in all changed PHP files.
```

Diff hygiene:

```text
git diff --check -- lanes/libsqlite
```

## Status Delta

Focused PASS-line delta: `+48`.

`lane-status.json` moves `phpPass` from `31557` to `31605`. Mapped upstream
denominator is unchanged because this is a focused PHP behavior slice rather
than a newly admitted upstream manifest unit.

## Non-overlap

Avoids accepted PRAGMA pointer-map/FK pagination, FK target filtering,
autoindex/index-integrity slices, and batch80/81 integrity/FK index pagination.
This slice is narrower: source-stable current/next resume admission for the
already accepted pointer-map plus targeted FK row stream.

## Dependency Closure

No new support component is needed. The patch reuses native PHP
`SQLitePragmaIntegrityCurrentNextYield`, `SQLitePragmaIntegrityCheck`, and
`SQLitePragmaForeignKeyIntegrity`.
