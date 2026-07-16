# DOCX/OpenXML Picture Effects

Slice: `pandoc-docx-openxml-core-current-base-20260609T011059Z`
Base: `09109401d59cee7a589aaf8125432abbe4aef718`

## Behavior

- `DocxReader` now preserves bounded DrawingML picture effect metadata from `a:blip` and `pic:spPr/a:effectLst` on imported DOCX image nodes.
- Covered blip effects: `a:alphaModFix`, `a:alphaMod`, `a:lum`, `a:duotone`, `a:grayscl`, and `a:biLevel`.
- Covered shape effects: `a:outerShdw`, `a:innerShdw`, `a:softEdge`, `a:reflection`, `a:glow`, and `a:blur`.
- The effect data is inert review metadata only: image AST nodes keep safe `docx-picture-*` classes and `data-docx-picture-*` attributes that flow through Markdown and WordPress block output.

## Evidence

- Red-first focused test after adding the new expectations:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed with `1 test files, 3402 assertions, 1 failures` because picture effect classes were absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3435 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-picture-effects-handoff.php --self-test`
  passed with `wordpress-docx-picture-effects-handoff self-test passed`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, OPC relationship resolution, DOM-based `DocxReader` DrawingML image parsing, `MarkdownWriter` attribute handoff, and `WordPressBlockWriter` image rendering.

No Pandoc, Word, LibreOffice, zip/unzip, ZipArchive, Cabal solver/build/test command, Haskell runner, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX image relationship parsing, DrawingML hyperlinks, crop/transform geometry, nonvisual picture metadata, captions, backgrounds, chart/diagram placeholders, VML images, textboxes, embedded objects/packages, subdocuments, glossary relationships, content controls, paragraph policy, tracked formatting, or deleted OMML coverage. It only closes the picture-effects follow-up named by earlier DOCX/OpenXML notes.

## Next

A next DOCX/OpenXML slice could cover group-shape or connector nonvisual metadata, chart embedded-data provenance, or style-based paragraph policy inheritance without repeating picture effects.
