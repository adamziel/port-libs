# Pandoc XML/HTML5 DOM Core Current Base - SVG CSS Resource URLs

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T023830Z`

Base accepted HEAD: `a08a790df779c22c7dd550d004ffff8f6c2232ce`

## Implementation

- `Html5DomFragment` now decodes bounded CSS escapes inside SVG presentation
  `url(...)` resource attributes before URL policy checks.
- Safe decoded local references such as escaped `#clip` and relative resources
  such as escaped `./mask.svg#review-mask` are preserved and still avoid
  accidental base expansion for fragment-local references.
- Escaped active schemes such as `javascript:` and `mailto:` are stripped from
  SVG presentation resource attributes, and comment-obfuscated URL tokens are
  rejected instead of being treated as relative paths.
- The WordPress HTML5 DOM fragment smoke now includes an obfuscated SVG marker
  resource and confirms it is absent from raw HTML block handoff output.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract: recovered
HTML fragments handed to WordPress review blocks may preserve SVG presentation
resource references, but they must not retain active or obfuscated fetch
schemes. This is bounded native PHP sanitizer behavior for Pandoc-reader support.
It is not full CSS parsing, full CSS cascade/media loading, browser sanitizer
parity, XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline focused fragment check before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 522 assertions, 0 failures`.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 536 assertions, 0 failures`.
- XML/HTML DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 790 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1608` -> `1609`.
- `phpPass`: `1158` -> `1159`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `49`.
- Focused `Html5DomFragmentTest.php`: `522` -> `536` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, trusted base URL resolution, and existing lane-local manifest and
status machinery.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and Haskell Tasty executable dependency closure.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML/CSS tool, online sanitizer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
complete HTML document doctype preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
integration-point casing, CDATA normalization, URL/srcset filtering, data-image
handling, base URL resolution, inactive fallback base isolation, SVG resource
filtering, plain SVG presentation resource URL filtering, form/embed/noscript/
template fallback unwrapping, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, explicit
input label preservation, or meta refresh link handoff. It owns only bounded
CSS escape/comment handling inside SVG presentation resource `url(...)` tokens.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, full CSS parsing
and cascade/media resource loading, XHTML-to-AST conversion, and full upstream
Haskell runner dependency closure as separate bounded slices.
