# DOCX/OpenXML custom XML store binding

Slice: `pandoc-docx-openxml-core-current-base-20260608T221413Z`
Base: `238c756134d68ede9072631361599c436a2f8d32`

## Behavior

- `DocxReader` now inventories package-level DOCX `customXml` datastore parts reached from package relationships.
- For each bounded datastore item, the reader records relationship target metadata, content type, byte size, root QName/namespace/local name, normalized text preview, `itemProps` store item ID, and schemaRef URIs.
- `readPackage()` exposes the inventory in `metadata.docxCustomXmlStore` and `importReport.customXmlStore`.
- Structured document tags with `w:dataBinding/@w:storeItemID` now attach matching datastore metadata to the existing content-control node attributes, including part, properties part, root metadata, text preview, and schema refs.
- The existing WordPress content-control binding smoke now includes a custom XML datastore item and verifies the reviewer-visible data attributes.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3203 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3247 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-content-control-binding-handoff.php --self-test` passed.
- Syntax checks passed for `DocxReader.php`, `DocxReaderTest.php`, and `wordpress-docx-content-control-binding-handoff.php`.

## Delta

- `phpPass`: `1912 -> 1913`.
- `benchmarkDenominator.mapped`: `2335 -> 2336`.
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`.
- Focused DOCX assertions: `3203 -> 3247` (`+44`).

## Dependency Closure

No new support component is needed. This reuses native `OpcRelationshipGraph`, `OpcRelationships`, `ZipPackage`, `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`, and the existing TestRunner. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX SDT form controls, repeating sections, glossary/docPart controls, tracked formatting, paragraph borders, deleted OMML, embedded objects, subdocuments, document settings, comments, DrawingML geometry, content-control prefix mappings, or OPC relationship preflights. It only closes the custom XML datastore inventory and SDT `storeItemID` binding metadata path named as a follow-up by the prior DOCX prefix-mapping slice.

## Next

Good non-overlapping DOCX/OpenXML follow-ups remain theme font inheritance edges, SEQ/caption numbering heuristics, additional DrawingML shape geometry/text metadata, or validation diagnostics for malformed custom XML datastore properties.
