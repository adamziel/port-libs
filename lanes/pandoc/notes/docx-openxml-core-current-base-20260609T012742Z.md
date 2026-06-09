# DOCX/OpenXML custom XML property diagnostics

Slice: `pandoc-docx-openxml-core-current-base-20260609T012742Z`
Base: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

## Behavior

- `DocxReader` now summarizes DOCX custom XML datastore property issues in `metadata.docxCustomXmlStore` and `importReport.customXmlStore`.
- Structured document tags with `w:dataBinding/@w:storeItemID` now report an inert failed-binding diagnostic when the package has custom XML store parts but no valid item matches the requested store item ID.
- Malformed or incomplete custom XML property parts, such as a `ds:datastoreItem` without `ds:itemID`, remain non-rendering metadata. The visible SDT content is preserved, while Markdown and WordPress handoff nodes expose `data-docx-sdt-custom-xml-bound="false"` plus issue codes for reviewer audit.
- The existing WordPress content-control binding smoke now includes one valid bound item and one malformed custom XML properties item, proving the diagnostic summary without changing the visible bound content-control output.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3435 assertions, 0 failures`.
- Red-first focused test after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed with `1 test files, 3438 assertions, 1 failures` because `issueCount` was absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3461 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-content-control-binding-handoff.php --self-test`
  passed with `wordpress-docx-content-control-binding-handoff self-test passed`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, OPC relationship graph loading, DOM-based `DocxReader` custom XML store parsing, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane TestRunner.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX content-control prefix mappings, successful custom XML store binding, SDT form controls, repeating sections, glossary/docPart controls, paragraph borders, tracked formatting, deleted OMML, embedded objects/packages, subdocuments, settings, comments, DrawingML geometry, image hyperlinks, picture effects, or OPC relationship preflight work. It only closes the validation diagnostic path for malformed custom XML datastore properties.

## Next

A next DOCX/OpenXML slice could cover theme font inheritance edges, connector/group-shape metadata, chart embedded-data provenance, or style-based paragraph policy inheritance without repeating custom XML binding diagnostics.
