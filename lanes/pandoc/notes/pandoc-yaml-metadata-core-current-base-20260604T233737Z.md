# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260604T233737Z`

Base accepted HEAD: `cb23e2485cd549063944df19de56bf77da035ccd`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to strip comments
  from plain scalar values, flow values, and block-scalar headers.
- Preserved quoted `#` characters and unspaced URL fragments such as
  `/exports/packet#front-matter`.
- Added bounded YAML block-scalar header parsing for `|` and `>` with chomp
  indicators and explicit indentation indicators, including headers with
  trailing comments.
- Preserved keep-chomp literal metadata notes for WordPress audit packets while
  keeping the accepted default strip behavior for existing block scalar tests.
- Updated the WordPress YAML metadata handoff smoke to exercise source comments,
  quoted hashes, folded strip-chomp summaries, literal keep-chomp audit notes,
  and URL-fragment metadata on the user-visible import path.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML. This
slice ports only the bounded YAML comment and block-scalar header behavior
needed by native front-matter metadata handoff. Source-truth reference remains
the Pandoc User's Guide `yaml_metadata_block` contract recorded by earlier YAML
notes: `https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2435 assertions, 0 failures`
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2447 assertions, 0 failures`
  - Delta: `+12` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 3922 assertions, 0 failures`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, or online
services.

## Non-Overlap

This patch does not repeat accepted YAML placement, fenced-code exclusion,
JSON-object metadata, flow-map metadata, block-style nested sequence metadata,
or YAML anchor, alias, merge-key, and explicit-tag metadata support. It owns
only bounded YAML metadata comments and block-scalar chomp/indent headers.
It also does not touch accepted Markdown/HTML reader/writer behavior, CSL,
DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table geometry,
Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF engine
handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, complex nested flow comments inside maps, multi-document YAML stream
handling, and full upstream runner dependency planning as separate bounded
slices.
