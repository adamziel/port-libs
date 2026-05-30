# SQLite Expression Covering Skip-Scan Current Source Next132

## Behavior

Adds a bounded current-source planner path for partial expression skip-scan indexes that can also prove expression projection coverage. The planner reuses the existing native PHP skip-scan and expression materialization path, then records whether requested expression payloads such as `lower(option_name)` are covered by the current index cursor or require a deferred table seek.

This is intentionally disjoint from accepted next127 partial covering skip-scan, next129 partial expression skip-scan, next122/next109 STAT4 expression covering, and accepted expression `ORDER BY` work. The new behavior is the combined expression-projection covering decision for stale prepared skip-scan plans against the current source.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionCoveringSkipScanCurrentSourceNext132Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-expression-covering-skipscan-current-source-next132.php --self-test
```

Result:

```text
application-expression-covering-skipscan-current-source-next132 self-test passed
```

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP expression skip-scan materialization, current-source fences, STAT4 skip-scan estimates, and covering-index cursor evidence.

## Next Gate

A follow-up planner slice can connect this expression-covering decision into broader parser-level SELECT planning once the integrator accepts the current-source next132 helper and focused test growth.
