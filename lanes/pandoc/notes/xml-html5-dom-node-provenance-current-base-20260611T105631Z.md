# XML/HTML5 DOM node provenance slice

Base: origin/main 77ddea8948834d046d1506811ffd5da5c6f4f6cd
Bead: plib-cax3s

This slice adds bounded native PHP DOM node provenance for package-reader handoff:

- `XmlHtmlDom::xmlNodeProvenance()` reports deterministic namespace-aware XML paths from the document element, element names, namespaces, bounded attributes, child element counts, and text samples.
- `XmlHtmlDom::htmlFragmentNodeProvenance()` reports deterministic HTML fragment paths relative to the public fragment boundary, so the internal wrapper attribute never leaks into provenance.

Focused coverage:

- `reports namespace-aware xml node provenance paths for package readers`
- `reports html fragment node provenance paths without wrapper leakage`

Accounting:

- phpPass moves 3049 -> 3051.
- Mapped denominator moves 3185 -> 3187.
- `mappedXmlHtmlDomNodeProvenanceCases = 2`
- `xmlHtmlDomNodeProvenanceAssertions = 34`
- Focused `XmlHtmlDomTest.php`: 1 test file, 448 assertions, 0 failures.
- Full `lanes/pandoc/tests`: 44 test files, 62505 assertions, 0 failures.

No Pandoc, browser renderer, external validator, online service, zip/unzip, office suite, TeX/PDF engine, Node tooling, or Jupyter execution is used.
