# real-upstream-corpus-btree-index-dynamic-20260530T205414Z-0

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

This slice adds focused real-upstream coverage for SQLite `test/numindex1.test`
sections `numindex1-1.1` through `numindex1-3.2`, covering large numeric
index-key behavior around integer/REAL precision boundaries.

Focused behavior:

- indexed delete integrity after removing a rounded REAL duplicate between
  adjacent large integer keys;
- storage-class preservation for values around `1<<58`;
- self-join equality where rounded REAL and integer keys compare equal but the
  adjacent integer remains distinct;
- duplicate-run deletion that leaves integer sentinel rows;
- mixed integer/REAL `ORDER BY` over indexed keys near
  `100000000000000000`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`: `1 test files, 150345 assertions, 0 failures`

Delta:

- Adds 1000 distinct focused TestRunner PASS cases to
  `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Expected `phpPass` movement after acceptance: `684212 -> 685212`.
- Mapped denominator remains `1589 / 1589`.

Dependency closure: no new support component is needed; this reuses the
existing lane-local B-tree/index corpus helper and records numeric index-key
comparison/order behavior as generic SQLite coverage.

Non-overlap: this does not repeat the accepted B-tree/index A-join, wide-column
index, partial-index affinity, expression-index, indexfault, autoindex5,
overflow/freeblock, page relocation, root-collapse, or pointer-map clusters.
