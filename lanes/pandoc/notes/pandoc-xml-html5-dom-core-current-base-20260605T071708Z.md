# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T071708Z`

Base accepted HEAD: `70ef77d166e9d74b86c970021f8d31ae3fa55c57`

## Behavior Added

- `Html5DomFragment` now unwraps fallback review content from active legacy
  embed containers: `iframe`, `object`, and `applet`.
- The unsafe container tags are still reported as `blocked-tag` diagnostics and
  do not reach WordPress raw HTML blocks.
- Object `param` metadata is now blocked and dropped instead of being exposed
  as a sanitized HTML element.
- Nested active content inside the fallback, such as `script`, is still dropped
  while visible fallback text, inline markup, and safe reviewer links are
  preserved.
- The WordPress HTML5 DOM handoff smoke now proves iframe/object/applet
  fallback content reaches the raw HTML review block without retaining unsafe
  wrappers or `param` metadata.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract: bounded
  HTML fragments must be recovered into deterministic review packets without
  handing active embed containers to WordPress raw HTML blocks.
- HTML `iframe`, `object`, and `applet` bodies can carry fallback source text
  that is useful during import review. Dropping the entire container loses that
  reviewer-visible content.
- This is a bounded sanitizer/handoff behavior. It is not a browser sanitizer,
  iframe/object execution model, plugin runtime, CSS/media resource handling,
  complete HTML5 tree-builder parity, template inert-content handling, or
  native XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 387 assertions, 0 failures`.
- Pre-edit behavior probe showed the sanitizer output for
  `iframe`/`object`/`applet` fallback fragments was only `<p>after</p>`;
  fallback text was lost while the raw DOM summary still contained it.
- Focused sanitizer verification:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 208 assertions, 0 failures`.
- Focused DOM-family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 411 assertions, 0 failures`.
- This slice adds 1 focused PHP PASS case and 24 XML/HTML DOM assertions.
- Lane status moves `phpPass` `739 -> 740`; manifest mapped checks move
  `1198 -> 1199`.

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
obsolete raw-text fallback handling for `xmp`/`noembed`/`noframes`, plaintext
state protection, HTML5 void/boolean attribute serialization, SVG/MathML
foreign-content casing, integration-point casing, URL/srcset filtering,
visible form unwrapping, table foster-parenting, charset/Unicode width
handling, Markdown/HTML reader AST coverage, ZIP/OPC package behavior,
DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF handoff,
BibTeX/CSL, YAML, table geometry, or legacy DOC/CFB work. It owns only bounded
active embed fallback unwrapping in the XML/HTML5 DOM sanitizer/handoff layer.

## Follow-Up

Keep template inert-content handling, complete HTML5 tree-builder parity,
richer sanitizer policy, CSS/media resource handling, EPUB/XHTML package
resource resolution, and native XHTML-to-AST conversion as separate bounded
slices.
