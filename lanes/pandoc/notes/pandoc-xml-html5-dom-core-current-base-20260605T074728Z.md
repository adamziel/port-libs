# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T074728Z`

Base accepted HEAD: `04872dbb3131d5a034d1e365b9c27ae699e2563e`

## Behavior Added

- `Html5DomFragment` now unwraps `noscript` fallback review content before
  WordPress raw HTML handoff.
- The `noscript` wrapper is still reported as a `blocked-tag` diagnostic and
  is not serialized into WordPress blocks.
- Nested active content, such as `script`, remains blocked and dropped.
- Nested fallback links and media continue through the existing sanitizer:
  safe URLs are preserved, unsafe URLs such as `javascript:` are removed, and
  the `href` filter is reported.
- The WordPress HTML5 DOM handoff smoke now proves script-disabled fallback
  content remains visible for reviewers without retaining the `noscript`
  container.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  bounded HTML fragments must be recovered into deterministic review packets
  while avoiding active or hidden browser-side containers in WordPress raw HTML
  blocks.
- `noscript` can carry useful script-disabled fallback source content in legacy
  imports. Keeping the wrapper hides that content in common review contexts,
  while dropping the entire element loses reviewer-visible text and links.
- This is a bounded sanitizer/handoff behavior. It is not complete HTML5
  tree-builder parity, browser scripting-state emulation, CSS/media resource
  handling, a general-purpose sanitizer, template inert-content handling, or
  native XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 411 assertions, 0 failures`.
- Red-first focused check after adding the noscript expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: failed because sanitized output retained the `<noscript>` wrapper:
    actual output started with `<noscript><p>Script-disabled fallback...`.
- Focused sanitizer verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 224 assertions, 0 failures`.
- Focused DOM-family verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 427 assertions, 0 failures`.
- Full Pandoc lane test-directory verification:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8843 assertions, 0 failures`.
- This slice adds 1 focused PHP PASS case and 16 XML/HTML DOM assertions.
- Lane status moves `phpPass` `755 -> 756`; manifest mapped checks move
  `1214 -> 1215`.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: both decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
the existing `Html5DomFragment` sanitizer, `AstNode`, and
`WordPressBlockWriter`. It did not invoke Pandoc, Cabal, Haskell test
binaries, citeproc, BibTeX, Biber, Word, LibreOffice, office tools, tar,
zip/unzip, lz4, external template engines, TeX/PDF engines, browser renderers,
browser layout engines, media players, MathJax, KaTeX, roff, Typst, online
sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, RCDATA handling for `title`/`textarea`,
obsolete raw-text fallback handling for `xmp`/`noembed`/`noframes`,
plaintext-state protection, active `iframe`/`object`/`applet` fallback
unwrapping, HTML5 void/boolean attribute serialization, SVG/MathML
foreign-content casing, integration-point casing, URL/srcset filtering,
extended URL/ping filtering, visible form unwrapping, table foster-parenting,
charset/Unicode width handling, Markdown/HTML reader AST coverage, ZIP/OPC
package behavior, DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF
handoff, BibTeX/CSL, YAML, table geometry, or legacy DOC/CFB work. It owns
only bounded `noscript` fallback unwrapping in the XML/HTML5 DOM
sanitizer/handoff layer.

## Follow-Up

Keep template inert-content handling, complete HTML5 tree-builder parity,
richer sanitizer policy, CSS/media resource handling, EPUB/XHTML resource
resolution, and native XHTML-to-AST conversion as separate bounded slices.
