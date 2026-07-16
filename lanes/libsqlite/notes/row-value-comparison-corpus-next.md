2026-05-27 - row-value comparison corpus next

- Added parser/executor support for row-value operands in SELECT SQL comparison predicates.
- Focused corpus covers direct predicate plans and parser-level WHERE comparisons for `=`, `<>`, `<`, `<=`, `>`, `>=`, `IS`, and `IS NOT`, including lexicographic short-circuiting and NULL/unknown behavior.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueComparisonCorpusTest.php` reported 1 test file / 37 assertions / 0 failures; `git diff --check -- lanes/libsqlite` passed.
- Dashboard delta: `phpPass` increases by 37 independent PASS cases, from 1336 to 1373. `benchmarkDenominator.mapped` is unchanged because this maps more selected upstream behavior into lane PHP tests without adding a new manifest unit.
- Dependency closure: no new support component is needed; this reuses the existing SELECT SQL parser, predicate evaluator, and row-array executor.
- Non-overlap: avoids accepted SELECT SQL subqueries, expression ORDER BY, GROUP BY/HAVING text, JSON table sources/constraints/cursor, VFS writer/sync/rollback/lock clusters, WAL byte truncation/checkpoint transaction, B-tree page move/root-collapse/overflow release, Unicode GLOB, and batch3 trigger/WAL/schema/json/operator corpus.
