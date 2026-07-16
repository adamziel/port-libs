# pandoc-odf-open-document-core-current-base-20260608T101142Z

## Scope

Bounded ODF/OpenDocument support-library slice for ordinary `draw:text-box`
frames. The slice preserves non-image frame metadata needed by Markdown and
WordPress review handoff:

- `draw:name`
- `draw:style-name`
- `text:anchor-type`
- `text:anchor-page-number`
- `svg:x`
- `svg:y`
- `svg:width`
- `svg:height`
- `draw:z-index`

The metadata is exposed on the AST as `odfFrameMetadata` and rendered as inert
`data-odf-frame-*` attributes on `odf-text-box` review divs.

## Source Truth

The pinned Pandoc upstream checkout is not hydrated in this isolated worktree
or under `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream
Haskell runner was available for this slice. The source truth used here is the
existing lane ODF contract around `draw:frame` image metadata and ODT content
XML mapping under `lanes/pandoc/src/OdfReader.php` and
`lanes/pandoc/tests/OdfReaderTest.php`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Implementation

- `OdfReader` now attaches frame metadata to `draw:text-box` div nodes when the
  enclosing frame carries more than just a name.
- Existing image-frame metadata behavior is left unchanged.
- The WordPress ODF handoff example now includes an anchored reviewer aside
  text box and verifies the rendered `data-odf-frame-*` attributes.

## Non-Overlap

This does not repeat accepted ODF slices for image frame dimensions, image
xlink metadata, image frame anchor metadata, text-box image captions, drop-down
fields, linked/hidden sections, generated indexes, field style names, tracked
changes, encoded package paths, or tab normalization.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
ODF XML reader, AST attributes, Markdown HTML block handoff, WordPress block
attribute rendering, and ODT ZIP/XML fixture helpers. Full upstream Pandoc
runner parity remains gated on a hydrated pinned Pandoc checkout and
non-mutating Cabal/Haskell plan, outside this isolated micro-slice.

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` =>
  `1 test files, 1846 assertions, 0 failures`

Red-first after adding the focused test:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` =>
  `1 test files, 1850 assertions, 1 failures`

Focused final verification:

- `php -l lanes/pandoc/src/OdfReader.php` =>
  `No syntax errors detected`
- `php -l lanes/pandoc/tests/OdfReaderTest.php` =>
  `No syntax errors detected`
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` =>
  `No syntax errors detected`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` =>
  `1 test files, 1861 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php` =>
  `2 test files, 1956 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` =>
  `odf open document handoff self-test ok`

Post-status verification:

- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` =>
  `pandoc json ok`
- `git diff --check -- lanes/pandoc` => passed with no whitespace errors

## Status Delta

- `lane-status.json` `phpPass`: `1611` -> `1612`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2030` ->
  `2031`
- `odfOpenDocumentCoreCases` / `mappedOdfOpenDocumentCoreCases`: `12` -> `13`
- `odfOpenDocumentCoreAssertions`: `276` -> `291`

## Next

Continue ODF/OpenDocument work on a non-overlapping content/styles/meta XML
gap such as note/cross-reference metadata, richer draw object positioning,
style inheritance for frames, or export-side ODT writer handoff.
