# Real Upstream Corpus: B-tree/Index Dynamic

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T194301Z-0`
- Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`
- Upstream source truth: hydrated SQLite `test/indexA.test`, sections `indexA-2.1` and `indexA-3.1`
- Change: widened the focused rowid/WITHOUT ROWID partial-index affinity matrix in `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` from 9 to 50 batches.
- Focused PASS-case delta: `+4920` TestRunner cases (`41` added batches x `120` matrix cases per batch).
- Non-overlap: stays in the existing B-tree/index dynamic corpus file and does not touch accepted page move, overflow freelist release, expression-index range cost, indexed-by planning, JSON, WAL, VFS, or source-neutral cleanup surfaces.
- Dependency closure: no new support component needed; this reuses the lane-local `SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialAffinityMatrixCases()` helper and existing test runner.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php
1 test files, 53785 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```
