# Pandoc ODF OpenDocument Core Slice

## Scope

Implemented bounded OpenDocument Text table metadata handoff in the current
native `OdfReader`:

- Preserves `table:table table:name` as the table review caption and
  `tableName` AST metadata.
- Preserves `table:style-name`, `table:protected`,
  `table:protection-key`, and `table:protection-key-digest-algorithm` as
  review metadata exposed through safe `data-odf-table-*` WordPress table
  attributes.
- Attaches the existing `TableGeometry` review packet to ODF table nodes so
  named source tables keep caption and grid coverage metadata together.
- Updates the WordPress ODF handoff smoke so named/protected ODT source tables
  render as reviewable block-table captions and data attributes.

This is bounded to ODT/OpenDocument content XML and existing native writer
handoff. It does not invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice,
zip/unzip, external office tools, browser renderers, external template engines,
or online services.

## Source Truth

The local upstream cache for this isolated worktree does not include a hydrated
Pandoc checkout or Cabal package files. This slice uses the ODF XML contract
already activated for `odf-open-document-core`: `table:table` may carry source
table names, styles, and protection metadata that should remain auditable when
an ODT packet is converted into Markdown or WordPress blocks. It also carries
forward the earlier bounded `OdtReader` behavior that treated ODT table names
as review captions.

## Evidence

- Focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: 1 test file, 547 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`

## Status Delta

- `phpPass`: 775 -> 776.
- `benchmarkDenominator.mapped`: 1234 -> 1235.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- Focused ODF reader coverage: 23 PASS / 527 assertions -> 24 PASS / 547
  assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`OdfReader`, `TableGeometry`, AST, Markdown writer, WordPress block writer, and
ZIP package fixture builder. Full upstream Pandoc runner parity remains blocked
on hydrating the pinned upstream checkout and Cabal package metadata.

## Non-Overlap

This avoids the accepted ODT mimetype/manifest/content/styles/meta/media/table
base cluster and the later ODT bookmark, reference mark, sequence, field,
bibliography mark, annotation range, nested-list style inheritance,
text-position, MathML object, linked/protected section, tracked-change,
encrypted-manifest, image-dimension, link-metadata, and list-header clusters.
It adds only bounded named/protected `table:table` metadata handoff for current
ODF reader tables.

