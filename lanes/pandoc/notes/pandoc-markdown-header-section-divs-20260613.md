# Pandoc Markdown Header Section Divs Slice

Date: 2026-06-13
Bead: plib-x3ybs

## Scope

This slice maps one bounded Markdown/CommonMark/GFM block-structure gap:
opt-in header-to-section grouping for native PHP Markdown imports. It does not
use external Pandoc, Cabal/Haskell runners, browser renderers, Node tooling,
TeX/PDF engines, office suites, online services, live provider tests, or
external validators.

## Implemented

`MarkdownReader` now accepts `sectionDivs => true`. When enabled, parsed
Markdown headings are grouped into nested `div` sections with `section` and
`levelN` classes. Heading identifiers, classes, and key-value attributes move
to the section wrapper so Markdown and WordPress handoff keep stable section
anchors without duplicating the same identifier on the inner heading. Body
blocks before the first heading remain top-level.

The focused regression covers:

- A pre-heading paragraph that stays outside generated sections.
- A level-1 section with explicit id, class, and key-value attributes.
- Nested level-2 and level-3 generated section wrappers.
- A second level-1 sibling section.
- Markdown fenced-Div output and WordPress nested wrapper HTML.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed: 1 file, 6,685 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 45 files, 74,621 assertions, 0 failures.

Counters move `phpPass` 3,324 -> 3,325, `phpFail` remains 0, and mapped
upstream evidence moves 3,283 -> 3,284 with
`mappedMarkdownHeaderSectionDivCases=1` and
`markdownHeaderSectionDivAssertions=17`.

## Remaining Gaps

Markdown/CommonMark/GFM remains partial. Remaining block-structure gaps include
broader fenced Div and section variant parity, nested section-boundary behavior
beyond this opt-in heading grouping, and unrelated JSON/native AST, table,
citation, metadata, CSV/TSV, and generic text-reader parity.
