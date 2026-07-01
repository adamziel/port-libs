<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'assigns source-specific inventory roles to chart and diagram embedded packages' => static function (TestRunner $t): void {
        $packageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
        $chartContentType = 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml';
        $diagramContentType = 'application/vnd.openxmlformats-officedocument.drawingml.diagramdata+xml';
        $parts = docx_embedded_package_inventory_roles_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $relationshipTypes = $package['relationshipTypes'];
        $packageRelationships = $relationshipTypes[$packageRel];

        $chartWorkbook = $inventory['word/embeddings/chart-workbook.xlsx'];
        $diagramModel = $inventory['word/embeddings/diagram-model.xlsx'];
        $ordinaryPackage = $inventory['word/embeddings/ordinary-package.xlsx'];
        $chartPreview = $inventory['word/media/chart-preview.png'];

        $t->true(in_array('relationship-target', $chartWorkbook['roles'], true), 'chart workbook relationship target role missing');
        $t->true(in_array('embedded-package', $chartWorkbook['roles'], true), 'chart workbook embedded package role missing');
        $t->true(in_array('chart-embedded-package', $chartWorkbook['roles'], true), 'chart workbook source role missing');
        $t->true(!in_array('diagram-embedded-package', $chartWorkbook['roles'], true), 'chart workbook should not get diagram package role');

        $t->true(in_array('relationship-target', $diagramModel['roles'], true), 'diagram model relationship target role missing');
        $t->true(in_array('embedded-package', $diagramModel['roles'], true), 'diagram model embedded package role missing');
        $t->true(in_array('diagram-embedded-package', $diagramModel['roles'], true), 'diagram model source role missing');
        $t->true(!in_array('chart-embedded-package', $diagramModel['roles'], true), 'diagram model should not get chart package role');

        $t->true(in_array('embedded-package', $ordinaryPackage['roles'], true), 'ordinary package embedded package role missing');
        $t->true(!in_array('chart-embedded-package', $ordinaryPackage['roles'], true), 'ordinary package should not get chart package role');
        $t->true(!in_array('diagram-embedded-package', $ordinaryPackage['roles'], true), 'ordinary package should not get diagram package role');
        $t->true(!in_array('chart-embedded-package', $chartPreview['roles'], true), 'chart image target should not get chart package role');

        $t->same(3, $summary['roleCounts']['embedded-package']);
        $t->same(1, $summary['roleCounts']['chart-embedded-package']);
        $t->same(1, $summary['roleCounts']['diagram-embedded-package']);
        $t->same(
            strlen($parts['word/embeddings/chart-workbook.xlsx'])
                + strlen($parts['word/embeddings/diagram-model.xlsx'])
                + strlen($parts['word/embeddings/ordinary-package.xlsx']),
            $summary['roleByteLengths']['embedded-package']
        );
        $t->same(strlen($parts['word/embeddings/chart-workbook.xlsx']), $summary['roleByteLengths']['chart-embedded-package']);
        $t->same(strlen($parts['word/embeddings/diagram-model.xlsx']), $summary['roleByteLengths']['diagram-embedded-package']);

        $t->same(3, $packageRelationships['count']);
        $t->same(3, $packageRelationships['internalCount']);
        $t->same(3, $packageRelationships['existingTargetCount']);
        $t->same(3, $packageRelationships['targetRoleCounts']['embedded-package']);
        $t->same(1, $packageRelationships['targetRoleCounts']['chart-embedded-package']);
        $t->same(1, $packageRelationships['targetRoleCounts']['diagram-embedded-package']);
        $t->same(1, $packageRelationships['sourceContentTypeCounts'][$chartContentType]);
        $t->same(1, $packageRelationships['sourceContentTypeCounts'][$diagramContentType]);
        $t->same(1, $packageRelationships['sourceRoleCounts']['chart-part']);
        $t->same(1, $packageRelationships['sourceRoleCounts']['diagram-data']);
        $t->true(!isset($docx['media']['word/embeddings/chart-workbook.xlsx']), 'chart workbook bytes should not become document media');
        $t->true(!isset($docx['media']['word/embeddings/diagram-model.xlsx']), 'diagram package bytes should not become document media');
    },
];

/**
 * @return array<string, string>
 */
function docx_embedded_package_inventory_roles_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/diagrams/data1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Embedded package inventory role fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
  <Relationship Id="rDiagramData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/data1.xml"/>
</Relationships>
XML,
        'word/charts/chart1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"/>
XML,
        'word/charts/_rels/chart1.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/chart-workbook.xlsx?sheet=Data#table"/>
  <Relationship Id="rPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/chart-preview.png"/>
</Relationships>
XML,
        'word/diagrams/data1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>
XML,
        'word/diagrams/_rels/data1.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rModel" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/diagram-model.xlsx"/>
</Relationships>
XML,
        'customXml/item1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<payload/>
XML,
        'customXml/_rels/item1.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rOrdinaryPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../word/embeddings/ordinary-package.xlsx"/>
</Relationships>
XML,
        'word/embeddings/chart-workbook.xlsx' => 'chart workbook bytes',
        'word/embeddings/diagram-model.xlsx' => 'diagram model workbook bytes',
        'word/embeddings/ordinary-package.xlsx' => 'ordinary embedded package bytes',
        'word/media/chart-preview.png' => 'chart preview bytes',
    ];
}
