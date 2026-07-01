# ZIP Package Manifest Internal Attributes

2026-07-01 `plib-059mr`

`ZipPackage::packageManifestPreflight()` now carries central-directory internal-file-attribute provenance through the shared ZIP/OPC manifest. Each manifest entry exposes the raw internal attribute bits, fixed-width hex form, decoded attribute names, text-bit and unknown-bit flags, and issue labels.

The manifest also includes deterministic internal-attribute rollups:

- total, text-bit, and unknown-bit entry counts
- exact internal-attribute hex values
- grouped summaries by exact bit pattern with directory root, extension-key, source-byte, and issue metadata
- compact entry lists for any internal attributes, text-bit entries, and unknown-bit entries

The fields are metadata-only and preserve the existing strict-import blocking policy. Focused validation covered `ZipPackageTest.php` and the OPC package manifest handoff tests without invoking external Pandoc, office suites, TeX/browser engines, Typst, Jupyter, Node, zip/unzip, validators, or live services.
