# XML/HTML5 DOM Button Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T045508Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T045508Z`
Base: `c3bf972961f45c846e53615bea5404a4baeec820`

## Scope

This slice converts stripped HTML `<button>` controls into inert reviewer
metadata spans before WordPress raw HTML handoff. Live button controls still
stay blocked, but bounded submit/reset/form metadata is preserved as
`data-pandoc-button-*` attributes on safe `<span>` nodes:

- default and explicit `type`;
- `name`, `value`, and `form`;
- safe, base-resolved `formaction`;
- valid `formmethod`, `formenctype`, and `formtarget`;
- boolean `formnovalidate` and `disabled`.

Unsafe or spoofed button metadata remains diagnostic-only, including reserved
source-owned `data-pandoc-button-*`, invalid button types, invalid form method
or enctype values, unsafe action URLs, and unsafe name/target tokens.

## Non-Overlap

This does not repeat accepted XML DTD/entity rejection, foreign-content casing,
HTML5 void serialization, generic URL diagnostic source lines, image
resource-policy metadata, `srcset` preflight, portal/iframe/source metadata,
form/fieldset/datalist/value/output metadata, popover state metadata, or OPC
relationship/package semantics. The new behavior is only inert metadata
handoff for stripped button controls.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused command before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1952 assertions, 0 failures`.
- Red-first focused command after adding the button-metadata expectation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` failed with `1 test files, 1953 assertions, 1 failures` because button submit metadata serialized as plain text rather than inert `data-pandoc-button-*` spans.
- Focused command after implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 2001 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php` passed with `4 test files, 2404 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Lane diff whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2333` to `2334`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2729` to `2730`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `mappedXmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `173`.
- Focused `Html5DomFragmentTest.php`: accepted baseline `1952` assertions to final `2001` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `Html5DomFragment`
parsing, existing URL/base/form metadata helpers, `WordPressBlockWriter` raw
HTML blocks, and the existing WordPress HTML5 DOM fragment example. Full
upstream Pandoc HTML reader runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, tar, gzip, lz4, external converter, TeX/PDF engine,
browser renderer, online service, live provider test, or live-service provider
test was run.

## Next Task

A useful follow-up is a non-overlapping metadata slice for semantic/microdata
or RDFa reviewer attributes, or richer HTML reader AST projection. Keep full
HTML5 tree-builder parity, browser sanitizer parity, CSS/media resource
loading, and external converter execution out of this bounded support-library
lane.
