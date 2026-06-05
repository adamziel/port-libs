# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T023724Z`

Base accepted HEAD: `76df382e122b77d31da81d50dde5ba40cf010573`

## Behavior Added

- Added tag-agnostic fetch URL filtering for media fetch attributes in
  `Html5DomFragment`.
- `href`, `cite`, and `xlink:href` keep the existing bounded reviewer URL
  policy, so `mailto:` and `tel:` review links remain available for audit
  packets.
- `src` and `poster` now use a fetch-only policy: relative URLs, root-relative
  URLs, fragments, query-only URLs, and `http`/`https` URLs survive, while
  `mailto:` and `tel:` are removed with `unsafe-url` diagnostics before raw
  HTML handoff.
- HTML5 void elements repaired by libxml with children are normalized back to
  deterministic HTML5 shape by emitting the void node with no children and
  reparenting normalized repaired children as following siblings. This keeps
  consecutive media `source` children visible instead of serializing a
  synthetic `</source>` wrapper.
- The WordPress HTML5 DOM handoff smoke now proves reviewer `mailto:` links
  survive while `mailto:`/`tel:` media fetch URLs are stripped from raw HTML
  blocks.

## Source Truth

- Source truth is the lane-local XML/HTML5 DOM support-library contract for
  bounded HTML fragment repair, active-content filtering, unsafe URL filtering,
  deterministic HTML5 void/boolean serialization, and WordPress raw HTML
  handoff.
- The gap was observable in the current base: media `src` and `poster`
  attributes reused the broad link URL policy and could retain non-fetch
  `mailto:`/`tel:` schemes. Consecutive HTML5 `source` elements could also be
  nested by libxml because libxml's HTML parser does not model all HTML5 void
  elements.
- This does not implement full browser tree-builder parity, CSS cascade,
  browser media candidate selection, media playback, online sanitization, or
  XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 82 assertions, 0 failures.
- Pre-slice XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 179 assertions, 0 failures.
- Initial focused rerun after adding the media URL expectation failed because
  libxml nested a safe second `source` under the first repaired `source`
  element and the serializer emitted a synthetic `</source>` closing tag. The
  implementation now reparents HTML5 void-element children before
  serialization.
- This slice adds 1 focused PHP PASS case and 10 XML/HTML DOM assertions.
- Focused `Html5DomFragmentTest.php` now passes 10 cases with 92 assertions.
- Focused XML/HTML DOM family now passes 25 cases with 189 assertions across
  `XmlHtmlDomTest.php`, `Html5DomTest.php`, and `Html5DomFragmentTest.php`.
- Lane status is updated to 548 PHP pass / 0 fail and 1,026 mapped native
  checks.

## Verification

- `python3 -m json.tool lanes/pandoc/lane-status.json`
  - Result: passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: passed.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 92 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 189 assertions, 0 failures.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and the
lane-local `Html5DomFragment` sanitizer/serializer. It did not invoke Pandoc,
Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice,
office tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, media players, MathJax, KaTeX,
roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, raw text
`script`/`style` serialization, HTML5 boolean attributes, XML/HTML declaration
preflight, SVG/MathML foreign-content casing, `srcset` URL filtering,
`srcset` descriptor normalization, Markdown/HTML reader AST coverage, syntax
highlighting, EPUB3 package handoff, DOCX/ODT readers, ZIP/OPC package
behavior, table geometry, archive compression, PDF handoff, math/TeX
conversion, charset/Unicode helpers, BibTeX/CSL, YAML, doctemplate, or legacy
DOC/CFB work. It owns only bounded media fetch URL filtering and libxml
HTML5-void-child reparenting inside the XML/HTML5 DOM fragment sanitizer.

## Follow-Up

Keep full HTML5 tree-builder parity, MathML/SVG HTML integration points,
CSS/media resource handling, browser-grade media candidate selection, broader
sanitizer policy, and XHTML-to-AST conversion as separate bounded slices.
