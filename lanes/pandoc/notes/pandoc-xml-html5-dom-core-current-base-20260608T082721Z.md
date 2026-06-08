# Pandoc XML/HTML5 DOM Core Current Base - Revision Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T082721Z`

Base accepted HEAD: `d26ecc00d103df4f8bfc0a6c5fcecf9fae053506`

## Implementation

- `Html5DomFragment` now converts bounded HTML `ins`/`del` `cite` and
  `datetime` source attributes into inert `data-pandoc-revision-*` reviewer
  metadata before WordPress raw HTML handoff.
- Safe revision `cite` URLs are normalized, resolved against the fragment base
  URL when available, and stored as `data-pandoc-revision-cite`.
- Valid revision `datetime` values are restricted to date, local datetime, and
  global datetime forms, then stored as `data-pandoc-revision-datetime` plus
  `data-pandoc-revision-kind`.
- Unsafe `cite` URLs, malformed or non-date revision datetimes, and source-owned
  `data-pandoc-revision-*` spoofing remain stripped with diagnostics.
- The WordPress HTML5 DOM fragment smoke now includes revision markup and asserts
  that live `cite`/`datetime` source attributes do not survive serialization.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the HTML
revision element model: imported fragments should preserve reviewer-visible
change provenance, but raw handoff HTML should not keep live source `cite` and
`datetime` attributes that can be confused with trusted output. This bounded PHP
support slice converts the revision metadata to inert audit attributes rather
than attempting browser sanitizer parity or full HTML5 tree-builder parity.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1206 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 1207 assertions, 1 failures` because raw
  `cite` and `datetime` attributes still serialized on `ins`/`del`.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1225 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1513 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `2000` -> `2001`.
- `phpPass`: `1579` -> `1580`.
- `xmlHtmlDomCoreCases`: `8` -> `9`.
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`.
- `xmlHtmlDomCoreAssertions`: `124` -> `143`.
- Added `mappedXmlHtmlDomRevisionMetadataCases: 1`.
- Focused `Html5DomFragmentTest.php`: `1206` -> `1225` assertions.

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
parser paths, URL normalization and base-resolution helpers, HTML datetime
normalization, focused DOM tests, and the existing reserved `data-pandoc-*`
attribute guard.

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
metadata, time datetime metadata, editing-state metadata, or source-line
diagnostics.

It owns only bounded `ins`/`del` revision metadata conversion for sanitized
reviewer fragments.

## Follow-Up

Keep richer language/direction normalization, `blockquote`/`q` cite provenance,
browser sanitizer parity, full HTML5 tree-builder parity, XHTML-to-AST
conversion, CSS/media execution, and upstream Haskell runner dependency closure
as separate bounded slices.
