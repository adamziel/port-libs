# DOCX/OpenXML connector and group-shape metadata

Slice: `pandoc-docx-openxml-core-current-base-20260609T030236Z`
Base: `229b80984669c571fd654cf306ec726e5c0ff753`

## Behavior

- `DocxReader` now preserves bounded DrawingML connector shapes as inert review spans.
- Captured connector metadata includes Wordprocessing drawing geometry, `wp:docPr`, connector `cNvPr`, connector locks, start/end connection ids and indexes, transform data, preset geometry, line width/style, and line color.
- `DocxReader` now preserves Wordprocessing group-shape containers as inert review spans.
- Captured group metadata includes Wordprocessing drawing geometry, `wp:docPr`, immediate child-shape count, group locks, group transform, and child-coordinate transform metadata.
- Markdown and WordPress block output reuse the existing span metadata path. The importer does not render connector routing, draw grouped shapes, or expose any media bytes for this metadata-only shape cluster.

## Source Truth

- Microsoft Open XML SDK docs identify DrawingML connection shapes as `a:cxnSp`: https://learn.microsoft.com/en-us/dotnet/api/documentformat.openxml.drawing.connectionshape?view=openxml-3.0.1
- Microsoft Open XML SDK docs identify Wordprocessing group-shape classes and the `wpg` namespace, including `wpg:grpSp` and `wpg:grpSpPr`: https://learn.microsoft.com/en-us/dotnet/api/documentformat.openxml.office2010.word.drawinggroup?view=openxml-3.0.1
- Microsoft Open XML SDK docs identify Wordprocessing nonvisual connector properties and `a:stCxn` / `a:endCxn` child metadata under the Word drawing-shape namespace: https://learn.microsoft.com/en-ie/dotnet/api/documentformat.openxml.office2010.word.drawingshape.nonvisualconnectorproperties?view=openxml-2.8.1

## Evidence

- Focused lane test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3699 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-connector-group-shape-handoff.php --self-test`
  passed with `wordpress-docx-connector-group-shape-handoff self-test passed`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, OPC package relationships, DOM-based DOCX parsing in `DocxReader`, generic Markdown/WordPress span serialization, and the focused lane TestRunner.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX image relationship parsing, DrawingML hyperlinks, crop/transform picture geometry, picture nonvisual metadata, picture effects, captions, backgrounds, chart/diagram placeholders, chart embedded-data provenance, VML images/textboxes, embedded objects/packages, subdocuments, glossary relationships, content controls, paragraph policy, tracked formatting, OMML, settings, comments, or OPC relationship preflight work. It only closes connector and group-shape metadata placeholders.

## Next

A next DOCX/OpenXML slice could cover theme color/style inheritance edges, chart style/color metadata, or table/numbering style interactions without repeating connector/group-shape metadata.
