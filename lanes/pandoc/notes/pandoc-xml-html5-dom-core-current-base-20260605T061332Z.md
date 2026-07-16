# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T061332Z`

Base accepted HEAD: `f35a619c7f21a255877365c107bd8809c41d57e8`

## Behavior Added

- Added bounded HTML5 table foster-parenting to the shared XML/HTML5 DOM
  support layer.
- `XmlHtmlDom` now lifts invalid non-whitespace text and invalid element
  children from direct `table`, row-group, and `tr` contexts before
  deterministic HTML serialization and fragment summaries.
- `Html5DomFragment` applies the same repair to sanitized review packets and
  records `table-foster-parented-content` diagnostics for WordPress importer
  audits.
- Valid table children such as captions, row groups, rows, and cells remain in
  place; loose imported paragraphs and text stay visible before the table
  instead of being serialized as invalid table children.
- The WordPress HTML5 DOM smoke now proves loose legacy table notes are handed
  to raw HTML blocks before the table with diagnostics.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract plus the
  HTML5 table insertion-mode behavior needed by HTML-reader and XHTML review
  handoffs.
- The current-base gap was observable with focused red tests: libxml left a
  paragraph and non-whitespace text as direct children of `<table>`, and the
  serializer handed that invalid shape to WordPress raw HTML blocks.
- This is a bounded table-scope repair. It is not a full HTML5 tree builder,
  foster-parenting parity for every insertion mode, CSS/media handling,
  browser sanitization parity, template/plaintext-state support, or
  XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 331 assertions, 0 failures`.
- Red check after adding foster-parenting expectations:
  - Same command.
  - Result: `3 test files, 334 assertions, 3 failures`.
  - Failures showed invalid paragraph/text children still serialized inside
    the table.
- Focused green verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 357 assertions, 0 failures`.
- Full lane verification:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8006 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- This slice adds 3 focused PHP PASS cases and 26 XML/HTML DOM assertions.
- Lane status moves `phpPass` `682 -> 685`; manifest mapped checks move
  `1160 -> 1163`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files decode.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
the shared deterministic serializer, the existing `Html5DomFragment`
sanitizer, `AstNode`, and `WordPressBlockWriter`. It did not invoke Pandoc,
Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice,
office tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, media players, MathJax, KaTeX,
roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, RCDATA handling for `title`/`textarea`,
obsolete raw-text fallback handling for `xmp`/`noembed`/`noframes`, HTML5
void/boolean attribute serialization, SVG/MathML foreign-content casing,
integration-point casing, URL/srcset filtering, visible form unwrapping,
charset/Unicode width handling, Markdown/HTML reader AST coverage, ZIP/OPC
package behavior, DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF
handoff, BibTeX/CSL, YAML, table geometry, or legacy DOC/CFB work. It owns
only bounded table-scope foster-parenting in the XML/HTML5 DOM support layer.

## Follow-Up

Keep richer HTML5 table insertion-mode parity, `template`/plaintext-state
handling, CSS/media resource policy, sanitizer policy expansion, and native
XHTML-to-AST conversion as separate bounded slices.
