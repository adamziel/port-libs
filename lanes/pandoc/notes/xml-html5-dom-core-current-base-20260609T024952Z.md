# XML/HTML5 DOM current-base SVG title integration point

Session: `port-dev-pandoc-xml-html5-dom-20260609T024952Z`

Base accepted HEAD: `f46ebd3f38d4045b46cad3c6483db1eb4cd9e92b`

## Scope

- Implemented one bounded XML/HTML5 DOM behavior cluster for SVG `title` as an HTML integration point.
- SVG `title` fallback markup is no longer pre-escaped as HTML RCDATA when the start tag is in SVG context.
- Descendants of SVG `title` are parsed in HTML casing, so fallback `p` and `textPath` markup becomes `p` and `textpath`.
- Nested `svg` descendants inside the fallback content re-enter SVG foreign-content casing, preserving names such as `viewBox` and `linearGradient`.
- Top-level HTML `title` handling remains RCDATA/metadata behavior; document-title metadata is not emitted for SVG `title`.

## Non-overlap

- This slice does not repeat accepted SVG `foreignObject`, SVG `desc`, MathML token-text integration, MathML annotation-xml, CDATA, unsafe XML/DTD rejection, metadata, orphan table repair, URL filtering, passive link metadata, image-map conversion, portal/source handling, or responsive image metadata slices.
- The covered gap is specifically SVG `title` integration-point behavior needed by XML/HTML5 DOM fragment and WordPress raw HTML handoff paths.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note was present before editing.
- Red-first behavior probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $body=PortLibs\Pandoc\Html5Dom::parseHtmlFragment("<svg><title><p viewBox=\"html attr\"><textPath>Title fallback</textPath><svg viewBox=\"0 0 1 1\"><linearGradient id=\"nested\"></linearGradient></svg></p></title></svg>"); echo PortLibs\Pandoc\Html5Dom::serializeHtmlChildren($body), "\n";'`
  serialized the fallback markup as escaped RCDATA:
  `<svg><title>&lt;p viewBox="html attr"&gt;&lt;textPath&gt;Title fallback&lt;/textPath&gt;&lt;svg viewBox="0 0 1 1"&gt;&lt;linearGradient id="nested"&gt;&lt;/linearGradient&gt;&lt;/svg&gt;&lt;/p&gt;</title></svg>`.
- Final focused DOM family: `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2180 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test` passed with `wordpress-html5-dom-handoff self-test passed`.
- PHP lint passed for `lanes/pandoc/src/XmlHtmlDom.php`, `lanes/pandoc/src/Html5Dom.php`, `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomTest.php`, `lanes/pandoc/tests/XmlHtmlDomTest.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-handoff.php`.
- `jq empty lanes/pandoc/lane-status.json && jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status delta

- `lane-status.json` `phpPass`: `2182 -> 2185`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2596 -> 2597`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 156`.
- Added `mappedXmlHtmlDomSvgTitleIntegrationCases: 1`.

## Dependency closure

No new support component is needed. This reuses native PHP `XmlHtmlDom`, `Html5Dom`, `Html5DomFragment`, and `WordPressBlockWriter` behavior.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external XML/HTML tools, browser renderers, external sanitizers, online services, live provider tests, and live-service provider tests were not run.

## Next task

Choose a non-overlapping XML/HTML5 DOM edge such as another remaining foreign-content integration-point case, a parser-level table insertion-mode repair outside accepted orphan row/cell and section/column wrapping, or another bounded inert metadata handoff.
