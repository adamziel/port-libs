# SQLite attach TEMP WAL schema cache current-source next877-892

Extends the rollout-window attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCachePlan::schemaCacheReviewWindow()`.

- next877-892 keeps the same consolidated attach schema-cache planner and records the next dependency range after the rollout-window predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next892, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached archive schema visibility, publish WAL movement, and detached-schema removal.
- The detached transient archive remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReviewWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-review-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReviewWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-review-window.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
