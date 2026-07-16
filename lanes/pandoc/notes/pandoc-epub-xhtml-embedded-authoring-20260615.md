# pandoc-epub-xhtml-embedded-authoring-20260615

Slice: EPUB3 package ingestion, XHTML embedded media authoring provenance after
rebase onto current main `3b4a5b1bed`.

## Behavior

`EpubReader` now carries XHTML embedded resource authoring metadata into static
package review rows:

- audio/video controls, autoplay, loop, muted, preload, playsinline, and size
  attributes;
- `source` type/media/sizes hints;
- timed-text `track` kind, srclang, label, and default state;
- `object`, `embed`, and `iframe` review attributes, including iframe sandbox,
  allow/referrer/loading, and inert `srcdoc` length/SHA-256 provenance.

Autoplay media is reported as `autoplay-xhtml-media-resource` so review packets
can flag playback semantics without executing media or fetching remote content.

## Evidence

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 4687 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `46 test files, 87858 assertions, 0 failures`.

## Scope

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, media playback,
external validator, online service, live provider test, or live-service provider
test was invoked.

This does not repeat accepted EPUB OCF sidecar, OPF metadata, manifest/spine,
navigation, media-overlay, CSS resource, XHTML link/script/style/form, ruby,
table, fallback, or byte-provenance slices. The new surface is limited to
authoring metadata for already discovered XHTML embedded resource references.
