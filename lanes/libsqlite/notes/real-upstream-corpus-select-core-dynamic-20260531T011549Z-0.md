# real-upstream-corpus-select-core-dynamic-20260531T011549Z-0

Base accepted HEAD: `2541019b82319811accbb79790d214be59d31028`.

Implemented an additive real upstream SELECT core corpus batch from hydrated
SQLite source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`
- Scenarios: `selectA-2.3`, `selectA-2.12`, `selectA-2.13`,
  `selectA-2.14`, `selectA-2.15`, `selectA-2.16`, `selectA-2.20`,
  `selectA-2.21`, `selectA-2.22`, `selectA-2.23`, `selectA-2.24`,
  `selectA-2.25`, and `selectA-2.26`.
- Behavior: compound `UNION ALL` and distinct `UNION` merge ordering over
  mixed storage classes, reversed compound arms, expression-column order terms,
  explicit `NOCASE` and `BINARY` collations, descending sort terms, and final
  whole-compound `LIMIT` / `OFFSET` windows.

Added `SQLiteRealUpstreamSelectAUnionDistinctOrderRemainderDynamicTest.php`
with 1,587 distinct TestRunner PASS cases and 12,679 focused assertions. This
is PASS-line growth only; mapped denominator coverage remains unchanged because
`selectA.test` is already present in the hydrated upstream inventory.

Non-overlap:

- The immediately prior selectA batch owns `selectA-2.1`, `selectA-2.1.1`,
  `selectA-2.1.2`, `selectA-2.2`, `selectA-2.4`, `selectA-2.5`,
  `selectA-2.6`, `selectA-2.10`, and `selectA-2.11`.
- Older selectA batches cover later `INTERSECT` / `EXCEPT` rows and separate
  select-core files cover `select1` through `select9` plus `selectB` through
  `selectH`.
- This batch does not add metadata-only runner rows, generated fake upstream
  script ids, or domain-specific API names.

Exclusion:

- `selectA-2.7`, `selectA-2.8`, `selectA-2.9`, `selectA-2.17`,
  `selectA-2.18`, and `selectA-2.19` remain excluded because the current
  row-array compound executor does not carry table-declared default
  `COLLATE NOCASE` metadata for the `c`/`z` result column. A later SELECT
  collation slice should implement default result-column collation propagation
  before admitting those rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionDistinctOrderRemainderDynamicTest.php`
  passed with `1 test files, 12679 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionDistinctOrderRemainderDynamicTest.php`
  passed with no syntax errors.
- `git diff --check -- lanes/libsqlite` passed.
- Dependency closure: no new support component is needed; this reuses the
  lane-local `SQLiteSelectSql` compound SELECT executor, explicit collation
  handling, distinct set ordering, and final LIMIT/OFFSET trimming.
