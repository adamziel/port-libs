# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with object metadata, copy, list, checksum sync plan, case-insensitive provider path lookup, rclone-style path filter rules, hash set/type aliases, multi-hashing, check report sigils, one-way checks, filtered copy-changed planning, checksum manifest parsing and verification, hashsum-style output, `lsf` path/size/hash listings, and `lsjson` list/stat JSON manifests.

## Filtered Backup Example

The fixture in `../fixtures/wordpress-backup-tree.php` models a small WordPress backup set with uploads, cache files, logs, WXR export data, and a SQL dump. The example in `../examples/wordpress-filtered-backup.php` includes uploads plus export/database artifacts while excluding cache, debug logs, and heavyweight design source files before planning changed paths. The current copy-changed test then copies only the included missing/changed artifacts and verifies the next filtered sync is empty.

The checksum and listing slice adds native pieces needed to publish or consume portable backup manifests: md5sum-style checksum files, hashsum output, stdin hash lines, and `lsf`-style path/size/hash listings for filtered WordPress artifacts.

The `../examples/wordpress-lsjson-manifest.php` example emits an rclone-style recursive JSON catalog for portable WordPress backup artifacts with MD5 hashes and metadata, while leaving cache, debug log, and source design files out of the published manifest.

The `../examples/wordpress-checksum-verify.php` example validates a published MD5 manifest against the portable backup set. It uses case-insensitive path matching to model shared-hosting and cloud-provider casing drift while still reporting rclone-style combined check lines.

The `../examples/wordpress-case-insensitive-stat.php` example models an rclone provider that advertises case-insensitive path behavior. Differently-cased upload and database requests resolve to canonical provider paths in `lsjson --stat` output, which is useful when WordPress backup manifests are moved between shared hosts, local filesystems, and cloud providers with different casing rules.

## Next Task

Map download-mode checksum verification/error behavior or deeper fs provider contract behavior beyond the in-memory case-insensitive lookup slice.
