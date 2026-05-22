# gitoxide WordPress Scenario

Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.

## Current Native Slice

Native loose Git object storage with canonical object headers, SHA-1 object IDs, commit header parsing, tree entry parsing/serialization, loose direct/symbolic reference parsing/storage, packed-ref header/reference/peeled lookup parsing, loose+packed reference-store overlay resolution, v2 pack-index parsing/lookup, pack data entry decoding, OFS_DELTA/REF_DELTA object resolution, and pack+loose+alternate object database lookup/prefix/iteration with replacement refs.

## WordPress Deploy Tree Example

`examples/wordpress-content-tree.php` parses a fixture Git tree with `.wp-env.json`, `wp-config.php`, and `wp-content` entries. This models server-side PHP code inspecting a WordPress repository snapshot in shared hosting, Playground, package install, or migration tooling without shelling out to `git`.

## WordPress Reference Example

`examples/wordpress-references.php` writes and reads `HEAD`, `refs/heads/main`, and `refs/remotes/origin/HEAD` using native PHP loose-ref files. This models a shared-hosting deployment tool or Playground snapshot manager discovering the active WordPress branch and its commit without invoking the Git binary.

## WordPress Packed Reference Example

`examples/wordpress-packed-refs.php` parses a compacted `packed-refs` buffer with a WordPress deployment branch, remote-tracking branch, and peeled release tag. This models a PHP deployment or package manager inspecting compacted repository state on shared hosting without invoking `git show-ref` or `git for-each-ref`.

## WordPress Reference Store Example

`examples/wordpress-reference-store.php` combines loose `HEAD` with packed branch and release-tag refs. This models a shared-hosting deployment tool resolving the active WordPress branch from loose refs while reading compacted branch/tag state from `packed-refs`.

## WordPress Pack Index Example

`examples/wordpress-pack-index.php` parses a deterministic v2 pack index fixture for a WordPress repository and locates compacted object offsets, including a large 64-bit media object offset. This models a PHP object database finding packed content objects on shared hosting without invoking `git`.

## WordPress Pack Data Example

`examples/wordpress-pack-data.php` pairs a deterministic pack index with pack data, then reads a packed commit, blob, and OFS_DELTA-reconstructed blob by object ID. This models a PHP object database reading compacted WordPress content on shared hosting without invoking `git cat-file`.

## WordPress Object Database Example

`examples/wordpress-object-database.php` writes the deterministic WordPress pack fixture into a temporary `.git/objects/pack` directory, adds a loose draft object, links an alternate shared package object cache through `objects/info/alternates`, maps a draft object through `refs/replace`, then reads every source through one object database. This models package managers, Playground snapshot tools, and shared-hosting deployment code traversing packed, loose, shared-cache, and replacement repository content without invoking the Git binary.

## Next Task

Map multi-pack-index semantics or run a controlled gix-odb/gix-pack crate no-run probe if the VM remains clear.
