# Pandoc ODF OpenDocument Core Slice

## Scope

Implemented bounded OpenDocument Text link metadata handoff in the native ODF
package reader:

- Preserves `text:a` source metadata from `office:name`,
  `office:target-frame-name`, `text:style-name`, `text:visited-style-name`,
  `xlink:type`, `xlink:show`, and `xlink:actuate`.
- Keeps `office:title` as the ordinary AST link title while exposing the ODF
  source metadata through `odfLinkMetadata` and `data-odf-link-*` attributes.
- Leaves plain href-only ODT links on the existing plain-link path, so accepted
  footnote and paragraph link output does not gain review classes unless source
  metadata exists.
- Updates the WordPress ODF handoff smoke so source package links retain their
  target-frame, style, and xlink review attributes in block HTML.

This is bounded to ODT/OpenDocument content XML and shared Markdown/WordPress
writer handoff semantics. It does not invoke Pandoc, Cabal, Haskell runners,
LibreOffice, office tools, zip/unzip, browser renderers, external template
engines, or online services.

## Source Truth

The local upstream cache for this isolated worktree does not include a hydrated
Pandoc checkout or Cabal package files. This slice uses the ODF XML contract
already activated for `odf-open-document-core`: `text:a` carries its destination
through `xlink:href` and may carry link/title/style/target-frame metadata that
must remain reviewable when an ODT packet is converted into Markdown or
WordPress blocks.

## Evidence

- Baseline before the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: 1 test file, 503 assertions, 0 failures.
- Red-first focused check after adding the expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: failed as expected because ODF link `sourceFormat` metadata was
    `NULL`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: 1 test file, 527 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`.

## Status Delta

- `phpPass`: 761 -> 762.
- `benchmarkDenominator.mapped`: 1220 -> 1221.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 241.
- Focused ODF test coverage: 503 -> 527 assertions.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`OdfReader` and reuses the existing AST, Markdown writer, WordPress block
writer, and ZIP package fixture builder. Full upstream Pandoc runner parity
remains blocked on hydrating the pinned upstream checkout and Cabal package
metadata.

## Non-Overlap

This avoids the accepted ODT mimetype/manifest/content/styles/meta/media/table
base cluster and the later ODT bookmark, reference mark, sequence, field,
bibliography mark, annotation range, nested-list style inheritance,
text-position, MathML object, linked/protected section, tracked-change,
encrypted-manifest, image-dimension, and list-header clusters. It adds only
bounded metadata-rich `text:a` source-link handoff.

