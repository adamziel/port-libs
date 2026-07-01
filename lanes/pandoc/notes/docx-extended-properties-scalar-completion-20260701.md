DOCX extended properties scalar completion

- Added native DOCX ingestion for the remaining common scalar app-property fields in `docProps/app.xml`: `PresentationFormat`, `Slides`, `Notes`, `TotalTime`, `HiddenSlides`, and `MMClips`.
- The values flow through the existing `docx.extendedProperties` and `meta.docxExtendedProperties` surfaces without exposing package bytes or using external DOCX tooling.
- Focused coverage lives in `DocxOpenXmlReaderTest.php` by extending the root relationship extended-properties fixture.
