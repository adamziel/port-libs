# encoding-collation-affinity-like-current-source-next250

## Behavior

Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext250Plan` for Application
`wp_options.option_name COLLATE RTRIM LIKE ? ESCAPE ?` current-source scans.
The new slice records the SQLite distinction between an RTRIM collation key and
the LIKE residual operand: `plugin_cache  ` can be an RTRIM peer of
`plugin_cache`, but it does not satisfy `LIKE 'plugin!_cache' ESCAPE '!'`
until the raw trailing spaces are removed. Tabs are not RTRIM spaces.

The plan also tracks UTF-16LE/UTF-16BE source switches, scalar text-affinity
coercion, raw byte changes, residual truth changes, matched-rowset changes, and
RTRIM-peer rejection changes so a copied import cursor cannot reuse a stale
current-source boundary.

## Verification

Focused commands run:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext250Test.php
php lanes/libsqlite/examples/application-rtrim-like-residual-current-source-next250.php --self-test
```

Expected focused test growth: the new test file adds focused PASS lines for the
next250 RTRIM/LIKE residual behavior.

Observed output:

- `1 test files, 94 assertions, 0 failures`
- `84` focused PASS lines
- `application-rtrim-like-residual-current-source-next250 self-test passed`

## Non-overlap

This slice avoids accepted next247 non-ASCII NOCASE prefixes, next246 dynamic
ESCAPE affinity, next241 embedded-NUL/malformed-byte LIKE, numeric
`option_value` LIKE next240, Unicode GLOB ranges, UTF-16 malformed guards, and
all SQL executor, JSON, WAL, VFS, and B-tree clusters.

## Dependency closure

No new support component is needed. The slice reuses native LIKE tokenization,
RTRIM collation-key diagnostics, mixed UTF decoding, scalar text affinity, and
current-source invalidation tracking.
