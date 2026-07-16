# Pandoc JSON/native Inline Helper Variant Matrix

Slice: `pandoc-json-native-inline-helper-variant-matrix`

This bounded JSON/native AST constructor-completeness slice adds a focused
helper-variant matrix for inline helper constructors:

- `SingleQuote` and `DoubleQuote` quote helpers.
- `InlineMath` and `DisplayMath` math helpers.
- `NormalCitation`, `AuthorInText`, and `SuppressAuthor` citation-mode helpers.

The matrix exercises `PandocJsonReader`, `NativeReader`, `PandocJsonWriter`, and
`NativeWriter` against the same packet and requires both writers to preserve the
original helper constructor payloads. It does not invoke Pandoc, JSON filters,
Cabal/Haskell runners, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: `1 test files, 4728 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 86911 assertions, 0 failures`

Accounting:

- rebased current main: `3e9dada260`
- `phpPass`: `3680 -> 3681`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3712 -> 3713`
- `mappedJsonNativeHelperConstructorVariantCases`: `10 -> 11`
- `jsonNativeHelperConstructorVariantAssertions`: `230 -> 254`
