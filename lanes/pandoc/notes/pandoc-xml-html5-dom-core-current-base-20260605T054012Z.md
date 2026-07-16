# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T054012Z`

Base accepted HEAD: `c243ffed38bf9ac26b8935ac6b66c7d9fd11f2ac`

## Behavior Added

- `XmlHtmlDom::protectHtmlRcdataElements()` now also protects bounded obsolete
  HTML raw-text fallback bodies for `xmp`, `noembed`, and `noframes`.
- Those bodies preserve tag-looking source as escaped reviewer text before
  libxml can reinterpret it as child DOM markup.
- Raw-text character references remain literal source text for those obsolete
  containers, so `&amp;` is handed off as reviewer-visible `&amp;` rather than
  silently decoded.
- `Html5DomFragment` now unwraps `xmp`, `noembed`, and `noframes` during safe
  HTML fragment handoff, preserving visible fallback text while preventing the
  obsolete wrapper tags from reaching WordPress raw HTML blocks.
- The WordPress HTML5 DOM smoke now proves legacy `xmp` source text reaches the
  review block escaped and without retaining the obsolete wrapper.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract: bounded
  HTML fragments must be recovered into deterministic review packets without
  turning source text into active markup before WordPress raw HTML handoff.
- HTML `xmp`, `noembed`, and `noframes` are obsolete raw-text/fallback
  containers whose tag-looking bodies should not become active child markup in
  the native support layer.
- This is not full HTML5 tree-builder parity, full plaintext-state support, a
  browser sanitizer, CSS/media resource handling, or XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 288 assertions, 0 failures`.
- Pre-edit probes showed the gap: `xmp`, `noembed`, and `noframes` wrappers
  survived serialization, and tag-looking fallback text was represented as
  parsed child markup instead of escaped source text.
- First focused run after implementation caught a diagnostic assertion issue:
  - Result: `3 test files, 317 assertions, 1 failures`.
  - Cause: libxml emits repair diagnostics for some obsolete fallback tags
    before the sanitizer records the three `blocked-tag` policy diagnostics.
- Final focused verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 331 assertions, 0 failures`.
- This slice adds 3 focused PHP PASS cases and 43 XML/HTML DOM assertions.
- Lane status moves `phpPass` `658 -> 661`; manifest mapped checks move
  `1136 -> 1139`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7653 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with bounded pre-parser raw-text protection plus the existing fragment
sanitizer and WordPress raw HTML writer handoff. It did not invoke Pandoc,
Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice,
office tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, media players, MathJax, KaTeX,
roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, RCDATA handling for
`title`/`textarea`, raw text `script`/`style` serialization, HTML5 void/boolean
attribute serialization, SVG/MathML foreign-content casing,
integration-point casing, `srcset` URL filtering, `srcset` descriptor
normalization, media fetch URL filtering, extended URL attribute filtering,
`ping` side-effect filtering, visible form wrapper unwrapping,
charset/Unicode width handling, Markdown/HTML reader AST coverage, ZIP/OPC
package behavior, DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF
handoff, BibTeX/CSL, YAML, table geometry, or legacy DOC/CFB work. It owns
only bounded obsolete raw-text fallback handling for `xmp`, `noembed`, and
`noframes` in the XML/HTML5 DOM support layer.

## Follow-Up

Keep full plaintext-state support, complete HTML5 tree-builder parity, richer
sanitizer policy, CSS cascade/media resource handling, EPUB/XHTML package
resource resolution, and native XHTML-to-AST conversion as separate bounded
slices.
