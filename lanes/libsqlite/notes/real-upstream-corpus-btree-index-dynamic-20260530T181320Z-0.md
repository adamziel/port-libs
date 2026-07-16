# real-upstream-corpus-btree-index-dynamic-20260530T181320Z-0

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexA.test`
- Sections `2.1` and `3.1`: TEXT, NUMERIC, and REAL partial-index affinity matrix for rowid and WITHOUT ROWID tables.

Implemented slice:

- Extended `SQLiteBTreeIndexDynamicCorpusPlan` with `indexAPartialAffinityMatrixCases()`.
- Added 1080 focused TestRunner cases covering storage mode, index predicate literal, query predicate literal, table affinity, and dynamic payload batch.
- The cases preserve SQLite result rows and `typeof()` expectations while checking whether the partial index is eligible or the table is scanned.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed: `1 test files, 7973 assertions, 0 failures`.

Expected dashboard movement:

- `+1080` focused PASS cases if counted directly from the changed test file.
- Mapped coverage unchanged.

Dependency closure:

- No new support component needed; this reuses lane-local B-tree/index corpus helpers and SQLite affinity comparison modeling.

Non-overlap:

- This does not repeat accepted B-tree overflow/freeblock/page-move/root-collapse clusters.
- This does not repeat accepted `index6`, `index9`, `indexedby`, `index2`, or `index4` dynamic coverage already present in the helper.
