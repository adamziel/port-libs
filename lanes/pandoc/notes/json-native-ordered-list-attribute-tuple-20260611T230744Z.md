# JSON/native OrderedList attribute tuple provenance

Bead: plib-hc1s5
Date: 2026-06-11 UTC
Base: origin/main ba99e7070b

This slice preserves Pandoc `OrderedList` attribute tuple provenance across JSON and native AST ingestion. `PandocJsonReader` and `NativeReader` now retain the full `[start, style, delimiter]` constructor tuple as `listAttributesNative`, while the JSON and native writers reuse that tuple only when it still matches the node's current `start`, `style`, and `delimiter` attrs.

The writer guard keeps edited list items from discarding current tuple shape, but prevents stale tuple payloads from surviving semantic list-attribute edits.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` - 1 test file, 1169 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 test files, 66947 assertions, 0 failures
