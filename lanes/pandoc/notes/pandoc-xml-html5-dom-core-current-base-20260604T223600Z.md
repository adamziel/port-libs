# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260604T223600Z`

Accepted base: `229db65917c88ede5fb968cbc6030180ac381585`

## Behavior

- Added `Html5DomFragment`, a bounded native PHP support component for safe
  XML/HTML fragment handling needed by document readers and WordPress handoff
  paths.
- HTML mode wraps fragments in a non-network DOM parse, records libxml repair
  diagnostics, normalizes repaired tree structure, preserves text/comment nodes,
  serializes deterministic HTML, and emits HTML void elements such as `<br>`.
- XML mode parses strict multi-root fragments behind a temporary wrapper,
  preserves prefixed attributes such as `xml:lang`, serializes empty nodes as
  XML empty elements, and rejects DTD/entity declarations before parsing.
- The sanitizer blocks active or form-style tags such as `script`, `style`,
  `iframe`, `object`, and `form`; filters event attributes, inline `style`,
  `srcdoc`, and unsafe URL schemes; and preserves safe `data-*`, `aria-*`,
  relative, fragment, HTTP(S), mail, and phone URLs.
- Exposes normalized nodes, diagnostic codes, summary counts, text content, and
  `raw_html` AST handoff with diagnostics for existing WordPress block output.
- Added a WordPress import-review smoke example that normalizes a legacy HTML
  packet, strips active content, and writes a deterministic raw HTML block
  without invoking Pandoc or a browser renderer.

## Source Truth

- This slice maps bounded support-library behavior needed by the accepted
  Pandoc HTML reader, EPUB XHTML handoff, and WordPress raw HTML block paths.
- It uses PHP DOM/libxml with `LIBXML_NONET` and does not shell out to Pandoc,
  Haskell runners, browser renderers, office tools, XML services, or online
  sanitizers.
- Full Pandoc HTML5 tree-builder parity is intentionally out of scope here.
  Foster parenting, namespace-rich SVG/MathML, CSS cascade/style policy, and
  full XHTML-to-AST conversion remain separate bounded follow-up slices.

## Evidence

- Before this slice, the Pandoc lane had 384 focused PHP PASS cases and 841
  mapped native checks.
- After this slice, `Html5DomFragmentTest.php` adds 4 focused PASS cases and
  42 assertions, raising the lane to 388 focused PHP PASS cases and 845 mapped
  native checks.
- `php -l lanes/pandoc/src/Html5DomFragment.php`:
  no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`:
  no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`:
  1 selected test file, 42 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  14 selected test files, 3,711 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`:
  `wordpress-html5-dom-handoff self-test passed`.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`:
  both files valid.
- `git diff --check -- lanes/pandoc`:
  no whitespace errors.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Markdown raw HTML parsing, structured HTML table
AST conversion, EPUB3 package handoff, DOCX/ODT package parsing, ZIP/OPC
package primitives, table geometry, doctemplate, YAML, CSL/BibTeX, archive
compression, math/TeX, legacy DOC/CFB, charset helpers, PDF handoff planning,
or upstream-runner dependency audit work.

## Dependency Closure

No external support component is needed. This adds one native PHP component
under `lanes/pandoc/src` and reuses the existing `AstNode` and
`WordPressBlockWriter` handoff. Remaining XML/HTML closure is bounded follow-up:
full HTML-reader AST conversion from normalized fragments, HTML5 table
insertion-mode parity, SVG/MathML namespace policy, CSS/style policy, richer
URL policy, and EPUB XHTML-to-AST conversion.
