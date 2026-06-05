# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T034103Z`

Base accepted HEAD: `9b8cda1eda5add842959c80a999a025da28ae740`

## Behavior Added

- Added bounded safe XML document processing-instruction preflight to
  `XmlHtmlDom::loadXmlDocument()` and `Html5Dom::parseXmlDocument()`.
- Complete XML documents now keep normal XML declarations such as
  `<?xml version="1.0" encoding="UTF-8"?>`, which package files commonly use.
- Non-declaration processing instruction nodes such as `xml-stylesheet` or
  importer-specific review PIs are rejected after libxml parsing and before the
  DOM is returned to OPC, DOCX, EPUB, or WordPress review handoff code.
- XML/HTML fragments still reject XML declarations and processing instructions
  before parser repair, preserving the existing fragment safety boundary.

## Source Truth

- Source truth is the lane-local Pandoc manifest and accepted XML/HTML5 DOM
  support contract: safe XML parsing for document readers, no external entity
  expansion, and no active external processing handoff.
- The pinned upstream Pandoc checkout is not hydrated in this worktree or
  `/home/claude/port-libs/.upstream-cache/pandoc`, matching the upstream-runner
  dependency blocker. This slice did not run Pandoc, Cabal, Haskell test
  binaries, browser renderers, online sanitizers, or online services.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 224 assertions, 0 failures`.
- Red-first focused run after adding expectations failed with 2 focused
  failures:
  - `Html5Dom::parseXmlDocument()` rejected a normal XML declaration.
  - `XmlHtmlDom::loadXmlDocument()` accepted a non-declaration processing
    instruction.
- This slice adds 2 focused PHP PASS cases and 11 XML/HTML DOM assertions.
- Lane status moves `phpPass` `580 -> 582`; manifest mapped checks move
  `1062 -> 1064`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5Dom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 73 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: `1 test files, 59 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 103 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 235 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6530 assertions, 0 failures`.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: both decoded successfully.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml with
`LIBXML_NONET` and lane-local DOM traversal to reject processing instruction
nodes after safe parsing while preserving standard XML declarations.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, HTML declaration
preflight for fragments, raw text `script`/`style` serialization, HTML5
boolean attributes, SVG/MathML foreign-content casing, integration-point
casing, `srcset` URL filtering, `srcset` descriptor normalization, charset/
Unicode width handling, Markdown/HTML reader AST coverage, ZIP/OPC package
behavior, DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF handoff,
BibTeX/CSL, YAML, table geometry, or legacy DOC/CFB work.

## Follow-Up

Keep XML catalog/schema validation, full HTML5 tree-builder parity, broader
sanitizer policy, CSS/media resource handling, and XHTML-to-AST conversion as
separate bounded slices.
