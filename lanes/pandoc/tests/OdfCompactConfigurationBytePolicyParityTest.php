<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

return [
    'keeps compact and rich ODT configuration package sidecar bytes blocked' => static function (TestRunner $t): void {
        $configurationXml = '<accel:acceleratorlist xmlns:accel="http://openoffice.org/2001/accel"/>';
        $orphanXml = '<statusbar:statusbar xmlns:statusbar="http://openoffice.org/2001/statusbar"/>';
        $manifestXml = '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">'
            . '<manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>'
            . '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="' . strlen($configurationXml) . '"/>'
            . '</manifest:manifest>';
        $contentXml = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:text><text:p>Review.</text:p></office:text></office:body></office:document-content>';
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationXml, 'compressionMethod' => 0],
            ['name' => 'Configurations2/statusbar/orphan.xml', 'data' => $orphanXml, 'compressionMethod' => 0],
        ], 'odt compact configuration byte policy');

        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $rich = (new OdfReader())->readPackage($package);
        $compactConfigurations = $compact['packageConfigurations'];
        $richConfigurations = $rich['packageConfigurations'];
        $compactItems = [];
        foreach ($compactConfigurations['items'] as $item) {
            $compactItems[$item['packagePath']] = $item;
        }
        $richItems = [];
        foreach ($richConfigurations['items'] as $item) {
            $richItems[$item['part']] = $item;
        }

        foreach ([
            'compact' => [$compactConfigurations, $compactItems],
            'rich' => [$richConfigurations, $richItems],
        ] as $label => [$summary, $items]) {
            $t->same(2, $summary['count'], "{$label} configuration count");
            $t->same(0, $summary['readableCount'], "{$label} readable count");
            $t->same(2, $summary['storedPartCount'], "{$label} stored count");
            $t->same('configuration-package-bytes-blocked', $summary['byteExposurePolicy'], "{$label} byte policy");

            $declared = $items['Configurations2/accelerator/current.xml'];
            $t->same(true, $declared['declared'], "{$label} declared flag");
            $t->same(null, $declared['byteLength'], "{$label} declared byte length");
            $t->same(null, $declared['crc32'], "{$label} declared crc");
            $t->same(strlen($configurationXml), $declared['storedByteLength'], "{$label} declared stored length");
            $t->same(sprintf('%08x', crc32($configurationXml)), $declared['storedCrc32'], "{$label} declared stored crc");
            $t->same(false, $declared['canExposeBytes'], "{$label} declared can expose bytes");
            $t->same(false, $declared['canExposeAsDocumentMedia'], "{$label} declared document media handoff");
            $t->same('configuration-package-bytes-blocked', $declared['byteExposurePolicy'], "{$label} declared byte policy");

            $orphan = $items['Configurations2/statusbar/orphan.xml'];
            $t->same(false, $orphan['declared'], "{$label} orphan declared flag");
            $t->same(true, $orphan['undeclared'], "{$label} orphan undeclared flag");
            $t->same(null, $orphan['byteLength'], "{$label} orphan byte length");
            $t->same(null, $orphan['crc32'], "{$label} orphan crc");
            $t->same(strlen($orphanXml), $orphan['storedByteLength'], "{$label} orphan stored length");
            $t->same(sprintf('%08x', crc32($orphanXml)), $orphan['storedCrc32'], "{$label} orphan stored crc");
            $t->same(false, $orphan['canExposeBytes'], "{$label} orphan can expose bytes");
            $t->same('configuration-package-bytes-blocked', $orphan['byteExposurePolicy'], "{$label} orphan byte policy");
        }

        $t->same(['content.xml'], array_column($compact['packageByteHandoff']['handoffEntries'], 'name'));
        $t->same(['content.xml'], array_column($rich['importReport']['manifest']['packageProvenance']['packageByteHandoff']['handoffEntries'], 'name'));
    },
];
