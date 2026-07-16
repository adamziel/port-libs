# yield-trigger-recursive-view-returning-current-source-next192

Status: focused PHP behavior growth for recursive view trigger RETURNING next-source cursor close fencing before a following current source is admitted.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Plan`. It builds on accepted next189 post-reset current-row acknowledgements and models the next upstream boundary: after queued next-source recursive view rows become visible, a following current-source generation remains hidden until every next-source RETURNING row is acknowledged and the matching next-source cursor is closed.

Application smoke: `application-trigger-recursive-view-returning-current-source-next192.php` covers a copied `wp_options` recursive import view where next-source rows for `home` and `next_plugin` must drain before a following current import for `blogdescription` and `stylesheet` becomes visible.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Plan.php

php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Test.php

php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next192.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next192.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext192Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 76 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next192.php
application-trigger-recursive-view-returning-current-source-next192 self-test passed

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +76`, from `92140` to `92216`. Mapped upstream coverage remains `617 / 1589`; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory rather than a fresh upstream manifest row.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger RETURNING current-source and next-source cursor modeling.

Non-overlap: this extends accepted next189 row-ack next-source admission with the later next-source cursor-close barrier before a following current-source generation. It avoids next183 rollback invalidation, next186 post-reset rebind, next189 current-row acknowledgements, row-value, UPSERT, WAL, VFS, JSON, planner, and B-tree slices.
