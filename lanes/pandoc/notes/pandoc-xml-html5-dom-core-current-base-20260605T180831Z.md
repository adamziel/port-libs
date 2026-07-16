# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T180831Z`

Base accepted HEAD: `326cb32be0e29897c91ef4b3b31f5f8ebbc605c6`

## Behavior Added

- `Html5Dom::parseHtmlDocument()` now allows only a single simple HTML doctype
  for complete HTML document parsing.
- External `SYSTEM` and `PUBLIC` doctypes are rejected before libxml loading,
  including local-file and remote DTD references.
- Non-HTML doctypes and duplicate doctypes are also rejected before parser
  repair can normalize or ignore them.
- Simple `<!doctype html>` complete documents still parse and serialize through
  the existing HTML-reader handoff path.
- The WordPress HTML5 DOM handoff smoke now verifies the same complete-document
  preflight boundary without invoking Pandoc, browser renderers, online
  sanitizers, or external conversion services.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
document-reader inputs: native PHP DOM/libxml parsing must stay network-disabled
and must not admit external or non-HTML doctype declarations as complete HTML
review packets. This is bounded preflight behavior for complete HTML documents.
It is not full HTML5 tree-builder parity, browser sanitizer parity, external
DTD validation, CSS/media resource handling, XHTML-to-AST conversion, or
upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: `1 test files, 110 assertions, 0 failures`.
- Red-first run after adding the new expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: failed with `1 test files, 113 assertions, 1 failures` because
    an external `SYSTEM` doctype was accepted.
- Green focused run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: `1 test files, 116 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- This slice adds 1 focused PHP PASS case and 6 XML/HTML DOM assertions.
- Lane status moves `phpPass` `1030 -> 1031`; manifest mapped checks move
  `1482 -> 1483`.

## Verification

Final verification is recorded in the worker final response:

- `php -l` for changed PHP files.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
- JSON validation for lane status and manifest.
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with network access disabled, `Html5Dom`, `Html5DomFragment`, `AstNode`,
`WordPressBlockWriter`, and lane-local manifest/status machinery. It did not
invoke Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word,
LibreOffice, office tools, tar, zip/unzip, lz4, external template engines,
TeX/PDF engines, browser renderers, browser layout engines, JavaScript, CSS
engines, media players, MathJax, KaTeX, roff, Typst, online sanitizers, or
online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, simple complete
HTML doctype parsing, internal-subset DTD/entity rejection, raw text
`script`/`style` serialization, RCDATA handling for `title`/`textarea`,
obsolete raw-text fallback handling, plaintext-state source protection,
HTML5 void/boolean attribute serialization, SVG/MathML foreign-content casing,
integration-point casing, URL/srcset filtering, base URL resolution, SVG
resource URL filtering, SVG presentation resource filtering,
comment-boundary-safe serialization, visible form/embed/noscript/template
unwrapping, table foster-parenting, XML namespace binding preservation,
charset/Unicode width handling, Markdown/HTML reader AST coverage,
ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive compression,
math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, syntax highlighting,
or legacy DOC/CFB work. It owns only bounded external/non-HTML/duplicate
doctype rejection for complete HTML document parsing.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, CSS cascade and
media resource handling, XHTML-to-AST conversion, malformed/incremental parser
recovery, and upstream Pandoc runner dependency closure as separate bounded
slices.
