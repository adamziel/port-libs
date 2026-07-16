# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T191209Z`

Base accepted HEAD: `1fee675cfc053b65d6824b32dd8851f66511d8c2`

## Behavior Added

- `XmlHtmlDom::loadHtmlFragment()` and `Html5Dom` raw reader paths now protect
  closed HTML `<template>` bodies before libxml parsing.
- Template body source is retained as escaped text instead of being exposed as
  live child markup, so script-looking or inline tag-looking source inside a
  template cannot become active raw HTML during WordPress review handoff.
- `Html5DomFragment` sanitizer behavior is intentionally unchanged: its
  accepted template-unwrapping path still exposes visible fallback content
  through the sanitizer and remains covered by the focused sanitizer test.
- The WordPress XML/HTML DOM handoff example now proves template source
  serialization stays escaped in raw HTML blocks without invoking Pandoc,
  browser renderers, JavaScript, online sanitizers, or external conversion
  services.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
document-reader inputs: raw DOM parsing and serialization must keep template
contents inert relative to the main fragment tree. The local Pandoc upstream
checkout is still absent in this environment, matching the existing
upstream-runner dependency blocker, so this remains a bounded native PHP
support-library case rather than Haskell runner parity.

This is not full HTML5 tree-builder parity, nested template stack modeling,
declarative shadow DOM support, browser sanitizer parity, CSS/media handling,
or XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 679 assertions, 0 failures`.
- Red-first runs after adding the new expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: failed with `1 test files, 142 assertions, 1 failures` because
    template text collapsed to `Template drop() & note`.
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: failed with `1 test files, 119 assertions, 1 failures` for the
    same parsed-child template behavior.
  - `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: failed because template content did not serialize as inert escaped
    source text.
- Green focused run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 702 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`.
- This slice adds 2 focused PHP PASS cases and 23 XML/HTML DOM assertions.
- Lane status moves `phpPass` `1045 -> 1047`; manifest mapped checks move
  `1498 -> 1499`.

## Verification

Final verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/Html5Dom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/Html5DomTest.php`
- `php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with network access disabled, `XmlHtmlDom`, `Html5Dom`, `Html5DomFragment`,
`AstNode`, `WordPressBlockWriter`, and the focused PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, citeproc, BibTeX,
Biber, Word, LibreOffice, office tool, tar, zip/unzip, lz4, external template
engine, external XML/HTML tool, TeX/PDF engine, browser renderer, JavaScript,
online sanitizer, or online service was executed.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, complete-document
doctype preflight, raw text `script`/`style` serialization, RCDATA handling
for `title`/`textarea`, obsolete raw-text fallback handling, plaintext-state
source protection, HTML5 void/boolean attribute serialization, SVG/MathML
foreign-content casing, integration-point casing, URL/srcset filtering, base
URL resolution, SVG resource URL filtering, SVG presentation resource
filtering, comment-boundary-safe serialization, visible form/embed/noscript/
template unwrapping in the sanitizer, table foster-parenting, XML namespace
binding preservation, charset/Unicode width handling, Markdown/HTML reader AST
coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive
compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, syntax
highlighting, or legacy DOC/CFB work.

It owns only bounded template-body inert-source protection for raw XML/HTML5
DOM parser/serializer handoffs.

## Follow-Up

Keep nested template stack parity, declarative shadow DOM policy, full HTML5
tree-builder parity, richer sanitizer policy, CSS cascade/media resource
handling, XHTML-to-AST conversion, and full upstream Pandoc runner dependency
closure as separate bounded slices.
