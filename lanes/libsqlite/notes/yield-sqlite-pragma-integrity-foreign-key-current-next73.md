# PRAGMA integrity/FK current-next73

## Behavior

Adds targeted `PRAGMA [schema.]foreign_key_check(table)` support to the
`SQLitePragmaIntegrityCurrentNextYield` paginator while preserving the existing
all-schema integrity-plus-FK path. This covers current/next pagination where
integrity rows are followed by only the requested child table's FK violations,
including schema-qualified `temp` checks, quoted target identifiers, `quick_check`
integration, empty tail pages, and parser guards.

## Application Smoke

`lanes/libsqlite/examples/application-pragma-integrity-foreign-key-current-next73.php`
models a copied Application temp `wp_options` import preflight: pointer-map
integrity findings fill the first current page and targeted
`temp.foreign_key_check(wp_options)` rows continue on the next page without
including unrelated main or temp tables.

## Verification

Focused:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyCurrentNext73Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 82 assertions, 0 failures
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLitePragmaIntegrityCurrentNextYield.php
php -l lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyCurrentNext73Test.php
php -l lanes/libsqlite/examples/application-pragma-integrity-foreign-key-current-next73.php
No syntax errors detected in all changed PHP files.
```

Smoke:

```text
php lanes/libsqlite/examples/application-pragma-integrity-foreign-key-current-next73.php --self-test
application-pragma-integrity-foreign-key-current-next73 self-test passed
```

Diff hygiene:

```text
git diff --check -- lanes/libsqlite
```

## Status Delta

Focused PASS-line delta: `+82`.

`lane-status.json` moves `phpPass` from `26631` to `26713`. Mapped upstream
denominator is unchanged because this is a focused PHP behavior slice rather
than a newly admitted upstream manifest unit.

## Non-overlap

Avoids accepted PRAGMA integrity pointer-map pagination, PRAGMA
foreign_key_check deferred/table-list behavior, batch68/69 VFS/WAL/JSON/trigger
surfaces, and the queued suite-denominator/VFS/file-control markers. This slice
is narrower: targeted schema/table FK admission inside the integrity
current/next paginator.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
`SQLitePragmaIntegrityCheck`, `SQLitePragmaForeignKeyIntegrity`, and
`SQLitePragmaForeignKeyCheck` helpers.
