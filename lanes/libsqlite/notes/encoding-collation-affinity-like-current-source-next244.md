# encoding-collation-affinity-like-current-source-next244

## Behavior

Adds a focused mixed-encoding WordPress `wp_options.option_name LIKE` current-source plan for UTF-8, UTF-16LE, and UTF-16BE rows. The slice preserves SQLite's ASCII-only `NOCASE` behavior, so ASCII prefix case folds while accented bytes such as `é` and `É` remain distinct. It also records encoded-byte and text-encoding changes across current/next sources so a copied import cursor is not reused after the same decoded option name moves between UTF-16 byte orders.

## Evidence

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext244Test.php
php lanes/libsqlite/examples/wordpress-encoding-collation-affinity-like-current-source-next244.php
php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext244Plan.php
php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext244Test.php
php -l lanes/libsqlite/examples/wordpress-encoding-collation-affinity-like-current-source-next244.php
git diff --check -- lanes/libsqlite
```

## Non-Overlap

This slice avoids accepted next240 numeric-affinity LIKE, next241 embedded-NUL/malformed byte LIKE, Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 RTRIM cursor fences, SQL executor, JSON, WAL, VFS, and B-tree clusters.

## Dependency Closure

No new support component is needed. The patch reuses the native encoding source cursor, UTF-16 encode/decode helpers, LIKE tokenizer, and ASCII-only NOCASE behavior already present in the libsqlite lane.
