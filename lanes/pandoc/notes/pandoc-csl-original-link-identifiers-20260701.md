# pandoc-csl-original-link-identifiers-20260701

Slice: `plib-x3jgx` Citation/CSL legacy BibLaTeX original link identifier handoff.

## Scope

`BibtexCslProcessor` and `BibtexCslParser` now preserve bounded original DOI
and URL aliases as review-visible CSL metadata:

- `origdoi`, `orig-doi`, `originaldoi`, and `original-doi` map to
  `original-doi`.
- `origurl`, `orig-url`, `originalurl`, and `original-url` map to
  `original-url`.
- `CitationCslProcessor` normalizes direct CSL JSON aliases such as `origDOI`
  and `original-URL` into `originalDoi` and `originalUrl`.
- Default bibliography text, CSL style variables, and WordPress bibliography
  handoff render the new `Original DOI` and `Original URL` labels.

This is metadata-only handoff. It does not resolve identifiers, fetch URLs,
read attachments, or invoke an external citation processor.

## Evidence

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorOriginalIdentifierTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorOriginalIdentifierTest.php`
  - Result: 1 file, 65 assertions, 0 failures.

## Manifest

`UPSTREAM_TEST_MANIFEST.json` now records one mapped
`legacyBiblatexOriginalLinkIdentifier` case with 32 assertions and increments
the benchmark mapped denominator from 2876 to 2877.

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine,
Typst, Jupyter, Node, zip/unzip, validator, or live service was invoked.
