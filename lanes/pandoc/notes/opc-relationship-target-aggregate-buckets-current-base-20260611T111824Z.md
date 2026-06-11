# OPC relationship target aggregate buckets current-base slice

Issue: `plib-qlhox`
Base: `984cac5415393303d463dc471fd1d4d0bf030586`

This slice extends `OpcRelationshipGraph::relationshipTargetSummary()` with
package-wide aggregate buckets for reviewer handoff. The existing per-target
records are preserved, and the summary now also exposes:

- relationship counts by source part;
- relationship counts by relationship type;
- external target counts by URI kind and scheme;
- target content-type counts.

The focused fixture builds a bounded native OPC package with package,
document, and footnote relationship sources. It covers internal targets,
missing targets, query/fragment targets, safe and unsafe external targets,
relative external targets, filtered image relationships, and content-type
bucket summaries.

Verification:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 4051 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62612 assertions, 0 failures`

No Pandoc binary, office suite, zip/unzip tool, Cabal/Haskell runner, browser
renderer, external validator, online service, or live-provider test was invoked.
