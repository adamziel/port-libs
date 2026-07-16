# XML/HTML5 DOM Datalist Reviewer Metadata Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T230552Z`
Base accepted HEAD: `10431f580294803a3ec23f7a211f80a2fb3c9659`

## Behavior

- Added native `Html5DomFragment` handling for HTML `<datalist>` suggestion controls.
- Live `datalist` and `option` controls are stripped before raw HTML/WordPress handoff.
- Safe datalist `id` values are preserved as inert `data-pandoc-datalist-id` metadata.
- Safe option labels and visible option text are deduplicated and preserved as inert `data-pandoc-datalist-options` reviewer metadata.
- Source-owned `data-pandoc-datalist-*` attributes and unsafe option labels remain diagnostics-only and are stripped.
- Datalist descendants are treated as inactive for `<base>` URL discovery, so fallback suggestions cannot change trusted reviewer URL resolution.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before work began.
- Upstream cache: no local Pandoc upstream cache was present under `/home/claude/port-libs/.upstream-cache`, so this worker did not cite or execute upstream fixtures.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1645 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1680 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2374 -> 2375`.
- XML/HTML5 DOM core mapped cases: `8 -> 9`.
- XML/HTML5 DOM core focused assertion counter: `124 -> 159`.
- `lane-status.json` `phpPass`: `1953 -> 1954`.

## Dependency Closure

No new support component is needed. The patch reuses native HTML fragment parsing, sanitizer diagnostics, trusted base URL resolution, raw HTML AST handoff, and `WordPressBlockWriter`. No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML parser, online sanitizer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for select/optgroup label fallback, iframe policy metadata, passive link relations, output metadata, form metadata, fieldset metadata, SVG data-image resources, foreign-content CDATA, picture/source pruning, image maps, time/data/meter/progress value metadata, or shadow-root accessibility metadata.

## Follow-Up

Next XML/HTML5 DOM work should target a non-overlapping native fragment parser or serializer gap, such as remaining HTML5 tree-repair edge cases, MathML annotation/source metadata, or tokenizer/entity handling needed by document readers.
