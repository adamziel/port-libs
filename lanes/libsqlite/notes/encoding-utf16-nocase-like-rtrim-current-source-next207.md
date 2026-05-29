# encoding-utf16-nocase-like-rtrim-current-source-next207

## Behavior

Adds a focused current-source cursor plan for UTF-16 `wp_options.option_name`
LIKE probes where a prepared `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?`
cursor is rebound to a no-`RTRIM` expression source. The slice keeps the LIKE
pattern/escape stable so the new invalidation is specifically the expression
collation key change, not the accepted next200 ESCAPE rebind or earlier
Unicode GLOB/malformed UTF-16 guard work.

## Evidence

Local verification on 2026-05-28:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext207Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 72 assertions, 0 failures`
  - 61 focused PASS lines for the new next207 test cases.
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next207.php`
  - `wordpress-utf16-nocase-like-rtrim-current-source-next207 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext207Test.php && php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next207.php`
  - no syntax errors in all three changed PHP files.
- `git diff --check -- lanes/libsqlite`
  - passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses native UTF-16
decode, LIKE prefix-range planning, ASCII-only NOCASE residual matching,
RTRIM expression keys, and existing current-source invalidation diagnostics.

## Non-Overlap

Avoids accepted next200 ESCAPE rebind, accepted next206 integrated
UTF-16/NOCASE/LIKE/RTRIM batch behavior, Unicode GLOB ranges, malformed
UTF-16 insert guards, JSON/planner/storage clusters, and VFS/WAL work.
