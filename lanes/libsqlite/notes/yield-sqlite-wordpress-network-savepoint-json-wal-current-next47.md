# WordPress Network JSON WAL Savepoint Current Next47

Adds `SQLiteWordPressNetworkJsonWalSavepointPlan`, a bounded WordPress-network
composition layer over the existing JSON import WAL savepoint planner. The
slice keeps per-site `wp_options` / `wp_<blog_id>_options` JSON imports and
network `wp_sitemeta` JSON imports isolated by savepoint prefix while exposing
network-scoped WAL frame and dirty-page namespaces.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWordPressNetworkJsonWalSavepointCurrentNext47Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 70 assertions, 0 failures
```

The focused test adds 70 PASS lines over site/global table separation, JSON
subtype and JSONB payload admission, malformed JSON rollback isolation,
per-site WAL frame mapping, network dirty-page namespacing, validation, and
continue-on-site-error behavior.

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-network-json-wal-savepoint-current-next47.php
```

Dependency closure: no new support component is needed. This reuses the
accepted bounded JSON WAL savepoint planner, JSON subtype/JSONB helpers, and
savepoint WAL rollback primitive; the new layer is a network composition and
namespacing step only.

Non-overlap: avoids accepted JSON table SELECT/cursor/hidden/visible
constraint work, WAL byte truncation, rollback-journal commit/apply,
super-journal/sync/VFS file writer and lock-state work, B-tree page move/root
collapse/overflow freelist release, Unicode GLOB, SELECT SQL subquery/GROUP BY
/ORDER expression/comma-LIMIT, and batch37 multisite row-batch savepoint
coverage. This slice is specifically JSON payload imports across a WordPress
network with WAL frame namespace evidence.
