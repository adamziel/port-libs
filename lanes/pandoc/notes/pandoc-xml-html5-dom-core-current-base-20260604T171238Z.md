# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260604T171238Z`

Base accepted HEAD: `a274037b1ab9e58a54c3766ff4161b00827fc3ef`

## Behavior Added

- Added `XmlHtml5Dom` as a bounded native support primitive for safe
  DOMDocument-backed parsing of HTML5 fragments, full HTML documents, and XML
  reader inputs.
- Centralized MarkdownReader HTML fragment/document/table parsing through the
  shared helper instead of repeated ad hoc `loadHTML` calls.
- Centralized the DocBook informaltable XML reader path through the shared safe
  XML parser and reject XML doctype declarations before any entity expansion
  can be considered.
- Added deterministic wrapper-free HTML fragment serialization for parsed
  body nodes and fragment nodes, preserving HTML5 void element output such as
  `br`, `img`, and `hr`.
- Added a `--self-test` mode to the existing WordPress native HTML standalone
  linebreak example.

## Source Truth

The accepted Pandoc lane inventory already maps bounded upstream HTML reader
fixtures from `test/html-reader.html` / `test/html-reader.native` plus DocBook
table command fixtures. Those readers need safe fragment/document parsing,
HTML5 void element handling, and XML table parsing without invoking Pandoc or
external document tools. This slice ports that support-library contract only;
it does not attempt a full browser-grade HTML5 parser, sanitize policy,
resource fetching, or full upstream runner parity.

## Verification

- `php -l lanes/pandoc/src/XmlHtml5Dom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtml5DomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - Result: `1 test files, 36 assertions, 0 failures`
  - Delta: `+36` focused assertions and `+5` focused PASS lines.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2435 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php --self-test`
  - Result: `html5 dom handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `12 test files, 3313 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No external dependency is needed. The slice adds a native PHP support component
under `lanes/pandoc/src` and reuses PHP DOMDocument with `LIBXML_NONET`,
disabled external resolution, disabled entity substitution, and explicit XML
doctype rejection. It does not invoke Pandoc, Cabal, Haskell test binaries,
Word, LibreOffice, `zip`, `unzip`, external template engines, TeX/PDF engines,
browser renderers, MathJax, KaTeX, Typst, roff, or online services.

## Non-Overlap

This patch does not change the accepted Markdown raw-HTML block behavior,
including raw `<hr>` preservation in Markdown raw HTML tests. It does not
repeat accepted YAML metadata, ZIP/OPC relationship graphs, archive streams,
doctemplate, CSL, DOCX/ODT, table geometry, math/TeX, PDF handoff, charset,
Unicode width, or legacy DOC/CFB slices.

## Follow-Up

Keep full HTML5 tree-construction parity, sanitizer policy, resource-loading
policy, richer XML schema validation, XML namespace policy for non-DocBook
readers, and integration of the helper into DOCX/ODT/OPC loaders as separate
bounded slices.
