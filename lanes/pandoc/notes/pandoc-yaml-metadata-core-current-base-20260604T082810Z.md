# Pandoc YAML Metadata Core Slice 2026-06-04 08:28 UTC

## Behavior

- Extended the native `MarkdownReader` YAML metadata parser to accept a whole
  JSON object as the metadata document between `---` and `---` / `...`.
- Added bounded YAML flow collection parsing for compact maps and nested
  sequences, including citation-style `references` arrays, issued
  `date-parts`, review labels, and reveal/PPT-style dashed metadata keys.
- Added quoted-key handling for root YAML mapping lines and flow-map keys.
- Preserved accepted YAML behavior: metadata blocks still require a nonblank
  first content line, non-initial blocks must be blank-line preceded, later
  top-level metadata blocks replace earlier fields, fenced code is excluded,
  and root keys ending in `_` remain hidden from document `meta`.
- Updated the WordPress YAML handoff example so final effective review metadata
  uses flow-map labels and inline reference metadata.

Source truth: Pandoc User's Guide `yaml_metadata_block` documents JSON as a
valid YAML subset for metadata blocks, arbitrary nested lists/objects in
metadata, Markdown interpretation of string scalar metadata, ignored
underscore-suffixed fields, later-block precedence, and blank-line placement
rules: https://pandoc.org/demo/example2.html#extension-yaml_metadata_block.

## Evidence

- `php -l lanes/pandoc/src/MarkdownReader.php` passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  with `1 test files, 2397 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` passed with `8 test files,
  2897 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed and
  printed flow-map review labels plus inline reference metadata before the
  WordPress block body.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader`. No external YAML library, Pandoc
binary, Haskell runner, Cabal build, zip/unzip, Word, LibreOffice, TeX/PDF
engine, template engine, or online service was invoked.

## Non-overlap / Blocker / Next

- Non-overlap: this does not repeat accepted table-geometry visual-column
  layout, DOCX/ODT package parsing, ZIP/OPC package primitives, CSL citation
  cluster rendering, doctemplate pipes, or prior YAML placement/fenced-code
  behavior. It only extends metadata value parsing for JSON and flow
  collections.
- Blocker: full upstream Pandoc runner parity remains unexecuted because
  upstream test-pandoc/test-pandoc-lua-engine require a hydrated Haskell
  checkout and Cabal dependency build. The lane-local PHP suite is green.
- Next: keep writer-side YAML emission, richer YAML anchors/tags, BibTeX/
  BibLaTeX parsing, CSL style XML/locale processing, and full upstream runner
  dependency planning as separate bounded slices.
