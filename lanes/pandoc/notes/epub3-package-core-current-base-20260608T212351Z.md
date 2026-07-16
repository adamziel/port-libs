# EPUB3 Package Current-Base: XHTML Meta Refresh Handoff

Slice: `pandoc-epub3-package-core-current-base-20260608T212351Z`
Base: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Behavior

- Added native EPUB XHTML `<meta http-equiv="refresh">` package-resource handoff.
- Refresh targets now remain inert but are exposed as `contentRefreshes`, `refreshItems`, and `contentRefreshDiagnostics`.
- Refresh URLs are also added to normal `contentReferences`, so remote-resource reconciliation sees external refresh targets and reports undeclared `remote-resources` policy gaps without fetching anything.
- WordPress raw HTML spine blocks now expose `contentRefreshes` and `contentRefreshDiagnostics` alongside existing links, scripts, switches, triggers, and semantic metadata.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Red-first command: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Failed as expected with `1 test files, 2817 assertions, 1 failures`.
  - Failure: `xhtmlResourceReport.refreshAssetCount` was absent for the new meta-refresh case.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Passed with `1 test files, 2876 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Passed with `epub3 package handoff self-test ok`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native EPUB3 package/XHTML scan, OPC package path resolution, manifest media metadata, remote-resource reconciliation, and WordPress EPUB package handoff example.

Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, external converters, external XML/HTML tools, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Next

Choose a non-overlapping EPUB3 package edge such as nav/NCX target provenance refinement, media-overlay resource handoff, or XHTML form/ping side-effect policy. Avoid repeating active XHTML script/link/switch/trigger/semantic/viewport coverage and this meta-refresh resource-policy handoff.
