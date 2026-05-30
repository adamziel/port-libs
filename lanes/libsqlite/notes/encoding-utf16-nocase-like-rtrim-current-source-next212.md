# encoding-utf16-nocase-like-rtrim-current-source-next212

Status: focused PHP behavior growth for UTF-16 prepared `LIKE` patterns that use a non-ASCII single-character `ESCAPE` with `RTRIM(option_name) COLLATE NOCASE`.

Behavior:

- Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`.
- Decodes UTF-16LE/UTF-16BE prepared pattern and escape bytes before LIKE prefix planning.
- Proves a fullwidth exclamation escape protects literal `_` and `%` characters while preserving the same candidate/matched rowsets as an ASCII-equivalent escape.
- Keeps ASCII-only NOCASE and ASCII-space-only RTRIM diagnostics from accepted current-source behavior.

Application path: `application-utf16-nocase-like-rtrim-current-source-next212.php` models copied `wp_options.option_name` scans where migration SQL binds UTF-16 pattern bytes such as `plugin！_%` and `plugin！%%`. The scan must decode the non-ASCII escape before calculating the NOCASE/RTRIM prefix range so literal wildcard bytes are not treated as wildcards.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext212Test.php`
  - `1 test files, 79 assertions, 0 failures`
  - `70` PASS lines.
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next212.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next212 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext212Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next212.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +70`, from `101605` to `101675`. Mapped upstream coverage remains `622 / 1589`; this reuses existing encoding/collation/LIKE inventory rather than claiming a fresh manifest row.

Non-overlap: avoids accepted next200 ASCII escape rebind, next203 no-fixed-prefix full scans, next206 BOM normalization, next209 ASCII-space RTRIM/ASCII-only NOCASE diagnostics, UTF-16 malformed insert guards, Unicode GLOB ranges, and VFS/WAL/B-tree/JSON/SQL executor clusters. The new surface is UTF-16 prepared non-ASCII `ESCAPE` decoding before NOCASE/RTRIM LIKE prefix planning and residual matching.

Dependency closure: no new support component is needed. This reuses native UTF-16 decoding, SQLite LIKE character splitting, ASCII NOCASE prefix range planning, RTRIM expression keys, and residual matching.

Next task: continue encoding only on a non-overlapping malformed-text, collation, affinity, or LIKE/GLOB edge; otherwise pivot to a higher-value non-encoding closure bucket.
