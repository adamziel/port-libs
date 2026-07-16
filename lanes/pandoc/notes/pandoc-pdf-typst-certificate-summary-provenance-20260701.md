# PDF/Typst certificate summary provenance

Slice: `plib-6xm5i`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now carries compact Typst certificate boundary rollups in
`typstBoundarySummary`:

- selected certificate count remains distinct from total boundary certificate
  entries;
- safe, unsafe, relative, workspace, absolute, URI, and invalid certificate
  counts;
- CLI versus environment certificate counts and deterministic environment
  variable names;
- certificate environment presence, shadowing state, unique issue codes, and
  issue counts.

The summary complements the existing certificate policy and `certificate-paths`
boundary matrix case so reviewer handoff can see certificate boundary pressure,
including shadowed `TYPST_CERT`, without reading engine outputs or executing
Typst/PDF engines. Direct-format parity remains active in blocker notes.

Validation targets:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstCertificateSummaryProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstCertificateSummaryProvenanceTest.php`
