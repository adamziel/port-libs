# Pandoc XML/HTML5 DOM Core Current Base - Select Label Fallback

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T054217Z`

Base accepted HEAD: `e4ea169e4e976809e607e8fc8164a335a8929b16`

## Implementation

- `Html5DomFragment` now preserves visible `<optgroup label>` and
  `<option label>` text when unwrapping legacy form controls for sanitized raw
  HTML review packets.
- `<option label>` takes precedence over child/submission text, so hidden
  values and fallback submission strings are not exposed in WordPress review
  HTML.
- The WordPress HTML5 DOM handoff smoke now proves labeled select fallback
  text survives while `<form>`, `<select>`, `<optgroup>`, `<option>`, and
  hidden option value text remain stripped.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract plus the
HTML form-control behavior that option and option-group `label` attributes are
visible labels, while form submission values are not reviewer-visible content.

This is bounded native PHP support-library behavior for Pandoc-reader review
handoff. It is not full HTML5 tree-builder parity, browser sanitizer parity,
CSS/media loading, interactive form reconstruction, XHTML-to-AST conversion, or
upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline focused file:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 573 assertions, 0 failures`.
- Baseline XML/HTML DOM family:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 846 assertions, 0 failures`.
- Pre-edit direct behavior probe:
  - `php -r 'require "tools/bootstrap.php"; $fragment = PortLibs\Pandoc\Html5DomFragment::fromHtml("<form><select><optgroup label=Status><option label=Draft></option><option>Final</option></optgroup></select></form>"); echo $fragment->serialize(), "\n";'`
  - Result: `Final`, proving the visible optgroup/option labels were dropped.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 593 assertions, 0 failures`.
- XML/HTML DOM family verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 866 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1662` -> `1663`.
- `phpPass`: `1218` -> `1219`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `90`.
- Focused `Html5DomFragmentTest.php`: `573` -> `593` assertions.
- Focused XML/HTML DOM family: `846` -> `866` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml parser paths,
and existing lane-local manifest and status machinery.

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
input button label preservation, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, meta
refresh link handoff, or SVG CSS resource URL escape handling.

It owns only bounded select fallback label preservation for visible
`optgroup label` and `option label` text during sanitized HTML fragment
handoff.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade and
media handling, form UI semantics beyond visible fallback labels,
XHTML-to-AST conversion, and full upstream Haskell runner dependency closure
as separate bounded slices.
