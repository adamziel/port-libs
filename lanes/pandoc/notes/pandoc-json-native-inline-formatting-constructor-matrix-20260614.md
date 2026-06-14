# Pandoc JSON/native Inline Formatting Constructor Matrix

Slice: `pandoc-json-native-inline-formatting-constructor-matrix-20260614`
Rebased over current main `56332b3b93`.

Implemented one bounded JSON/native AST constructor matrix coverage case:
the matrix now exercises inline formatting constructor payloads for `Emph`,
`Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`,
`SmallCaps`, `Quoted`/`SingleQuote`, `InlineMath`, `DisplayMath`,
`SoftBreak`, and `LineBreak` through `PandocJsonReader`, `NativeReader`,
`PandocJsonWriter`, and `NativeWriter`.

This is a constructor-completeness accounting slice. It uses the existing
native PHP reader/writer paths and does not invoke Pandoc, JSON filters,
Cabal/Haskell runners, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Result: `1 test files, 3531 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `46 test files, 83912 assertions, 0 failures`

Accounting:

- `phpPass`: `3576 -> 3577`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3500 -> 3501`
- `mappedJsonNativeConstructorMatrixCases`: `9 -> 10`
- `jsonNativeConstructorMatrixAssertions`: `126 -> 140`

Non-goals:

This slice does not change parser or writer behavior outside the existing
JSON/native AST reader/writer constructor paths, and it does not broaden into
Markdown, HTML, CSL, package ingestion, or external Pandoc runner parity.
