# Pandoc JSON/native Leaf Block Constructor Matrix

Slice: `pandoc-json-native-leaf-block-constructor-matrix-20260615`
Rebased over current main `721c2a3d70`.

Implemented one bounded JSON/native AST constructor matrix coverage case:
the matrix now exact-round-trips `Plain`, `Para`, `Header`, and `CodeBlock`
leaf block constructors through `PandocJsonReader`, `NativeReader`,
`PandocJsonWriter`, and `NativeWriter`.

This is a constructor-completeness accounting slice. It uses the existing
native PHP JSON/native reader and writer paths and does not invoke Pandoc,
JSON filters, Cabal/Haskell runners, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Result: `1 test files, 4157 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `46 test files, 85493 assertions, 0 failures`

Accounting:

- `phpPass`: `3632 -> 3633`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3670 -> 3671`
- `mappedJsonNativeConstructorMatrixCases`: `10 -> 11`
- `jsonNativeConstructorMatrixAssertions`: `140 -> 154`

Non-goals:

This slice does not change parser or writer behavior outside the existing
JSON/native AST constructor matrix paths, and it does not broaden into
Markdown, HTML, CSL, package ingestion, or external Pandoc runner parity.
