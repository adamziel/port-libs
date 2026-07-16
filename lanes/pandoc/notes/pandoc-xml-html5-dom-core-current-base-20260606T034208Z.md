# Pandoc XML/HTML5 DOM Core Current Base - MathML Text Integration Points

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T034208Z`

Base accepted HEAD: `24c52a21c864b6f386083d32c7a119569cc95770`

## Implementation

- `XmlHtmlDom` and `Html5Dom` now treat MathML `mi`, `mn`, `mo`, `ms`, and
  `mtext` elements as HTML integration points for descendant casing.
- `Html5DomFragment` now leaves foreign-content context for descendants of the
  same MathML token text elements while still re-entering SVG/MathML casing for
  nested foreign descendants.
- Focused tests cover raw DOM summarization, HTML fragment parsing, sanitized
  fragment serialization, and WordPress raw HTML block handoff.
- The WordPress HTML5 DOM handoff example now includes a MathML `mtext`
  descendant that should serialize with lowercase HTML names/attributes while
  its nested SVG keeps foreign-content casing.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract plus the
HTML Standard MathML text integration-point behavior for `mi`, `mn`, `mo`,
`ms`, and `mtext`:
`https://html.spec.whatwg.org/#mathml-text-integration-point`.

This is bounded native PHP support-library behavior for Pandoc-reader review
handoff. It is not full HTML5 tree-builder parity, browser sanitizer parity,
CSS/media loading, XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline DOM family check before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 790 assertions, 0 failures`.
- Red-first after adding the focused cases:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 797 assertions, 3 failures`.
  - Failures: `viewBox` and `textPath` descendants under MathML `mtext` still
    used foreign-content casing instead of HTML lowercase casing.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 825 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1631` -> `1632`.
- `phpPass`: `1181` -> `1184`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `70`.
- DOM family coverage: `67` -> `70` PASS cases and `790` -> `825`
  assertions.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/Html5Dom.php`
- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/Html5DomTest.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `XmlHtmlDom`,
`Html5Dom`, `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml
parser paths, and existing lane-local manifest and status machinery.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty executable dependency closure.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
complete HTML document doctype preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
`foreignObject` integration-point casing, `annotation-xml` HTML integration,
foreign-content CDATA normalization, URL/srcset filtering, data-image
handling, base URL resolution, inactive fallback base isolation, SVG resource
filtering, SVG presentation resource URL filtering, form/embed/noscript/
template fallback unwrapping, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, explicit
input label preservation, meta refresh link handoff, or SVG CSS resource URL
escape handling.

It owns only bounded MathML token text integration-point descendant casing for
`mi`, `mn`, `mo`, `ms`, and `mtext`.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade/media
resource handling, XHTML-to-AST conversion, and full upstream Haskell runner
dependency closure as separate bounded slices.
