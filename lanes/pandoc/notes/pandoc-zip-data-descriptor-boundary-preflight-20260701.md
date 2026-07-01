# ZIP data descriptor boundary preflight

Issue: `plib-f2zku`

The shared `ZipPackage::dataDescriptorIntegrityPreflight()` raw package gate now
classifies descriptor boundaries before package construction when central
directory sizes place a data-descriptor entry directly on the next ZIP record.

The preflight records metadata-only boundary provenance:

- bytes before the next local or central-directory record;
- whether the descriptor offset begins with a known ZIP record signature;
- the signature hex and record kind when present;
- missing/truncated issue codes for descriptors that end at the next local
  header or central-directory boundary.

The raw OPC central-directory manifest path now also passes through the same
metadata-only ZIP EOCD fixed-field source summary as `ZipPackage` package
manifests, so package-backed and raw OPC preflights keep package source records
in parity.

This preserves the native PHP direct-format path for DOCX/EPUB/ODF ZIP package
readers without invoking Pandoc, office suites, external ZIP tools, browser
engines, TeX, Typst, Node, Jupyter, or validators.

Focused coverage lives in `lanes/pandoc/tests/ZipPackageTest.php` under the
`preflights missing data descriptors before the next local header record` case
and in `lanes/pandoc/tests/OpenPackagingConventionsTest.php` source-record
parity coverage.
