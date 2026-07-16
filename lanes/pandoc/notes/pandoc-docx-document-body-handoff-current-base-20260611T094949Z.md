# DOCX document.xml body ingestion handoff current-base slice

- Bead: `plib-ny1m2`
- Required base: `8d5c0cc07d07304fe0a35de41b61be2333d40d3b`
- Scope: bounded DOCX `document.xml` body ingestion under `lanes/pandoc`; no Pandoc, office suite, zip/unzip, browser, TeX/PDF, Node, Jupyter, external validator, online service, or live-service execution.

## Implementation

`DocxReader` already maps supported direct body paragraphs and tables into the shared AST. This slice closes the silent-drop fallback for unsupported direct `w:body` children by emitting a structured `div` handoff with:

- stable review classes (`docx-body-handoff`, `docx-unsupported-body-element`, and a source element suffix),
- source element name, qualified node name, namespace, and handoff kind in `data-*` attributes,
- a bounded text preview when the unsupported subtree has visible text,
- a visible paragraph label that survives existing Markdown and WordPress block writers.

Supported paragraph/table ingestion stays on the existing native AST path.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> 1 test files, 4893 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 62188 assertions, 0 failures
