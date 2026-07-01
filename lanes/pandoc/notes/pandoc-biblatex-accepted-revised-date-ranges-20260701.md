# BibLaTeX accepted/revised date ranges

Slice: `plib-j3zq4` citation/bibliography CSL core blocker.

`BibtexCslProcessor` now maps legacy BibLaTeX accepted and revised split date
ranges through the CSL handoff. The legacy path already accepted single split
dates and raw range strings; this closes the end-year/month/day split-field gap
for `accepted*` and `revised*` fields, including hyphenated aliases.

Manifest counters:
- `mappedLegacyBiblatexAcceptedRevisedDateRangeCases`: `1`
- `legacyBiblatexAcceptedRevisedDateRangeAssertions`: `29`

Validation:
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorAcceptedRevisedDateRangeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorAcceptedRevisedDateRangeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorAcceptedRevisedDateRangeTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine,
Node tooling, validators, or live services were invoked.
