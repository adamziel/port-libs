# Pandoc JSON/native quote and math enum payloads current base 20260611T160442Z

Slice: `plib-crztl`

Scope: JSON/native AST constructor completeness.

`PandocJsonReader` and `NativeReader` now preserve the original helper enum
payloads for supported `Quoted` and `Math` inline constructors:

- `quoteTypeNative` beside `quoteTypeConstructor` for `SingleQuote` and
  `DoubleQuote` payloads.
- `mathTypeNative` beside `mathTypeConstructor` for `InlineMath` and
  `DisplayMath` payloads.

This mirrors the existing ordered-list `listStyleNative` and
`listDelimiterNative` provenance and keeps reviewer handoff metadata complete
without changing writer output.

Direct-format parity accounting is not affected by this metadata-only
constructor provenance slice.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` - 1 test file, 793 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 test files, 63807 assertions, 0 failures

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
