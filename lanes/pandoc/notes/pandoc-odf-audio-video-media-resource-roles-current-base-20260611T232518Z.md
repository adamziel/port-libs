# ODF Audio/Video Media Resource Role Slice

Current base at implementation: `4d330e2a13`.

This slice extends native PHP ODF/OpenDocument package ingestion so `OdfReader`
classifies manifest-declared audio and video package parts as `media-resource`
ZIP inventory roles. The role check now uses normalized manifest media-type
bases, so parameterized values such as `audio/ogg; codecs="opus"` classify the
same way as images and `Pictures/` entries.

The new focused test proves that ODT audio/video package resources are present
in media handoff, media-type summaries, package provenance roles, and byte/CRC
review metadata without invoking Pandoc, office suites, zip/unzip, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Direct-format parity accounting:
- `phpPass`: `3140 -> 3141`
- `phpFail`: `0`
- `mappedOdfMediaResourcePackageRoleCases`: `0 -> 1`
- `odfMediaResourcePackageRoleAssertions`: `0 -> 35`

Verification:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 4089 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67075 assertions, 0 failures`

This does not repeat prior ODF slices for manifest suffixes, missing media
types, encryption provenance, thumbnails, RDF/signature sidecars, script
packages, or compact OpenDocumentPackage audio/video media summaries.
