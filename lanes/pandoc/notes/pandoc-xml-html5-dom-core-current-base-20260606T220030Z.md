# Pandoc XML/HTML5 DOM Charset Metadata Slice

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T220030Z`
Base: `f4e557172b3b27cae095ea7602a0976b77d2578b`

## Behavior

`Html5DomFragment` now converts passive HTML charset metadata into inert reviewer spans before WordPress raw HTML handoff:

- `<meta charset="Windows-1252">` becomes a hidden-source-free reviewer span with `data-pandoc-meta-charset="windows-1252"` and `data-pandoc-meta-source="charset"`.
- Legacy `<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">` becomes a reviewer span with `data-pandoc-meta-source="content-type"`.
- Quoted charset parameters are normalized.
- Invalid charset labels, unrelated `http-equiv` metadata, and resource-affecting metadata remain stripped with blocked-tag diagnostics.

This keeps charset declarations visible to import reviewers without allowing `<meta>` elements or resource-changing metadata through the WordPress raw HTML path.

## Evidence

Red-first path:

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 776 assertions, 0 failures`
- Red-first after adding the charset expectation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 777 assertions, 1 failures`
- Failure reason: charset `<meta>` declarations were dropped instead of converted into reviewer metadata spans.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 795 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

## Mapping Delta

- `lane-status.json` `phpPass`: `1406 -> 1407`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1819 -> 1820`
- `xmlHtmlDomCoreCases`: `5 -> 6`
- `mappedXmlHtmlDomCoreCases`: `5 -> 6`
- `xmlHtmlDomCoreAssertions`: `70 -> 89`
- Added `mappedHtmlReaderMetaCharsetCases: 1`

## Dependency Closure

No new support component is needed. The slice reuses native PHP `Html5DomFragment` metadata normalization, DOM/libxml `NONET` parsing, `AstNode`, `WordPressBlockWriter`, and the existing WordPress HTML5 DOM fragment example smoke.

Full Pandoc runner parity, browser tree-builder parity, complete byte-stream charset detection/recoding, CSS/media loading, XHTML-to-AST conversion, online services, live provider tests, and live-service provider tests remain out of scope for this bounded support-library slice.

## Non-Overlap

This slice avoids the accepted XML/HTML5 DOM clusters for SVG/MathML CDATA, SVG data-image resources, select/option label fallback text, passive link relations, iframe policy metadata, meta refresh, named/property metadata, details disclosure, data attributes, form unwrapping, srcset filtering, and resource URL filtering. It is limited to passive charset declarations in `<meta>` tags.

## Follow-Up

Possible next XML/HTML5 DOM work should stay non-overlapping: richer inert review metadata, additional parser recovery cases, or bounded XHTML-to-AST handoff. Do not run Pandoc, Cabal/Haskell runners, browser renderers, external XML/HTML tools, online sanitizers, online services, live provider tests, or live-service provider tests unless explicitly authorized.
