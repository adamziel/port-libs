# Pandoc XML/HTML5 DOM Core Current Base - Passive Link Relations

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T065056Z`

Base accepted HEAD: `6f96d2de713278a0b65fd38d292916760b47c0fc`

## Implementation

- `Html5DomFragment` now converts safe passive HTML link metadata for
  `rel="canonical"`, `rel="alternate"`, and `rel="shortlink"` into
  reviewer-visible anchors.
- Link targets are normalized and resolved against trusted base URL metadata
  before WordPress raw HTML handoff.
- Active resource relations such as `stylesheet` and `preload`, plus unsafe
  link targets such as control-separated `javascript:`, remain stripped with
  diagnostics.
- The WordPress HTML5 DOM fragment smoke now proves canonical, alternate, and
  shortlink review metadata survives while active link resources are omitted.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract plus the
existing follow-up gate for broader metadata/link relation handling: recovered
HTML fragments should not execute or fetch active link resources, but passive
source-navigation metadata should remain visible to WordPress reviewers.

This is bounded native PHP support-library behavior for Pandoc-reader review
handoff. It is not full HTML5 tree-builder parity, browser sanitizer parity,
CSS/media resource loading, complete link-relation taxonomy support,
XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline focused fragment check before adding this case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 593 assertions, 0 failures`.
- Red-first after adding the focused relation case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 594 assertions, 1 failures`.
  - Failure: safe passive link metadata was dropped with the active `link`
    tags, leaving only `<p>after</p>`.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 611 assertions, 0 failures`.
- XML/HTML DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 884 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1675` -> `1676`.
- `phpPass`: `1232` -> `1233`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `88`.
- Focused `Html5DomFragmentTest.php`: `593` -> `611` assertions.
- Focused XML/HTML DOM family: `866` -> `884` assertions.

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

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml parser paths,
trusted base URL resolution, the HTML5 DOM fragment example, and lane-local
manifest/status machinery.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty executable dependency closure.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
complete HTML document doctype preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
`foreignObject` integration-point casing, `annotation-xml` HTML integration,
MathML token text integration, foreign-content CDATA normalization,
URL/srcset filtering, data-image handling, base URL resolution, inactive
fallback base isolation, SVG resource filtering, SVG presentation resource URL
filtering, generic form/embed/noscript/template fallback unwrapping, explicit
input button and select-label preservation, table foster-parenting, XML
namespace serialization, obsolete media URL attributes, picture-source
pruning, meta refresh link handoff, or SVG CSS resource URL escape handling.

It owns only bounded passive `link` relation review handoff for safe
canonical, alternate, and shortlink metadata.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade and
media resource handling, link relation families beyond bounded
canonical/alternate/shortlink review metadata, XHTML-to-AST conversion, and
full upstream Haskell runner dependency closure as separate bounded slices.
