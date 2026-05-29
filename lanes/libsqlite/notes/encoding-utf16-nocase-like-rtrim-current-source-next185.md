# Encoding UTF-16 NOCASE LIKE RTRIM current-source next185

This slice adds deleted-token replay diagnostics for UTF-16 `wp_options`
`rtrim(option_name) COLLATE NOCASE LIKE ?` scans. If the last yielded row has
been deleted in the next source, the cursor can continue from the decoded
`(rtrim/nocase key, rowid)` boundary only when source/schema identity is
stable, the token key is canonical, malformed text is absent, and the peer
rows before the token did not change.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next185.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next185.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses native
UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, and
key/rowid yield-token replay diagnostics.

Non-overlap: avoids accepted ESCAPE operand validation next182, equal-peer
replay next181, canonical token fingerprint next175, Unicode GLOB ranges,
UTF-16 malformed insert guards, JSON/SQL planner, B-tree, WAL, and VFS
clusters. The new behavior is specifically deleted last-yielded row resume
for current/next UTF-16 RTRIM/NOCASE LIKE scans.
