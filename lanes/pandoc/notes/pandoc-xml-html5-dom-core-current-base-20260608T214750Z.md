# Pandoc XML/HTML5 DOM Core Current Base - Focus Navigation Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T214750Z`

Base accepted HEAD: `de56150306796ff6c39d1f6214abe62da3666962`

## Implementation

- `Html5DomFragment` now converts live HTML focus and keyboard shortcut
  attributes into inert reviewer metadata before WordPress raw HTML handoff.
- `autofocus` becomes `data-pandoc-autofocus-state="true"`.
- `tabindex` becomes bounded normalized integer `data-pandoc-tabindex`.
- `accesskey` becomes de-duplicated single-character `data-pandoc-accesskey`
  metadata.
- Invalid tabindex/accesskey values and source-owned `data-pandoc-*` spoofing
  remain diagnostic-only and are stripped from serialized HTML.
- The WordPress HTML5 DOM fragment smoke now includes the focus-navigation
  review path and asserts that live source attributes do not survive.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the HTML
global focus/navigation attribute model: imported fragments may contain useful
focus and shortcut provenance, but WordPress raw HTML handoff should not keep
live autofocus, tabindex, or accesskey behavior from legacy source documents.
This is a bounded native PHP sanitizer/serializer slice, not browser sanitizer
parity, full HTML5 tree-builder parity, XHTML-to-AST conversion, or upstream
Pandoc runner parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present before
  editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1540 assertions, 0 failures`.
- Red-first after adding the focused case:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 1541 assertions, 1 failures` because
  `tabindex`, `accesskey`, and `autofocus` still serialized as live source
  attributes.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1564 assertions, 0 failures`.
- DOM family:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1879 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: `1884 -> 1885`.
- `benchmarkDenominator.mapped`: `2308 -> 2309`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 148`.
- Added `mappedXmlHtmlDomFocusNavigationCases: 1`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, the lane-local focused
test harness, and the existing WordPress HTML5 DOM fragment handoff example.

## Non-Overlap

This slice does not repeat accepted DTD/entity rejection, processing
instruction filtering, XML declaration preflight, comment-boundary
serialization, raw text/RCDATA/plaintext handling, SVG/MathML foreign-content
casing, foreign-content CDATA normalization, URL/srcset filtering, safe data
image handling, base URL resolution, inactive fallback base isolation, SVG
resource filtering, form/embed/object/applet/noscript/template fallback
unwrapping, iframe srcdoc handoff, safe iframe source links, iframe policy
metadata, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, picture-source pruning, input/select label preservation, media
track metadata, meta refresh filtering, passive named/property meta handoff,
passive link relation handoff, navigation side-effect stripping, image map
links, details/dialog/hidden/inert/popover review metadata, semantic
microdata/RDFa review metadata, time datetime metadata, live editing-state
metadata, language/direction metadata, translation metadata, revision metadata,
quote-cite metadata, ruby annotation metadata, custom-element metadata, ARIA
metadata, or source-line diagnostics.

It owns only bounded HTML global focus/navigation metadata conversion for
sanitized reviewer fragments.

## Follow-Up

Keep browser sanitizer parity, full HTML5 tree-builder parity, XHTML-to-AST
conversion, CSS/media execution, richer inert widget provenance, and upstream
Haskell runner dependency closure as separate bounded slices.
