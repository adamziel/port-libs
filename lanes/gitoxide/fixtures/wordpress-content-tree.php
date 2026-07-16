<?php

declare(strict_types=1);

$entry = static function (string $mode, string $filename, string $oid): string {
    $oidBytes = hex2bin($oid);
    if ($oidBytes === false) {
        throw new RuntimeException('Invalid fixture oid');
    }

    return $mode . ' ' . $filename . "\0" . $oidBytes;
};

$rootTreeBody = $entry('100644', '.wp-env.json', '99c2ff79b23f9abfc0c3c778b05056d9309f7ce7')
    . $entry('100644', 'wp-config.php', '63cf8426a83c969ed0db16f54ce50ca6ad16502b')
    . $entry('40000', 'wp-content', '4b825dc642cb6eb9a060e54bf8d69288fbee4904');

return [
    'rootTreeBody' => $rootTreeBody,
    'expectedRootOid' => '61c12e437d735721d83cdced24b60aca3eef7b53',
    'wordpressUse' => 'A server-side PHP Git primitive can inspect deployable WordPress repo trees without shelling out to git.',
];
