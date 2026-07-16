# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T004819Z`
- Accepted base: `e25ad1f2ac6eaccdea2f6b6dc8a510504a91892b`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text package handoff for encrypted
resources declared in `META-INF/manifest.xml`:

- Parses `manifest:encryption-data` on `manifest:file-entry` elements.
- Preserves checksum type/checksum, algorithm name and initialization vector,
  key-derivation name/iteration/salt, and start-key-generation name/key size.
- Adds `encrypted`, `canExposeBytes`, `declaredSize`, and `encryption`
  metadata to manifest entries, media reports, and image AST nodes.
- Keeps encrypted package bytes unavailable as normal import media while still
  reporting the stored encrypted entry size for reviewer diagnostics.
- Updates the WordPress ODF handoff example self-test to cover encrypted media
  preflight.

This is bounded to ODF manifest/resource preflight. It does not invoke Pandoc,
LibreOffice, Word, zip/unzip, browser renderers, external template engines,
TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 165 assertions, 0 failures`
- Red-first after adding the encrypted manifest test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 166 assertions, 1 failures`
  - Expected failure: missing `encrypted` manifest metadata.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 193 assertions, 0 failures`
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `19 test files, 4800 assertions, 0 failures`
- `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `477 -> 478`.
- `benchmarkDenominator.mapped`: `950 -> 951`.
- Focused `OdfReaderTest.php`: `7 -> 8` cases, `165 -> 193`
  assertions.
- `odfOpenDocumentCoreCases`: `10 -> 11`.
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`.
- `odfOpenDocumentCoreAssertions`: `217 -> 245`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local encrypted manifest preflight is not blocked by that
runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/styles/meta/media/table/
list/annotation/text-box/image, footnote/endnote, bookmark-reference, and
tracked-change clusters. It adds only bounded OpenDocument manifest encryption
metadata and media exposure policy.

Remaining ODT follow-up stays separate: formulas, charts, linked sections,
forms, richer style cascades, embedded-object/page-style policy, table
continuation semantics, export-side ODT writing, and full Pandoc ODT reader
parity.
