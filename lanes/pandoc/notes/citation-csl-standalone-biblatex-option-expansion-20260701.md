# Citation/CSL Standalone BibLaTeX Option Expansion

Slice: `plib-rru52`

This slice expands the native PHP BibTeX/BibLaTeX CSL handoff for standalone
BibLaTeX entry-option fields. The `BibtexCslParser` and legacy
`BibtexCslProcessor` paths now carry the common max/min alpha, bibliography,
and citation name thresholds plus `mergedate`, `singletitle`, `usetitle`,
`usevenue`, and `uniquetitle` aliases into canonical `biblatex-options` review
metadata.

Coverage:

- Compact fields such as `maxbibnames`, `minalphanames`, `mergedate`,
  `singletitle`, `usetitle`, `usevenue`, and `uniquetitle` are preserved with
  canonical option names.
- Hyphenated aliases such as `max-bib-names`, `min-cite-names`, `merge-date`,
  `single-title`, `use-title`, `use-venue`, and `unique-title` normalize to the
  same canonical option names.
- Explicit standalone fields continue to override same-named values from
  `options={...}` before Citation/CSL style rendering, citation handoff, and
  WordPress bibliography output.

Accounting:

- Adds one focused mapped Citation/CSL behavior check.
- Adds 28 assertions covering raw BibTeX fields, direct CSL items,
  `CitationCslProcessor` normalization, CSL style variables, citation handoff,
  and WordPress output.
- Increments the lane mapped-check denominator from 2,316 to 2,317.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorStandaloneOptionExpansionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorStandaloneOptionExpansionTest.php`
  passed with 28 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with 1,250 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with 6,136 assertions and 0 failures.

No external citeproc, BibTeX, Biber, Pandoc, office suite, browser, TeX, package,
network, or validator process is invoked by this slice.
