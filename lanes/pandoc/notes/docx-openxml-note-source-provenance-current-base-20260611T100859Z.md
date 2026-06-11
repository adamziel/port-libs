# DOCX OpenXML Note Source Provenance

- Bead: `plib-q3cpt`
- Required base: `48eb1ae26784e73aa7aeb76f07f5f46a8f036758`
- Scope: bounded DOCX OpenXML footnotes/endnotes/comments/commentsExtended part discovery and structured provenance handoff from document relationships.

## Implementation

- `DocxReader` now summarizes document-level note/comment relationships in `metadata.docxNotes` and `importReport.notes.sources`.
- Imported note AST nodes now retain source part, relationship id/type/target, relationships part, and content type provenance.
- The relationship summary preserves query and fragment suffixes while loading the stripped package part path, and inventories item counts for footnotes, endnotes, comments, and commentsExtended parts.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 4970 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62378 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, zip/unzip, Jupyter, Node tooling, or external validator was invoked.
