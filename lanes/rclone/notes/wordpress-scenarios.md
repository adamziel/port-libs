# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with object metadata, copy, list, checksum sync plan, rclone-style path filter rules, hash set/type aliases, multi-hashing, check report sigils, one-way checks, filtered copy-changed planning, checksum manifest parsing, hashsum-style output, and `lsf` path/size/hash listings.

## Filtered Backup Example

The fixture in `../fixtures/wordpress-backup-tree.php` models a small WordPress backup set with uploads, cache files, logs, WXR export data, and a SQL dump. The example in `../examples/wordpress-filtered-backup.php` includes uploads plus export/database artifacts while excluding cache, debug logs, and heavyweight design source files before planning changed paths. The current copy-changed test then copies only the included missing/changed artifacts and verifies the next filtered sync is empty.

The checksum and listing slice adds native pieces needed to publish or consume portable backup manifests: md5sum-style checksum files, hashsum output, stdin hash lines, and `lsf`-style path/size/hash listings for filtered WordPress artifacts.

## Next Task

Map deeper fs provider contract behavior, `lsjson` output, or checksum verification against parsed sum files.
