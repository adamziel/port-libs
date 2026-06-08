## pandoc-xml-html5-dom-core-current-base-20260608T055636Z

Accepted base: `01048f98727ca2e231e798c72d6a8093d9f4eefd`

Scope: bounded native HTML5 DOM sanitizer support for standard document
metadata before Pandoc-style Raw HTML AST and WordPress block handoff.

Source truth:

- WHATWG HTML Standard standard metadata names define `application-name`,
  `theme-color`, and `color-scheme`, with `theme-color` carrying a CSS color
  and optional `media` query metadata:
  <https://html.spec.whatwg.org/multipage/semantics.html#standard-metadata-names>
- No local Pandoc upstream checkout was present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so this slice stayed in the
  native support-library contract and did not run Pandoc, Cabal/Haskell
  runners, browser renderers, external XML/HTML tools, online sanitizers, or
  online services.

Implementation:

- `Html5DomFragment` now preserves safe `meta name=application-name`,
  `meta name=theme-color`, and `meta name=color-scheme` values as inert
  reviewer spans.
- `theme-color` content is bounded to safe color tokens/functions already
  accepted by the review-style sanitizer, and unsafe color content is dropped
  with an `unsafe-attribute` diagnostic.
- `theme-color` `media` metadata is preserved only when it passes the same
  bounded CSS token guard; unsafe media is omitted while the safe color remains
  reviewable.
- `color-scheme` is normalized to deduplicated `normal`, `light`, `dark`, and
  `only` tokens, while unsupported tokens are removed with diagnostics.
- The WordPress HTML5 DOM handoff smoke now includes the new metadata spans and
  confirms unsafe source metadata stays out of the block output.

Verification:

- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1143 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/pandoc/src/Html5DomFragment.php`
  passed.
- PHP lint:
  `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed.
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
  passed.
- Focused check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1164 assertions, 0 failures`.
- Adjacent DOM check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1452 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.
- `git diff --check -- lanes/pandoc` passed.

Status delta:

- `+1` PHP PASS case.
- `+21` focused assertions for `Html5DomFragmentTest.php`.
- Manifest mapped denominator moves from `1967` to `1968`.
- XML/HTML5 DOM mapped core cases move from `7` to `8`.

Dependency closure:

No new support component is needed. The slice reuses the existing native
`Html5DomFragment` sanitizer, bounded style-value guard, metadata diagnostics,
Raw HTML AST handoff, focused DOM tests, and WordPress HTML5 DOM handoff
example.

Root harness: not run - isolated micro-slice.
