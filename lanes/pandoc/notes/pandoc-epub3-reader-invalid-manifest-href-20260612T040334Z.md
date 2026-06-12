# Pandoc EPUB3 reader invalid manifest href - 20260612T040334Z

Bead: plib-juqnb
Base: origin/main acb8fb36b3

Focused verification:
`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
Result: 1 test file, 4185 assertions, 0 failures.

Full verification:
`php tools/run-tests.php lanes/pandoc/tests`
Result: 44 test files, 70485 assertions, 0 failures.

Mapped one native `EpubReader` package-ingestion boundary case. Invalid non-spine OPF manifest href targets now stay visible as `invalid-manifest-href-target` review rows in manifest import reports, byte provenance, and asset summaries without aborting XHTML handoff.

No Pandoc, EPUBCheck, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.
