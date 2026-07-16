<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\FetchFilterSpec;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$gitDir = sys_get_temp_dir() . '/port-libs-git-partial-clone-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack directory: {$packDir}");
}

$basename = 'pack-' . $fixture['packChecksum'];
file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);
file_put_contents($packDir . '/' . $basename . '.promisor', "blobless WordPress partial clone\n");

$database = new ObjectDatabase($gitDir);
$looseStore = new LooseObjectStore($gitDir);
$filter = FetchFilterSpec::blobNone();
$packedContentOid = $fixture['objects'][1]['oid'];
$missingMediaBlob = new GitObject('blob', 'Large media bytes intentionally omitted by blob:none');
$treeOid = $looseStore->write((new Tree([
    new TreeEntry('100644', 'wp-posts.txt', $packedContentOid),
    new TreeEntry('100644', 'hero.jpg', $missingMediaBlob->oid()),
]))->toObject());

return [
    'filter' => (string) $filter,
    'filterArgument' => $filter->requestArgument(),
    'promisorPacks' => $database->promisorPackNames(),
    'promisorObjectCount' => count($database->promisorObjectIds()),
    'wordpressTreeObject' => $treeOid,
    'packedContentState' => $database->objectState($packedContentOid),
    'mediaObjectState' => $database->objectState($missingMediaBlob->oid()),
    'mediaObjectIncludedByFilter' => $filter->includesObject($missingMediaBlob),
];
