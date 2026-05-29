# SQLite Planner Join Order STAT4 Range Current Source Next116

- Added `SQLiteJoinOrderStat4RangeCurrentSourceNextPlan` for connected join-order ranking that reparses stale prepared plans against current schema/stat4/index signatures.
- The focused WordPress scenario models a plugin import where refreshed `sqlite_stat4` samples make the `wp_options(option_name, autoload)` range loop cheaper than older prepared estimates, so the selected connected order becomes `wp_options -> wp_postmeta -> wp_posts`.
- Non-overlap: this avoids accepted expression-index range-cost, expression ORDER BY, STAT4 range-order cursor, JSON table planner, and basic join row-production slices by proving multi-table join ordering from current-source STAT4 range estimates.
- Dependency closure: no new support component needed; this composes existing native planner metadata and `SQLiteMultiColumnRangePlan`.

Verification run for this handoff:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJoinOrderStat4RangeCurrentSourceNext116Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-join-order-stat4-range-current-source-next116.php
wordpress-join-order-stat4-range-current-source-next116 self-test passed

php -l lanes/libsqlite/src/SQLiteJoinOrderStat4RangeCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJoinOrderStat4RangeCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteJoinOrderStat4RangeCurrentSourceNext116Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJoinOrderStat4RangeCurrentSourceNext116Test.php

php -l lanes/libsqlite/examples/wordpress-join-order-stat4-range-current-source-next116.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-join-order-stat4-range-current-source-next116.php

git diff --check -- lanes/libsqlite
<no output>
```

Expected dashboard movement if accepted: `phpPass` 44622 -> 44676 (+54). Mapped coverage stays `604 / 1589`.
