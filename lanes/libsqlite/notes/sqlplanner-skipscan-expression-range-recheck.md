# SQL Planner Skip-Scan Expression Range Recheck

- Scope: adds `SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan`, composing the accepted expression skip-scan range fence and adding a current-source residual `RecheckExpressionRange` audit before yielding cursor rows.
- Application path: copied `wp_options` plugin option lookups using `(autoload, lower(option_name))` skip-scan plans now reject stale/generated expression-key rows after schema/stat4/range-source changes.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSkipScanExpressionRangeRecheckTest.php` reports 1 test file, 62 assertions, 0 failures.
- Smoke evidence: `php lanes/libsqlite/examples/application-skipscan-expression-range-recheck.php` reports `requires-current-source-range-recheck`, accepted rowids `[1,2]`, rejected rowid `[3]`, and `RecheckExpressionRange`.
- Dependency closure: no new support component needed; this reuses native PHP expression skip-scan range planning and adds residual range recheck metadata.
- Non-overlap: avoids accepted range-fence selection, STAT4 prefix programs, planner STAT4 partial skip-scan, expression-index range-cost ranking, SQL expression ORDER BY, and JSON/VFS/WAL/B-tree surfaces.
