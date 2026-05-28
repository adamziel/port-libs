# encoding-collation-affinity-like-current-source-next247

## Behavior

- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext247Plan` for WordPress `wp_options.option_name` LIKE scans whose literal prefix contains non-ASCII text.
- Models SQLite NOCASE as ASCII-only for LIKE residual evaluation: `PLUGIN_CAFÉ%` matches `plugin_café%`, while `plugin_cafÉ%` does not match `plugin_café%`.
- Keeps non-ASCII LIKE prefixes out of the optimistic NOCASE range path and records `non_ascii_prefix_requires_residual_scan`.
- Tracks current-source invalidation across UTF-16LE/UTF-16BE source switches, scalar text-affinity coercions, matched rowset changes, and encoded-byte changes.

## Verification

Focused commands run:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext247Test.php
php lanes/libsqlite/examples/wordpress-unicode-nocase-like-current-source-next247.php --self-test
```

Observed output:

- `1 test files, 101 assertions, 0 failures`
- `87` focused PASS lines
- `wordpress-unicode-nocase-like-current-source-next247 self-test passed`

## Non-overlap

This next247 slice avoids accepted Unicode GLOB ranges, UTF-16 malformed guards, numeric `option_value` LIKE next240, byte/NUL `option_name` LIKE next241, mixed-UTF ASCII-prefix LIKE next244, and all SQL executor, JSON, WAL, VFS, and B-tree clusters.

## Dependency closure

No new support component is needed. The slice reuses lane-local LIKE tokenization, mixed UTF decoding, ASCII-only NOCASE semantics, and text-affinity diagnostics.
