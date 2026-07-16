# real-upstream-corpus-expression-affinity-dynamic-20260531T072757Z-0

- Base accepted HEAD: `49647c646cee956ed1d4c9609a0c5aac0efc4e84`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/intreal.test`.
- Ported scenarios:
  - `intreal-100` through `intreal-180`: integer-looking REAL scalar expression results, equality, concatenation, `typeof()`, and scalar `max()` storage-class behavior.
  - `intreal-2.1` through `intreal-2.6`: REAL-affinity table comparisons against `CAST(... AS REAL)` exact/range predicates.
  - `intreal-3.0`: replacement/update path preserves REAL storage-class behavior around expression-index values.
  - `intreal-4.0` through `intreal-4.3`: expression-derived values inserted into REAL-affinity columns report `real`.
- Implementation delta:
  - `SQLiteCoreScalarFunction::substring()` now uses SQLite text coercion for scalar REAL input instead of PHP's raw `(string)` conversion, preserving `substr(CAST(1 AS REAL),1,4) == '1.0'`.
  - `SQLiteCoreScalarFunction::formatFloat()` trims redundant trailing zeroes in large integer-valued REAL mantissas, matching SQLite `quote()` output such as `4.75022839619449344e+18`.
- Focused evidence:
  - `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIntReal20260531T072757ZTest.php`
  - Result: `1 test files, 3626 assertions, 0 failures`.
- Non-overlap:
  - Avoids accepted `affinity2-500..507`, `affinity2-600..601`, `affinity3` REAL view joins, broad `e_expr` operator/CASE/BETWEEN/LIKE/GLOB/collation shards, expression `ORDER BY`, grouped SELECT text, JSON, B-tree, WAL, VFS, PRAGMA, trigger/FK, and source-neutral cleanup batches.
- Dependency closure:
  - No new support component needed; the slice reuses `SQLiteSelectSql`, scalar function dispatch, REAL CAST/storage-class handling, table affinity metadata, and `sqlite3` oracle parity against hydrated upstream `intreal.test`.
