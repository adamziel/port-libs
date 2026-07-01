# CSL JSON Citation Alias Review

`plib-bmslh` adds metadata-only citation alias review rollups to the CSL JSON bibliography reader path.

- `BibliographyReader::cslJsonReview()` now reports alias-bearing item counts, alias field counts, and per-field alias value counts without retaining alias strings.
- Per-item CSL JSON review packets now expose `citationAliasFields`, `citationAliasFieldCount`, `citationAliasValueCount`, and `citationAliasValueCounts`.
- `BibliographyReaderTest` covers aggregate and per-item alias provenance and asserts alias payload values remain omitted from review JSON.

Validation:

- `php -l lanes/pandoc/src/BibliographyReader.php`
- `php -l lanes/pandoc/tests/BibliographyReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services were invoked.
