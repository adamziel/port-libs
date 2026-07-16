# Source-Neutral Cast/Like/Glob Defaults Dynamic

Micro-slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T072352Z-0`

## Scope

Neutralized a bounded encoding cast/LIKE/GLOB source group:

- `SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan`
- `SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan`
- `SQLiteEncodingNumericAffinityCurrentSourceNextPlan`

The production helpers and directly coupled tests/examples now use generic application setting names:

- `setting_id`
- `key_name` / `key_name_bytes`
- `key_value`
- `load_policy`
- `app_settings` source labels

The existing behavior remains the same: current/next source invalidation, LIKE/GLOB range planning, malformed UTF-16 omission, and numeric-affinity comparison deltas are preserved.

## Red-First Evidence

Before the cleanup, the direct focused run failed because the shared source cursor already required generic `setting_id` / `key_name_bytes` rows while the owned source/tests still used legacy option-shaped row keys:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationIndexLikeGlobCurrentSourceNext89Test.php lanes/libsqlite/tests/SQLiteMalformedUtf16LikeRangeCurrentSourceNext93Test.php lanes/libsqlite/tests/SQLiteEncodingNumericAffinityCurrentSourceNext107Test.php
3 test files, 113 assertions, 71 failures
```

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationIndexLikeGlobCurrentSourceNext89Test.php lanes/libsqlite/tests/SQLiteMalformedUtf16LikeRangeCurrentSourceNext93Test.php lanes/libsqlite/tests/SQLiteEncodingNumericAffinityCurrentSourceNext107Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php
4 test files, 185 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php
1 test files, 23 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-encoding-collation-index-like-glob-current-source-next89.php --self-test
application-encoding-collation-index-like-glob-current-source-next89 self-test passed
```

```text
php lanes/libsqlite/examples/application-malformed-utf16-like-range-current-source-next93.php
passed as a JSON smoke with app_settings source labels
```

```text
php lanes/libsqlite/examples/application-encoding-numeric-affinity-current-source-next107.php
passed as a JSON smoke with app_settings source labels
```

```text
git diff --check -- lanes/libsqlite
passed
```

## Dependency Closure

No new support component is needed. This slice reuses the existing encoding/collation source cursor, LIKE collation plan, numeric-affinity comparator, and malformed UTF-16 decoder paths.

## Root Harness

Not run - isolated micro-slice.
