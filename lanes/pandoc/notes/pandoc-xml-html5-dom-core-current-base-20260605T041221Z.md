# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T041221Z`

Base accepted HEAD: `d3bc324411ba9f0b346899b5ee4492f6e49d34eb`

## Behavior Added

- Extended `Html5DomFragment` sanitizer URL policy beyond `href`, `src`,
  `poster`, `cite`, `xlink:href`, and `srcset`.
- Browser-side `ping` side-effect attributes are now blocked before raw HTML
  is handed to WordPress review blocks.
- Form/fetch/resource attributes including `action`, `formaction`,
  `background`, `longdesc`, `data`, `codebase`, `manifest`, and `profile`
  are now URL-checked.
- Safe relative and HTTP(S) values survive; unsafe `javascript:` values and
  non-fetch `mailto:`/`tel:` values on fetch-side attributes are stripped with
  diagnostics.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  recovered HTML fragments must be safe and deterministic before they are
  exposed as raw HTML AST nodes or WordPress HTML blocks.
- This is bounded support-library work for richer Pandoc reader handoff. It
  does not attempt full HTML5 tree-builder parity, browser sanitization parity,
  CSS cascade/media loading, or XHTML-to-AST conversion.
- No pinned Pandoc checkout is hydrated under
  `/home/claude/port-libs/.upstream-cache/pandoc`; this slice did not run
  Pandoc, Cabal, Haskell test binaries, browser renderers, online sanitizers,
  or online services.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 103 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 235 assertions, 0 failures`.
- Red-first focused run after adding expectations failed:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 104 assertions, 1 failures` because `ping`,
    `formaction`, unsafe `longdesc`, and unsafe `background` survived
    serialization.
- This slice adds 1 focused PHP PASS case and 11 XML/HTML DOM assertions.
- Lane status moves `phpPass` `608 -> 609`; manifest mapped checks move
  `1082 -> 1083`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 114 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 246 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
DOM/libxml-backed fragment sanitizer and extends its bounded attribute policy.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, HTML5 boolean attributes, SVG/MathML
foreign-content casing, integration-point casing, `srcset` URL filtering,
`srcset` descriptor normalization, charset/Unicode width handling,
Markdown/HTML reader AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB
readers, archive compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table
geometry, or legacy DOC/CFB work. It owns only the extended URL-attribute and
`ping` side-effect filtering boundary in sanitized HTML fragment handoff.

## Follow-Up

Keep full HTML5 tree-builder parity, richer context-aware URL policy for
EPUB/XHTML package resources, CSS cascade/media handling, and full
XHTML-to-AST conversion as separate bounded slices.
