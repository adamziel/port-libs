# XML/HTML5 DOM Output Metadata Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T220946Z`
Base accepted HEAD: `84e13589d21c5af760a67a92ba24763789ab267f`

## Behavior

- Added native `Html5DomFragment` handling for HTML `<output>` calculation metadata.
- Safe `for` token lists are deduplicated and preserved as inert `data-pandoc-output-for`.
- Safe `form` and `name` values are preserved as `data-pandoc-output-form` and `data-pandoc-output-name`.
- Malformed `for`, `form`, and `name` values plus source-owned `data-pandoc-output-*` spoofing remain diagnostics-only and are stripped before WordPress raw HTML handoff.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before work began.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1564 assertions, 0 failures`.
- Red-first: same focused command failed after adding the new case with `1 test files, 1565 assertions, 1 failures` because live `<output for form name>` attributes still serialized.
- Final: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1584 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses native HTML fragment parsing, sanitizer diagnostics, base/raw HTML handoff metadata, and `WordPressBlockWriter`. No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for iframe policy metadata, passive link relations, select/optgroup labels, SVG data-image resources, foreign-content CDATA, quote cite metadata, media tracks, picture/source pruning, image maps, time/data/meter/progress value metadata, or shadow-root accessibility metadata.
