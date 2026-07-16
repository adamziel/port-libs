# UTF-16 NOCASE LIKE RTRIM Embedded NUL Current-Source Next174

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next174`
- Behavior: `SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan` preserves decoded UTF-16 embedded NUL bytes through `RTRIM`, ASCII-only `NOCASE`, and `LIKE` residual checks. It flags rows that a C-string-prefix comparison would falsely match, and invalidates current-source cursor reuse when embedded-NUL text, bytes, rowsets, residuals, or malformed payload state changes.
- Application smoke: `lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-nul-current-source-next.php` covers copied `wp_options.option_name` rows where plugin option names contain embedded NULs and must not be truncated before import/diff scans.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextTest.php`
  - Result: `1 test files, 70 assertions, 0 failures`, `60` PASS lines.
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-nul-current-source-next.php --self-test`
  - Result: `application-utf16-nocase-like-rtrim-nul-current-source-next self-test passed`
- Non-overlap: avoids accepted next168 case-sensitive `LIKE` reprepare, next163 RHS `RTRIM` pattern bytes, next170 resume tokens, Unicode GLOB ranges, and UTF-16 malformed record guards. This slice is specifically embedded-NUL full-text comparison and stale cursor invalidation.
- Dependency closure: no new support component is needed; reuses native UTF-16 decode, LIKE/NOCASE/RTRIM helpers, and lane-local current-source diagnostics.
