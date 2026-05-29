# Encoding NOCASE LIKE RTRIM current-source next146

This slice adds `SQLiteNocaseLikeRtrimCurrentSourceNextPlan`, a focused
current/next cursor plan for WordPress option-name predicates shaped like:

```sql
rtrim(option_name) COLLATE NOCASE LIKE 'plugin\_%' ESCAPE '\'
```

The behavior is intentionally separate from the accepted Unicode GLOB,
UTF-16-malformed guard, RTRIM/GLOB, and prior NOCASE LIKE slices. It covers
ASCII-only NOCASE prefix range keys over `rtrim()` expression values while the
residual LIKE match is evaluated against the trimmed expression text, not the
raw stored bytes. Mixed UTF-8, UTF-16LE, and UTF-16BE option-name rows are
decoded through the existing native source cursor, malformed text is
quarantined, and current/next source invalidation records source-cookie,
encoding, bytes, rtrim-value, key, candidate-rowset, and matched-rowset
deltas.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNocaseLikeRtrimCurrentSourceNext146Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 87 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-nocase-like-rtrim-current-source-next146.php
```

Dependency closure: no new support component is needed. The slice reuses the
native UTF text decoder, ASCII NOCASE LIKE range planning, RTRIM expression
keys, and current-source invalidation metadata already present in libsqlite.

Non-overlap: this does not repeat accepted Unicode GLOB range handling,
UTF-16 malformed insert guards, RTRIM/GLOB current-source next140/142
clusters, VFS/WAL/B-tree current-source slices, or parser-level SELECT work.
