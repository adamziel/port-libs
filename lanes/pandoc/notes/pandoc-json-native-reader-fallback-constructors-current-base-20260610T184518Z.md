# Pandoc JSON/native reader fallback constructors current base 20260610T184518Z

Slice: `plib-0wg09`

Scope: JSON/native AST constructor completeness.

`PandocJsonReader` now preserves unknown tagged Pandoc JSON block and inline constructors as opaque native fallback nodes instead of rejecting them. Unknown blocks become `native_block` nodes and unknown inlines become `native_inline` nodes, each carrying both the original constructor name and the full tagged JSON object in `native` for round-trip emission through `PandocJsonWriter`.

This matches the existing `NativeReader` fallback behavior and closes the reader-side gap for Pandoc JSON packets produced by filters or newer Pandoc versions that contain extension constructors not yet modeled as first-class shared AST nodes.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` — 1 file, 405 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` — 44 files, 61000 assertions, 0 failures
