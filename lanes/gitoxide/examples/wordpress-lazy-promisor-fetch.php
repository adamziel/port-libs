<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PromisorObjectResolver;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$gitDir = sys_get_temp_dir() . '/port-libs-git-lazy-promisor-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack directory: {$packDir}");
}

$basename = 'pack-' . $fixture['packChecksum'];
file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);
file_put_contents($packDir . '/' . $basename . '.promisor', "blobless WordPress lazy fetch\n");
file_put_contents($gitDir . '/config', <<<CFG
[remote "origin"]
    url = https://git.example.test/wp-content.git
    promisor = true
    partialCloneFilter = blob:none
CFG);

$mediaBlob = new GitObject('blob', 'Lazily fetched WordPress media attachment bytes');
$resolver = new class($mediaBlob, $gitDir) implements PromisorObjectResolver {
    public array $requests = [];
    public ?string $hydrationPack = null;

    public function __construct(
        private readonly GitObject $object,
        private readonly string $gitDir,
    ) {
    }

    public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
    {
        $this->requests[] = $oid;
        if ($oid !== $this->object->oid()) {
            return null;
        }

        $pack = PackBuilder::build([$this->object]);
        $packDir = $this->gitDir . '/objects/pack';
        $basename = 'pack-' . $pack->packChecksum();
        $this->hydrationPack = $basename . '.promisor';
        file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
        file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
        file_put_contents($packDir . '/' . $basename . '.promisor', "WordPress media lazy hydration\n");

        return $this->object;
    }
};

$database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);
$before = $database->objectState($mediaBlob->oid());
$resolved = $database->read($mediaBlob->oid());
$after = $database->objectState($mediaBlob->oid());

$templateBlob = new GitObject('blob', 'Externally hydrated WordPress block template bytes');
$templateOid = $templateBlob->oid();
$beforeExternalHydration = $database->objectState($templateOid);
$templatePack = PackBuilder::build([$templateBlob]);
$templatePackBase = 'pack-' . $templatePack->packChecksum();
file_put_contents($packDir . '/' . $templatePackBase . '.pack', $templatePack->packBytes());
file_put_contents($packDir . '/' . $templatePackBase . '.idx', $templatePack->indexBytes());
file_put_contents($packDir . '/' . $templatePackBase . '.promisor', "WordPress template external hydration\n");
$containsAfterExternalHydration = $database->contains($templateOid);
$prefixAfterExternalHydration = $database->lookupPrefix(strtoupper(substr($templateOid, 0, 12)));
$afterExternalHydration = $database->objectState($templateOid);

return [
    'promisorRemotes' => $database->promisorRemotes(),
    'promisorPacks' => $database->promisorPackNames(),
    'mediaObject' => $mediaBlob->oid(),
    'beforeRead' => $before,
    'resolverRequests' => $resolver->requests,
    'hydrationPack' => $resolver->hydrationPack,
    'promisorPacksAfterHydration' => $database->promisorPackNames(),
    'resolvedType' => $resolved->type,
    'resolvedSize' => strlen($resolved->body),
    'afterRead' => $after,
    'persistedInPackStore' => (new ObjectDatabase($gitDir))->read($mediaBlob->oid())->body === $mediaBlob->body,
    'externalHydratedObject' => $templateOid,
    'beforeExternalHydration' => $beforeExternalHydration,
    'externalHydrationPack' => $templatePackBase . '.promisor',
    'containsAfterExternalHydration' => $containsAfterExternalHydration,
    'prefixAfterExternalHydration' => $prefixAfterExternalHydration,
    'afterExternalHydration' => $afterExternalHydration,
    'promisorPacksAfterExternalHydration' => $database->promisorPackNames(),
];
