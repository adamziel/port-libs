# XML/HTML5 DOM fieldset legend diagnostics

Session: `port_libs/polecats/1219`
Micro-slice: `pandoc-xml-html5-dom-fieldset-legend-diagnostics-20260615`
Base accepted HEAD: `5ca74382ac7272927de8eff807e0f457cf6d35b7`

## Scope

This slice keeps the XML/HTML5 DOM work bounded to native PHP DOM fragment
review metadata. It extends `XmlHtmlDom::summarizeHtmlFragment()` fieldset
summaries with:

- direct legend text inventory and first-legend index metadata;
- enabled/disabled form-control buckets derived from existing fieldset disabled
  inheritance handling;
- nested fieldset inventory for reviewer handoff;
- diagnostic codes for missing legends, multiple legends, and nested fieldset
  review.

The behavior remains summary-only. It does not claim direct Pandoc HTML reader
parity and does not change HTML serialization.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for global attributes,
labels, datalist handoff, form owners, output `for` references, select/option
state, popovers, button command targets, media/image/object resources,
template/noscript/raw-text handling, image maps, table foster parenting,
DocBook, JATS/BITS, namespaces, or foreign SVG/MathML casing.

## Evidence

Focused verification after implementation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
- Result: `1 test files, 4277 assertions, 0 failures`

Adjacent XML/HTML DOM verification:

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `4 test files, 7181 assertions, 0 failures`

Full post-rebase gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `46 test files, 89005 assertions, 0 failures`

Status delta:

- Added `mappedXmlHtmlDomFieldsetLegendDiagnosticsCases: 1`
- Added `xmlHtmlDomFieldsetLegendDiagnosticsAssertions: 40`

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument`
fragment parsing, existing HTML form-control disabled inheritance helpers,
`AstNode`, and `WordPressBlockWriter`.

No Pandoc executable, office suite, TeX/PDF engine, browser renderer, Node
tooling, `zip`/`unzip`, external validator, online service, live provider test,
or external converter was executed.
