# DOCX OpenXML Content Control Package Summary

Slice: `plib-7rkwx` on 2026-07-01.

The DOCX/OpenXML reader already records custom XML content-control data binding details under `docx.contentControls`. This slice promotes the missing-store-item package-review field into `docx.packageProvenance.summary` so callers can inspect unbound data bindings from the package summary without walking every control item.

The focused fixture covers a block-scoped content control with `w:dataBinding` that has XPath and prefix mappings but no `w:storeItemID`. The reader reports the missing-store-item diagnostic and preserves the metadata-only handoff; no Pandoc, office suite, browser, unzip/zip, or external validator is invoked.
