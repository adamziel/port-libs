# Pandoc JSON/native Attr validation

Bead: `plib-pqfk7`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

This slice tightens native `Attr` constructor ingestion for both JSON and native
reader paths. Attribute class lists now require string class names instead of
silently coercing scalar values, and native-reader attribute key-value payloads
now reject malformed pairs instead of ignoring them.

The behavior keeps valid duplicate attribute tuple provenance unchanged while
making malformed constructor payloads fail at the reader boundary, matching the
existing strict handling for `Attr` identifiers and key-value strings in the
Pandoc JSON reader.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online services,
live provider tests, or live-service provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1545 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70068 assertions, 0 failures`
