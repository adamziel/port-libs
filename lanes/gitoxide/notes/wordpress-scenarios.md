# gitoxide WordPress Scenario

Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.

## Current Native Slice

Native loose Git object storage with canonical object headers, SHA-1 object IDs, commit header parsing, tree entry parsing/serialization, loose direct/symbolic reference parsing/storage, packed-ref header/reference/peeled lookup parsing, protocol v2 capability and `ls-refs` parsing, protocol v2 fetch negotiation argument building, loose+packed reference-store overlay resolution, v2 pack-index parsing/lookup, multi-pack-index parsing/lookup, MIDX-backed object database pack selection/de-duplication, pack data entry decoding, OFS_DELTA/REF_DELTA object resolution, and pack+loose+alternate object database lookup/prefix/iteration with replacement refs.

## WordPress Deploy Tree Example

`examples/wordpress-content-tree.php` parses a fixture Git tree with `.wp-env.json`, `wp-config.php`, and `wp-content` entries. This models server-side PHP code inspecting a WordPress repository snapshot in shared hosting, Playground, package install, or migration tooling without shelling out to `git`.

## WordPress Reference Example

`examples/wordpress-references.php` writes and reads `HEAD`, `refs/heads/main`, and `refs/remotes/origin/HEAD` using native PHP loose-ref files. This models a shared-hosting deployment tool or Playground snapshot manager discovering the active WordPress branch and its commit without invoking the Git binary.

## WordPress Packed Reference Example

`examples/wordpress-packed-refs.php` parses a compacted `packed-refs` buffer with a WordPress deployment branch, remote-tracking branch, and peeled release tag. This models a PHP deployment or package manager inspecting compacted repository state on shared hosting without invoking `git show-ref` or `git for-each-ref`.

## WordPress Reference Store Example

`examples/wordpress-reference-store.php` combines loose `HEAD` with packed branch and release-tag refs. This models a shared-hosting deployment tool resolving the active WordPress branch from loose refs while reading compacted branch/tag state from `packed-refs`.

## WordPress Protocol V2 ls-refs Example

`examples/wordpress-protocol-v2-ls-refs.php` parses a deterministic protocol v2 capability advertisement, builds an `ls-refs` request with de-duplicated `ref-prefix` arguments and `unborn` support, then parses the remote response into active branch, peeled release tag, and unborn staging branch refs. This models a PHP deployment or package manager discovering remote WordPress repo state before deciding what to fetch.

## WordPress Protocol V2 Fetch Example

`examples/wordpress-protocol-v2-fetch.php` parses a deterministic protocol v2 capability advertisement and builds a shallow blobless `fetch` request using `want-ref refs/heads/main`, `deepen 1`, `filter blob:none`, and local `have` negotiation state. This models a PHP deployment or package manager requesting only the WordPress branch metadata and reachable pack data it needs before object database reads.

## WordPress Pack Index Example

`examples/wordpress-pack-index.php` parses a deterministic v2 pack index fixture for a WordPress repository and locates compacted object offsets, including a large 64-bit media object offset. This models a PHP object database finding packed content objects on shared hosting without invoking `git`.

## WordPress Multi-Pack Index Example

`examples/wordpress-multi-pack-index.php` parses a deterministic multi-pack-index fixture that maps content, template, and large media objects to the pack index names and offsets that contain them. This models a PHP object database using one compact MIDX fanout table to select the right WordPress content or media pack before reading pack data.

## WordPress Pack Data Example

`examples/wordpress-pack-data.php` pairs a deterministic pack index with pack data, then reads a packed commit, blob, and OFS_DELTA-reconstructed blob by object ID. This models a PHP object database reading compacted WordPress content on shared hosting without invoking `git cat-file`.

## WordPress Object Database Example

`examples/wordpress-object-database.php` writes the deterministic WordPress pack fixture into a temporary `.git/objects/pack` directory, adds a loose draft object, links an alternate shared package object cache through `objects/info/alternates`, maps a draft object through `refs/replace`, then reads every source through one object database. This models package managers, Playground snapshot tools, and shared-hosting deployment code traversing packed, loose, shared-cache, and replacement repository content without invoking the Git binary.

## WordPress Multi-Pack Object Database Example

`examples/wordpress-object-database-multi-pack.php` writes two deterministic pack/index pairs plus a `multi-pack-index`, including a package object duplicated across both packs. The object database uses the MIDX to count three indexed objects from four raw pack-index entries, read content/media/shared objects by pack selection, and keep prefix and pack-offset iteration aligned with the MIDX.

## Next Task

Map protocol v2 fetch response sections and sideband pack handling, or run a controlled gix-protocol/gix-transport crate no-run probe if the VM remains clear.
