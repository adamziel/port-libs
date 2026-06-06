# XML/HTML5 DOM Core Current-Base Slice - 2026-06-06

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260606T181522Z`
Accepted base: `1e410cead670f3b7f0a6d59fbda33d24f54995c6`

## Behavior

`Html5DomFragment` now strips source-owned reserved reviewer metadata attributes before WordPress raw HTML handoff:

- `data-pandoc-*` source attributes are rejected everywhere in sanitized HTML fragments.
- HTML-context `xmlns` / `xmlns:*` declarations are rejected so legacy source markup cannot spoof parser or reviewer state.
- Ordinary source-owned `data-*`, `aria-*`, and safe SVG foreign-content namespace declarations remain preserved.
- Internally generated inert reviewer attributes such as `data-pandoc-link-rel`, `data-pandoc-iframe-src`, and `data-pandoc-image-map-area` are still emitted only by sanitizer-created review links/metadata.

This is a bounded support-library behavior for safer HTML fragment handoff. It does not run Pandoc, Cabal, Haskell runners, browser renderers, external XML/HTML tools, online sanitizers, online services, live provider tests, or live-service provider tests.

## Evidence

Red check after adding the focused case:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 743 assertions, 1 failures`
- Failure: source-owned `data-pandoc-*` and HTML namespace declarations were retained in serialized review HTML.

Green focused check after the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 756 assertions, 0 failures`

Status delta:

- `phpPass`: `1382 -> 1383`
- mapped denominator: `1795 -> 1796`
- XML/HTML DOM core cases: `5 -> 6`
- XML/HTML DOM core assertions: `70 -> 84`
- New focused assertions: `+14`

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP `Html5DomFragment` sanitizer, `WordPressBlockWriter` raw HTML handoff, and focused lane test harness. Full upstream Pandoc HTML reader/tree-builder parity, browser sanitizer parity, and XHTML-to-AST normalization remain separate bounded follow-up work.

## Non-Overlap

This avoids the already accepted XML/HTML5 DOM slices for foreign-content CDATA normalization, SVG data-image resources, select/option label fallback text, passive canonical/alternate/shortlink relation handoff, and iframe policy metadata. The slice only owns source-spoofing prevention for reserved reviewer attributes and HTML-context namespace declarations.
