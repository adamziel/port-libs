<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$gitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack example directory: {$packDir}");
}

$basename = 'pack-' . $fixture['packChecksum'];
file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);

$loose = new LooseObjectStore($gitDir);
$draftOid = $loose->write(new GitObject('blob', 'Draft block content pending the next packed snapshot.'));
$alternateObjectsDir = dirname($gitDir) . '/shared-package-cache/.git/objects';
if (!mkdir($alternateObjectsDir, 0777, true) && !is_dir($alternateObjectsDir)) {
    throw new RuntimeException("Unable to create alternate object directory: {$alternateObjectsDir}");
}
$sharedPackageOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDir)
    ->write(new GitObject('blob', 'Shared plugin package object from an alternate cache.'));
$infoDir = $gitDir . '/objects/info';
if (!mkdir($infoDir, 0777, true) && !is_dir($infoDir)) {
    throw new RuntimeException("Unable to create objects info directory: {$infoDir}");
}
file_put_contents($infoDir . '/alternates', "# shared object cache\n{$alternateObjectsDir}\n");

$database = new ObjectDatabase($gitDir);
$deltaBlob = $database->read($fixture['objects'][2]['oid']);
$draft = $database->read($draftOid);
$sharedPackage = $database->read($sharedPackageOid);
$prefix = $database->lookupPrefix(substr($fixture['objects'][2]['oid'], 0, 8));

return [
    'packedObjects' => $database->packedObjectCount(),
    'totalIterableObjects' => count($database->objectIds()),
    'alternateObjectDatabases' => count($database->alternateObjectDirectories()),
    'deltaBlobOid' => $deltaBlob->oid(),
    'deltaBlobHasPackedEdit' => str_contains($deltaBlob->body, 'reconstructed packed edit'),
    'draftOid' => $draft->oid(),
    'draftSource' => 'loose object',
    'sharedPackageOid' => $sharedPackage->oid(),
    'sharedPackageSource' => 'alternate object database',
    'deltaPrefixStatus' => $prefix['status'],
    'firstPackOffsetOid' => $database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL)[0],
];
