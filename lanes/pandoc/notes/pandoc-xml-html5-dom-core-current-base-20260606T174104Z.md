# Pandoc XML/HTML5 DOM Core Current Base - Image Map Area Review Links

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T174104Z`

Base accepted HEAD: `9e3c99a8e5c01950dfc5cf7a611b50350af53219`

## Implementation

- `Html5DomFragment` now treats HTML image maps as active navigation
  structures for WordPress review handoff.
- `<map>` wrappers are stripped, and safe `<area href=...>` regions are
  converted into inert reviewer `<a>` links with
  `data-pandoc-image-map-area="true"`.
- Area provenance is preserved as bounded inert metadata:
  `data-pandoc-image-map-name`, normalized `shape`, normalized numeric
  `coords`, and `alt` label text.
- Unsafe area `href` values are dropped with diagnostics, and unsafe
  `target`, `download`, `ping`, `rel=opener`, unknown shape values, and
  invalid coord lists stay diagnostics-only.
- The WordPress HTML5 DOM fragment handoff smoke now covers a legacy image map
  and verifies that no live `<map>` or `<area>` elements reach the raw HTML
  block.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the already
accepted `usemap` and navigation side-effect policy: recovered HTML fragments
can preserve safe source-navigation URLs and review metadata, but should not
preserve active click-region DOM structures in WordPress raw HTML handoff.

This is bounded native PHP sanitizer behavior. It is not full HTML5
tree-builder parity, browser sanitizer parity, CSS/media loading, active image
map interaction, XHTML-to-AST conversion, or full upstream Pandoc runner
parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 712 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 682 assertions, 3 failures` because the new and
  adjusted image-map assertions still saw live `<map><area>` serialization
  instead of inert reviewer links.
- Focused green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 742 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1015 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1789` -> `1790`.
- `phpPass`: `1376` -> `1377`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `100`.
- Added `mappedXmlHtmlDomImageMapAreaCases: 1`.
- Focused `Html5DomFragmentTest.php`: `712` -> `742` assertions.

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
parser paths, trusted base URL resolution, existing image-map `usemap` policy,
navigation side-effect filtering, and the focused lane test harness.

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
passive named/property meta handoff, passive link relation handoff, or
navigation side-effect stripping.

It owns only bounded image-map `<area>` review-link conversion and area
shape/coord provenance handoff.

## Follow-Up

Keep richer safe media provenance, additional parser recovery cases, fuller
XHTML-to-AST conversion, full HTML5 tree-builder parity, browser sanitizer
parity, CSS/media loading, and full upstream Haskell runner dependency closure
as separate bounded slices.
