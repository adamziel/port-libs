# Pandoc ODF OpenDocument Core Slice

## Scope

Implemented bounded OpenDocument Text `text:list-header` handling in the native
ODF package reader:

- Tags `text:list-header` children as metadata-bearing `list_item` nodes with
  `odf-list-header` review metadata.
- Keeps list headers out of ordered-list continuation counters so headers do not
  consume numbering positions.
- Renders ODF list headers as unnumbered review divs in Markdown and WordPress
  output before the numbered/bulleted list items.
- Adds `listHeaderCount` to the ODF import report for WordPress review queues.

This is bounded to ODT/OpenDocument content XML and shared writer handoff
semantics. It does not invoke Pandoc, Cabal, Haskell runners, Word,
LibreOffice, zip/unzip, browser renderers, external template engines, or online
services.

## Source Truth

The local upstream cache for this isolated worktree does not include a hydrated
Pandoc checkout or Cabal package files, so the slice uses the ODF XML contract
already activated for `odf-open-document-core`: `text:list-header` is a
distinct OpenDocument list child from `text:list-item`, and should remain
reviewable without being treated as an ordinary numbered item.

## Evidence

- Before this slice, `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed at 1 test file, 485 assertions, 0 failures.
- After implementation, `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed at 1 test file, 503 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  passed.
- Syntax checks passed for changed PHP files:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
  passed.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `phpPass`: 744 -> 745.
- `benchmarkDenominator.mapped`: 1203 -> 1204.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 235.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
OpenDocument XML reader, shared AST, Markdown writer, WordPress block writer,
and ZIP package fixture builder. Full upstream Pandoc runner parity remains
blocked on hydrating the pinned upstream checkout and Cabal package metadata.

## Non-Overlap

This avoids the accepted ODT mimetype/manifest/content/styles/meta/media/table
base cluster and the later ODT bookmark, reference mark, sequence, field,
bibliography mark, annotation range, nested-list style inheritance,
text-position, MathML object, linked/protected section, tracked-change, and
image-dimension clusters. It adds only bounded `text:list-header` handoff.
