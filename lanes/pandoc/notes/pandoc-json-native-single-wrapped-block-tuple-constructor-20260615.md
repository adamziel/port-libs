# Pandoc JSON/native single-wrapped block tuple constructors

Slice: `pandoc-json-native-single-wrapped-block-tuple-constructor`
Base: current main `38ffeef295`.

Implemented one bounded JSON/native AST constructor-completeness case:
`PandocJsonReader` and `NativeReader` now accept single-wrapped fixed-width
block tuple payloads for `Header`, `CodeBlock`, `RawBlock`, `OrderedList`,
`Div`, `Figure`, and `Table` constructors.

The focused fixture verifies both JSON and native reader paths, reusable sidecar
preservation for simple block wrappers through both writer stacks, semantic
stability for Figure/Table writer output, and canonical unwrapped output when
an edited heading invalidates the original sidecar.

This slice does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: `1 test files, 5024 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 87512 assertions, 0 failures`

Accounting:

- `phpPass`: `3698 -> 3699`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3723 -> 3724`
- `mappedJsonNativeConstructorCompletenessCases`: `45 -> 46` in lane status; `43 -> 44` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1005 -> 1091` in lane status; `962 -> 1048` in the upstream manifest
- `mappedJsonNativeSingleWrappedBlockTupleCases`: `1`
- `jsonNativeSingleWrappedBlockTupleAssertions`: `86`
