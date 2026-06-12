# ODT manifest file-entry child provenance

This slice preserves direct `manifest:file-entry` child element provenance in compact OpenDocument package review packets.

Behavior added:

- `OpenDocumentPackage` records direct child elements for every manifest file entry, marking `manifest:encryption-data` as known and all other child elements as unknown.
- Child records include qualified element names, namespace URIs, local names, known/role flags, and compact attribute provenance.
- Unknown direct children add `odf-manifest-file-entry-unknown-child` diagnostics.
- Child and unknown-child counts propagate through manifest entries, media part summaries, `manifestReview` items/order/aggregate unknown-child lists, and ZIP `packageInventory` entries.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` (594 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 68193 assertions)

The slice stays native PHP and does not invoke Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
