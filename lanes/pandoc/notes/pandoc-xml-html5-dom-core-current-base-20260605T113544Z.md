# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T113544Z`

Base accepted HEAD: `651615e05fea9d010bb9bbcaa297afe05c6cf991`

## Behavior Added

- `Html5DomFragment::fromXml()` now carries XML namespace bindings into its
  normalized fragment nodes before deterministic serialization.
- Prefixed XML elements and prefixed attributes emit the required `xmlns:*`
  binding in the serialized review fragment.
- Default namespace roots emit `xmlns`, and unqualified child elements below a
  default namespace emit `xmlns=""` resets when needed.
- The WordPress XML/HTML DOM handoff example now proves a namespace-bound XML
  review fragment serializes and round-trips through the safe XML fragment
  parser.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for
  native document-reader handoff: XML fragments coming from OOXML, OPF, ODT,
  XHTML, or review packets must remain namespace-well-formed after the safe
  normalized serializer.
- Pre-edit behavior probe:
  - `Html5DomFragment::fromXml('<root xmlns:x="urn:x"><x:item x:role="review" xml:lang="en">A</x:item></root>')->serialize()`
  - Result before the patch: `<root><x:item x:role="review" xml:lang="en">A</x:item></root>`
  - That output retained prefixed names but dropped `xmlns:x`, so it was not a
    namespace-well-formed XML fragment.
- This slice is bounded namespace binding preservation. It is not exact
  original namespace declaration placement, full XHTML-to-AST conversion,
  browser DOM parity, CSS/media handling, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 311 assertions, 0 failures`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 537 assertions, 0 failures`.
- First focused run after implementation caught one fixture mismatch: an
  unprefixed XML `<link>` stayed subject to the existing active HTML `link`
  policy. The test fixture was corrected to prefixed relationship markup.
- This slice adds 1 focused PHP PASS case and 15 XML/HTML DOM assertions.
- Focused XML/HTML DOM family now passes 53 cases with 552 assertions.
- Lane status moves `phpPass` `867 -> 868`; manifest mapped checks move
  `1325 -> 1326`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 326 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 552 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`.
- PHP lint, JSON validation, and `git diff --check -- lanes/pandoc` are recorded
  in this handoff's final verification.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
the existing `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, and
lane-local manifest/status machinery. It did not invoke Pandoc, Cabal, Haskell
test binaries, citeproc, BibTeX, Biber, Word, LibreOffice, office tools, tar,
zip/unzip, lz4, external template engines, TeX/PDF engines, browser renderers,
browser layout engines, media players, MathJax, KaTeX, roff, Typst, online
sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, processing
instruction rejection, complete HTML document unsafe-declaration preflight, HTML
fragment declaration preflight, raw text `script`/`style` serialization, RCDATA
handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, URL/srcset
filtering, base URL resolution, SVG local-resource URL policy,
comment-boundary-safe serialization, visible form/embed/noscript/template
unwrapping, table foster-parenting, charset/Unicode width handling,
Markdown/HTML reader AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB
readers, archive compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table
geometry, syntax highlighting, or legacy DOC/CFB work. It owns only namespace
binding preservation for normalized XML fragment serialization.

## Follow-Up

Keep exact original XML namespace declaration placement, full HTML5 tree-builder
parity, richer sanitizer policy, CSS/media resource handling, EPUB/XHTML package
resource resolution, native XHTML-to-AST conversion, and upstream Pandoc runner
dependency closure as separate bounded slices.
