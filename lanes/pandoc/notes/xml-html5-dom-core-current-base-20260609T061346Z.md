# XML/HTML5 DOM Current-Base Diagnostic Provenance Slice

Session: `port-dev-pandoc-xml-html5-dom-20260609T061346Z`
Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T061346Z`
Base accepted HEAD: `ad25c5c67f0859a34d555620436625e00d668451`

## Scope

This slice keeps the XML/HTML5 DOM support-library work bounded to native
`Html5DomFragment` fragment sanitization before WordPress handoff.

The implementation attaches DOM source-line metadata to diagnostics that were
already emitted for unsafe or review-needed HTML helper paths:

- iframe sandbox token validation
- iframe invalid `srcdoc` parse failures
- iframe referrer-policy validation
- safe `a`, `img`, and `area` referrer-policy review metadata
- image-map `area` shape and coordinate validation

The WordPress HTML block output remains safe and bounded: live iframe, map,
and area nodes are still converted into reviewer-visible metadata links/spans
or removed as before, but the diagnostics now identify the original fragment
line.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for unsafe DTD/entity
preflight, MathML/SVG casing, void-element serialization, named entity repair,
URL repair line metadata, document metadata source lines, select/form metadata,
image resource source lines, iframe/portal wrapper output, or the prior
duplicate DOM notes. The new behavior is limited to source-line provenance for
the iframe invalid `srcdoc`, iframe policy, and image-map helper diagnostics
listed above.

No rework note was present for this lane at:

`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2192 assertions, 0 failures`

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 2216 assertions, 0 failures`

Adjacent XML/HTML DOM family verification:

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `4 test files, 2666 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- Result: `html5 dom fragment handoff self-test ok`

Status delta:

- `phpPass`: `2428` -> `2429`
- `benchmarkDenominator.mapped`: `2817` -> `2818`
- `xmlHtmlDomCoreCases`: `8` -> `9`
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`
- `xmlHtmlDomCoreAssertions`: `124` -> `148`
- Focused assertion delta: `+24`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DOMDocument` fragment parser, `Html5DomFragment` source-line diagnostic
helper, Pandoc-like AST handoff, and `WordPressBlockWriter` raw HTML block
path.

The full upstream Pandoc runner remains out of scope for this isolated
support-library slice. No Pandoc, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, external converter, external template
engine, TeX/PDF engine, browser renderer, online service, live provider test,
or live-service provider test was executed.

## Next Task

For a follow-up XML/HTML5 DOM slice, choose a non-overlapping native fragment
gap such as source-line provenance for remaining frame/embed helper
diagnostics, bounded `srcdoc` review extraction, or parser-level HTML fragment
metadata handoff.
