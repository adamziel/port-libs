# encoding-utf16-canonical-like-current-source-next233

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` scans over canonical-equivalent Unicode option names.

WordPress path: `wordpress-utf16-canonical-like-current-source-next233.php` models copied `wp_options.option_name` rows where plugin keys may contain precomposed `é` or decomposed `e` plus a combining acute mark. SQLite does not normalize Unicode for `NOCASE` or `LIKE`, and `_` consumes one Unicode code point, so the decomposed form remains a residual false positive for a single trailing wildcard even when it is visually equivalent to the precomposed key.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext233Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `77` PASS lines
  - `1 test files, 85 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext233Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-canonical-like-current-source-next233.php`
- `php lanes/libsqlite/examples/wordpress-utf16-canonical-like-current-source-next233.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +77`, from `113830` to `113907`. Mapped upstream coverage remains `634 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE, RTRIM, LIKE, and current-source inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guards, non-ASCII NOCASE LIKE prefix fallback, non-ASCII whitespace RTRIM behavior, supplementary-plane wildcard handling, ESCAPE rebinding, keyset resume, and storage/JSON/SQL planner clusters. The new surface is canonical-equivalent precomposed/decomposed Unicode text at the UTF-16 NOCASE/RTRIM LIKE current-source boundary without Unicode normalization.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode helpers, LIKE prefix planning, ASCII-only NOCASE comparison, RTRIM expression keys, and Unicode code point splitting.

Next task: continue encoding work only on a non-overlapping malformed-text, collation, affinity, or LIKE/GLOB edge with focused tests.
