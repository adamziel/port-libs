# DOCX ZIP Source Creator Host System Maps

Slice: `plib-76wqa`

`DocxOpenXmlReader` now promotes compact creator-host metadata from loaded DOCX
ZIP source records:

- `creatorHostSystemCounts` and `entryNamesByCreatorHostSystem` on
  `zipPackage.sourceRecords`;
- `zipSourceCreatorHostSystemCounts` and
  `zipSourceEntryNamesByCreatorHostSystem` in package summary;
- the same compact maps in package identity so metadata-only creator-host
  changes affect the stable review digest.

This remains ZIP/package provenance only. The reader does not shell out to
Pandoc, Office, unzip/zip tools, external validators, or live services, and the
identity entries continue to avoid payload byte exposure.
