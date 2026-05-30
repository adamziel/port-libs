# real-upstream-corpus-expression-affinity-dynamic-20260530T184327Z-0

Base accepted HEAD: `3b5859ae04f0cdc4e296cdcbe93d14e8b284a829`.

This slice adds `SQLiteRealUpstreamExpressionAffinityDynamicCorpusTest.php`, a
real upstream corpus batch derived from the hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-1` integer arithmetic, bitwise shifts/operators, NULL propagation
    through arithmetic plus `coalesce()`, and comparison behavior.
  - `expr-2` REAL arithmetic and modulo/division storage-class behavior.
  - `expr-3`/`expr-4` text comparison and numeric-looking text comparison
    behavior without column affinity.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-110` through `affinity2-150` storage class behavior.
  - `affinity2-200` through `affinity2-300` column-affinity comparison
    families represented as cast/storage checks against generic rows.

Focused coverage added:

- 3,080 distinct TestRunner PASS cases.
- 25,400 behavior assertions.
- Expected dashboard movement: `phpPass` +3,080 if admitted independently.
- Mapped denominator movement: none; this is PASS-line growth over already
  hydrated upstream source files, not runner-map denominator growth.

Non-overlap:

- Does not touch generated veryquick shard admission, suite-denominator rows,
  or runner-map records.
- Avoids the accepted date/affinity dynamic, expression affinity hex/text,
  encoding LIKE/GLOB, range-cost, expression ORDER BY, SELECT SQL, JSON, WAL,
  VFS, pager, and B-tree clusters.
- Uses generic `app`/SQLite terms only and adds no WordPress-specific API.

Dependency closure:

- No new support component is needed. The batch reuses existing
  `SQLiteSelectExpression`, `SQLiteSelectPredicate`, and
  `SQLiteCoreScalarFunction` behavior.
