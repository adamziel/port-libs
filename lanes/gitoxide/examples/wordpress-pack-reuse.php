<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-reuse.php';

return [
    'oldExport' => $fixture['oldExport'],
    'newExport' => $fixture['newExport'],
    'reusedPackChecksum' => $fixture['reusedPackChecksum'],
    'reusedEntryStorage' => array_column($fixture['reusedEntries'], 'storage'),
    'reusedEntrySourceOffsets' => array_column($fixture['reusedEntries'], 'sourceOffset'),
    'thinEntryStorage' => $fixture['thinEntry']['storage'],
    'thinBaseOid' => $fixture['thinEntry']['baseOid'],
    'legacySourceTargetStorage' => $fixture['legacySourceTargetEntry']['storage'],
    'legacyRepackedStorage' => array_column($fixture['legacyRepackedEntries'], 'storage'),
    'legacyRepackedReused' => array_column($fixture['legacyRepackedEntries'], 'reused'),
    'legacyRepackedHasDelta' => $fixture['legacyRepackedHasDelta'],
    'wordpressUse' => $fixture['wordpressUse'],
];
