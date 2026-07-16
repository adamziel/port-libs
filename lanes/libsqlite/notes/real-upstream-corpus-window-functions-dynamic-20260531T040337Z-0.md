# real-upstream-corpus-window-functions-dynamic-20260531T040337Z-0

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`.
- Ported sections: `windowE.test` 1.0-1.3, covering a custom text collation, an index built under that collation, and `group_concat()` window `RANGE BETWEEN 1 PRECEDING AND 2 PRECEDING` behavior before and after the custom collation callback is changed.
- Focused growth: 1004 TestRunner PASS cases and 3006 assertions in `SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php`.
- Red-first evidence: the first focused run failed 1002 assertions because the active collation comparison direction was inverted; after correcting the model, the same focused file passed.
- Non-overlap: this owns `windowE.test` custom-collation text `RANGE` behavior. It avoids accepted `windowE` 3.1/4.1/4.2/5.1/5.2 numeric range/sum/total batches, `windowA` descending numeric range, `windowC` separator batches, `windowD` truth semantics, `window9` collation/filter min frames, JSON table windows, grouped SELECT text, expression ORDER BY, and storage/VFS/B-tree clusters.
- Dependency closure: no new support component is needed; this uses a deterministic lane-local model of indexed text ordering and active custom collation comparison.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php` -> 1 test file, 3006 assertions, 0 failures.
