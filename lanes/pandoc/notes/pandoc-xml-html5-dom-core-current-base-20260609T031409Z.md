# Pandoc XML/HTML5 DOM Core Current Base - Object/Embed Source Handoff

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T031409Z`

Base accepted HEAD: `915ae6d7e19462f5fae70630857416b816400e62`

## Implementation

- `Html5DomFragment` now converts safe HTML `object data` and `embed src`
  values into inert reviewer links before WordPress raw HTML handoff.
- Live `<object>` and `<embed>` containers are still stripped.
- Safe object sources emit `data-pandoc-object-data="true"` review links.
- Safe embed sources emit `data-pandoc-embed-src="true"` review links.
- Relative object sources continue to resolve through trusted fragment
  `<base href>` metadata or caller base URLs.
- Control-separated safe embed URLs are normalized before review output.
- Unsafe embedded sources remain hidden with the stripped active container.
- Repaired child content under void `embed` nodes is normalized and returned so
  libxml repair does not drop following reviewer-visible content.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the HTML
`object`/`embed` resource model: imported active embeds should not survive into
WordPress raw HTML, but safe source URLs should remain reviewable as inert links
when the importer can preserve them without executing plugins, browser layout,
or media fetches.

No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, online
service, external XML/HTML tool, Word, LibreOffice, zip/unzip, or external
conversion service was executed.

## Evidence

- Rework notes: no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  note was present before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1812 assertions, 0 failures`.
- Red-first behavior probe before implementation:
  safe `object data="./docs/source.pdf"` and safe
  `embed src="./media/demo.mp4"` serialized as only object fallback text, with
  the embed source dropped.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1834 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  passed with `5 test files, 2294 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  passed with `wordpress-html5-dom-handoff self-test passed`.

## Status Delta

- `lane-status.json` `phpPass`: `2211 -> 2212`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2621 -> 2622`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 146`.
- Added `mappedXmlHtmlDomEmbeddedSourceCases: 1`.
- Focused `Html5DomFragmentTest.php`: `1812 -> 1834` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, existing URL normalization/base-resolution helpers, and the
existing WordPress HTML5 DOM handoff example.

## Non-Overlap

This slice does not repeat DTD/entity rejection, XML declaration preflight,
processing-instruction filtering, comment-boundary serialization, raw text/
RCDATA/plaintext handling, SVG/MathML foreign-content casing,
foreign-content CDATA normalization, iframe srcdoc, iframe source links,
iframe policy metadata, portal source links, form metadata, datalist metadata,
image map links, passive metadata/link relations, base URL/target metadata,
responsive picture/source handling, unsafe URL filtering, table repair, source
line diagnostics, or SVG title/desc integration-point behavior.

It owns only safe `object data` and `embed src` preservation as inert reviewer
links while keeping active embed containers out of WordPress HTML.

## Follow-Up

Keep richer HTML object fallback AST conversion, media attachment extraction,
browser/plugin execution parity, full HTML5 tree-builder parity, XHTML-to-AST
conversion, and upstream Haskell runner dependency closure as separate gates.
