# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T013647Z`

Base accepted HEAD: `5c1e831a4cd16b50e19b19a5942fd02353b5a990`

## Behavior Added

- Added HTML5 boolean-attribute serialization to `Html5DomFragment`.
- Sanitized HTML fragments now serialize boolean attributes such as `open`,
  `controls`, `muted`, `playsinline`, and `loop` without redundant `="..."`
  values when the DOM value is empty or equal to the attribute name.
- Normal URL-bearing attributes such as `poster` and `source src` still keep
  escaped values.
- The WordPress HTML5 DOM handoff smoke now proves open disclosure packets and
  muted media controls stay as deterministic raw HTML blocks after sanitizer
  filtering.

## Source Truth

- Source truth is the lane-local XML/HTML5 DOM support-library contract for
  bounded HTML fragment repair, active-content filtering, unsafe URL filtering,
  deterministic HTML5 void/boolean serialization, and WordPress raw HTML
  handoff.
- This keeps Pandoc HTML/EPUB/DOCX altChunk review paths native PHP and does
  not implement full browser tree-builder parity, media playback, CSS cascade,
  `sizes` evaluation, browser layout, or XHTML-to-AST conversion.

## Evidence

- Pre-slice focused baseline on this worktree:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 65 assertions, 0 failures.
- Pre-slice XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 154 assertions, 0 failures.
- This slice adds 1 focused PHP PASS case and 10 assertions in
  `Html5DomFragmentTest.php`.
- Focused `Html5DomFragmentTest.php` now passes 8 cases with 75 assertions.
- Focused XML/HTML DOM family now passes 21 cases with 164 assertions across
  `XmlHtmlDomTest.php`, `Html5DomTest.php`, and `Html5DomFragmentTest.php`.
- Full lane-focused verification now passes 19 test files with 5270 assertions
  and 0 failures.
- Lane status is updated to 506 PHP pass / 0 fail and 981 mapped native checks.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 75 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 164 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 19 test files, 5270 assertions, 0 failures.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `python3 -m json.tool lanes/pandoc/lane-status.json`
  - Result: passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: passed.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and the
lane-local `Html5DomFragment` sanitizer. It did not invoke Pandoc, Cabal,
Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice, office
tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, media players, MathJax, KaTeX,
roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, raw text
`script`/`style` serialization, `XmlHtmlDom` boolean attributes, HTML5 void
element serialization, SVG/MathML foreign-content casing, `srcset` URL
filtering, `srcset` descriptor normalization, Markdown/HTML reader AST
coverage, syntax highlighting, EPUB3 package handoff, DOCX/ODT readers,
ZIP/OPC package behavior, table geometry, archive compression, PDF handoff,
math/TeX conversion, charset/Unicode helpers, BibTeX/CSL, YAML, doctemplate,
or legacy DOC/CFB work. It owns only bounded `Html5DomFragment` boolean
attribute serialization for sanitized HTML review fragments.

## Follow-Up

Keep full HTML5 tree-builder parity, browser-grade media selection, `sizes`
and media-query policy, broader sanitizer policy, CSS/media resource handling,
and XHTML-to-AST conversion as separate bounded slices.
