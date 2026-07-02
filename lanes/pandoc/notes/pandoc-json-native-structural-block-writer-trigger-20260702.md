# Pandoc JSON/native structural block writer trigger

Slice: `plib-1crr0`

## Change

- `NativeWriter` now treats `blockQuoteBlocksNative`, `divBlocksNative`,
  and `figureBlocksNative` as JSON/native constructor provenance.
- Manually assembled documents that carry only structural block child-list
  sidecars now choose JSON/native output, preserving unchanged child-list
  wrapper payloads while regenerating edited children.

## Parity

This is JSON/native AST constructor completeness work only. It does not change
direct-format reader or writer parity accounting.

## Verification

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonStructuralBlockListWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonStructuralBlockListWrapperTest.php`
