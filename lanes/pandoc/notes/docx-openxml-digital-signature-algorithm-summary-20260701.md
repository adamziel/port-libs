# DOCX OpenXML Digital Signature Algorithm Summary

Slice: `plib-j1jfs` on 2026-07-01.

The DOCX/OpenXML reader already parses XML signature metadata under `docx.digitalSignatures`, including reference transform algorithms, digest method algorithms, signature method algorithms, and canonicalization method algorithms. This slice promotes those metadata-only algorithm rollups into `docx.packageProvenance.summary` with unique counts so callers can review signature package shape without walking every signature item.

No cryptographic validation is performed and no signature/package bytes are exposed. The focused fixture stays native PHP only and does not invoke Pandoc, office suites, TeX/browser engines, unzip/zip, Node tooling, or external validators.
