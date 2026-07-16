# Pandoc PDF/Typst Decimal PPI Boundary Slice

Slice: `plib-9lccc`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now accepts Typst decimal `--ppi` values such as `144.5`
as bounded numeric provenance instead of marking them invalid. Integer PPI
values keep their existing integer shape, while decimal values carry through
the Typst boundary plan, summary, boundary matrix, fake-run artifact review,
and fake-run sequence summaries.

This stays native PHP only. No Pandoc binary, Typst engine, TeX/PDF engine,
browser renderer, office suite, archive tool, external validator, or online
service is invoked.

Direct-format parity accounting remains active: this closes a narrow
PDF/Typst handoff provenance gap, not full Typst/PDF output parity.
