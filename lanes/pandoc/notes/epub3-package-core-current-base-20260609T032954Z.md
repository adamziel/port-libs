# EPUB3 OPF Metadata Refinement Preflight

Slice: `pandoc-epub3-package-core-current-base-20260609T032954Z`
Base accepted HEAD: `507b06f9840603abbb77bf4b360c0377f959830e`

## Behavior

Implemented bounded native PHP EPUB3 package preflight support for OPF metadata
refinements.

- `EpubPackage` now preserves OPF `meta refines` entries grouped by subject id
  for package metadata review.
- Lightweight package metadata now exposes structured title, creator,
  contributor, and identifier detail packets while keeping existing scalar
  `title`, `creators`, `language`, `identifier`, `properties`, and `meta`
  fields stable.
- Title details include `title-type`, `file-as`, `display-seq`, and
  `alternate-script` refinements, with `mainTitle`, `subtitle`, `shortTitle`,
  `sortTitle`, and `titlesByType` summary fields for importer handoff.
- Creator/contributor details include `role`, `file-as`, `display-seq`, and
  role grouping; identifier details include `identifier-type` grouping.
- `summary()['wordpressImport']['metadataDetails']` now carries the refinement
  handoff so WordPress EPUB preflight can surface sortable titles, creator
  roles, and identifier types without running Pandoc or external tools.

## Evidence

Baseline focused verification before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 62 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 90 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`
- `git diff --check -- lanes/pandoc`
- Result: passed

Focused delta: +1 PHP PASS case and +28 focused assertions in
`EpubPackageTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubPackage`,
`ZipPackage`, DOM/libxml NONET XML parsing, existing OPF metadata preflight,
focused PHP tests, and the WordPress EPUB3 package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, browser renderer, external converter, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the already accepted EPUB vendor metadata, rich
`EpubReader` metadata refinement, OPF manifest property vocabulary,
metadata-link vocabulary, accessibility metadata, nav/NCX page-list and label
audio, XHTML viewport/language/meta-refresh/form/ping/link/script/switch/
trigger/semantic, media-overlay, remote-resources reconciliation, CSS resource,
OCF sidecar, encryption, asset fallback, or XHTML embedded media/object/frame
slices. It is restricted to the lightweight `EpubPackage` preflight metadata
detail handoff.

## Follow-Up

Next EPUB3 package work should choose a non-overlapping package gap such as OPF
link records in lightweight preflight, media-overlay summary parity, or a
remaining nav/NCX provenance edge not already covered by the rich reader.
