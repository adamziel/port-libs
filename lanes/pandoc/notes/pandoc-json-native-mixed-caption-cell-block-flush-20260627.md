# Pandoc JSON/native mixed caption/cell block flush slice

Date: 2026-06-27
Bead: plib-4di9a

## Scope

This slice closes the existing JSON/native constructor regression for mixed inline and block children inside Pandoc table caption and table cell containers.

NativeWriter now uses the same inline-run flushing helper for list items, figures, table captions, and table cells. Inline runs adjacent to nested block nodes are emitted as generated `Plain` blocks before NativeWriter renders the Pandoc native JSON constructors.

The writer also detects mixed block-container content before choosing the native text path, so documents with mixed caption/cell block lists route through the JSON-native output path expected by the existing regression.

## Validation

- `php -l lanes/pandoc/src/NativeWriter.php`
- Selected `PandocJsonNativeAstTest.php` closure `flushes mixed table caption and cell inline runs around nested blocks`: 26 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`: 5,973 assertions, 11 remaining failures. This improves the known baseline by one failure; remaining failures are outside this slice.

No upstream Pandoc, Haskell, TeX, browser, or converter process was invoked.
