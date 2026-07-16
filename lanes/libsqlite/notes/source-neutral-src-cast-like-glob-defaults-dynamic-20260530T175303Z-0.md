Source-neutral cast/LIKE/GLOB cursor cleanup

Slice: source-neutral-src-cast-like-glob-defaults-dynamic-20260530T175303Z-0
Base accepted HEAD: e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0

Changed production source:

- SQLiteUtf16LikeGlobCurrentNextCursor now accepts generic `setting_id` and
  `key_name_utf16` rows in its application key scan helper.
- SQLiteUtf16LikeGlobAffinityCurrentSourceCursor now derives rowids from
  generic `setting_id` rows in its application value scan helper.
- SQLiteNocaseGlobAffinityCurrentSourceNextPlan now traces generic `key_name`
  and `setting_id` rows instead of option-shaped defaults.
- SQLiteEncodingSourceNeutralDefaultsTest now guards those adjacent LIKE/GLOB
  cursor source files in the same source-neutral family.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobCurrentNext75Test.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityCurrentSourceNext87Test.php lanes/libsqlite/tests/SQLiteNocaseGlobAffinityCurrentSourceNext139Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  - 4 test files, 189 assertions, 0 failures.
- `php -l` passed for all changed PHP source and test files.
- `git diff --check -- lanes/libsqlite` passed.

API guard:

- `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present
  in this worktree, so no generic no-domain API guard command was available.

Dependency closure:

- No new support component is needed. This is a source-neutral naming cleanup
  over existing LIKE/GLOB and UTF-16 cursor behavior.
