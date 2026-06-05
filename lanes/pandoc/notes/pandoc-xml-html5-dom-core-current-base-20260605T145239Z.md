# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T145239Z`

Base accepted HEAD: `44170a629757d61b851ec8fee38b7d6611784378`

## Behavior Added

- `Html5DomFragment` now unwraps `iframe srcdoc` HTML through the existing safe
  fragment sanitizer before WordPress raw HTML block handoff.
- The active `iframe` wrapper and `srcdoc` attribute are stripped, while the
  embedded review document remains visible.
- Nested `srcdoc` `<base href>` values resolve relative URLs against the
  embedding fragment base URL, then the base element itself is dropped.
- Nested active scripts and unsafe URLs inside `srcdoc` remain filtered by the
  existing policy.
- The WordPress HTML5 DOM fragment smoke now proves `srcdoc` review content is
  retained without invoking Pandoc, browser renderers, JavaScript, online
  sanitizers, or external converters.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
  raw HTML fragment handoff: bounded HTML reader/review fragments should keep
  reviewer-visible source content while dropping active browser containers.
- `iframe srcdoc` carries inline document content that would otherwise be lost
  when the `iframe` wrapper is removed.
- This slice is bounded `srcdoc` sanitizer handoff behavior. It is not full
  HTML5 tree-builder parity, nested browsing-context emulation, CSS/media
  loading, generic sanitizer parity, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 372 assertions, 0 failures`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 598 assertions, 0 failures`.
- Red-first run after adding the new expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: failed because expected sanitized embedded `srcdoc` HTML was absent;
    actual output was only `<p>after</p>`.
- This slice adds 1 focused PHP PASS case and 20 XML/HTML DOM assertions.
- Lane status moves `phpPass` `956 -> 957`; manifest mapped checks move
  `1411 -> 1412`.

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
media players, MathJax, KaTeX, roff, Typst, online sanitizers, or online
services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, complete HTML document unsafe-declaration preflight,
HTML fragment declaration preflight, raw text `script`/`style` serialization,
RCDATA handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, URL/srcset
filtering, base URL resolution for the main fragment, SVG local-resource URL
policy, comment-boundary-safe serialization, visible form/embed/noscript/
template unwrapping, table foster-parenting, XML namespace binding
preservation, charset/Unicode width handling, Markdown/HTML reader AST
coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive compression,
math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, syntax highlighting,
or legacy DOC/CFB work. It owns only bounded `iframe srcdoc` HTML handoff inside
the XML/HTML5 DOM sanitizer layer.

## Follow-Up

Keep full HTML5 tree-builder parity, nested browsing-context fidelity, richer
sanitizer policy, CSS/media resource handling, EPUB/XHTML resource resolution,
native XHTML-to-AST conversion, and upstream Pandoc runner dependency closure
as separate bounded slices.
