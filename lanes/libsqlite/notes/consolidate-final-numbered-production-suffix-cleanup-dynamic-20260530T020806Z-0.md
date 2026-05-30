# Final Numbered Production Suffix Cleanup Dynamic

Consolidated private production helper names in `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for the prepared UTF-16 LIKE byte-signature and descending yield-page windows. The public entry points, result keys, status strings, dependency strings, action labels, and test-visible proof names are unchanged.

Renamed helpers:

- `v221_decodePreparedText()` -> `decodePreparedLikeText()`
- `v221_preparedSignature()` -> `preparedLikeByteSignature()`
- `v221_encodingId()` -> `preparedTextEncodingId()`
- `v221_encodingName()` -> `preparedTextEncodingName()`
- `v223_descRows()` -> `descendingRtrimNocaseRows()`
- `v223_rowids()` -> `orderedRowids()`
- `v223_sortedDiff()` -> `sortedRowidDiff()`
- `v223_pageToken()` -> `descendingYieldPageToken()`
- `v223_assertCursor()` -> `assertDescendingYieldCursor()`

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext221Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext223Test.php` -> `2 test files, 170 assertions, 0 failures`
- UTF-16 RTRIM family: `php tools/run-tests.php $(find lanes/libsqlite/tests -name 'SQLiteUtf16NocaseLikeRtrimCurrentSourceNext*Test.php' -print | sort) lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext156Test.php` -> `61 test files, 4697 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This cleanup reuses existing native UTF-16 decoding, prepared LIKE byte metadata, RTRIM/NOCASE key construction, and current-source yield cursor diagnostics.
