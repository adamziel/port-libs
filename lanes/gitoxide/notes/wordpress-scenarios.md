# gitoxide WordPress Scenario

Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.

## Current Native Slice

Native loose Git object storage with canonical object headers, SHA-1 object IDs, commit header parsing, tree entry parsing/serialization, merge-base ancestry traversal, flat and recursive tree three-way merge decisions, exact same-object rename/delete and rename/rename conflict detection, bounded similar-blob rename/delete and rename/modify conflict detection, same-target rename content merges, bounded directory rename/modify merges with renamed plugin entry file heuristics and strict-best similar rename candidate selection, recursive blob-content merges with conflict-marker output, Git index v2 writes for ancestor/ours/theirs blob conflict stages, checkout-clean merged worktree and conflict marker-file writes for content conflicts, directory-file conflict classification, loose direct/symbolic reference parsing/storage, packed-ref header/reference/peeled lookup parsing, protocol v2 capability and `ls-refs` parsing, protocol v2 fetch negotiation argument building, common partial-clone fetch filter specs, sparse checkout path matching, lazy promisor object hydration, protocol v2 fetch response section and sideband parsing, protocol v1 receive-pack push request building, receive-pack advertisement parsing, generated pack handoff, REF_DELTA pack generation, thin send-pack request building, stream-backed, git-daemon, smart HTTP auth/session/redirect/proxy plus proxy auth-method handling and native SOCKS4/SOCKS4a/SOCKS5/SOCKS5h default-requester handshakes, and SSH receive-pack transport boundaries, send-pack session orchestration, status parsing, loose+packed reference-store overlay resolution, v2 pack-index parsing/lookup, multi-pack-index parsing/lookup, MIDX-backed object database pack selection/de-duplication, pack data entry decoding, OFS_DELTA/REF_DELTA object resolution, and pack+loose+alternate+promisor object database lookup/prefix/iteration with replacement refs.

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

## WordPress Protocol V2 Fetch Response Example

`examples/wordpress-protocol-v2-fetch-response.php` parses deterministic protocol v2 fetch response packet lines, including `acknowledgments`, `shallow-info`, `wanted-refs`, and `packfile` sections. It extracts the wanted WordPress branch object, shallow boundary update, sideband progress text, and sideband pack bytes that can be passed to the native pack/object database layer.

## WordPress Protocol V1 Push Example

`examples/wordpress-protocol-v1-push.php` builds a deterministic receive-pack request for updating `refs/heads/main`, creating `refs/tags/wp-release`, requesting `report-status-v2`, `side-band-64k`, `object-format=sha1`, `atomic`, and `push-options`, then packet-lines the ref updates before placeholder pack bytes. This models a PHP deployment tool preparing a WordPress branch/tag push without invoking `git push`.

## WordPress Protocol V1 Push Pack Example

`examples/wordpress-protocol-v1-push-pack.php` builds native pack bytes for a deterministic WordPress commit/tree/blob set, verifies the generated pack and index through the native pack reader, and appends those bytes to a receive-pack branch/tag request. This models a PHP deployment tool preparing the object payload for `git-receive-pack` without invoking `git pack-objects`.

## WordPress Protocol V1 Push Response Example

`examples/wordpress-protocol-v1-push-response.php` parses a deterministic sidebanded receive-pack response for a WordPress deployment push. It extracts progress messages, `unpack ok`, and accepted branch/tag ref statuses from nested report-status packet lines, so a PHP deployment tool can determine whether the remote accepted the push without invoking `git push`.

## WordPress Send-Pack Session Example

`examples/wordpress-send-pack-session.php` parses advertised receive-pack refs and capabilities, plans a WordPress branch update plus release-tag creation, generates native pack bytes for the commit/tree/blob payload, builds the receive-pack request, and parses the status response. This models the local orchestration around a WordPress deployment push before actual transport I/O is ported.

## WordPress Thin Send-Pack Example

