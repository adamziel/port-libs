# Pandoc BibTeX CSL Source File Aliases - 2026-07-01

## Scope

- Added bounded BibTeX/BibLaTeX compact source-file aliases for CSL metadata:
  `source-file`, `source-files`, `sourcefile`, `sourcefiles`,
  `source-attachment`, `source-attachments`, `sourceattachment`,
  `sourceattachments`, `attachment`, and `attachments`.
- Existing `file` and `pdf` precedence is unchanged.
- All aliases reuse the existing source-file attachment parser and diagnostics;
  no attachment bytes are imported and remote/absolute/traversal paths remain
  diagnostic-only.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
- `git diff --check -- lanes/pandoc`
