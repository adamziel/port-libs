# Real Upstream Corpus: Expression Affinity REAL Index Drift

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T004319Z-0`

Base accepted HEAD: `ad16a572f80ccf85246d93f3ad58ce0402786c09`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expridx1.test`
- Sections `expridx1-1.1.*` through `expridx1-1.3.*`
- Sections `expridx1-4.2` through `expridx1-4.6`

Patch:

- Added `SQLiteRealExpressionAffinityCorpusPlan::realExpressionIndexDriftPlan()`.
- Added `SQLiteRealUpstreamExpressionAffinityRealIndexDynamicTest.php`.
- The test ports stale REAL expression-index behavior: near ULP drift is reported as imprecise, larger REAL drift is reported as missing, and rowid-targeted cleanup removes stale entries.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealIndexDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealIndexDynamicTest.php`
  - `1 test files, 2096 assertions, 0 failures`

Countability:

- Adds `1045` focused TestRunner PASS cases in one new real-upstream behavior test file.
- Adds `2096` focused assertions.
- Mapped denominator coverage is unchanged because this is behavior coverage from already hydrated upstream corpus inventory.

Non-overlap:

- Avoids accepted signed-literal affinity, BETWEEN affinity, types2 text affinity, affinity2/affinity3, expression ORDER BY, expression-index range-cost, STAT4 expression-planner, JSON, WAL, VFS, and B-tree page-move/freeblock clusters.

Dependency closure:

- No new support component needed. The helper reuses the existing expression-affinity corpus class and native PHP REAL classification logic.
