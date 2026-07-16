# ODF Media Resource Role Conflicts 2026-06-13

Implemented a bounded native PHP ODF/OpenDocument package provenance slice in
`OdfReader`.

The reader now records a stable `packageProvenance.mediaResources` summary for
manifest-declared package media candidates. The summary uses normalized
`manifest:media-type` bases to classify image, audio, and video resources,
records path-vs-declared media-family conflicts, keeps missing media entries
visible, and records package-role precedence when sidecar roles such as
`ObjectReplacements/` or `Thumbnails/` must stay out of document media handoff.
The follow-up adds explicit `pathMediaTypeConflictItems`,
`pathMediaTypeConflictPairs`, and `missingMediaResourceItems` buckets so
downstream diagnostics do not need to infer path/media-type conflicts or missing
resources from the aggregate item list.

The existing package ingestion and media handoff ordering are preserved. Audio
and video declarations receive the same `media-resource` inventory role as image
resources when no package sidecar role takes precedence; sidecar package roles
remain metadata-only review items.

Verification:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 file, 4611 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 79452 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- conflict-marker scan over touched ODF/metadata files
- `git diff --check`

No Pandoc binary, Cabal/Haskell runner, office suite, zip/unzip, ZipArchive,
browser renderer, Node tooling, online service, live provider test,
live-service provider test, or external validator was invoked.
