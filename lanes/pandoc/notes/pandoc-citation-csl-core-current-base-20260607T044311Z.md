## Citation/CSL Locator Range Delimiter Slice

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T044311Z`
Accepted base: `69d7585618048be7a5327c65ade026da42be2670`

Implemented bounded CSL locator range rendering in native PHP. When a CSL
style renders `locator` through `cs:text` or `cs:number`, hyphenated locator
ranges are normalized to an en dash at render time. Parsed citation metadata
such as `locatorValue`, `locatorLabel`, locator condition routing, and
bibliography page variables remain unchanged.

Source truth: CSL 1.0.2 Range Delimiters
(`https://docs.citationstyles.org/en/v1.0.2/specification.html#range-delimiters`)
requires locator variables to render hyphens as en dashes; this slice maps that
bounded format-contract behavior without running Pandoc, citeproc, BibTeX,
Biber, Cabal, Haskell runners, or external bibliography managers.

Focused evidence:

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1880 assertions, 0 failures`.
- Red-first: the new locator-range behavior changed rendered locator output; the direct focused run failed until existing CSL locator expectations were updated from hyphen ranges to en dash ranges.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1887 assertions, 0 failures`.
- WordPress examples updated for locator-condition and is-numeric handoff self-tests.

Dependency closure: no new support component is needed. The slice reuses
`CitationCslProcessor`, `MarkdownReader`, `AstNode`, `MarkdownWriter`,
`WordPressBlockWriter`, and the focused lane PHP harness. Full citeproc/Pandoc
bibliography-manager parity remains out of scope for this bounded support
library slice.

Non-overlap: avoided accepted CSL date-part range delimiter work, locator label
condition routing, is-numeric branch routing, et-al/subsequent-author behavior,
and BibTeX/BibLaTeX metadata handoff. This patch only changes rendered CSL
locator range punctuation.
