# real-upstream-corpus-window-functions-dynamic-20260530T195313Z

Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`.

Added `SQLiteRealUpstreamWindow3DynamicMatrixTest.php`, a real upstream-backed PHP corpus slice for `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test` section `1.20`.

Owned upstream sections:

- `window3.test 1.20.3` row_number generated ORDER/PARTITION/EXCLUDE matrix
- `window3.test 1.20.4` dense_rank generated ORDER/PARTITION/EXCLUDE matrix
- `window3.test 1.20.5` rank generated ORDER/PARTITION/EXCLUDE matrix
- `window3.test 1.20.7` percent_rank generated matrix
- `window3.test 1.20.8` cume_dist generated matrix
- `window3.test 1.20.9` last_value generated matrix
- `window3.test 1.20.10` nth_value generated matrix
- `window3.test 1.20.11` first_value generated matrix
- `window3.test 1.20.12` lead generated matrix
- `window3.test 1.20.13` lag generated matrix
- `window3.test 1.20.14` group_concat generated matrix
- `window3.test 1.20.15` FILTER aggregate generated matrix

Focused result:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow3DynamicMatrixTest.php`
- `1 test files, 4322 assertions, 0 failures`
- `2161` selected PASS lines

Non-overlap:

- Does not touch existing `windowB.test` JSON/RANGE dynamic coverage.
- Does not repeat accepted GROUPS/RANGE current-next frame-without-order rejection coverage.
- Does not add metadata-only rows or fabricated upstream script ids; all cases cite `window3.test` generated section `1.20` and exercise generic `SQLiteWindowFunction` behavior.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded native PHP `SQLiteWindowFunction` support.
