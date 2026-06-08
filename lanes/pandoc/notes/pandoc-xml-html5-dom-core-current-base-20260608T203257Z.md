# pandoc-xml-html5-dom-core-current-base-20260608T203257Z

## Scope

Implemented one bounded XML/HTML5 DOM sanitizer support cluster on accepted base
`bb37a42dff2002404bb134df44da31542c787c36`: semantic machine-readable value
attributes on HTML `<data>`, `<meter>`, and `<progress>` are now converted into
inert reviewer metadata before WordPress raw HTML handoff.

The slice preserves valid bounded values as:

- `data-pandoc-data-value`
- `data-pandoc-meter-value`, `data-pandoc-meter-min`, `data-pandoc-meter-max`,
  `data-pandoc-meter-low`, `data-pandoc-meter-high`,
  `data-pandoc-meter-optimum`
- `data-pandoc-progress-value`, `data-pandoc-progress-max`

Invalid values, source-owned `data-pandoc-*` spoofing, and live
`value`/`min`/`max`/`low`/`high`/`optimum` attributes are stripped from the
serialized review HTML with diagnostics.

## Source Truth

This is a native PHP support-library behavior needed by HTML fragment handoff.
It maps the HTML5 semantic value attributes into Pandoc-lane review metadata
without invoking Pandoc, Haskell test binaries, browser renderers, external
XML/HTML tools, online sanitizers, or online services.

## Verification

No active lane rework note existed for this pandoc slice:

`ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`

Baseline focused test before the new case:

`php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`

Result: `1 test files, 1437 assertions, 0 failures`.

Red-first focused test after adding the case and before implementation:

`php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`

Result: `1 test files, 1438 assertions, 1 failures`. The failure showed
`value`, `min`, `max`, `low`, `high`, and `optimum` still serialized as live
attributes.

Final focused test after implementation:

`php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`

Result: `1 test files, 1464 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`

Result: `html5 dom fragment handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Counters

- `lane-status.json` `phpPass`: `1816 -> 1817`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2240 -> 2241`
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`
- `xmlHtmlDomCoreAssertions`: `124 -> 151`
- Focused assertion delta in `Html5DomFragmentTest.php`: `+27`

## Dependency Closure

No new support component is needed. This reuses the existing native
`Html5DomFragment` sanitizer, `WordPressBlockWriter` raw HTML handoff, focused
PHP tests, and the lane-local WordPress HTML5 DOM handoff example.

## Non-Overlap

This slice does not repeat recently accepted XML/HTML5 DOM clusters for unsafe
XML/DTD rejection, RCDATA/plaintext unwrap, SVG/MathML/CDATA handling, URL and
srcset filtering, safe raster data images, base/link/meta/title metadata,
iframe policy metadata, form control fallback text, image maps, details/dialog/
popover, editing/translate/revision/time/language metadata, figure metadata,
shadowroot/slot/custom element metadata, passive link relations, ARIA metadata,
or reserved `data-pandoc-*` filtering. It is limited to semantic value metadata
for `<data>`, `<meter>`, and `<progress>`.

## Follow-Up

A useful next XML/HTML5 DOM slice would be another non-overlapping native
sanitizer gap such as bounded media timed-text handoff, additional passive form
metadata, or parser-level HTML reader integration. Do not execute Pandoc,
Cabal/Haskell runners, browser renderers, external XML/HTML tools, online
sanitizers, online services, live provider tests, or live-service provider
tests from this lane.
