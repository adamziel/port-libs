# Pandoc XML/HTML5 DOM Core Current Base - Details Disclosure Review

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T212812Z`

Base accepted HEAD: `cb4a520948498ea2ea4f2172addef8f8f2d882da`

## Implementation

- `Html5DomFragment` now marks closed HTML `<details>` blocks with inert
  `data-pandoc-details-state="closed"` metadata before WordPress raw HTML
  handoff.
- The first `<summary>` child in a closed details block is marked with
  `data-pandoc-details-summary="true"` so import review packets can identify
  the disclosure label without relying on browser UI state.
- Closed details content remains present and sanitized: safe relative links are
  resolved through trusted base metadata, unsafe URLs and event handlers are
  stripped, and source-owned `data-pandoc-*` spoofing remains blocked.
- The WordPress HTML5 DOM fragment smoke now covers closed and open details
  blocks in the same legacy import packet.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the existing
raw HTML handoff policy: recovered HTML fragments should preserve reviewer
visible content and provenance while stripping active behavior and source-owned
reviewer metadata. Closed disclosure content should not become invisible to
WordPress migration review just because a browser would initially collapse it.

This is bounded native PHP sanitizer/serializer behavior. It is not full HTML5
tree-builder parity, browser sanitizer parity, active disclosure UI execution,
CSS/media execution, XHTML-to-AST conversion, or full upstream Pandoc runner
parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 756 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 757 assertions, 1 failures` because closed
  `<details>` serialized without details/summary review metadata.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 776 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1049 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1815` -> `1816`.
- `phpPass`: `1402` -> `1403`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `90`.
- Added `mappedXmlHtmlDomDetailsDisclosureCases: 1`.
- Focused `Html5DomFragmentTest.php`: `756` -> `776` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, trusted base URL resolution, existing reserved `data-pandoc-*`
source-attribute filtering, and the focused lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction
filtering, XML declaration preflight, comment-boundary serialization,
raw text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
foreign-content CDATA normalization, URL/srcset filtering, data-image
handling, base URL resolution, inactive fallback base isolation, SVG resource
filtering, SVG presentation resource URL filtering, form/embed/object/applet/
noscript/template fallback unwrapping, `iframe srcdoc` content handoff, safe
iframe source link conversion, iframe policy metadata, table foster-parenting,
XML namespace serialization, obsolete media URL attributes, picture-source
pruning, explicit input/select label preservation, meta refresh filtering,
passive named/property meta handoff, passive link relation handoff,
navigation side-effect stripping, image-map area review links, or reserved
source-owned reviewer attribute filtering.

It owns only bounded closed `<details>` disclosure review metadata while
preserving sanitized disclosure content.

## Follow-Up

Keep richer interactive-widget provenance, additional parser recovery cases,
full XHTML-to-AST conversion, full HTML5 tree-builder parity, browser sanitizer
parity, CSS/media execution, and upstream Haskell runner dependency closure as
separate bounded slices.
