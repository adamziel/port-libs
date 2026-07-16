# Pandoc JSON/native structural block-list wrappers

Slice: `plib-movgm`

## Change

- `BlockQuote`, `Div`, and `Figure` readers now keep native child block-list
  payloads as sidecars.
- JSON/native writers reuse those child block-list sidecars when rebuilding the
  parent constructor and the semantic child blocks are unchanged.
- Edited child blocks regenerate canonical child block lists instead of keeping
  stale single-wrapper payloads.

## Parity

This is JSON/native AST constructor completeness work only. It does not change
direct-format reader or writer parity accounting.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonStructuralBlockListWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonStructuralBlockListWrapperTest.php`
