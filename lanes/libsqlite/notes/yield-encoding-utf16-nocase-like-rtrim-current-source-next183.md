# UTF-16 NOCASE LIKE RTRIM Current-Source Next183

## Slice

- Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for ASCII `NOCASE LIKE` prefix range reuse over mixed UTF-16 option-name bytes.
- The slice is intentionally disjoint from accepted next180 non-ASCII prefix full-scan fallback. Next183 covers the range-usable path where `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` can use the ASCII prefix cursor, while residual RTRIM matching changes current/next row membership.
- WordPress smoke: copied `wp_options` option-name cache rows switch UTF-16 encodings and trailing-space shape between current/next database images, so stale range cursors must be invalidated before import/query previews reuse them.

## Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 83 assertions, 0 failures
```

## Non-Overlap

- Avoids accepted malformed UTF-16 guard, Unicode GLOB ranges, next180 non-ASCII NOCASE LIKE full-scan fallback, UTF-16 RTRIM/NOCASE current-source next103, and accepted LIKE/GLOB affinity range slices.
- No `UPSTREAM_TEST_MANIFEST.json` mapped denominator change is claimed; this is focused PHP PASS-line growth only.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode, existing `SQLiteLikeCollationPlan` prefix ranges, `SQLiteDatabase::likeMatches()`, RTRIM residual keys, and current-source invalidation diagnostics.
