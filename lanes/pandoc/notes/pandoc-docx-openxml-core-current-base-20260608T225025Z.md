# DOCX/OpenXML Section Header/Footer Relationships

## Behavior

Mapped one bounded DOCX/OpenXML support case for section header/footer parts that carry their own local relationship inventories.

- `DocxReader` now reports local relationship part names and relationship counts on existing section header/footer reference metadata.
- Header/footer relationship summaries preserve relationship id, type, resolved target, external flag, and internal target content type where available.
- External header/footer links keep `contentType` as `null`, matching OPC relationship semantics.
- Header/footer body parsing still uses the existing AST import path; this slice only adds relationship review metadata to section references.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3247 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3259 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed.
- PHP lint passed for changed PHP files.

Focused delta: one mapped DOCX/OpenXML support behavior gains `+12` assertions inside existing `DocxReaderTest.php` PASS cases. Lane `phpPass` is unchanged because no new TestRunner case name was added. Mapped denominator moves `2362 -> 2363`; `mappedDocxOpenXmlCoreCases` moves `33 -> 34`; `docxOpenXmlCoreAssertions` moves `385 -> 397`.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML slices for direct hyperlink metadata, field-code hyperlink display, glossary-local relationships, repeating-section content controls, data-binding prefix mappings, custom XML store binding, DrawingML picture crop/transform, caption SEQ metadata, document settings, embedded objects/packages, subdocuments, tracked changes, section geometry, or header/footer block import. It only exposes local relationship inventories for already-resolved header/footer section reference parts.

## Dependency Closure

No new support component is needed. The slice reuses native `DocxReader` section reference parsing, `OpcRelationships` local relationship parsing/resolution, `ZipPackage` in-memory fixtures, focused DOCX tests, and the existing WordPress DOCX body handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Good next DOCX/OpenXML gaps include remaining header/footer style inheritance, section-scoped numbering interactions, and non-overlapping shape metadata. Keep them native PHP and external-tool free.
