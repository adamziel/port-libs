# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T173513Z`

Base accepted HEAD: `bcb14c0948d0135ec9c2e5e7666c4d8e81594f15`

## Behavior Added

- `XmlHtmlDom::protectHtmlRcdataElements()` now normalizes literal
  `<![CDATA[...]]>` sections outside raw-text/RCDATA elements before libxml's
  HTML parser sees them.
- SVG and MathML foreign-content CDATA review text now survives as text instead
  of being truncated to the tail or dropped by libxml repair.
- The deterministic HTML serializer still emits escaped text, so tag-looking
  CDATA source such as `<source>` is not reintroduced as live markup.
- `Html5Dom`, `Html5DomFragment`, and the WordPress raw HTML fragment smoke now
  cover the same bounded CDATA handoff path.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
HTML reader and WordPress review handoff: XML-ish foreign content in HTML
fragments must preserve reviewer-visible text while serialized raw HTML remains
safe to hand to WordPress blocks. This is bounded CDATA-as-text normalization
for SVG/MathML handoff. It is not full HTML5 tree-builder parity, script/style
raw-text CDATA emulation, browser sanitizer parity, CSS/media fetching, native
XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.

Baseline focused DOM-family verification before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 636 assertions, 0 failures
```

Red-first run after adding CDATA expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 643 assertions, 3 failures
```

The failures showed SVG `desc` CDATA truncated to `& notes]]>` and MathML
annotation CDATA dropped before serialization.

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 673 assertions, 0 failures
```

Delta:

- Adds 3 focused PHP PASS cases.
- Adds 37 XML/HTML DOM assertions.
- Updates `phpPass` from `1021` to `1024`.
- Updates mapped static inventory coverage from `1475` to `1476`.

## Verification

Final verification is recorded in the worker final response:

- `php -l` for changed PHP files.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- JSON validation for lane status and manifest.
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with network access disabled, `XmlHtmlDom`, `Html5Dom`, `Html5DomFragment`,
`AstNode`, `WordPressBlockWriter`, and lane-local manifest/status machinery. It
did not invoke Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber,
Word, LibreOffice, office tools, tar, zip/unzip, lz4, external template
engines, TeX/PDF engines, browser renderers, browser layout engines,
JavaScript, CSS engines, media players, MathJax, KaTeX, roff, Typst, online
sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, complete HTML document unsafe-declaration preflight,
HTML fragment declaration preflight, raw text `script`/`style` serialization,
RCDATA handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, URL/srcset
filtering, base URL resolution, SVG resource URL filtering, SVG presentation
resource filtering, comment-boundary-safe serialization, visible
form/embed/noscript/template unwrapping, table foster-parenting, XML namespace
binding preservation, charset/Unicode width handling, Markdown/HTML reader AST
coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive
compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, syntax
highlighting, or legacy DOC/CFB work. It owns only bounded HTML foreign-content
CDATA text normalization before fragment parsing and serialization.

## Follow-Up

Keep full HTML5 tree-builder parity, script/style CDATA raw-text semantics,
broader sanitizer policy, CSS/media resource handling, native XHTML-to-AST
conversion, and upstream Pandoc runner dependency closure as separate bounded
slices.
