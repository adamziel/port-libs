# Pandoc ODF OpenDocument Core Slice

## Scope

Implemented bounded OpenDocument package-path normalization in the current
native `OdfReader`:

- Decodes percent-encoded package references before ZIP lookup, so ODT
  manifest entries such as `Pictures/source%20hero.png` resolve to stored
  package parts such as `Pictures/source hero.png`.
- Applies the same normalization to `draw:image` and `draw:object`
  references, including MathML object packages such as `./Object%201`.
- Keeps the original encoded image URL for WordPress block output while
  exposing decoded `part` and `sourcePart` metadata for importer audit.
- Rejects decoded traversal, backslash, and scheme-shaped package paths,
  including `%2e%2e` traversal attempts.
- Updates the WordPress ODF handoff smoke to cover encoded manifest/image/math
  object references without invoking office tooling.

This is bounded to ODT/OpenDocument package references and the existing native
ZIP package reader. It does not invoke Pandoc, Cabal, Haskell runners, Word,
LibreOffice, zip/unzip, external office tools, browser renderers, external
conversion services, or online services.

## Source Truth

The local upstream cache for this isolated worktree does not include a hydrated
Pandoc checkout or Cabal package files. This slice uses the OpenDocument package
contract already activated for `odf-open-document-core`: package entries and
`xlink:href` references are URI-like enough to carry percent-encoded spaces,
while the actual ZIP part names remain decoded package names. The behavior is
bounded to safe package-relative lookup and preserves the existing traversal
preflight after decoding.

## Evidence

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: 1 test file, 547 assertions, 0 failures.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: 1 test file, 568 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'`
- Whitespace:
  - `git diff --check -- lanes/pandoc`

## Status Delta

- `phpPass`: 790 -> 791.
- `benchmarkDenominator.mapped`: 1250 -> 1251.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- `odfOpenDocumentCoreAssertions`: 217 -> 238.
- Focused `OdfReaderTest.php`: 24 -> 25 PASS cases, 547 -> 568 assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`OdfReader`, `ZipPackage`, AST, WordPress block writer, and ODF handoff fixture
builder. Full upstream Pandoc runner parity remains blocked on hydrating the
pinned upstream checkout and Cabal package metadata.

## Non-Overlap

This avoids the accepted ODT mimetype/manifest/content/styles/meta/media/table
base cluster and the later ODT bookmark, reference mark, sequence, field,
bibliography mark, annotation range, nested-list style inheritance,
text-position, MathML object, linked/protected section, tracked-change,
encrypted-manifest, image-dimension, link-metadata, list-header, and protected
table metadata clusters. It adds only safe URI-decoded ODF package part lookup
for manifest/media/object handoff.
