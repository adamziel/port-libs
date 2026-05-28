# Trigger deferred RETURNING savepoint current-source next141

Status: focused PHP behavior growth for `trigger-deferred-returning-savepoint-current-source-next141`.

This slice adds `SQLiteTriggerDeferredReturningSavepointCurrentSourceNext141Plan`. It composes the existing deferred trigger RETURNING savepoint executor with a commit-attempt barrier: RETURNING rows are yielded during the current statement, a deferred FK violation blocks commit at the next source, `ROLLBACK TO` restores the savepoint image, invalidates those yielded rows as non-durable, clears the deferred queue, and admits the next retry from the restored current source.

WordPress path: `wordpress-trigger-deferred-returning-savepoint-current-source-next141.php` models a copied `wp_posts`/`wp_postmeta` import that recursively rekeys posts, emits RETURNING rows for UI/import diagnostics, then hits a deferred FK audit orphan at commit and retries from the restored savepoint image.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNext141Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointCurrentSourceNext141Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-deferred-returning-savepoint-current-source-next141.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointCurrentSourceNext141Test.php
php lanes/libsqlite/examples/wordpress-trigger-deferred-returning-savepoint-current-source-next141.php --self-test
git diff --check -- lanes/libsqlite
```

Dashboard delta: `phpPass` moves by the exact focused PASS-line count verified for this new test file. Mapped upstream coverage remains unchanged because this is fresh PHP current-source behavior over already mapped trigger/deferred-FK/RETURNING/savepoint inventory.

Non-overlap: avoids accepted next119 deferred RETURNING savepoint rollback, next125 recursive deferred RETURNING barriers, next139 recursive RETURNING savepoint rollback, next138 RAISE IGNORE/upsert trigger behavior, row-value RETURNING savepoint clusters, VFS savepoint rollback, WAL byte truncation/checkpoint/readers, pager hot-journal savepoint cache work, and accepted SELECT/JSON/B-tree/encoding surfaces. The new behavior is the commit barrier and retry admission after RETURNING rows were yielded but deferred FK commit validation failed.

Dependency closure: no new support component is needed. The slice reuses lane-local trigger deferred FK RETURNING, savepoint rollback, and current-source markers.

Next task: wire this commit-barrier retry state into a broader native statement executor when deferred FK commit validation and RETURNING row delivery share one VDBE-style pipeline.
