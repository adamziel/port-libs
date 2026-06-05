# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T170456Z`

Base accepted HEAD: `1fd65111f67f51b2d9aa737f5be6be428c62949a`

## Behavior Added

- `Html5DomFragment` now treats bounded SVG presentation attributes as
  resource URL carriers when sanitizing HTML fragments for WordPress handoff.
- The sanitizer covers `clip-path`, `color-profile`, `cursor`, `fill`,
  `filter`, `marker`, `marker-start`, `marker-mid`, `marker-end`, `mask`, and
  `stroke` when the element is in SVG foreign-content context.
- Local references such as `url(#clip)` and `url(#paint)` stay local and are
  not expanded against the fragment base URL.
- Safe relative resource references such as `url(./mask.svg#review-mask)` are
  resolved against trusted base metadata.
- Unsafe script/mail/phone resource URLs are removed before raw HTML is handed
  to WordPress blocks.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
  raw HTML fragment handoff: bounded HTML reader/review fragments should keep
  reviewer-visible SVG structure while dropping active or unsafe resource
  references.
- The existing accepted SVG resource slice covered `href` and `xlink:href`.
  This slice is additive for FuncIRI-style presentation attributes.
- This is bounded sanitizer support, not full HTML5 tree-builder parity, CSS
  cascade evaluation, complete SVG presentation-attribute grammar, browser
  layout, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 392 assertions, 0 failures`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 618 assertions, 0 failures`.
- Red-first run after adding the new expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: failed with `1 test files, 393 assertions, 1 failures` because
    unsafe SVG `filter`, `marker-start`, and `stroke` resource URLs survived.
- Green focused run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 410 assertions, 0 failures`.
- This slice adds 1 focused PHP PASS case and 18 XML/HTML DOM assertions.
- Lane status moves `phpPass` `1012 -> 1013`; manifest mapped checks move
  `1466 -> 1467`.

## Verification

Final verification is recorded in the worker final response:

- `php -l` for changed PHP files.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- JSON validation for lane status and manifest.
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with network access disabled, the existing `Html5DomFragment`, `AstNode`, and
`WordPressBlockWriter` support boundary, and lane-local manifest/status
machinery. It did not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
BibTeX, Biber, Word, LibreOffice, office tools, tar, zip/unzip, lz4, external
template engines, TeX/PDF engines, browser renderers, browser layout engines,
JavaScript, CSS engines, media players, MathJax, KaTeX, roff, Typst, online
sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, complete HTML document unsafe-declaration preflight,
HTML fragment declaration preflight, raw text `script`/`style` serialization,
RCDATA handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, `href`/`xlink:href`
SVG resource filtering, URL/srcset filtering, base URL resolution for normal
HTML links, SVG local-resource `href` preservation, comment-boundary-safe
serialization, visible form/embed/noscript/template unwrapping, table
foster-parenting, XML namespace binding preservation, charset/Unicode width
handling, Markdown/HTML reader AST coverage, ZIP/OPC package behavior,
DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF handoff,
BibTeX/CSL, YAML, table geometry, syntax highlighting, or legacy DOC/CFB work.
It owns only bounded SVG presentation-resource attribute URL handling inside
the XML/HTML5 DOM sanitizer layer.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, SVG
style-attribute parsing, CSS cascade/media resource handling, complete SVG
presentation-attribute grammar, XHTML-to-AST conversion, and upstream Pandoc
runner dependency closure as separate bounded slices.
