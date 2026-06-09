# XML/HTML5 DOM Select State Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T054800Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T054800Z`
Base: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Scope

This slice maps one bounded XML/HTML5 DOM behavior cluster: stripped HTML
`select` controls now preserve reviewer-relevant state as inert metadata before
WordPress raw HTML block handoff.

`Html5DomFragment` still removes live `select` and `option` elements. It now
adds a safe `span` with `data-pandoc-select-*` attributes for bounded
`name`, `form`, `multiple`, `required`, `disabled`, positive `size`, explicit
selected option labels, and the first default option label for single-select
controls. Option values, reserved source-owned `data-pandoc-select-*`
attributes, and invalid select metadata remain stripped and diagnostic-backed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for unsafe XML/DTD rejection,
HTML5 named-reference decoding, RCDATA/template/plaintext protection, SVG/MathML
foreign-content casing, CDATA normalization, table insertion-mode repair, base
URL/target diagnostics, URL/image/semantic source-line diagnostics, or existing
form/button/datalist metadata. The behavior is only the bounded select-state
review metadata handoff.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Red-first probe before implementation: `php -r` over `Html5DomFragment::fromHtml("<select><option label=One>First<option>Second</select><p>after</p>")` serialized `OneSecond<p>after</p>` with only `blocked-tag` diagnostics, so selected/default select state was not preserved.
- Baseline focused command before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 2114 assertions, 0 failures`.
- Focused command after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 2165 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `4 test files, 2615 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Lane diff whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2399` to `2400`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2790` to `2791`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `mappedXmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `175`.
- Focused assertion delta: `+51` in `Html5DomFragmentTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`Html5DomFragment`, `WordPressBlockWriter`, the focused PHP test runner, and
the existing WordPress HTML5 DOM fragment example. Full upstream Pandoc HTML
reader runner parity remains a separate upstream-runner dependency task
requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF
engine, browser renderer, online service, live provider test, or live-service
provider test was run.

## Next Task

A useful follow-up is a non-overlapping parser-level HTML reader behavior such
as another source-position diagnostic path outside accepted URL/image/base/
semantic metadata, or bounded review metadata for a passive HTML5 construct not
already covered by form, button, datalist, fieldset, or select handling.
