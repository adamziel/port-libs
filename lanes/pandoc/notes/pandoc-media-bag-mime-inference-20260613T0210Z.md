# Pandoc MediaBag MIME Inference

Implemented one bounded native PHP media/resource handoff slice for package-local
linked resources whose supplied or preloaded bytes omit `mimeType`.

## Behavior

- `MediaBag::insertMedia()` now infers common package/resource MIME types from
  source paths beyond images, PDF, and plain text: CSS, JavaScript, JSON/XML/HTML,
  audio/video, fonts, EPUB, Markdown, CSV, and TSV.
- Data URI and hashed remote or URL-suffixed resources now receive MIME-derived
  hash extensions for the same resource classes.
- The focused test proves inferred CSS, audio, font, and JSON MIME provenance is
  preserved through extraction attributes, Markdown output, WordPress links, and
  JSON/native round-trip.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines,
external validators, online services, live provider tests, or live-service
provider tests.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 168 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 45 test files, 75296 assertions, 0 failures.

Status delta: `phpPass` moves from `3345` to `3346`; `phpFail` remains `0`.
`UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `3304` to `3305`.
The slice records `mappedMediaBagMimeInferenceCases=1` and
`mediaBagMimeInferenceAssertions=24`.
