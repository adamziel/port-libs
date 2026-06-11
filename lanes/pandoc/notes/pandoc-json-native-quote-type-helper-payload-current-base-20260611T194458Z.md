# pandoc-json-native-quote-type-helper-payload-current-base-20260611T194458Z

Slice: `plib-movgm` on current main `6673f4a17`.

## Scope

This bounded JSON/native AST constructor-completeness slice preserves quote-type
helper payload provenance for `Quoted` inline constructors. It does not touch
package ingestion, CSL, PDF/Typst, XML/HTML, Markdown/plain/CommonMark/wiki/roff,
or external runner behavior.

## Change

`PandocJsonWriter` and `NativeWriter` now reuse compatible `quoteTypeNative`
payloads when writing edited quoted inline nodes. This keeps string helper
payloads such as `SingleQuote` and `DoubleQuote` intact after quote text edits.
If the preserved helper conflicts with the normalized quote kind, writers fall
back to canonical constructor objects.

No Pandoc, JSON filter, Cabal/Haskell runner, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Passed: `1 test file, 1060 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Passed: `44 test files, 65764 assertions, 0 failures`.
