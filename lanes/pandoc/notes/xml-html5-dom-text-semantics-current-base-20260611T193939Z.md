# XML/HTML5 DOM Text-Level Semantics Slice

Session: `plib-mfi53`
Base: `e6767f509`

This slice extends native PHP `XmlHtmlDom` reviewer summaries for bounded HTML
text-level semantic elements. Summaries now expose `textSemantic`,
`semanticTag`, and `semanticText` for `abbr`, `dfn`, `mark`, `code`, `kbd`,
`samp`, `var`, `small`, `sub`, `sup`, `bdi`, `bdo`, `u`, and `s`. The `abbr`
and `dfn` paths preserve title/term provenance, and `bdi`/`bdo` preserve
normalized direction provenance.

Verification:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> `1 test files, 712 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 65624 assertions, 0 failures`

Accounting:
- `phpPass`: `3107 -> 3108`
- `benchmarkDenominator.mapped`: `3206 -> 3207`
- `mappedXmlHtmlDomTextSemanticCases`: `1`
- `xmlHtmlDomTextSemanticAssertions`: `24`

This does not repeat accepted XML/HTML5 DOM work for lists, quote/cite,
revision datetime, time/data/meter/progress values, form controls, media
resources, hyperlinks, figures, tables, SVG/MathML casing, raw text/RCDATA,
microdata/RDFa, or sanitizer source-line diagnostics. It is limited to
`XmlHtmlDom` reviewer-summary metadata for text-level semantic inline elements.
