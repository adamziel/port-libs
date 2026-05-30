# UTF-16 NOCASE LIKE Production Suffix Cleanup

Consolidated the UTF-16 NOCASE LIKE range-byte and residual helper implementations behind the stable `SQLiteUtf16NocaseLikeCurrentSourceNextPlan` facade:

- Renamed internal production helper classes from generated current-source-next helper names to descriptive `SQLiteUtf16NocaseLikeRangeBytesPlan` and `SQLiteUtf16NocaseLikeResidualPlan`.
- Renamed the paired focused tests and Application examples to descriptive unsuffixed filenames.
- Preserved returned plan keys and dependency strings, including historical dependency aliases consumed by existing tests.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRangeBytesTest.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeResidualTest.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-range-bytes.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-residual.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRangeBytesTest.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeResidualTest.php` -> `2 test files, 159 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRangeBytesTest.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeResidualTest.php $(find lanes/libsqlite/tests -maxdepth 1 -type f \( -name 'SQLiteUtf16*Nocase*CurrentSourceNext*.php' -o -name 'SQLiteUtf16*NoCase*CurrentSourceNext*.php' -o -name 'SQLiteRtrimNocaseGlobCurrentSourceNext*.php' -o -name 'SQLiteEncodingCollationAffinityLikeCurrentSourceNext*.php' \) | sort)` -> `103 test files, 7973 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-range-bytes.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-residual.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing UTF-16 encoding cursor and LIKE collation plan.

Non-overlap: this does not change accepted Unicode GLOB range behavior, UTF-16 malformed record serialization guards, or observable dependency/action metadata.
