# pandoc-csl-original-link-identifiers-20260702

Slice: `plib-x3jgx` Citation/CSL legacy BibLaTeX original identifier handoff.

## Scope

`BibtexCslProcessor` and `BibtexCslParser` now preserve bounded original
publication identifier aliases as review-visible CSL metadata:

- `origisbn`, `orig-isbn`, `originalisbn`, and `original-isbn` map to
  `original-isbn`.
- `origissn`, `orig-issn`, `originalissn`, and `original-issn` map to
  `original-issn`.
- `origdoi`, `orig-doi`, `originaldoi`, and `original-doi` map to
  `original-doi`.
- `origurl`, `orig-url`, `originalurl`, and `original-url` map to
  `original-url`.
- `CitationCslProcessor` normalizes direct CSL JSON aliases such as `origISBN`,
  `original-ISSN`, `origDOI`, and `original-URL` into camel-case item fields.
- Default bibliography text, CSL style variables, and WordPress bibliography
  handoff render the new original identifier labels.

This is metadata-only handoff. It does not resolve identifiers, fetch URLs,
read attachments, or invoke an external citation processor.

## Evidence

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorOriginalIdentifierTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorOriginalIdentifierTest.php`
  - Result: 1 file, 65 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorOriginalIdentifierTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - Result: 4 files, 7,507 assertions, 0 failures.

## Manifest

`UPSTREAM_TEST_MANIFEST.json` records one mapped
`legacyBiblatexOriginalIdentifier` case with 33 assertions and one mapped
`legacyBiblatexOriginalLinkIdentifier` case with 32 assertions, incrementing
the benchmark mapped denominator from 2316 to 2318 on this integration branch.

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine,
Typst, Jupyter, Node, zip/unzip, validator, or live service was invoked.
