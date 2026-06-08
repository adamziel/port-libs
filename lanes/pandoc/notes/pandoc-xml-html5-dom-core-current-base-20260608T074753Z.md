# Pandoc XML/HTML5 DOM Core Current Base - Editing State Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T074753Z`

Base accepted HEAD: `abd1af5843ccdf0a6730b63402c30abf96a3e9f7`

## Implementation

- `Html5DomFragment` now converts bounded `contenteditable`, `spellcheck`, and
  `draggable` attributes into generated inert `data-pandoc-*` reviewer metadata
  before WordPress raw HTML handoff.
- Valid `contenteditable` states are `true`, `false`, and `plaintext-only`.
  Empty `contenteditable` normalizes to `true`.
- Valid `spellcheck` states are `true`, `false`, and `default`. Empty
  `spellcheck` normalizes to `true`.
- Valid `draggable` states are `true`, `false`, and `auto`. Empty `draggable`
  normalizes to `true`.
- Invalid states are stripped with `unsafe-attribute` diagnostics, and
  source-owned `data-pandoc-*` spoofing continues to be rejected by the existing
  reserved attribute guard.
- The WordPress HTML5 DOM fragment smoke now includes editable legacy content
  and asserts that live editing, spellcheck, and dragging attributes do not
  survive in serialized raw HTML.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the HTML
global attribute model: imported fragments should preserve reviewer-visible
state and provenance, but raw handoff HTML should not keep live editability or
dragging behavior. This bounded PHP support slice converts those attributes to
inert review metadata rather than attempting browser sanitizer parity or full
HTML5 tree-builder parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1187 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 1188 assertions, 1 failures` because raw
  `contenteditable`, `spellcheck`, and `draggable` attributes were still
  serialized.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1206 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1494 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1987` -> `1988`.
- `phpPass`: `1566` -> `1567`.
- `xmlHtmlDomCoreCases`: `8` -> `9`.
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`.
- `xmlHtmlDomCoreAssertions`: `124` -> `143`.
- Added `mappedXmlHtmlDomEditingStateCases: 1`.
- Focused `Html5DomFragmentTest.php`: `1187` -> `1206` assertions.

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
parser paths, focused DOM tests, the existing URL and reserved-attribute
diagnostic plumbing, and the WordPress HTML5 DOM fragment handoff example.

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
metadata, time datetime metadata, or source-line diagnostics.

It owns only bounded live editing-state attribute conversion for sanitized
reviewer fragments.

## Follow-Up

Keep richer language/direction normalization, `ins`/`del` revision metadata,
browser sanitizer parity, full HTML5 tree-builder parity, XHTML-to-AST
conversion, CSS/media execution, and upstream Haskell runner dependency closure
as separate bounded slices.
