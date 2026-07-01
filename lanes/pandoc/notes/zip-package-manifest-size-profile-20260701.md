# ZIP package manifest size profile

`ZipPackage::packageManifestPreflight()` now carries the shared size-profile
provenance already exposed by `ZipPackage::sizePreflight()` into the
metadata-only package manifest used by ZIP/OPC handoff.

The manifest now reports:

- whole-package expansion ratio and largest-entry summary;
- zero-byte entry, zero-byte file, and empty-directory counts;
- zero-byte entry summaries using the existing size-preflight entry shape;
- unknown expansion-ratio counts and entry summaries for zero-compressed,
  non-empty declarations.

Focused coverage keeps the deterministic manifest hash fixture updated and
checks that zero-byte and unknown-ratio package manifests mirror
`sizePreflight()` without invoking external ZIP, office, browser, or converter
tooling.
