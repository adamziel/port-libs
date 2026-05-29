# Encoding UTF-16 LIKE RTRIM current-source next137

This slice adds `SQLiteUtf16LikeRtrimCurrentSourceNextPlan` for a
current/next `wp_options.option_name` scan where the source bytes are UTF-8,
UTF-16LE, or UTF-16BE and the predicate is `LIKE ... COLLATE RTRIM`.

Behavior covered:

- RTRIM collation supplies candidate ordering/comparison keys but does not make
  the LIKE prefix range usable.
- Candidate rows are full-scanned and then filtered by byte-preserving LIKE
  residual semantics, so trailing ASCII spaces, tabs, and NBSP are distinct for
  LIKE even though ASCII spaces trim for the RTRIM key.
- Current/next plans record matched, entered, exited, residual-rejected,
  malformed, endian-changed, byte-changed, text-changed, and RTRIM-key-changed
  rowids.
- WordPress smoke coverage demonstrates copied `wp_options` option-name scans
  during import/diff planning without relying on ext/sqlite.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16LikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16LikeRtrimCurrentSourceNext137Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-like-rtrim-current-source-next137.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeRtrimCurrentSourceNext137Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-like-rtrim-current-source-next137.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next121 UTF-16 RTRIM LIKE rowset invalidation,
next132 RTRIM/NOCASE equality reprepare behavior, next134 NOCASE/RTRIM LIKE
mixed-collation range metadata, Unicode GLOB range work, UTF-16 malformed record
guards, and VDBE/index cursor collation slices. This slice is specifically the
full-scan candidate/current-next stream and residual-rejection behavior for
UTF-16 `LIKE ... COLLATE RTRIM`.

Dependency closure: no new support component is needed; it reuses lane-local
UTF text decoding, LIKE residual matching, RTRIM key metadata, and current
source invalidation state.
