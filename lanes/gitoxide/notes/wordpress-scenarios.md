# gitoxide WordPress Scenario

Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.

## Current Native Slice

Native loose Git object storage with canonical object headers, SHA-1 object IDs, commit header parsing, tree entry parsing/serialization, and loose direct/symbolic reference parsing/storage.

## WordPress Deploy Tree Example

`examples/wordpress-content-tree.php` parses a fixture Git tree with `.wp-env.json`, `wp-config.php`, and `wp-content` entries. This models server-side PHP code inspecting a WordPress repository snapshot in shared hosting, Playground, package install, or migration tooling without shelling out to `git`.

## WordPress Reference Example

`examples/wordpress-references.php` writes and reads `HEAD`, `refs/heads/main`, and `refs/remotes/origin/HEAD` using native PHP loose-ref files. This models a shared-hosting deployment tool or Playground snapshot manager discovering the active WordPress branch and its commit without invoking the Git binary.

## Next Task

Map packed-ref fixture parsing and lookup rules from `gix-ref`, starting with the committed `packed-refs/without-header` and `packed-refs/unsorted` fixtures.
