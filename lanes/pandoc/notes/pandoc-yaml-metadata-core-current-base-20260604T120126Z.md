# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260604T120126Z`

Base accepted HEAD: `787e8dd10c1719fcb8b1124ad3ded6c5edccfe81`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset for block-style
  sequence-of-map values, such as bibliography/reference entries written as:
  `- id: source-export` followed by indented `title`, `author`, and `issued`
  keys.
- Preserved nested block sequence values inside those maps, including CSL-style
  `issued.date-parts` written as nested YAML dashes rather than compact flow
  arrays.
- Preserved structured `author`/`authors` metadata arrays when entries are maps
  instead of scalar names, while keeping the accepted scalar author splitting
  behavior unchanged.
- Updated the WordPress YAML metadata handoff smoke to use block-style
  reference metadata and a `--self-test` check for the later review override,
  nested date-parts, and rendered imported body.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats YAML metadata values as arbitrary
nested lists, objects, and scalar strings, with strings interpreted as Markdown
where relevant. This slice ports only the bounded native PHP subset needed for
block-style metadata handoff. Source-truth reference remains the Pandoc User's
Guide section recorded by the earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2397 assertions, 0 failures`
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2413 assertions, 0 failures`
  - PASS lines: 224
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `10 test files, 3092 assertions, 0 failures`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, or online services.

## Non-Overlap

This patch does not repeat accepted YAML placement, fenced-code exclusion,
JSON-object metadata, flow-map metadata, Markdown/HTML reader/writer behavior,
CSL citation rendering, DOCX/ODT package parsing, legacy DOC/CFB extraction,
ZIP/OPC package primitives, doctemplate support, table geometry, Math/TeX
conversion, archive compression, or PDF engine handoff planning. It owns only
the new block-style nested YAML metadata value path.

## Follow-Up

Keep writer-side YAML emission, anchors, aliases, tags, custom YAML schemas,
BibTeX/BibLaTeX parsing, CSL style XML/locale processing, and broader upstream
runner Cabal dependency planning as separate bounded slices.
