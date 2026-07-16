# Pandoc XML/HTML5 DOM Core Current Base - Hidden/Inert Review Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260607T040149Z`
Base: `27db0d0fdf84daf246a72a97a79e230eec3fa716`

## Behavior Added

`Html5DomFragment` now converts source `hidden` and `inert` browser-state
attributes into sanitizer-owned reviewer metadata before WordPress raw HTML
handoff.

- Source-owned `hidden` is stripped from the serialized fragment and replaced
  with `data-pandoc-hidden-state="hidden"`.
- `hidden="until-found"` preserves that state as
  `data-pandoc-hidden-state="until-found"`.
- Source-owned `inert` is stripped and replaced with
  `data-pandoc-inert-state="true"`.
- Spoofed source `data-pandoc-hidden-state` is still rejected by the existing
  reserved-attribute filter before sanitizer metadata is emitted.
- Nested links in hidden/inert content still use the existing trusted base URL
  resolution and unsafe URL stripping.

This keeps import-review content visible while preserving enough provenance for
reviewers to identify originally hidden or inert source sections.

## Evidence

No lane rework note was present for this session before editing.

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 853 assertions, 0 failures`

Red focused check after adding the case:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 854 assertions, 1 failures`
- Failure: source `hidden`/`inert` attributes still serialized instead of
  visible `data-pandoc-*` review metadata.

Green focused check after the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 874 assertions, 0 failures`

Status delta:

- `phpPass`: `1449 -> 1450`
- mapped denominator: `1865 -> 1866`
- XML/HTML5 DOM core cases: `6 -> 7`
- XML/HTML5 DOM core assertions: `89 -> 110`
- focused assertions: `+21`

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment`,
`AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET` parsing, trusted base URL
resolution, the existing reserved `data-pandoc-*` source-attribute filter, the
WordPress HTML5 DOM fragment handoff example, and the focused lane PHP harness.

Full Pandoc HTML reader/tree-builder parity, browser sanitizer parity, remote
resource fetching, Haskell/Cabal runner parity, live provider tests, and
live-service provider tests remain explicitly out of scope for this isolated
support-library slice.

## Non-Overlap

This avoids the accepted XML/HTML5 DOM slices for DTD/entity handling,
processing instructions, comments, RCDATA/plaintext handling, SVG/MathML
foreign-content CDATA, safe raster data-image SVG resources, URL/srcset and
obsolete media URL attributes, trusted base URL fallback, form/embed/object/
applet/noscript/template unwrapping, iframe src/policy metadata, table foster
repair, XML namespace handling, picture/source pruning, input/select labels,
meta refresh/name/property/social image metadata, passive link relations,
navigation side effects, image maps, reserved reviewer attribute stripping, and
inline style review metadata.

This slice owns only the conversion of source `hidden` and `inert` attributes
into visible sanitizer-owned review metadata before WordPress raw HTML handoff.
