# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T110526Z`

Base accepted HEAD: `4d4145c84343a3b3d02a26c922d711205e8e3014`

## Behavior Added

- Added bounded complete-HTML document preflight to `Html5Dom::parseHtmlDocument()`.
- Simple doctype-bearing HTML documents still parse for HTML reader and WordPress review handoff.
- Complete HTML inputs with internal DTD subsets, standalone DTD/entity declarations, processing instructions, or NUL bytes now fail before libxml can repair or reinterpret them.
- The WordPress HTML5 DOM handoff smoke now proves safe complete-document parsing and unsafe complete-document rejection alongside the existing fragment sanitizer checks.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract: complete HTML reader inputs may carry normal HTML doctypes, but importer handoff must not admit DTD/entity or processing-instruction inputs that can hide external processing or unsafe repair behavior.
- Pre-edit behavior probe showed `Html5Dom::parseHtmlDocument()` loaded an internal-entity doctype document and a processing-instruction document instead of rejecting them:
  - `loaded:html:]>&reviewer;`
  - `loaded:html:bad`
- This slice is bounded preflight behavior. It is not full HTML5 tree-builder parity, XHTML-to-AST conversion, CSS/media resource handling, browser sanitization parity, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc` rework note was present under `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: `1 test files, 90 assertions, 0 failures`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 527 assertions, 0 failures`.
- First focused run after implementation caught one expectation mismatch in `normalizedText()` spacing, then the corrected focused verification passed.
- This slice adds 1 focused PHP PASS case and 10 XML/HTML DOM assertions.
- Focused XML/HTML DOM family now passes 52 cases with 537 assertions.
- Lane status moves `phpPass` `855 -> 856`; manifest mapped checks move `1313 -> 1314`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: `1 test files, 100 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 537 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- PHP lint, JSON validation, and `git diff --check -- lanes/pandoc` are recorded in this handoff's final verification.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
the existing `Html5Dom`, `Html5DomFragment`, `AstNode`, and
`WordPressBlockWriter` support boundary, with network access disabled during
libxml parsing. It did not invoke Pandoc, Cabal, Haskell test binaries,
citeproc, BibTeX, Biber, Word, LibreOffice, office tools, tar, zip/unzip, lz4,
external template engines, TeX/PDF engines, browser renderers, browser layout
engines, media players, MathJax, KaTeX, roff, Typst, online sanitizers, or
online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection for XML documents
or fragments, HTML fragment declaration preflight, XML processing-instruction
rejection, raw text `script`/`style` serialization, RCDATA handling for
`title`/`textarea`, obsolete raw-text fallback handling, plaintext-state source
protection, HTML5 void/boolean attribute serialization, SVG/MathML
foreign-content casing, integration-point casing, URL/srcset filtering, base
URL resolution, comment-boundary-safe serialization, visible form/embed/
noscript/template unwrapping, table foster-parenting, charset/Unicode width
handling, Markdown/HTML reader AST coverage, ZIP/OPC package behavior,
DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF handoff,
BibTeX/CSL, YAML, table geometry, syntax highlighting, or legacy DOC/CFB work.
It owns only complete HTML document unsafe-declaration preflight in the
XML/HTML5 DOM support layer.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS/media
resource handling, EPUB/XHTML package resource resolution, native XHTML-to-AST
conversion, and upstream Pandoc runner dependency closure as separate bounded
slices.
