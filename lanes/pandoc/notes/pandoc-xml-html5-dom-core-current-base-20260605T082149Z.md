# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T082149Z`

Base accepted HEAD: `047062ffae599f2aed5868dc8e085f869923184a`

## Behavior Added

- `Html5DomFragment` now unwraps `template` inert review content before
  WordPress raw HTML handoff.
- The `template` wrapper is reported as a `blocked-tag` diagnostic and is not
  serialized into WordPress blocks.
- Safe child content remains visible for reviewers, including nested links and
  media.
- Unsafe child URLs still go through existing URL filtering, and nested active
  content such as `script` remains blocked and dropped.
- The WordPress HTML5 DOM handoff smoke now proves hidden template content is
  surfaced without retaining the inert container.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract:
  bounded HTML fragments must be recovered into deterministic review packets
  while avoiding active or hidden browser-side containers in WordPress raw HTML
  blocks.
- HTML `template` content is inert in browser contexts. Retaining the wrapper
  hides reviewer-relevant legacy import content, while dropping the entire
  element loses source text and links that should remain auditable.
- This is a bounded sanitizer/handoff behavior. It is not complete HTML5
  tree-builder parity, browser template document-fragment emulation, CSS/media
  resource handling, a general-purpose sanitizer, or native XHTML-to-AST
  conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 427 assertions, 0 failures`.
- Red-first focused probe:
  - `Html5DomFragment::fromHtml("<template>...</template><p>after</p>")`
  - Result: sanitized output retained the `<template>` wrapper and reported
    only `script` as blocked.
- Red-first focused test after adding the template expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: failed because actual output retained
    `<template data-source="legacy-hidden">...`.
- Focused sanitizer verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 240 assertions, 0 failures`.
- Focused DOM-family verification after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 443 assertions, 0 failures`.
- This slice adds 1 focused PHP PASS case and 16 XML/HTML DOM assertions.
- Lane status moves `phpPass` `773 -> 774`; manifest mapped checks move
  `1232 -> 1233`.

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
unwrapping, `noscript` fallback unwrapping, HTML5 void/boolean attribute
serialization, SVG/MathML foreign-content casing, integration-point casing,
URL/srcset filtering, extended URL/ping filtering, visible form unwrapping,
table foster-parenting, charset/Unicode width handling, Markdown/HTML reader
AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive
compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, or
legacy DOC/CFB work. It owns only bounded `template` inert-content unwrapping
in the XML/HTML5 DOM sanitizer/handoff layer.

## Follow-Up

Keep complete HTML5 tree-builder parity, richer sanitizer policy, CSS/media
resource handling, EPUB/XHTML resource resolution, and native XHTML-to-AST
conversion as separate bounded slices.
