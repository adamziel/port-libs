# libsqlite JSONB Inspection Evidence - 2026-05-23T034010Z

Scope: bounded native PHP `libsqlite` JSONB inspection slice for SQLite-style
`json_type()` and `json_array_length()` behavior over existing SQLite JSONB
bytes and the lane's JSON path parser.

## Source Context

- Upstream checkout: `.upstream-cache/libsqlite`
- Upstream Git HEAD: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite source id observed by testfixture: `2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d`
- Pre-slice repo HEAD observed during work: `6c4f34f Map Syncthing pull scanner scheduling`

## Native Change

- Added `SQLiteJsonB::type()` and `SQLiteJsonB::arrayLength()`.
- Validates the JSONB byte string with the existing decoder, then inspects
  element headers so integer/real, true/false, null, text, array, and object
  type names match SQLite.
- Uses the existing JSON path parser for root and path targets.
- Returns `null` for missing paths, and returns `0` from `arrayLength()` when
  the located target exists but is not an array.
- Added `examples/wordpress-jsonb-inspect-option-arrays.php` for WordPress
  option/meta migration array preflight checks.

## Commands And Results

Focused native libsqlite harness:

```sh
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests = require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $runner = new TestRunner(); $runner->runTests($tests, "lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT, "\n1 test file, ".$runner->assertions()." assertions, ".$runner->failures()." failures\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result: `1` test file, `888` assertions, `0` failures.

Focused upstream SQLite Tcl JSON evidence:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json102.test jsonb01.test
```

Result: `0` errors out of `356` tests in `00:00`.

Focused upstream JSONB SQL probe:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture <<'EOF'
sqlite3 db :memory:
puts [db one {SELECT json_type(jsonb('{"a":[2,3.5,true,false,null,"x"]}'),'$.a[2]')}]
puts [db one {SELECT json_array_length(jsonb('{"optionMigrations":[{"name":"core"},{"name":"cache"}],"metaKeys":["_legacy_flag"],"legacyMode":"skip"}'),'$.optionMigrations')}]
puts [db one {SELECT json_array_length(jsonb('{"optionMigrations":[{"name":"core"},{"name":"cache"}],"metaKeys":["_legacy_flag"],"legacyMode":"skip"}'),'$.legacyMode')}]
puts [db one {SELECT json_type(jsonb('{"optionMigrations":[{"name":"core"},{"name":"cache"}],"metaKeys":["_legacy_flag"],"legacyMode":"skip"}'),'$.postMetaQueue') IS NULL}]
EOF
```

Result: `true`, `2`, `0`, `1`.

WordPress preflight example smoke test:

```sh
php lanes/libsqlite/examples/wordpress-jsonb-inspect-option-arrays.php \
  '{optionMigrations:[{name:"core"},{name:"cache"}],metaKeys:["_legacy_flag"],legacyMode:"skip"}' \
  '$.optionMigrations' '$.metaKeys' '$.legacyMode' '$.postMetaQueue'
```

Result: `optionMigrations` array length `2`, `metaKeys` array length `1`,
`legacyMode` type `text` with array length `0`, and missing `postMetaQueue`
with null type and null array length.

PHP lint:

```sh
php -l lanes/libsqlite/src/SQLiteJsonB.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-jsonb-inspect-option-arrays.php
```

Result: no syntax errors in all three touched PHP files.

JSON validation:

```sh
php -r 'foreach (["lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json", "lanes/libsqlite/lane-status.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " valid\n"; }'
```

Result after status updates: both JSON files valid.

Whitespace check:

```sh
git diff --check -- lanes/libsqlite
```

Result after status updates: no output, exit code `0`.

Root PHP harness:

```sh
php tools/run-tests.php
```

Result: `180` test files, `17368` assertions, `0` failures.

## Remaining Boundary

- Full SQLite `all`/`release` permutations were not run for this bounded
  slice.
- Broader JSONB output/edit behavior outside focused type/array-length,
  remove, insert/set/replace, array-insert, merge-patch, and non-finite
  normalization remains future work.
