# real-upstream-corpus-select-core-dynamic-20260531T014304Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`.

Added focused real-upstream SELECT corpus coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`.

Owned upstream scenarios:

- `selectC-1.1` / `selectC-1.2`: result-column alias and expression predicate
  visibility for `WHERE ... IN`.
- `selectC-1.3` through `selectC-1.7`: alias/expression equality predicates,
  including unary-plus alias references.
- `selectC-1.8` through `selectC-1.11`: `GROUP BY` and `HAVING` visibility for
  result aliases and equivalent expressions.
- `selectC-1.12` through `selectC-1.14`: expression alias behavior with
  `DISTINCT`, `GROUP BY`, and `ORDER BY DESC`.

The new file `SQLiteRealUpstreamSelectCAliasDynamicTest.php` adds 1 citation
case plus 1,000 dynamic TestRunner PASS cases. Each dynamic case varies source
row text and verifies flat result values, value counts, per-position values,
and fingerprints across alias WHERE, expression WHERE, DISTINCT, GROUP BY,
HAVING, and ORDER BY behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCAliasDynamicTest.php`
  passed: `1 test files, 55005 assertions, 0 failures`.

Expected selected throughput movement:

- PASS lines: `+1001`.
- Assertions: `+55005`.
- Mapped denominator: unchanged, already `1589 / 1589`.

Dependency closure: no new support component is needed; this reuses the
existing native `SQLiteSelectSql` executor and real upstream SQLite test corpus.
