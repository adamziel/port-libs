# encoding-utf16-rtrim-glob-affinity-current-source-next145

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE GLOB scans with NUMERIC affinity filtering.

This slice adds `SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan`. It composes a copied `wp_options` current-source scan where `rtrim(option_name) COLLATE NOCASE` provides the broad GLOB range, the byte-sensitive SQLite GLOB residual keeps uppercase false positives out, and `option_value` is decoded from UTF-8/UTF-16LE/UTF-16BE before SQLite-style NUMERIC affinity decides the visible rowset.

WordPress path: `wordpress-utf16-rtrim-glob-affinity-current-source-next145.php` models plugin option scans whose names and numeric values change encoding across copied import sources. The cursor is invalidated when source/schema/collation changes are accompanied by affinity rowset or value-byte changes, even when the name range still looks reusable.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimGlobAffinityCurrentSourceNext145Test.php`
  - `1 test files, 94 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-utf16-rtrim-glob-affinity-current-source-next145.php --self-test`
  - `wordpress-utf16-rtrim-glob-affinity-current-source-next145 self-test passed`

PASS delta: `+94` focused assertions. `lane-status.json` `phpPass` moves from `64226` to `64320`. Mapped upstream coverage remains `606 / 1589`; this reuses existing encoding/collation/GLOB/affinity inventory rather than claiming a fresh manifest-backed upstream row.

Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guard, malformed UTF-16 LIKE/GLOB range next97, literal-bracket UTF-16 GLOB next122, RTRIM/NOCASE GLOB next136, and batch141 NOCASE/RTRIM/GLOB affinity coverage. The narrower new surface is the current-source invalidation boundary where decoded UTF-16 `option_value` NUMERIC affinity changes the post-GLOB visible rowset.

Dependency closure: no new support component is needed. The patch reuses the native PHP UTF-16 encoder/decoder, existing GLOB prefix/residual helpers, and local numeric affinity coercion.

Next task: continue encoding only on a non-overlapping malformed-text comparison or collation/affinity predicate edge, or leave further GLOB variants until a new upstream runner blocker is identified.
