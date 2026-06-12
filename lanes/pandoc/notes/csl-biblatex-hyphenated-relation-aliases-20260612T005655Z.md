# CSL BibLaTeX Hyphenated Relation Aliases

Bead: `plib-9vmm8`

Base: `2a569e454141e2dcfcd92a8d8f63a44b0fec5448`

Scope:

- `BibtexCslParser` maps hyphenated BibLaTeX relation aliases for `xdata-keys`, `entry-set`, `related-keys`, `related-type`, `related-string`, `related-options`, and `xref-keys`.
- The bounded CSL handoff preserves xdata inheritance, relation summaries, xref provenance, citation rendering, bibliography entries, and WordPress review blocks.
- No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests are invoked.

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 4972 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 68331 assertions, 0 failures`.

Bookkeeping:

- `mappedCslBiblatexHyphenatedRelationAliasCases`: `1`
- `cslBiblatexHyphenatedRelationAliasAssertions`: `26`
- mapped denominator: `3227`
- `phpPass`: `3160 -> 3161`
