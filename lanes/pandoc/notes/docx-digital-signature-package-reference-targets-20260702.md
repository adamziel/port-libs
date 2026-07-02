# DOCX digital signature package reference targets

`plib-mey5c` adds a metadata-only DOCX/OpenXML digital-signature rollup for package-part references inside XMLDSIG `Reference` elements.

The reader already parsed each reference URI and kind. This slice promotes the unique signed package-part target names and their non-empty query/fragment suffixes into `docx.packageProvenance.digitalSignatures` and `docx.packageProvenance.summary`, so package reviewers can see which package parts a signature claims without walking every signature item.

The implementation remains native PHP and does not validate cryptographic signatures, invoke Office/Pandoc/ZIP tools, fetch external references, or expose package payload bytes.
