# UTF-16 NOCASE LIKE RTRIM current-source next219

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for
current-source `wp_options` scans using:

`rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?`

The focused behavior is supplementary-plane UTF-16 text in option names. A
decoded emoji is one SQL text character, so one unescaped LIKE `_` wildcard must
match it even though it occupies two UTF-16 code units. The plan records the
code-unit trap separately from normal residual matches so stale cursor replay
does not regress to UTF-16 code-unit matching. It also preserves ASCII-only
NOCASE and ASCII-space-only RTRIM behavior.

Application smoke: `examples/application-utf16-nocase-like-rtrim-current-source-next219.php`
models copied `wp_options` rows with UTF-16LE/UTF-16BE emoji option names and a
plugin cache wildcard scan.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext219Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next219.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext219Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next219.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected focused movement: `+65` PASS lines in the new focused test file.
Mapped upstream coverage remains unchanged; this reuses already mapped UTF-16,
NOCASE, LIKE, RTRIM, and current-source inventory rather than claiming a fresh
manifest row.

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decoding, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and
binary-safe Unicode character splitting.

Non-overlap: avoids accepted embedded-NUL next210, Unicode ESCAPE next212/213,
source refresh next211, pattern-space next217, Unicode GLOB ranges, malformed
UTF-16 insert guards, and storage/planner clusters. The new surface is
supplementary-plane UTF-16 decoded characters consumed by one LIKE `_` wildcard.
