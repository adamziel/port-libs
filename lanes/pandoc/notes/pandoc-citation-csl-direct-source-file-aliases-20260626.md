# Pandoc Citation CSL Direct Source File Aliases

Issue: `plib-qi9bf`
Date: 2026-06-26

## Scope

This bounded native PHP citation/CSL slice extends direct CSL JSON item
normalization for compact source-file attachment aliases. Direct items may now
provide reviewer attachment metadata through `attachments`, `attachment`,
`source-file`, `source-files`, `sourceFile`, `source-attachments`, `file`, or
`pdf` without using the canonical strict `sourceFiles` list.

The compact alias parser accepts the same review form used by BibTeX attachment
fields: `label:path:media-type` entries separated by semicolons. Importable
relative paths are preserved as `sourceFiles`; remote, absolute, traversal, and
otherwise unsafe paths remain blocked as `sourceFileDiagnostics`.

## Non-Overlap

This does not repeat accepted BibTeX `file`/`pdf` attachment parsing, RIS
attachment tags, EndNote attachment diagnostics, or the existing CSL
`sourceFiles` list contract. The canonical `sourceFiles` field remains strict
and still rejects scalar values; only direct alias keys accept compact strings.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 6018 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
zip/unzip, browser renderer, external bibliography manager, online service, live
provider test, or external validator was executed.
