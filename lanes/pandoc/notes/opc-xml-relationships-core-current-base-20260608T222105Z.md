# OPC XML Relationships Core Current Base 2026-06-08T222105Z

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T222105Z`
Base accepted HEAD: `482dab075a80303a3de728b33addb2e6ee48c0a9`

## Behavior

Added bounded native XMLDSig digest policy metadata to OPC package-signature
preflight. `OpcRelationshipGraph` now classifies `ds:DigestMethod`
algorithms on both `SignedInfo` references and package-signature
`ds:Object`/`ds:Manifest` references:

- `digestAlgorithmKnown`
- `digestAlgorithmProfile`
- `digestExpectedDecodedBytes`
- `digestValueLengthValid`

The slice covers SHA-1, SHA-256, SHA-384, SHA-512, and RIPEMD-160 algorithm
URIs. Unknown algorithms are surfaced as unknown metadata without changing the
existing structural-validity result. Placeholder digest values in current
fixtures remain valid structurally while still reporting decoded-length
mismatches for reviewer/importer policy.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` notes existed before work.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2664 assertions, 0 failures`.
- Red-first: same command failed with `1 test files, 2666 assertions, 1 failures`
  because the new digest metadata keys were absent.
- Final: same command passed with `1 test files, 2677 assertions, 0 failures`.

No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool,
online service, live provider test, or live-service provider test was executed.
Root harness was not run - isolated micro-slice.

## Non-Overlap

This builds on the accepted OPC signature content-type and relationship
transform preflight work without changing relationship target resolution,
relationship transform materialization, signature relationship role checks, or
encrypted/embedded-package policy.

## Dependency Closure

No new support component is needed. The slice reuses native
`OpcRelationshipGraph` signature preflight, DOM parsing, content-type lookup,
`ZipPackage` fixtures, and focused `OpenPackagingConventionsTest` coverage.
Full cryptographic XMLDSig validation and external validator parity remain out
of scope for this bounded support-library slice.

## Next

A non-overlapping follow-up could add native digest-comparison handoff when
canonical bytes are supplied, deeper relationship-transform selector policy,
or package-signature object reference cross-checks without invoking external
XMLDSig validators.
