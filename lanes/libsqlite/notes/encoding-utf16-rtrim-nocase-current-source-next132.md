# Encoding UTF-16 RTRIM NOCASE Current Source Next132

## Behavior

- Extended `SQLiteUtf16RtrimNocaseCurrentSourceNextPlan` so retained
  `RTRIM` + `NOCASE` matches are invalidated when their decoded comparison
  rowids remain the same but the underlying text encoding or key bytes change.
- The slice preserves SQLite's ASCII-only `NOCASE` behavior and SQLite
  `RTRIM` behavior that trims only ASCII space, not tabs, newlines, NBSP, or
  non-ASCII case pairs.
- Added a Application `wp_options` smoke where `Plugin_Cache` rows keep the same
  logical match set while switching between UTF-16LE, UTF-16BE, and padded
  byte representations.

## Evidence

- New focused test:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext132Test.php`
  - `1 test files, 73 assertions, 0 failures`
  - `56` PASS lines
- Regression focus:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext132Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext103Test.php`
  - `2 test files, 126 assertions, 0 failures`
- Application smoke:
  `php lanes/libsqlite/examples/application-utf16-rtrim-nocase-current-source-next132.php --self-test`
  - `application-utf16-rtrim-nocase-current-source-next132 self-test passed`

## Non-Overlap

- Avoids accepted UTF-16 malformed guard, UTF-16 RTRIM/GLOB range, Unicode
  GLOB range, dynamic affinity LIKE/GLOB, and existing next103 logical rowset
  invalidation coverage.
- This patch is narrower: retained rowids under `RTRIM` + `NOCASE` now carry
  byte/encoding current-source invalidation, preventing reuse of a cursor whose
  logical key is unchanged but whose source bytes changed.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native
  UTF-16 encoder/decoder and collation helpers in `lanes/libsqlite/src`.
