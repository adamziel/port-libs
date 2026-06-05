# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T043837Z`

Base accepted HEAD: `6aaf0f620e0a4ee5fbfffd3a2afb15e30bb56a45`

## Behavior Added

- `Html5DomFragment` now unwraps visible legacy form containers before
  WordPress raw HTML handoff.
- `form`, `button`, `select`, `option`, `optgroup`, and `textarea` wrappers
  are stripped with diagnostics, while their visible child text/content remains
  available for review.
- Active controls such as `input` remain dropped, and unsafe form-side
  attributes such as JavaScript `formaction` values do not survive because the
  wrapper element is not emitted.
- The WordPress HTML5 DOM smoke now proves legacy form review text reaches the
  HTML block without active form markup.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  recovered HTML fragments must be safe and deterministic before being exposed
  as raw HTML AST nodes or WordPress HTML blocks.
- The current-base gap was observable in focused tests: a blocked `form`
  dropped the whole subtree, including visible reviewer text from buttons,
  select options, and textareas.
- This is bounded support-library behavior. It is not full HTML5 tree-builder
  parity, a browser sanitizer, CSS/media resource handling, form submission
  support, or XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 246 assertions, 0 failures`.
- Red-first focused run after adding expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 115 assertions, 1 failures` because the actual
    serialization was `<p>after</p>` and all visible form text was dropped.
- This slice adds 1 focused PHP PASS case and 15 XML/HTML DOM assertions.
- Lane status moves `phpPass` `626 -> 627`; manifest mapped checks move
  `1100 -> 1101`.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: both decoded successfully.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 129 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 261 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7093 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
DOM/libxml-backed `Html5DomFragment` sanitizer and serializer. It did not
invoke Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word,
LibreOffice, office tools, tar, zip/unzip, lz4, external template engines,
TeX/PDF engines, browser renderers, browser layout engines, media players,
MathJax, KaTeX, roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, HTML5 boolean attributes, SVG/MathML
foreign-content casing, integration-point casing, `srcset` URL filtering,
`srcset` descriptor normalization, media fetch URL filtering, extended URL
attribute filtering, `ping` side-effect filtering, charset/Unicode width
handling, Markdown/HTML reader AST coverage, ZIP/OPC package behavior,
DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF handoff,
BibTeX/CSL, YAML, table geometry, or legacy DOC/CFB work. It owns only
visible legacy form-content preservation inside the sanitized HTML fragment
handoff.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade/media
resource handling, EPUB/XHTML package resource resolution, native
XHTML-to-AST conversion, and actual form semantics as separate bounded slices.
