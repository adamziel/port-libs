Real upstream corpus window1 dynamic slice

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `1.1-1.5`, `4.1-4.10.2`, `5.4`, `7.2-7.4`, `13.2-13.5`, and `36.10-36.40`.

Focused coverage:
- Added `SQLiteRealUpstreamWindow1DynamicTest.php` with 1,006 distinct TestRunner PASS cases and 7,025 behavior assertions.
- Static cases cover whole-partition sums, partitioned running sums, descending running `count()`/`group_concat()`, frame-agnostic `lead()`, `row_number()`, compound `rank()` arms, `ntile()` empty-input admission, and `VALUES(count(*) OVER())` placement.
- Dynamic cases cover 1,000 generated upstream-shaped ranking/lead/lag/ntile/running-sum windows with varying row counts, offsets, peer groups, and bucket counts.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1DynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1DynamicTest.php`
  - `1 test files, 7025 assertions, 0 failures`

Dependency closure:
- No new support component needed. This reuses existing lane-local `SQLiteWindowFunction` ranking, lead/lag, ntile, frame aggregate, and group-concat behavior.

Non-overlap:
- This ports `window1.test` sections not covered by the existing real-upstream `windowB`, `window9`, `windowC`, `windowD`, `windowE`, `window4`, `window5`, `window6`, `window7`, `window8`, or window fault/pushdown dynamic files. It does not add metadata-only admission rows and does not repeat accepted JSON table window ranking, SQL expression ORDER BY, grouped SELECT text, row-value/RETURNING window, compound recursive window current-source, or WordPress-shaped API coverage.

Status delta:
- Expected focused PASS-line movement: `+1006`.
- `phpPass`: `1697468 -> 1698474`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; mapped inventory is already complete and this is behavior admission from a hydrated upstream file.
