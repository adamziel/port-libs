# Pandoc XML/HTML5 DOM Core Current Base - Iframe Policy Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T170846Z`

Base accepted HEAD: `5fc3730fb1c0fbf3355d466f48a23306a784bc3c`

## Implementation

- `Html5DomFragment` now preserves bounded iframe policy provenance when a
  blocked safe `<iframe src=...>` is converted into an inert reviewer link.
- Recognized `sandbox` tokens are lowercased, deduplicated, and emitted as
  `data-pandoc-iframe-sandbox`; unknown sandbox tokens stay out of output and
  are recorded as diagnostics.
- Cleaned `allow` directives, valid `referrerpolicy` values, and
  `allowfullscreen` are emitted as inert `data-pandoc-iframe-*` attributes.
- The WordPress HTML5 DOM fragment handoff smoke now proves a safe iframe
  source link carries the sandbox, allow, referrer-policy, and fullscreen
  metadata while the iframe wrapper remains stripped.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract and the
previously accepted safe iframe-source reviewer-link handoff: recovered HTML
fragments should not preserve active nested browsing contexts, but policy
metadata attached to a safe frame source is reviewer provenance for WordPress
migration audits.

This is bounded native PHP sanitizer support. It is not iframe execution,
browser sanitizer parity, complete HTML5 tree-builder parity, permissions
policy enforcement, CSS/media loading, XHTML-to-AST conversion, or full
upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 696 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 697 assertions, 1 failures`; the new iframe
  policy metadata case produced reviewer links without `data-pandoc-iframe-*`
  policy metadata.
- Focused green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 712 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 985 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1783` -> `1784`.
- `phpPass`: `1370` -> `1371`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `86`.
- Focused `Html5DomFragmentTest.php`: `696` -> `712` assertions.

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
parser paths, trusted base URL resolution, the existing iframe source
review-link handoff, and the focused lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction
filtering, XML declaration preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
foreign-content CDATA normalization, URL/srcset filtering, data-image
handling, base URL resolution, inactive fallback base isolation, SVG resource
filtering, SVG presentation resource URL filtering, form/embed/object/applet/
noscript/template fallback unwrapping, `iframe srcdoc` content handoff, safe
plain `iframe src` link conversion, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, explicit
input/select label preservation, meta refresh filtering, passive named/
property meta handoff, passive link relation handoff, or navigation
side-effect stripping.

It owns only bounded iframe policy metadata review handoff for safe blocked
iframe source links.

## Follow-Up

Keep image-map area review handoff, richer safe media provenance, full HTML5
tree-builder parity, richer sanitizer policy, CSS/media resource loading,
XHTML-to-AST conversion, and full upstream Haskell runner dependency closure
as separate bounded slices.
