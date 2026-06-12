# JSON/native NativeWriter inline container payload provenance

Slice: `plib-t86f7`

Base: current main `8aa111e490`

This slice keeps current inline container constructor payloads reusable through `NativeWriter` after a parent block is regenerated. The reuse check now compares payloads through both `PandocJsonReader` and `NativeReader`, so JSON-read source-native payloads survive for unchanged `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Quoted`, `Note`, and `Span` nodes, while stale payloads are dropped after semantic inline edits.

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests are invoked.

Verification:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` (1 test file, 1283 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 test files, 68520 assertions, 0 failures)
