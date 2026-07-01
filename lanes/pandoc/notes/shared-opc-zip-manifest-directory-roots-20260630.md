# Shared OPC ZIP manifest directory roots

Slice: `plib-4a52e`, shared ZIP/OPC package primitives.

`OpcRelationshipGraph::preflightZipEntryManifest()` and
`OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now carry
package directory-root provenance before XML package handoff. Each manifest row
reports its first package directory root, and manifest summaries expose root
counts, entry-name provenance, bytes, role counts, handoff-kind counts, validity
counts, and issue buckets.

The raw central-directory path reports the same root summaries without requiring
`ZipPackage` construction. Unknown ZIP64 sentinel byte counts remain metadata
only: root byte totals use exact counts where available and track
`unknownByteCountEntryCount` for entries that cannot provide bounded sizes yet.

Focused validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 4683 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` remains baseline-red with
  `303 test files, 118736 assertions, 9634 failures`; observed failures are in
  unrelated broad Markdown/plain-writer, Unicode reference label, YAML metadata,
  and related legacy suites, while the focused OPC manifest suite is green.

No Pandoc, office suites, TeX/PDF engines, browser renderers, `zip`/`unzip`,
`ZipArchive`, external validators, online services, live provider tests, or
live-service provider tests were invoked.
