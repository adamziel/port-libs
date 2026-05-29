# encoding-utf16-nocase-like-rtrim-current-source-next167

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE` current-source scans when the LIKE prefix cannot use the NOCASE index range.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`. It preserves SQLite behavior for non-ASCII and leading-wildcard LIKE prefixes by falling back to a decoded full residual scan instead of returning an empty candidate set when the NOCASE prefix range is not usable. The residual matcher still applies ASCII-only NOCASE and RTRIM semantics, so `éclair%` matches lowercase `éclair_*` rows but does not equate uppercase `É`.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next167.php` models copied `wp_options` rows whose localized option names are stored across UTF-8/UTF-16LE/UTF-16BE sources. Plugin scans over localized prefixes must remain visible even when the planner cannot derive a safe NOCASE range.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext167Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next167.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext167Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next167.php --self-test`
- `git diff --check -- lanes/libsqlite`

PASS delta: focused test adds `+70` PASS lines and `84` assertions. `lane-status.json` `phpPass` moves from `75459` to `75529`. Mapped upstream coverage remains `611 / 1589`; this reuses existing encoding/collation/LIKE/RTRIM inventory rather than claiming a new manifest-backed row.

Non-overlap: avoids accepted next164 UTF-16 NOCASE/RTRIM LIKE yield fingerprint behavior, next162 normalized pattern bytes, next160 pattern decoding, Unicode GLOB range handling, UTF-16 malformed insert guard, VFS/WAL/B-tree/JSON/SQL planner accepted clusters, and batch156 accepted UTF-16 NOCASE/LIKE/RTRIM behavior. The narrower new surface is fallback candidate admission when LIKE planning reports `nocase_like_prefix_must_be_ascii_for_range` or `no_fixed_prefix`.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE residual matching, and current-source diagnostics.

Next task: continue encoding only on a non-overlapping malformed comparison or collation/affinity predicate edge, or leave further LIKE/RTRIM variants until a new upstream runner blocker identifies a gap.
