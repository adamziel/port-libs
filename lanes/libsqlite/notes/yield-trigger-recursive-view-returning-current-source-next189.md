# yield-trigger-recursive-view-returning-current-source-next189

Status: focused PHP behavior growth for recursive view trigger RETURNING post-reset current-row acknowledgements before next-source admission.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Plan`. It builds on accepted next186 post-reset current-source RETURNING rebinding and models the following upstream boundary: queued next-source recursive view rows remain hidden until every freshly rebound current-source RETURNING row has been acknowledged with the matching reset generation and next-source token.

Application smoke: `application-trigger-recursive-view-returning-current-source-next189.php` covers a copied `wp_options` recursive import view where post-reset current-source rows for `siteurl` and `rewrite_rules` must be acknowledged before next-source rows for `home` and `next_plugin` become visible.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Plan.php

php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Test.php

php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next189.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next189.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext189Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 72 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next189.php
application-trigger-recursive-view-returning-current-source-next189 self-test passed
```

Expected dashboard movement: `phpPass +72`, from `90084` to `90156`. Mapped upstream coverage remains `616 / 1589`; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory rather than a fresh upstream manifest row.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger RETURNING reset/rebind modeling and adds row-ack next-source admission fencing.

Non-overlap: this extends accepted next186 post-reset current-source rebinding by requiring fresh rebound RETURNING row acknowledgements before queued next-source recursive view rows are visible. It avoids next171/176 cursor/page acknowledgement, next183 rollback invalidation, next186 stale cursor rebinding, DELETE RETURNING, UPSERT, row-value, WAL, VFS, JSON, planner, and B-tree slices.
