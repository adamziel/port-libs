# XML/HTML5 DOM Canvas Fallback Handoff

Date: 2026-06-09 UTC
Lane: pandoc
Micro-slice: pandoc-xml-html5-dom-core-current-base-20260609T032954Z
Base accepted HEAD: 507b06f9840603abbb77bf4b360c0377f959830e

## Behavior

- Treat `canvas` as an active HTML wrapper during fragment sanitization.
- Preserve sanitized fallback children for reviewer-visible WordPress block output.
- Ignore `base` elements under `canvas` when resolving the fragment base URL, so canvas fallback metadata cannot spoof sibling links.
- Drop fallback scripts and strip unsafe fallback URLs while keeping visible fallback text.

This is intentionally bounded to native PHP XML/HTML5 DOM fragment handling. It does not change object/embed source links, iframe srcdoc handling, template/noscript fallback handling, or parameter metadata policy.

## Evidence

- Red-first probe on this base serialized live `<canvas>` wrappers and did not block canvas drawing attributes.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - before: 1 test files, 1861 assertions, 0 failures
  - after: 1 test files, 1883 assertions, 0 failures
  - delta: +1 PHP PASS case, +22 focused assertions
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  - after: 5 test files, 2343 assertions, 0 failures
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - after: wordpress-html5-dom-handoff self-test passed

Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: 2233 -> 2234.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: 2642 -> 2643.
- XML/HTML5 DOM core mapped cases: 8 -> 9.
- XML/HTML5 DOM core focused assertions: 124 -> 146.

## Dependency Closure

No new support component is required. This slice reuses the native PHP DOM loader, `Html5DomFragment` sanitizer/unwrapper path, existing base URL resolver, `WordPressBlockWriter` raw HTML handoff, focused PHP tests, and the existing WordPress HTML5 DOM example.

Full upstream Pandoc HTML-reader runner parity remains a separate upstream-runner dependency task requiring a hydrated pinned checkout and Haskell test executables.

## Non-overlap

Avoided accepted XML/HTML5 DOM clusters for iframe `srcdoc`, safe iframe/object/embed source links, object `param` dropping, noscript/template fallback, raw text fallback containers, image maps, passive meta/link metadata, and HTML5 entity handling. This slice only adds live `canvas` wrapper removal plus inactive canvas-local base handling.

## Next

Choose a non-overlapping XML/HTML5 DOM gap such as remaining passive metadata normalization, dynamic-source handoff, or serializer parity that is not already covered by iframe/object/embed/noscript/template/canvas fallback behavior.
