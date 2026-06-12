# Pandoc CSL BibTeX Shelfmark Archive Aliases

Bead: `plib-z1n6o`

Scope:
- Import BibTeX/BibLaTeX `shelfmark` and `shelf-mark` fields into the existing CSL `call-number` metadata path.
- Import compact BibTeX `archiveLocation`/`archivelocation` into the existing CSL `archive_location` path.
- Preserve direct-format parity with direct CSL item aliases already accepted by `CitationCslProcessor`.

Out of scope:
- New CSL variables, archive extraction, external validators, or Pandoc/office-suite subprocesses.

Verification:
- Focused citation processor regression in `lanes/pandoc/tests/CitationCslProcessorTest.php`.
- Full `lanes/pandoc/tests` lane before completion.
