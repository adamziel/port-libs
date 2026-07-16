# consolidate-utf16-nocase-like-rtrim-source-delta-helpers

Status: consolidated the UTF-16 NOCASE LIKE RTRIM source-delta helper block inside `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` by replacing private `v158_*` production helper names with descriptive source-delta helper names.

Behavior preservation: observable status strings, dependency strings, action labels, rowset keys, invalidation reasons, and assertion coverage are unchanged. This is production-helper cleanup only; the existing direct source-delta test and full UTF-16 NOCASE LIKE RTRIM family remain green.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext158Test.php`
  - `1 test files, 91 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextTest.php`
  - `64 test files, 4918 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - no output

Root harness: not run - isolated micro-slice.

Non-overlap: avoids changing root-gate evidence, dashboard/progress files, and observable UTF-16 NOCASE LIKE RTRIM metadata. This does not repeat Unicode GLOB ranges, malformed UTF-16 guards, accepted VFS/WAL/B-tree/JSON/SQL executor clusters, or suite-runner evidence work.

Dependency closure: no new support component is needed. The slice reuses the existing lane-local UTF-16 decode, RTRIM expression key, ASCII NOCASE LIKE range, residual LIKE, and current-source invalidation helpers.
