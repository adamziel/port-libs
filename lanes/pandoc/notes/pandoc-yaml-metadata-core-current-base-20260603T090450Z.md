# Pandoc YAML Metadata Core Slice 2026-06-03 09:04 UTC

## Behavior

- Extended `MarkdownReader` from leading-only YAML front matter to bounded
  Pandoc `yaml_metadata_block` placement semantics: a metadata block may be at
  the document start or later when preceded by a blank line.
- Multiple YAML metadata blocks now merge in document order with later
  top-level fields replacing earlier ones.
- YAML metadata fields ending in `_` are ignored before document `meta`
  exposure.
- A YAML-looking opening fence followed by a blank line stays on the existing
  Markdown body path instead of being consumed as metadata.
- YAML-looking fences inside fenced code blocks stay in code content and are
  not scanned as document metadata.
- Updated `examples/wordpress-yaml-metadata-handoff.php` so the WordPress
  import smoke covers a source preface before metadata plus a later review
  metadata override.

Source truth: Pandoc User's Guide `yaml_metadata_block` section
(`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`) records
document-start or blank-line-preceded placement, multiple block merging with
later field values winning, ignored underscore-suffixed fields, and the
blank-after-opening restriction. The upstream checkout cache for this lane is
not hydrated in this isolated worktree, so this slice uses the official guide
and the accepted lane manifest as bounded source truth.

## Evidence

- Baseline before edit: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 2346 assertions, 0 failures`.
- After combined integration: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed as part of the focused Pandoc batch with `4 test files, 2586
  assertions, 0 failures`.
- `php -l lanes/pandoc/src/MarkdownReader.php` passed.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` passed and
  printed the non-initial metadata handoff plus WordPress blocks.
- `php tools/run-tests.php lanes/pandoc/tests` passed with `5 test files,
  2641 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
Markdown reader YAML support. No external YAML library, Pandoc binary, Haskell
runner, Cabal build, zip/unzip, Word, LibreOffice, TeX/PDF engine, template
engine, or online service was invoked.

## Blocker / Next

- Blocker: full upstream Pandoc runner parity remains unexecuted because the
  upstream test-pandoc/test-pandoc-lua-engine runners require a hydrated
  Haskell checkout and Cabal dependency build. The lane-local PHP suite is
  green.
- Next: use the native YAML/ZIP/OPC/doctemplate primitives to parse a minimal
  DOCX document part plus metadata into the existing AST/WordPress handoff
  path. Keep writer-side YAML metadata emission as a separate bounded slice.
