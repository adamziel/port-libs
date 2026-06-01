# source-neutral-src-encoding-default-sources-dynamic-20260601T155209Z-0

## Scope

Owned the UTF-16 encoding pattern wrapper callers for:

- `SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan`
- `SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan`
- `SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan`

## Red-first evidence

Before the cleanup, the direct tests were still feeding option-shaped rows into neutral production APIs:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNext114Test.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinityPatternCurrentSourceNext118Test.php lanes/libsqlite/tests/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextTest.php` failed with `3 test files, 14 assertions, 183 failures`.
- The three directly coupled examples failed their `--self-test` entrypoints with undefined legacy `optionRowValuePlan()` / `optionRowNamePlan()` calls.

## Source-neutral cleanup

- Replaced stale direct fixture keys `option_id`, `option_value`, `option_value_bytes`, and `option_name_bytes` with `setting_id`, `key_value`, `key_value_bytes`, and `key_name_bytes`.
- Replaced the remaining `autoload` fixture prefix with neutral `loadflag` while preserving UTF-16 pattern bytes, LIKE/GLOB matching, collation range, malformed text, and source invalidation assertions.
- Migrated the direct examples from legacy option-row method calls to `keyValueRowValuePlan()` and `keyValueRowKeyPlan()`.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest.php` to guard the three wrapper source files and the consolidated UTF-16 NOCASE/RTRIM source against legacy option/autoload/wp terms.

## Verification

- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l` passed for all changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNext114Test.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinityPatternCurrentSourceNext118Test.php lanes/libsqlite/tests/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `5 test files, 215 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-utf16-pattern-like-glob-affinity-current-source-next114.php --self-test` passed.
- `php lanes/libsqlite/examples/application-utf16-glob-literal-bracket-current-source-next122.php --self-test` passed.
- `php lanes/libsqlite/examples/application-utf16-pattern-nocase-like-rtrim-current-source-next.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Status delta

Source-neutral cleanup only. No `phpPass`, mapped-coverage, or lane-status counter movement is claimed.

## Dependency closure

No new support component is needed. The cleanup reuses the existing UTF-16 decode, LIKE/GLOB pattern decoding, NOCASE/RTRIM range planning, and current-source invalidation helpers.
