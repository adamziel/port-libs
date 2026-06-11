# Pandoc EPUB3 Rootfile ZIP Provenance

Slice: `plib-d0wgw`

This slice extends native EPUB package ingestion so OCF `container.xml`
rootfile validation carries ZIP provenance for each declared rootfile target.

- Present rootfiles now report byte length, compressed byte length,
  compression method/name/support, CRC32, and byte-exposure status.
- Missing rootfiles retain null ZIP metadata and `canExposeBytes=false`.
- Selected, alternate, duplicate, and non-OPF rootfile reports all preserve the
  same provenance shape because they reuse the validation item.

Focused coverage lives in `lanes/pandoc/tests/EpubPackageTest.php`.
