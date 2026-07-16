# Pandoc XML/HTML5 DOM Core Current Base - Meta Refresh Review Links

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T020509Z`

Base accepted HEAD: `0f344fe5e92e069e811b55e3b6740f8331906302`

## Implementation

- `Html5DomFragment` now strips active `meta http-equiv="refresh"` elements
  while preserving safe refresh targets as explicit reviewer links.
- Safe relative refresh targets are resolved against the trusted fragment base
  URL before WordPress raw HTML handoff.
- Unsafe refresh targets such as control-separated `javascript:` URLs are
  stripped with `unsafe-url` diagnostics.
- Passive metadata such as viewport `<meta>` remains omitted from review HTML.
- The WordPress HTML5 DOM fragment smoke now covers safe and unsafe refresh
  metadata without invoking Pandoc, a browser, or online services.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract: recovered
HTML fragments handed to WordPress review blocks must not execute metadata
refreshes, but safe source-navigation targets should remain inspectable for
reviewers. This is bounded native PHP sanitizer behavior. It is not full HTML5
tree-builder parity, browser sanitizer parity, browser navigation semantics,
CSS/media loading, XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline focused fragment check before adding the case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 505 assertions, 0 failures`.
- Red-first after adding the focused case:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 506 assertions, 1 failures`.
  - Failure: the safe refresh target was dropped with the active meta tag.
- Focused verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 522 assertions, 0 failures`.
- XML/HTML DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 776 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1600` -> `1601`.
- `phpPass`: `1150` -> `1151`.
- `xmlHtmlDomCoreCases`: `4` -> `5`.
- `mappedXmlHtmlDomCoreCases`: `4` -> `5`.
- `xmlHtmlDomCoreAssertions`: `35` -> `51`.
- Focused `Html5DomFragmentTest.php`: `505` -> `522` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, trusted base URL resolution, and existing lane-local manifest and
status machinery.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and test executable dependency closure.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, or live provider test
was executed.

## Non-Overlap

This slice does not repeat accepted XML/HTML5 DOM work for DTD/entity
rejection, processing-instruction filtering, XML declaration preflight,
complete HTML document doctype preflight, comment-boundary serialization, raw
text/RCDATA/plaintext handling, SVG/MathML foreign-content casing,
integration-point casing, CDATA normalization, URL/srcset filtering, data-image
handling, base URL resolution, inactive fallback base isolation, SVG resource
filtering, SVG presentation resource URL filtering, form/embed/noscript/
template fallback unwrapping, table foster-parenting, XML namespace
serialization, obsolete media URL attributes, picture-source pruning, or
explicit input label preservation. It owns only bounded meta refresh target
handoff for sanitized HTML fragments.

## Follow-Up

Keep full HTML5 tree-builder parity, broader metadata/link relation handling,
CSS/media resource policy, browser sanitizer parity, XHTML-to-AST conversion,
and full upstream Haskell runner dependency closure as separate bounded slices.
