# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T010629Z`

Base accepted HEAD: `782ec4e5d4eeceff189a4a51f971016f0ae0bd0d`

## Behavior Added

- Extended `Html5DomFragment` `srcset` handling from all-or-nothing URL
  filtering to bounded candidate-level normalization.
- Safe `srcset` candidates now survive when a neighboring candidate is unsafe.
- Width descriptors are normalized to positive integer `w` values, so
  `0640w` becomes `640w`.
- Density descriptors are normalized to positive decimal `x` values, so
  `02.00x` and `1.50X` become `2x` and `1.5x`.
- Unsafe URL candidates, zero-width descriptors, unknown descriptors, and
  mixed descriptor candidates are dropped with diagnostics before WordPress raw
  HTML block handoff.
- The WordPress HTML5 DOM handoff smoke now proves safe responsive-image
  candidates are retained while unsafe or invalid candidates are stripped.

## Source Truth

- Source truth is the lane-local XML/HTML5 DOM support-library contract for
  bounded HTML fragment repair, active-content filtering, unsafe URL filtering,
  deterministic serialization, and WordPress raw HTML handoff.
- This keeps Pandoc HTML/EPUB/DOCX altChunk review paths native PHP and does
  not implement browser-grade image candidate selection, `sizes` evaluation,
  media queries, CSS loading, browser layout, or XHTML-to-AST conversion.

## Evidence

- Pre-slice focused baseline on this worktree was
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` with 6
  PASS cases and 57 assertions.
- This slice adds 1 focused PHP PASS case and 8 assertions in
  `Html5DomFragmentTest.php`.
- Focused `Html5DomFragmentTest.php` now passes 7 cases with 65 assertions.
- Focused XML/HTML DOM family now passes 20 cases with 154 assertions across
  `XmlHtmlDomTest.php`, `Html5DomTest.php`, and `Html5DomFragmentTest.php`.
- Full lane-focused verification now passes 19 test files with 4,994
  assertions and 0 failures.
- Lane status is updated to 489 PHP pass / 0 fail and 962 mapped native checks.

## Verification

- `python3 -m json.tool lanes/pandoc/lane-status.json`
  - Result: passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: passed.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 65 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 154 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 19 test files, 4,994 assertions, 0 failures.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and the
lane-local `Html5DomFragment` sanitizer. It did not invoke Pandoc, Cabal,
Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice, office
tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, MathJax, KaTeX, roff, Typst, online
sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, raw text
`script`/`style` serialization, HTML5 boolean attributes, SVG/MathML
foreign-content casing, prior all-or-nothing `srcset` URL filtering,
Markdown/HTML reader AST coverage, syntax highlighting, EPUB3 package
handoff, DOCX/ODT readers, ZIP/OPC package behavior, table geometry, archive
compression, PDF handoff, math/TeX conversion, charset/Unicode helpers,
BibTeX/CSL, YAML, doctemplate, or legacy DOC/CFB work. It owns only bounded
`srcset` width/density descriptor normalization and candidate-level retention
inside the XML/HTML5 DOM fragment sanitizer.

## Follow-Up

Keep full HTML5 tree-builder parity, browser-grade image candidate selection,
`sizes` and media-query policy, broader sanitizer policy, CSS/media resource
handling, and XHTML-to-AST conversion as separate bounded slices.
