# Consolidate Final Numbered Methods Attach Schema Forty Fourth Pass

This consolidation pass removes residual generated worker-range helper names
from direct attach temp WAL schema-cache final-window tests. The production
family already exposes stable canonical entrypoints on
`SQLiteAttachWalTempSchemaCachePlan`; this pass keeps those production APIs and
renames the remaining test-local helpers to descriptive window names:

- review, archive, metrics, audit, and report windows
- publish handoff and final handoff windows
- final preparation and final attach windows

No production compatibility shim, numbered production class, numbered
production file, or numbered production helper was added.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReviewWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheArchiveWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheMetricsWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheAuditWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReportWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishHandoffWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalHandoffWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPreparationWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalAttachWindowTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReviewWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheArchiveWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheMetricsWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheAuditWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReportWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishHandoffWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalHandoffWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPreparationWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalAttachWindowTest.php
```

Focused result:

```text
9 test files, 235 assertions, 0 failures
```

Dependency closure: no new support component is needed; this is a direct
test-helper consolidation over existing attach/schema cache primitives.
