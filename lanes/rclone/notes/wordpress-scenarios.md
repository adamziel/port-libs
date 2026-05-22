# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with advertised hash sets, object metadata, copy, list, checksum sync plan, case-insensitive provider path lookup, rclone-style path filter rules, hash set/type aliases, multi-hashing, check report sigils, one-way checks, filtered copy-changed planning, checksum manifest parsing and verification including download mode for providers without advertised hashes, `CheckEqualReaders`-style byte comparison for downloaded artifacts, provider-to-provider `CheckDownload` byte/error reporting, hashsum-style output, `lsf` path/size/hash listings, and `lsjson` list/stat JSON manifests.

## Filtered Backup Example

The fixture in `../fixtures/wordpress-backup-tree.php` models a small WordPress backup set with uploads, cache files, logs, WXR export data, and a SQL dump. The example in `../examples/wordpress-filtered-backup.php` includes uploads plus export/database artifacts while excluding cache, debug logs, and heavyweight design source files before planning changed paths. The current copy-changed test then copies only the included missing/changed artifacts and verifies the next filtered sync is empty.

The checksum and listing slice adds native pieces needed to publish or consume portable backup manifests: md5sum-style checksum files, hashsum output, stdin hash lines, and `lsf`-style path/size/hash listings for filtered WordPress artifacts.

The `../examples/wordpress-lsjson-manifest.php` example emits an rclone-style recursive JSON catalog for portable WordPress backup artifacts with MD5 hashes and metadata, while leaving cache, debug log, and source design files out of the published manifest.

The `../examples/wordpress-checksum-verify.php` example validates a published MD5 manifest against the portable backup set. It uses case-insensitive path matching to model shared-hosting and cloud-provider casing drift while still reporting rclone-style combined check lines.

The `../examples/wordpress-download-checksum-verify.php` example models a provider that does not advertise MD5 or other hashes. Ordinary checksum verification rejects that provider capability, while download mode hashes the portable WordPress backup bytes locally and verifies the same manifest.

The `../examples/wordpress-download-byte-compare.php` example compares restored WXR and SQL artifacts byte-for-byte and shows a corrupted upload object as unequal, matching the native download comparison slice used when checksum metadata is unavailable.

The `../examples/wordpress-provider-download-check.php` example compares two no-hash providers as a restore validation pass. It reports a corrupted uploaded image with `*` and an interrupted database stream with `!`, matching the upstream `CheckDownload` distinction between content differences and download errors.

The `../examples/wordpress-case-insensitive-stat.php` example models an rclone provider that advertises case-insensitive path behavior. Differently-cased upload and database requests resolve to canonical provider paths in `lsjson --stat` output, which is useful when WordPress backup manifests are moved between shared hosts, local filesystems, and cloud providers with different casing rules.

## Next Task

Map `operations.ReOpen` retry/range reader behavior or deeper fs provider contract behavior beyond the in-memory checksum/listing/download-check slices.
