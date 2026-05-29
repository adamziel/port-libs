# encoding-utf16-nocase-like-rtrim-current-source-next180

Status: focused PHP behavior growth for UTF-16 NOCASE LIKE over RTRIM expression keys when the fixed LIKE prefix contains non-ASCII text.

Behavior:
- `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` keeps accepted ASCII NOCASE prefix range behavior, but falls back to decoded full-scan residual matching when `SQLiteLikeCollationPlan` rejects a non-ASCII fixed prefix such as `éclair_`.
- The residual matcher still uses SQLite ASCII-only NOCASE behavior: `éCLAIR_cache` matches `éclair_%`, while `Éclair_cache` does not.
- The scan decodes mixed UTF-16LE/UTF-16BE current and next rows, trims only ASCII spaces for the RTRIM expression key, preserves tabs, isolates malformed UTF-16 rows, and reports current-source invalidation reasons.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next180.php` models copied `wp_options.option_name` plugin keys with localized non-ASCII prefixes during a current/next source transition.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next180.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next180.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 67 assertions, 0 failures`
  - `56` PASS lines
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next180.php --self-test`
  - `wordpress-utf16-nocase-like-rtrim-current-source-next180 self-test passed`

Expected dashboard movement: `phpPass +56`, from `84672` to `84728`. Mapped upstream coverage remains `614 / 1589`; this is focused PHP behavior over already mapped encoding/collation/LIKE current-source inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next177 Unicode wildcard recheck, next176 peer-yield resume ordering, next174 embedded-NUL handling, next166 ESCAPE handling, next160 pattern byte decoding, next156/162 normalized UTF-16 NOCASE/RTRIM matching, malformed UTF-16 guard/range slices, Unicode GLOB ranges, SELECT/JSON/WAL/B-tree/VFS clusters, and suite-runner evidence. The new surface is the non-ASCII fixed-prefix fallback from unusable NOCASE range planning to decoded full-scan residual LIKE matching.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
