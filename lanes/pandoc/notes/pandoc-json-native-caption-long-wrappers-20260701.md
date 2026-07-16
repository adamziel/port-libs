# Pandoc JSON/native caption long-wrapper preservation

Slice: `plib-ne43e`

## Change

- Preserves a parsed `Caption` native sidecar when an unchanged long-caption
  block list used Pandoc's single extra list wrapper shape.
- Applies to `Table` and `Figure` captions when the parent wrapper is rebuilt
  but caption attrs are otherwise unchanged.
- If the short caption changes, the stale top-level caption sidecar is still
  dropped while the unchanged wrapped long-caption payload is retained.
- If the long caption changes, the long-caption payload is regenerated in the
  canonical block-list shape and stale long-block sidecars are not retained.

## Parity

This is JSON/native AST constructor completeness work only. It does not change
direct-format reader or writer parity accounting.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonCaptionLongWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonCaptionLongWrapperTest.php`
