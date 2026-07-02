# DOCX ZIP source-record package path byte-length buckets

Work item: plib-u94sz

DocxOpenXmlReader now groups loaded DOCX ZIP source records by package path byte-length buckets. The package provenance summary and package identity expose ordered `up-to-8-bytes`, `9-to-16-bytes`, `17-to-32-bytes`, `33-to-64-bytes`, and `over-64-bytes` buckets with part counts, source-record byte totals, compressed/uncompressed byte totals, content-type source/base rollups, compression-method rollups, role counts, longest entry names, and sanitized largest source-record part metadata.

The focused fixture uses native `ZipPackage::fromParts` coverage and does not invoke Pandoc, office suites, zip/unzip tools, browser engines, validators, or package payload byte exposure.
