# Pandoc XML/HTML5 DOM Core Current Base - Inline Style Review Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T233824Z`
Base: `eb7d11e9bcd6594ca75065e9ce45b3589c10aa36`

## Behavior Added

`Html5DomFragment` now converts a bounded allowlist of safe inline HTML style
declarations into inert `data-pandoc-style` reviewer metadata before WordPress
raw HTML handoff.

The sanitizer still strips active `style=` attributes. It drops unbounded CSS
properties and rejects declaration values containing CSS `url(...)`,
`expression(...)`, `@import`, `javascript:`, `vbscript:`, `data:`,
`-moz-binding`, comments, or tag-like delimiters. Safe CSS escape sequences are
decoded before review metadata is serialized, so legacy source values such as
escaped `red` and `0.5em` become readable audit metadata without enabling CSS.

## Source Truth And Scope

This is bounded XML/HTML5 DOM support-library behavior for raw HTML review
packets used by Pandoc HTML/EPUB/DOCX altChunk-style import paths. It does not
implement a CSS cascade, CSS parser, browser sanitizer, media loader,
stylesheet fetcher, or XHTML-to-AST conversion.

The work is additive to accepted XML/HTML5 DOM sanitizer slices for DTD/entity
rejection, srcset filtering, passive link relations, meta review handoff,
form/control fallback text, iframe policy metadata, SVG/MathML foreign-content
normalization, SVG resource filtering, table foster parenting, and raw text
fallback unwrapping.

## Evidence

- Rework notes checked: no `port-pandoc-*.needs-lane-rework.md` note was present before editing.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 814 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 830 assertions, 0 failures`.
- XML/HTML DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1103 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- Assertion delta: `+16` focused assertions and `+1` PHP PASS case.
- Manifest delta: mapped denominator `1830 -> 1831`; XML/HTML5 DOM core cases `6 -> 7`; XML/HTML5 DOM core assertions `89 -> 105`.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php` - no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php` - no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php` - no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` - `1 test files, 830 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` - `3 test files, 1103 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` - `html5 dom fragment handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "UPSTREAM_TEST_MANIFEST.json ok\n";'` - `UPSTREAM_TEST_MANIFEST.json ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json ok\n";'` - `lane-status.json ok`.
- `git diff --check -- lanes/pandoc` - passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`Html5DomFragment` CSS declaration filtering, `AstNode` raw HTML handoff,
`WordPressBlockWriter`, DOM/libxml `NONET` parser paths, and existing
lane-local manifest/status records.

Remaining follow-up stays separate: full CSS cascade/application semantics,
CSS media/resource loading, browser sanitizer parity, XHTML-to-AST conversion,
full HTML5 tree-builder parity, full Pandoc runner parity, live provider tests,
and live-service provider tests.
