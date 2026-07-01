# XML/HTML5 DOM XML Attribute Records

## Scope

- Added public `XmlHtmlDom::xmlAttributeRecords()` for bounded XML attribute
  enumeration with qualified name, local name, prefix, namespace URI, value,
  namespaced flag, and namespace-declaration flag.
- Added public `XmlHtmlDom::xmlAttributes()` as the sorted qualified-name map
  counterpart and routed the existing internal XML root-attribute map through
  it.
- Covered namespaced package-style attributes (`xml:*`, relationship-style
  prefixes, and plain attributes) without changing existing namespace review
  packets.

## Direct-format parity accounting

This slice is a shared XML DOM primitive for DOCX/EPUB/ODF-style package
readers. It does not claim direct XML reader parity and does not repeat the
existing namespace declaration, namespace scope, HTML attribute policy, or
fragment declaration recovery review packets.
