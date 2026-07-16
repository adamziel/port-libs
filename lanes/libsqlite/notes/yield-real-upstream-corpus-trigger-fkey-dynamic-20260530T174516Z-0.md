# Real Upstream Corpus Trigger/FK Dynamic Slice

- Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`.
- Ported sections: `e_fkey-31.2` / `e_fkey-31.3`, `e_fkey-32.1..32.9`, `e_fkey-36.1..36.4`, `e_fkey-37.1..37.6`, and `e_fkey-51.1..51.3`.
- New focused coverage: `lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php`, 51 PASS lines / 51 assertions.
- Implementation: `SQLiteTriggerForeignKeyDynamicPlan` models immediate FK statement rollback versus AFTER-trigger repair, deferred transaction commit blocking and repair, nested versus transaction savepoint release behavior, and parent UPDATE ordering through BEFORE trigger, FK SET DEFAULT action, and AFTER trigger.
- Non-overlap: this does not add metadata-only upstream rows and does not repeat previously accepted RETURNING, recursive trigger, or pragma FK catalog surfaces. It targets dynamic trigger/FK timing behavior from real `e_fkey.test` scenarios.
- Dependency closure: no new support component is needed; the slice reuses lane-local PHP array execution models for focused trigger/FK behavior.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerForeignKeyDynamicPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php
# 1 test files, 51 assertions, 0 failures
git diff --check -- lanes/libsqlite
```

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` could not run because that guard file is not present in this checkout.
