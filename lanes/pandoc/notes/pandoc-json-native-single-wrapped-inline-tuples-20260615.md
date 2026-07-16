# Pandoc JSON/native single-wrapped inline tuples

Date: 2026-06-15
Bead: plib-3am18

## Scope

JSON and native readers now accept single-wrapped tuple payloads for inline constructors that Pandoc represents as tuple-like payloads:

- `Quoted`
- `Code`
- `Math`
- `RawInline`
- `Cite`
- `Link`
- `Image`
- `Span`

The JSON and native writers reuse unchanged wrapper payloads for those constructors and regenerate canonical tuple payloads after edits.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 4450 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 86408 assertions, 0 failures

No external Pandoc, Haskell, JSON filter, browser, validator, online service, or live provider was invoked.
