# Pandoc XML/HTML5 DOM Core Current Base - Social Image Metadata URLs

Slice: `pandoc-xml-html5-dom-core-current-base-20260607T014636Z`
Base: `ee38ac4e40d34d8ace81ef748756b7c6f6cb32f9`

## Behavior Added

`Html5DomFragment` now converts bounded social image metadata URLs into inert
reviewer links before WordPress raw HTML handoff.

- `og:image`, `og:image:url`, `og:image:secure_url`, `twitter:image`, and
  `twitter:image:src` meta tags with safe HTTP(S), root-relative, or
  document-relative content become `<a>` review nodes.
- Safe relative social image URLs are resolved against trusted `<base>` or
  caller base metadata.
- Generated review links carry `data-pandoc-meta-property` or
  `data-pandoc-meta-name`, resolved `data-pandoc-meta-content`, and
  `data-pandoc-meta-url="true"` provenance.
- Prose-looking values such as `content="Open graph image"` remain hidden, and
  unsafe `javascript:` / active `data:` targets are stripped with diagnostics.

The slice keeps social images as reviewer links rather than active `<img>`
loads, so import queues can audit source cards without fetching or rendering
remote media.

## Evidence

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 830 assertions, 0 failures`

Focused check after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 853 assertions, 0 failures`

Status delta:

- `phpPass`: `1430 -> 1431`
- mapped denominator: `1846 -> 1847`
- XML/HTML5 DOM core cases: `6 -> 7`
- XML/HTML5 DOM core assertions: `89 -> 112`
- focused assertions: `+23`

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "UPSTREAM_TEST_MANIFEST.json ok\n";'`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment`
meta/link sanitizer paths, trusted base URL resolution, `AstNode` raw HTML
handoff, `WordPressBlockWriter`, the existing WordPress HTML5 DOM fragment
handoff example, and the focused lane PHP harness.

Full Pandoc HTML reader/tree-builder parity, browser sanitizer parity, remote
media fetching, social-card rendering, Haskell/Cabal runner parity, live
provider tests, and live-service provider tests remain explicitly out of scope
for this isolated support-library slice.

## Non-Overlap

This avoids the accepted XML/HTML5 DOM slices for foreign-content CDATA
normalization, SVG raster data-image resources, select/option label fallback
text, passive canonical/alternate/shortlink/editorial link relations, iframe
policy metadata, source-owned reserved reviewer attribute filtering, and
inline style review metadata.
