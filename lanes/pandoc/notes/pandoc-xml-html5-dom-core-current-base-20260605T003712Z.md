# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T003712Z`

Base accepted HEAD: `c4f27d025d3314feed19bb340c482c75f5b35ef6`

## Behavior Added

- Added candidate-aware `srcset` URL filtering to `Html5DomFragment`.
- Safe image candidates using relative, root-relative, and `http` or `https`
  URLs survive raw HTML review handoff.
- Mixed unsafe candidates such as secondary `javascript:` or `mailto:`
  entries cause the whole `srcset` attribute to be removed and reported as an
  `unsafe-url` diagnostic.
- Updated the WordPress HTML5 DOM smoke so imported raw HTML blocks prove that
  unsafe responsive-image candidates are stripped before handoff.

## Source Truth

- Source truth is the lane's accepted XML/HTML5 DOM support-library contract:
  bounded HTML fragment repair, active-content filtering, unsafe URL
  filtering, deterministic serialization, and WordPress raw HTML block handoff.
- This is support-library behavior needed by Pandoc HTML/EPUB/DOCX altChunk
  review paths. It is not full HTML5 image candidate selection, CSS/media
  loading, browser layout, or XHTML-to-AST conversion.

## Evidence

- Red-first check:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed because mixed unsafe `srcset` candidates were retained.
- This slice adds 1 focused PHP PASS case and 5 assertions in
  `Html5DomFragmentTest.php`.
- Focused `Html5DomFragmentTest.php` now passes 6 cases with 57 assertions.
- Focused XML/HTML DOM family now passes 19 cases with 146 assertions across
  `XmlHtmlDomTest.php`, `Html5DomTest.php`, and `Html5DomFragmentTest.php`.
- Lane status is updated to 472 PHP pass / 0 fail and 944 mapped native checks.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 57 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 146 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local
`Html5DomFragment` sanitizer and PHP DOM/libxml parsing only. It did not invoke
Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber, office tools,
archive tools, external template engines, TeX/PDF engines, browser renderers,
online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, raw text
`script`/`style` serialization, HTML5 boolean attributes, SVG/MathML
foreign-content casing, Markdown/HTML reader AST coverage, syntax highlighting,
EPUB3 package handoff, DOCX/ODT readers, ZIP/OPC package behavior, table
geometry, archive compression, PDF handoff, math/TeX conversion,
charset/Unicode helpers, BibTeX/CSL, YAML, doctemplate, or legacy DOC/CFB work.
It owns only the bounded `srcset` candidate URL filtering boundary in the
XML/HTML5 DOM fragment sanitizer.

## Follow-Up

Keep full HTML5 tree-builder parity, `srcset` width/density normalization,
image candidate selection, CSS/media resource policy, broader sanitizer policy,
and XHTML-to-AST conversion as separate bounded slices.
