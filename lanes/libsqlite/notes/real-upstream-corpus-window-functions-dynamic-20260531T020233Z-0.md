# real-upstream-corpus-window-functions-dynamic-20260531T020233Z-0

Added `SQLiteRealUpstreamWindowBJsonObjectInverseExtensionTest.php` for real upstream
SQLite window behavior from `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`.

Owned upstream scenarios:

- `windowB.test` `3.11`: `json_group_object(if(id>4,k||'@'),v)` over `ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING`.
- `windowB.test` `3.12`: unfiltered `json_group_object(k,v)` over the same inverse frame.
- `windowB.test` `3.13`: interior-only keys with first/last frame labels omitted.
- `windowB.test` `3.15`: edge-only keys with middle labels omitted.

Focused assertion count:

- New focused file: `1205` TestRunner cases.
- New focused file assertions: `8437`.

Non-overlap:

- Existing accepted `windowB` coverage already covered null RANGE peers, `3.9`, `3.10`, `3.14`, `3.16`, range peer cases, varying separators, duplicate JSON aggregates, and JSON table-valued running sums.
- This slice owns the adjacent missing `windowB.test` `3.11`, `3.12`, `3.13`, and `3.15` JSON object inverse-frame key-filter variants plus a dynamic key-filter/value-offset matrix over the same real upstream behavior.
- It does not add metadata-only runner rows, fake upstream script ids, domain-specific APIs, or JSON table/WAL/B-tree/VFS behavior.

Dependency closure:

- No new support component is needed. The test reuses native `SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit()` and compares it to an independent sliding-frame oracle in the test.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowBJsonObjectInverseExtensionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBJsonObjectInverseExtensionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBJsonRangeDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBJsonObjectInverseExtensionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
