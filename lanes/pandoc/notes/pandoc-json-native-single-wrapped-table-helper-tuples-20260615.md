# Pandoc JSON/native single-wrapped table helper tuples

Slice: `plib-n0gn2`

Area: Pandoc JSON/native AST constructor completeness.

This bounded slice accepts single-wrapped table helper tuple constructors across
the JSON and native readers for `TableHead`, `TableBody`, `Row`, `Cell`, and
`TableFoot`. The writers now reuse those wrapped helper sidecars when a table is
rebuilt and the generated helper payload is unchanged, while the regenerated top
table wrapper still drops stale wrapper-level sidecars.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4938 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87396 assertions, 0 failures`

Accounting:

- rebased current main: `4d5d5de2a`
- `phpPass`: `3696 -> 3697`
- `phpFail`: `0`
- `upstream.mapped`: `3721 -> 3722`
- `mappedJsonNativeConstructorCompletenessCases`: `44 -> 45` in lane status; `42 -> 43` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `963 -> 1005` in lane status; `920 -> 962` in the upstream manifest
- `mappedJsonNativeSingleWrappedTableHelperTupleCases`: `1`
- `jsonNativeSingleWrappedTableHelperTupleAssertions`: `42`
