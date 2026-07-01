# Legacy BibLaTeX Compact Identifier Aliases

Implemented one bounded native PHP CSL/BibLaTeX handoff slice for compact ISBN and ISSN alias fields in the legacy `BibtexCslProcessor` path.

- `BibtexCslProcessor` now accepts `isbn13`, `isbn-13`, `isbn10`, `isbn-10`, `eisbn`, `e-isbn`, `electronicisbn`, and `electronic-isbn` as ISBN aliases.
- It also accepts `printissn`, `print-issn`, `pissn`, `p-issn`, `eissn`, `e-issn`, `electronicissn`, `electronic-issn`, `onlineissn`, `online-issn`, `issnonline`, and `issn-online` as ISSN aliases.
- The existing identifier normalizers preserve raw BibLaTeX fields while carrying normalized CSL `ISBN`/`ISSN` metadata into `CitationCslProcessor` item normalization, style variables, default bibliography text, and WordPress bibliography output.
- `UPSTREAM_TEST_MANIFEST.json` records one mapped legacy BibLaTeX compact identifier alias case with 19 focused assertions.

Validation so far:

```bash
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
# 1 test files, 1044 assertions, 0 failures
```

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Node, zip/unzip, validators, identifier lookups, attachment reads, or live services were invoked.
