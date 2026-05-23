# libsqlite JSON Array Insert Evidence - 2026-05-23T031525Z

Scope: bounded native PHP `libsqlite` JSONB mutation slice for SQLite-style
`json_array_insert()`/`jsonb_array_insert()` behavior. This audit records
local implementation evidence only; it does not claim full SQLite release/all
parity.

## Source Context

- Upstream checkout: `.upstream-cache/libsqlite`
- Upstream Git HEAD: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite source id observed by testfixture: `2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d`
- Previous libsqlite slice: `dcf3a7d Implement libsqlite JSONB mutation helpers`

## Native Change

- Added `SQLiteJsonB::arrayInsert()` and reused the existing JSONB
  decode/encode and JSON path parser.
- Implemented SQLite-style array insertion:
  - insert before existing array indexes;
  - append at `[N]` when `N` equals the current array length;
  - append for `[#]` and `[#-0]`;
  - use `[#-N]` reverse offsets as insertion points;
  - create missing object/array substructure only when the remaining path
    ends in an array element;
  - leave out-of-range and non-array traversal as no-ops;
  - reject paths that resolve to non-array elements such as `$.a`.
- Added a WordPress option/meta migration preflight example:
  `lanes/libsqlite/examples/wordpress-jsonb-array-insert-option-field.php`.

## Commands And Results

Focused native libsqlite harness:

```sh
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests = require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $runner = new TestRunner(); $runner->runTests($tests, "lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT, "\n1 test file, ".$runner->assertions()." assertions, ".$runner->failures()." failures\n"); exit($runner->failures() === 0 ? 0 : 1);'
```

Result: `1` test file, `833` assertions, `0` failures.

Focused upstream SQLite Tcl JSON evidence:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  json109.test json102.test jsonb01.test
```

Result: `0` errors out of `374` tests in `00:00`.

Focused upstream JSONB SQL probe:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture <<'EOF'
sqlite3 db :memory:
puts [db one {SELECT hex(jsonb_array_insert(jsonb('[1,2,3]'),'$[1]',jsonb('{"kind":"cache"}')))}]
puts [db one {SELECT json(jsonb_array_insert(jsonb('{"a":[1,2,3]}'),'$.b[0]',jsonb('{"kind":"cache"}')))}]
EOF
```

Result:

```text
CB121331BC476B696E6457636163686513321333
{"a":[1,2,3],"b":[{"kind":"cache"}]}
```

PHP lint:

```sh
php -l lanes/libsqlite/src/SQLiteJsonB.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-jsonb-array-insert-option-field.php
```

Result: no syntax errors in all three touched PHP files.

JSON validation:

```sh
php -r 'foreach (["lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json", "lanes/libsqlite/lane-status.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " valid\n"; }'
```

Result: both JSON files valid.

Whitespace check:

```sh
git diff --check -- lanes/libsqlite
```

Result: no output, exit code `0`.

WordPress preflight example smoke test:

```sh
php lanes/libsqlite/examples/wordpress-jsonb-array-insert-option-field.php \
  '{optionMigrations:[{name:"core"},{name:"cache"}],metaKeys:["_legacy_flag"]}' \
  '$.optionMigrations[1]' '{"name":"permalink"}' '$.metaKeys[#]' 'text:_generated_css'
```

Result: inserted `{"name":"permalink"}` before the existing `cache` migration
entry and appended `_generated_css` to the `metaKeys` array.

Root PHP harness:

```sh
php tools/run-tests.php
```

Result: `177` test files, `16958` assertions, `0` failures.

## Remaining Boundary

- Full SQLite `all`/`release` permutations were not run for this bounded
  slice.
- Broader JSONB edit behavior outside focused remove, insert/set/replace,
  array-insert, and merge-patch helpers remains future work.
