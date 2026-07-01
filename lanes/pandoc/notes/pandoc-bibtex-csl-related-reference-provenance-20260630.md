# BibLaTeX Related Reference Provenance

Slice: `plib-q7nbz`

`BibtexCslProcessor` now resolves BibLaTeX relation references into bounded CSL
review metadata:

- `related` keys are preserved as `related-keys`.
- Local related entries are summarized as `relatedItems` with id, requested key,
  type, title, and issued date metadata.
- Missing related keys are preserved as `missing-related-keys`.
- `relatedoptions` is normalized into `relatedOptions` while retaining the raw
  BibLaTeX field.
- `crossref`/`xref` keys now carry `xref-keys`, `xrefItems`,
  `missing-xref-keys`, and `xrefSummary` metadata for CSL rendering.

The downstream `CitationCslProcessor` already understood these structured
fields; this slice bridges BibLaTeX parser output into that existing renderer
path without invoking Pandoc, BibTeX, Biber, citeproc, external validators, or
network fetches.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 663 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `2 test files, 6681 assertions, 0 failures`

Accounting:

- `lane-status.json` `phpPass`: `469 -> 470`
- Direct-format parity remains active; this is native PHP CSL/BibLaTeX metadata
  coverage only.
