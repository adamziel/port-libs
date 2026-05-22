# rclone WordPress Scenario

Portable backup/import/export sync for shared hosts and cloud storage providers.

## Current Native Slice

Native in-memory provider contract with object metadata, copy, list, checksum sync plan, and rclone-style path filter rules.

## Filtered Backup Example

The fixture in `../fixtures/wordpress-backup-tree.php` models a small WordPress backup set with uploads, cache files, logs, WXR export data, and a SQL dump. The example in `../examples/wordpress-filtered-backup.php` includes uploads plus export/database artifacts while excluding cache, debug logs, and heavyweight design source files before planning changed paths.

## Next Task

Map filesystem provider contract tests, hash set behavior, and rclone check/copy semantics.
