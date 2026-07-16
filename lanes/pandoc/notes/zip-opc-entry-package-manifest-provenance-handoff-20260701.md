# ZIP OPC entry package manifest provenance handoff

Date: 2026-07-01
Slice: `plib-lmeau`

`OpcRelationshipGraph::preflightZipEntryManifest()` now carries the ZIP package
manifest's per-entry creator/version identity and local/central variable-field
provenance into OPC entry handoff rows. The raw central-directory OPC preflight
joins the same values from bounded ZIP preflight helpers, so reviewers can
compare standard package construction against raw package triage without
recomputing byte ranges.

The focused OPC fixtures now pin package-manifest parity for creator host
metadata, central-directory fixed headers, central variable fields, local
header variable fields, raw names, extra fields, entry comments, review-field
bytes, and the associated SHA-256 byte identities.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 5,149 assertions, 0 failures

Direct-format parity accounting remains unchanged. This slice is limited to
bounded native PHP ZIP/OPC package metadata and does not invoke Pandoc, office
suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node tooling, live
services, or external validators.
