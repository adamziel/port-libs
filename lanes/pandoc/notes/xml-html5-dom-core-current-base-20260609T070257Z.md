# XML/HTML5 DOM object/embed source diagnostics

Session: `port-dev-pandoc-xml-html5-dom-20260609T070257Z`
Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T070257Z`
Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`

## Scope

This slice keeps the XML/HTML5 DOM work bounded to native PHP fragment
sanitization before WordPress raw HTML handoff.

`Html5DomFragment` already converted safe `object[data]` and `embed[src]`
resources into inert reviewer links. This patch adds the missing diagnostics
for rejected object/embed source URLs:

- unsafe `object data` values now emit `unsafe-url` with DOM source line
  metadata;
- unsafe `embed src` values now emit `unsafe-url` with DOM source line
  metadata;
- safe object/embed source review links still preserve source-line metadata in
  the raw HTML AST diagnostics.

The updated WordPress fragment smoke includes safe object/embed sources and
unsafe object/embed sources, proving unsafe titles and live wrappers stay out
of WordPress block output while fallback text remains reviewable.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for unsafe XML/DTD
preflight, HTML5 named references, RCDATA/plaintext/template handling,
SVG/MathML integration points, iframe source/policy diagnostics, portal/source
handoff, image/media resource policy diagnostics, semantic metadata source
lines, image-map helper diagnostics, table insertion-mode repair, form/select
metadata, or generic URL source-line diagnostics. The new behavior is limited
to object/embed source diagnostics.

No rework note was present for this lane at:

`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2216 assertions, 0 failures`

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2231 assertions, 0 failures`

Adjacent XML/HTML DOM family verification:

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `4 test files, 2681 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

Status delta:

- `phpPass`: `2467` -> `2468`
- `benchmarkDenominator.mapped`: `2851` -> `2852`
- `xmlHtmlDomCoreCases`: `8` -> `9`
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`
- `xmlHtmlDomCoreAssertions`: `124` -> `139`
- Added `mappedXmlHtmlDomEmbeddedSourceDiagnosticLineCases: 1`
- Focused assertion delta: `+15`

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument`
fragment parsing, `Html5DomFragment` URL normalization and source-line
diagnostic helpers, the Pandoc-like raw HTML AST handoff, and
`WordPressBlockWriter`.

The full upstream Pandoc runner remains out of scope for this isolated
support-library slice. No Pandoc, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, external converter, external template
engine, TeX/PDF engine, browser renderer, online service, live provider test,
or live-service provider test was executed.

## Next Task

For a follow-up XML/HTML5 DOM slice, choose a non-overlapping native fragment
gap such as bounded object `param` review metadata, parser-level HTML fragment
metadata handoff, or another source-position helper diagnostic not already
covered by URL, iframe, semantic, image, media, and object/embed source
diagnostics.
