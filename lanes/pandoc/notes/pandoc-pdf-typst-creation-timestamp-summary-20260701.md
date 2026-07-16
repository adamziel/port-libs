# Pandoc PDF/Typst Creation Timestamp Summary

`PdfEngineHandoff` now carries compact Typst creation-timestamp summary fields through
`typstBoundarySummary` alongside the existing boundary provenance and matrix case.
The summary records selected timestamp value/Unix/ISO-8601 fields, deterministic,
invalid, Unix-second, history, override, and environment-shadow counts so
`SOURCE_DATE_EPOCH` versus `--creation-timestamp` handoffs remain reviewable in the
metadata-only PDF engine boundary.

Direct-format parity accounting remains active for the Pandoc lane. This slice does
not execute Typst, PDF engines, Pandoc, TeX/browser engines, office suites, Node,
zip/unzip, Jupyter, external validators, or live services.
