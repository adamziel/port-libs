# Encoding/Collation RTRIM CAST Current-Source Next131

## Behavior

- Adds `SQLiteCastRtrimLikeCurrentSourceNextPlan` for current-source `CAST(option_value AS ...) COLLATE RTRIM LIKE` planning over copied `wp_options` rows.
- The range candidate key trims ASCII space only, matching SQLite `RTRIM` collation behavior.
- The residual LIKE match still uses the original cast text, so exact LIKE does not silently match space-padded or tab-padded values.
- Tracks current/next cursor invalidation by source name, schema cookie, cast result, RTRIM key, candidate rowset, and matched rowset.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCastRtrimLikeCurrentSourceNext131Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-cast-rtrim-like-current-source-next131.php --self-test`
- Lint: `php -l lanes/libsqlite/src/SQLiteCastRtrimLikeCurrentSourceNextPlan.php`, `php -l lanes/libsqlite/tests/SQLiteCastRtrimLikeCurrentSourceNext131Test.php`, `php -l lanes/libsqlite/examples/application-cast-rtrim-like-current-source-next131.php`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids accepted UTF-16 malformed guards, Unicode GLOB ranges, CAST/NOCASE LIKE prefix ranges, CAST/RTRIM GLOB ranges, and UTF-16 RTRIM LIKE/GLOB slices. The new surface is specifically current-source `CAST(... AS TEXT) COLLATE RTRIM LIKE` exact/prefix behavior with RTRIM candidate keys and untrimmed LIKE residuals.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP SELECT CAST, LIKE pattern planning, SQLite BLOB values, and current-source row-array execution helpers.
