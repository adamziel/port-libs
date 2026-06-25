# Pandoc JSON Ordered List Enum Payloads

Date: 2026-06-25
Base: origin/plainmath-parity-20260625
Bead: plib-1crr0

This slice preserves mixed ordered-list style and delimiter enum payload shapes
on the plainmath parity branch.

Covered behavior:

- `JsonReader` accepts ordered-list style and delimiter enum payloads as either
  bare constructor strings or tagged Pandoc JSON objects.
- Parsed enum payloads are retained as `listStyleNative` and
  `listDelimiterNative` sidecars on the shared AST.
- `JsonWriter` reuses matching retained enum payloads, so bare enum strings
  remain bare and tagged enum payloads remain tagged.
- `NativeReader` records its parsed ordered-list enum identifiers as sidecars
  so native input can be re-emitted through `JsonWriter` without losing the
  original constructor form.

Verification:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/src/JsonWriter.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/JsonReaderWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php`
  passed 1 file, 68 assertions, 0 failures.

No shell-out to Pandoc, Haskell tooling, office suites, TeX/browser engines,
Node tooling, or external validators was used.
