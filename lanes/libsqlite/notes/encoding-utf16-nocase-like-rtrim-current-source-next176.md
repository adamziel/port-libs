# SQLite UTF-16 NOCASE LIKE RTRIM current-source next176

Status: focused PHP behavior growth for duplicate UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE` keys at a current-source yield boundary.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`. It extends the accepted next165/168/169/173 UTF-16 NOCASE LIKE/RTRIM current-source chain without repeating their pattern normalization, case-sensitive LIKE, byte-only reprepare, or high-water page behavior. The new surface is rowid-tied peer ordering when case and trailing-space variants collapse to the same NOCASE/RTRIM key, including duplicate peer groups that straddle the resume token or a bounded yield page.

Application smoke: `application-utf16-nocase-like-rtrim-current-source-next176.php` models copied `wp_options` rows where `Plugin_Cache`, `plugin_cache  `, and `PLUGIN_CACHE   ` share the same index key but must resume in rowid order without duplicating or skipping the next peer.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext176Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next176.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext176Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next176.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +66`, from `81770` to `81836`. Mapped upstream coverage remains `613 / 1589`; this is additional current-source PHP behavior over the already mapped encoding/collation/LIKE inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next141 malformed-row isolation, next156/157/158/161/162/164/165/168/169/171/172/173 UTF-16 NOCASE LIKE/RTRIM source behavior, next160 prepared-pattern bytes, next166 escape bytes, Unicode GLOB ranges, UTF-16 malformed record guards, and VFS/WAL/B-tree/JSON/SQL executor clusters.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source diagnostics.
