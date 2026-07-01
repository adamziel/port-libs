# Pandoc PPTX Reader Current Fixture Audit - 2026-07-01

Scope: native PHP `PortLibs\Pandoc\PptxReader` coverage for the upstream Pandoc PPTX reader golden fixture. PPTX writer package parity remains out of scope for this reader audit.

## Upstream Basis

- Upstream repository: `jgm/pandoc`.
- Fixture commit: `612e143fbe6d735b612c4800d21e61b7d44e4dca`.
- Reader test source: `test/Tests/Readers/Pptx.hs`.
- Fixture pair:
  - `test/pptx-reader/basic.pptx`
  - `test/pptx-reader/basic.native`

## Checked-In Fixture Evidence

- Local fixture directory: `lanes/pandoc/fixtures/upstream-current-pptx-reader`.
- `basic.pptx` SHA-256: `e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc`.
- `basic.native` SHA-256: `42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4`.

## Local Gate

`lanes/pandoc/tests/PptxReaderTest.php` now parses `basic.native` through `NativeReader`, parses `basic.pptx` through `PptxReader`, and compares a normalized Pandoc-reader content signature. The normalization excludes local package review sidecars, local native pretty-printer tokenization differences, and empty table-foot representation differences while preserving the golden document block tree, heading ids, text content, list structure, table content, image target/title, SmartArt classes, and SmartArt nested blocks.

The focused reader suite also locks a missing-image relationship case against the upstream visible-content behavior: an image relationship whose internal package part is absent no longer emits a visible `image` node. The reader records `missing-image-part` with relationship id, target, and package part name in slide review metadata so the omission remains auditable.

The reader now also exposes upstream-style presentation slide-size metadata from `p:sldSz`, including the raw EMU dimensions and the same integer `EMU / 914400` width/height values used by upstream's intermediate parser. Presentations without `p:sldSz` receive the upstream default `9144000 x 6858000` EMU size.

The grouped-shape path now recursively reads drawable children inside `p:grpSp`, including nested groups, so grouped text boxes and grouped pictures enter the visible AST through the same paragraph/image paths as top-level shapes.

Unsupported drawable shapes such as `p:cxnSp` connectors now produce slide-level `shapeIssues` review metadata instead of disappearing silently. The diagnostic preserves the element type, non-visual drawing properties, z-order, and transform metadata while avoiding fabricated visible content.

Text runs with DrawingML hyperlink relationships now emit Pandoc `link` inlines and slide-level `links` review metadata. External targets are preserved without fetching them, along with relationship id/type, target mode, tooltip title, and external-target preflight metadata.

Latest focused verification:

- `php -l lanes/pandoc/src/PptxReader.php`
- `php -l lanes/pandoc/tests/PptxReaderTest.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php`: `188` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php lanes/pandoc/tests/PptxWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`: `1045` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php lanes/pandoc/tests/PptxWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/pandoc/tests/DocxWriterTest.php`: `1478` assertions, `0` failures.

This closes the current upstream PPTX reader golden fixture content gate. It does not claim full PPTX writer parity or full PowerPoint package round-trip parity.
