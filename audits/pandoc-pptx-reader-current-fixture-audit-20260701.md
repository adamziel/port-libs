# Pandoc PPTX Reader Current Fixture Audit - 2026-07-01

Scope: native PHP `PortLibs\Pandoc\PptxReader` coverage for the upstream Pandoc PPTX reader golden fixture. PPTX writer package parity remains out of scope for this reader audit.

## Upstream Basis

- Upstream repository: `jgm/pandoc`.
- Fixture/source commit: `d8ea25c10e980105d4d023d656990a56e295ccb4` (`main`, checked 2026-07-01). The PPTX reader source files and `test/pptx-reader/basic.*` fixture bytes match the previously pinned `612e143fbe6d735b612c4800d21e61b7d44e4dca` evidence.
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

Linked image relationships using `a:blip r:link` are now distinguished from embedded image relationships. External linked images remain out of visible content and produce `external-image-target` review metadata with the `link` relationship attribute and external-target preflight result instead of a generic missing-image diagnostic.

The reader now also exposes upstream-style presentation slide-size metadata from `p:sldSz`, including the raw EMU dimensions and the same integer `EMU / 914400` width/height values used by upstream's intermediate parser. Presentations without `p:sldSz` receive the upstream default `9144000 x 6858000` EMU size.

The grouped-shape path now recursively reads drawable children inside `p:grpSp`, including nested groups, so grouped text boxes and grouped pictures enter the visible AST through the same paragraph/image paths as top-level shapes.

Unsupported drawable shapes such as `p:cxnSp` connectors now produce slide-level `shapeIssues` review metadata instead of disappearing silently. The diagnostic preserves the element type, non-visual drawing properties, z-order, and transform metadata while avoiding fabricated visible content.

Text runs with DrawingML hyperlink relationships now emit Pandoc `link` inlines and slide-level `links` review metadata. External targets are preserved without fetching them, along with relationship id/type, target mode, tooltip title, and external-target preflight metadata.

Text-box-level `a:hlinkClick` relationships on non-visual drawing properties now wrap paragraph/list inline content in Pandoc `link` inlines, preserving whole-text-box hyperlinks without disturbing more specific run-level links.

Picture-level `a:hlinkClick` relationships on non-visual drawing properties now wrap the emitted image inline in a Pandoc `link`, so whole-picture links survive while the image media relationship remains separate and auditable.

DrawingML text runs now preserve explicit `a:br` and `a:tab` markers in text boxes as structured Pandoc inlines. Breaks become `linebreak` nodes and tabs preserve separation as `space`, while paragraphs without those structural markers keep the previous fixture-stable flattened text path.

DrawingML auto-numbered paragraphs using `a:buAutoNum` now import as Pandoc `ordered_list` blocks. The reader preserves the first `startAt` value, maps common PowerPoint auto-numbering type prefixes into Pandoc list styles, maps period/parenthesis/plain suffixes into Pandoc delimiters, and keeps the raw PPTX auto-numbering type as reviewable AST metadata.

DrawingML list paragraph levels now import as nested Pandoc lists instead of adjacent flat lists. Higher-level `lvl` paragraphs attach to the previous list item, preserving mixed nested bullet and ordered children while keeping same-level restart/style boundaries intact.

DrawingML `a:buNone` paragraphs at deeper list levels now import as continuation blocks inside the previous list item instead of escaping to top-level paragraphs. This matches the reader to the existing PPTX writer's list-continuation shape while preserving standalone non-list paragraphs.

Rowless DrawingML tables now emit no visible table block, matching upstream's `PptxTable []` behavior rather than fabricating an empty table in the AST.

Broken SmartArt diagram relationships now fail soft into visible parse-diagnostic paragraphs instead of aborting the PPTX import when referenced diagram data/layout parts are missing or invalid. The fallback block keeps shape metadata so the unsupported/broken drawing frame remains traceable.

Slide-level `notesSlide` relationships now import speaker notes as Pandoc `Div` blocks with the `notes` class. The reader extracts note-body DrawingML paragraphs, skips notes-page slide-image and slide-number placeholders, and records relationship id, target, package part, text, and block count in slide review metadata without embedding AST nodes in the review sidecar.

Latest focused verification:

- `php -l lanes/pandoc/src/PptxReader.php`
- `php -l lanes/pandoc/tests/PptxReaderTest.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php`: `291` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php lanes/pandoc/tests/PptxWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`: `1148` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php lanes/pandoc/tests/PptxWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/pandoc/tests/DocxWriterTest.php`: `1581` assertions, `0` failures.

This closes the current upstream PPTX reader golden fixture content gate. It does not claim full PPTX writer parity or full PowerPoint package round-trip parity.
