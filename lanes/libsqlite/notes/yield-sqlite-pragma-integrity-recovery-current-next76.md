# PRAGMA Integrity Recovery Current/Next 76

## Behavior

Adds `SQLitePragmaIntegrityRecoveryCurrentNextPlan`, a recovery gate that compares
`PRAGMA integrity_check` or `quick_check` diagnostics for the current dirty
database image and the next recovered image. The plan classifies diagnostics as
resolved, persisting, or introduced, preserves recovery operation reasons, and
sets `must_block_commit` when the recovered image still has integrity findings
or introduces new ones.

This is intentionally layered on the existing integrity/FK/pointer-map yield
collector instead of reimplementing b-tree, pointer-map, or foreign-key checks.

## Focused Evidence

Final local verification commands:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIntegrityRecoveryCurrentNextPlan.php
php -l lanes/libsqlite/tests/SQLitePragmaIntegrityRecoveryCurrentNext76Test.php
php -l lanes/libsqlite/examples/application-pragma-integrity-recovery-current-next76.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityRecoveryCurrentNext76Test.php
php lanes/libsqlite/examples/application-pragma-integrity-recovery-current-next76.php
git diff --check -- lanes/libsqlite
```

Focused test result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

## Non-Overlap

Avoids accepted PRAGMA integrity b-tree page ordering, table-scope pagination,
foreign-key/index/pointer-map integrity yield slices, and accepted rollback/WAL
recovery application helpers. This patch only adds the current/next comparison
gate that consumes existing recovery outputs and existing integrity collectors.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLitePragmaIntegrityCurrentNextYield`, `SQLitePragmaIntegrityCheck`, and
foreign-key integrity collectors. Future pager/VFS recovery workers can feed
their recovered database bytes and operation list into this gate.