`examples/wordpress-send-pack-thin.php` builds a thin receive-pack request for a WordPress content update. It uses remote base objects to encode changed content as REF_DELTA entries, omits those bases from the transit pack, and preserves the update command envelope without invoking `git pack-objects` or `git push`.

## WordPress Receive-Pack Transport Example

`examples/wordpress-receive-pack-transport.php` runs a deterministic receive-pack handshake, request write, and status response read over native PHP stream resources. The transport tests now also cover git-daemon `git-receive-pack` service requests, smart HTTP discovery/POST exchanges with auth headers, Set-Cookie session propagation, safe initial redirects with effective-base reuse, proxy/no-proxy, Basic proxy credential-helper boundaries, proxy auth-method normalization, native default-requester SOCKS5h and SOCKS4a receive-pack discovery through local proxy handshakes, and SSH `git-receive-pack` command setup over injected streams, modeling concrete URL adapter boundaries a shared-hosting deployment tool needs before actual SSH authentication/channel integration and broader HTTPS-through-SOCKS coverage are added.

## WordPress Tree Merge Example

`examples/wordpress-tree-merge.php` merges flat root-tree changes where one side updates `wp-content` and the other adds `.wp-env.json`, then reports a structured `theme.json` modify/modify conflict. This models the first PHP-native merge decision layer for WordPress deployment snapshots before recursive tree traversal and blob conflict-marker merges are added.

## WordPress Blob Merge Example

`examples/wordpress-blob-merge.php` line-merges independent WordPress metadata edits and emits diff3 conflict markers for overlapping `theme.json` changes. This models the content-merge layer needed before tree conflicts can be resolved into merged blobs.

## WordPress Recursive Tree Merge Example

`examples/wordpress-recursive-tree-merge.php` walks nested WordPress trees, merges independent `post.meta` edits into a new blob, records a full-path `theme.json` content conflict with diff3 marker output, writes the ancestor/ours/theirs blob stages into a Git index v2 file, and checks out a merged worktree containing both clean metadata and the marker-file `theme.json`. The checkout removes a stale plugin file while preserving `.git/config`. The underlying recursive merge also classifies nested directory-file collisions such as a cache directory on one side and a cache file on the other; those tree stages can now expand into file-level index entries. The merge engine detects exact same-object rename/delete and rename/rename conflicts plus bounded similar-blob rename/delete and rename/modify conflicts for renamed plugin entry files, and same-target renamed plugin entry edits now merge or conflict at the renamed path. Bounded plugin directory renames are detected by descendant similarity, including unique renamed internal leaves, so edits left under the old directory on the other side merge or conflict at the new directory path even when the renamed side also renames the main plugin file. When a plugin split creates multiple similar candidate directories, the merge chooses the strictly best match and keeps equal-score ambiguity on the ordinary add/delete path.

## WordPress Partial Clone Example

`examples/wordpress-partial-clone.php` writes a deterministic WordPress pack/index pair with a `.promisor` sidecar, builds a blobless `FetchFilterSpec`, and stores a tree that references both packed content and an omitted media blob. This models a PHP deployment or package manager distinguishing local packed WordPress content from media bytes promised by a partial clone without invoking `git cat-file`.

## WordPress Lazy Promisor Fetch Example

`examples/wordpress-lazy-promisor-fetch.php` starts with a blobless promisor pack and an omitted media object, then resolves that object through a native `PromisorObjectResolver`. The object database verifies the returned object ID, persists the blob into loose storage, and reports the media object as present for a fresh database instance.

## WordPress Sparse Checkout Example

`examples/wordpress-sparse-checkout.php` combines a blobless fetch filter with a cone-mode sparse checkout rooted at `wp-content/plugins/gutenberg`. It filters deterministic WordPress tree entries so root files, ancestor-directory files, and Gutenberg plugin files are materialized while unrelated plugin/admin paths remain skipped.

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

Add HTTPS-through-SOCKS/TLS coverage, broader directory rename conflict cases, or more rename heuristics.
