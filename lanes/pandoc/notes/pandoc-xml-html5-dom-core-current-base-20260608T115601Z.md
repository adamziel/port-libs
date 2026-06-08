# Pandoc XML/HTML5 DOM Core Current Base - Translate State Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T115601Z`

Base accepted HEAD: `5dedc386a6e250b7617ad436c30fa6c69d882db9`

## Implementation

- `Html5DomFragment` now converts the HTML global `translate` attribute into
  generated inert `data-pandoc-translate-state` reviewer metadata.
- Valid source states are `yes`, `no`, and the empty attribute form, which
  normalizes to `yes`.
- Invalid `translate` values are stripped with `unsafe-attribute`
  diagnostics, and source-owned `data-pandoc-translate-state` spoofing remains
  blocked by the existing reserved `data-pandoc-*` guard.
- The WordPress HTML5 DOM fragment smoke now asserts that raw `translate`
  attributes do not survive raw HTML block handoff while the generated review
  metadata does.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the HTML
global attribute model: imported fragments should preserve useful source
translation-state provenance for review, but WordPress raw HTML handoff should
not keep live source attributes or source-owned reviewer metadata. This is a
bounded native PHP sanitizer/serializer slice, not browser sanitizer parity,
machine translation, full HTML5 tree-builder parity, XHTML-to-AST conversion,
or upstream Pandoc runner parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present before
  editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1288 assertions, 0 failures`.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1304 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1605 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `2052` -> `2053`.
- `phpPass`: `1631` -> `1632`.
- `xmlHtmlDomCoreCases`: `8` -> `9`.
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`.
- `xmlHtmlDomCoreAssertions`: `124` -> `140`.
- Added `mappedXmlHtmlDomTranslateStateCases: 1`.
- Focused `Html5DomFragmentTest.php`: `1288` -> `1304` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, existing reserved `data-pandoc-*` source-attribute filtering, and
the WordPress HTML5 DOM fragment handoff example.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction
filtering, XML declaration preflight, comment-boundary serialization, raw text/
RCDATA/plaintext handling, SVG/MathML foreign-content casing,
foreign-content CDATA normalization, URL/srcset filtering, data-image handling,
base URL resolution, inactive fallback base isolation, SVG resource filtering,
form/embed/object/applet/noscript/template fallback unwrapping, iframe srcdoc
handoff, safe iframe source links, iframe policy metadata, table
foster-parenting, XML namespace serialization, obsolete media URL attributes,
picture-source pruning, input/select label preservation, media track metadata,
meta refresh filtering, passive named/property meta handoff, passive link
relation handoff, navigation side-effect stripping, image map links, details/
dialog/hidden/inert/popover review metadata, semantic microdata/RDFa review
metadata, time datetime metadata, live editing-state metadata,
language/direction metadata, revision metadata, or source-line diagnostics.

It owns only bounded HTML global `translate` state conversion for sanitized
reviewer fragments.

## Follow-Up

Keep ruby annotation handoff, slot/template shadow metadata, browser sanitizer
parity, full HTML5 tree-builder parity, XHTML-to-AST conversion, CSS/media
execution, and upstream Haskell runner dependency closure as separate bounded
slices.
