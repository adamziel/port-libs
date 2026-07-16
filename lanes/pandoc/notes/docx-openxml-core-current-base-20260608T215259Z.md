# DOCX/OpenXML content-control prefix mappings

Slice: `pandoc-docx-openxml-core-current-base-20260608T215259Z`
Base: `6f8463809fe932bed047f1bc503ab1bca68687f8`

## Behavior

- `DocxReader` now preserves `w:dataBinding/@w:prefixMappings` on structured document tags.
- The raw namespace declaration string is kept in `data-docx-sdt-prefix-mappings` for audit.
- Bounded `xmlns` declarations are parsed into stable numbered `data-docx-sdt-prefix-N-name` and `data-docx-sdt-prefix-N-uri` attributes, including default namespace declarations as `default`.
- Inline and block content controls keep the existing visible content handoff while exposing XPath, custom XML store ID, and namespace mappings to Markdown and WordPress block output.

## Evidence

- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3164 assertions, 0 failures`.
- Red-first focused test after adding the new expectations failed with `1 test files, 3175 assertions, 1 failures` because `data-docx-sdt-prefix-mappings` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3203 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-content-control-binding-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. This reuses native `DocxReader`, `ZipPackage`, `MarkdownWriter`, `WordPressBlockWriter`, and the existing TestRunner. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX SDT form controls, repeating sections, glossary/docPart controls, tracked formatting, paragraph borders, deleted OMML, embedded objects, subdocuments, settings, comments, DrawingML geometry, or OPC relationship preflights. It only closes the separate data-binding namespace-prefix metadata path for content controls.

## Next

Good non-overlapping DOCX/OpenXML follow-ups: custom XML part inventory for bound content controls, richer theme font inheritance, SEQ/caption numbering heuristics, or additional DrawingML shape geometry metadata.
