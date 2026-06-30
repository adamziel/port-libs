<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxWriter
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_EP = 'http://schemas.openxmlformats.org/officeDocument/2006/extended-properties';
    private const NS_VT = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const REL_OFFICE_DOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const REL_CORE_PROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    private const REL_EXTENDED_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    private const REL_CUSTOM_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    private const REL_COMMENTS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    private const REL_FOOTNOTES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    private const REL_THEME = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    private const REL_FONT_TABLE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable';
    private const REL_WEB_SETTINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings';
    private const REL_STYLES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    private const REL_NUMBERING = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    private const REL_SETTINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    private const REL_HYPERLINK = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    private const CT_CORE_PROPERTIES = 'application/vnd.openxmlformats-package.core-properties+xml';
    private const CT_EXTENDED_PROPERTIES = 'application/vnd.openxmlformats-officedocument.extended-properties+xml';
    private const CT_CUSTOM_PROPERTIES = 'application/vnd.openxmlformats-officedocument.custom-properties+xml';
    private const CT_COMMENTS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml';
    private const CT_FOOTNOTES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml';
    private const CT_FONT_TABLE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml';
    private const CT_THEME = 'application/vnd.openxmlformats-officedocument.theme+xml';
    private const CT_WEB_SETTINGS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml';
    private const CT_MAIN_DOCUMENT = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
    private const CT_STYLES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml';
    private const CT_NUMBERING = 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml';
    private const CT_SETTINGS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml';
    private const CT_RELATIONSHIPS = 'application/vnd.openxmlformats-package.relationships+xml';
    private const CT_OBFUSCATED_FONT = 'application/vnd.openxmlformats-officedocument.obfuscatedFont';
    private const CT_XML = 'application/xml';
    private const GENERATED_TIMESTAMP = '1980-01-01T00:00:00Z';
    private const GENERATED_DOS_TIME = 0;
    private const GENERATED_DOS_DATE = 33; // 1980-01-01 00:00:00, the earliest valid DOS ZIP date.
    private const BULLET_NUM_ID = 1;
    private const ORDERED_NUM_ID = 2;

    /** @var array<string, int> */
    private const CORE_PART_ORDER = [
        '[Content_Types].xml' => 0,
        '_rels/.rels' => 1,
        'docProps/core.xml' => 2,
        'docProps/app.xml' => 3,
        'docProps/custom.xml' => 4,
        'word/document.xml' => 5,
        'word/_rels/document.xml.rels' => 6,
        'word/_rels/footnotes.xml.rels' => 7,
        'word/comments.xml' => 8,
        'word/footnotes.xml' => 9,
        'word/fontTable.xml' => 10,
        'word/numbering.xml' => 11,
        'word/settings.xml' => 12,
        'word/styles.xml' => 13,
        'word/theme/theme1.xml' => 14,
        'word/webSettings.xml' => 15,
    ];

    /** @var list<OpcRelationship> */
    private array $documentRelationships = [];

    /** @var array<string, array{numId:int, level:int, start:int}> */
    private array $orderedListOverrides = [];

    private int $nextDocumentRelationshipId = 9;
    private int $nextNumberingId = 10;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        return ZipPackage::build($this->packageParts($document));
    }

    /**
     * @return list<array{name:string, data:string, modifiedDosTime:int, modifiedDosDate:int}>
     */
    public function packageParts(AstNode $document): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('DOCX writer expects a document node');
        }

        $this->resetState();
        $documentXml = $this->documentXml($document);

        return $this->normalizePackageParts([
            ['name' => '[Content_Types].xml', 'data' => $this->contentTypesXml()],
            ['name' => '_rels/.rels', 'data' => $this->rootRelationshipsXml()],
            ['name' => 'docProps/core.xml', 'data' => $this->corePropertiesXml($document)],
            ['name' => 'docProps/app.xml', 'data' => $this->extendedPropertiesXml()],
            ['name' => 'docProps/custom.xml', 'data' => $this->customPropertiesXml()],
            ['name' => 'word/document.xml', 'data' => $documentXml],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $this->documentRelationshipsXml()],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $this->footnotesRelationshipsXml()],
            ['name' => 'word/comments.xml', 'data' => $this->commentsXml()],
            ['name' => 'word/footnotes.xml', 'data' => $this->footnotesXml()],
            ['name' => 'word/fontTable.xml', 'data' => $this->fontTableXml()],
            ['name' => 'word/styles.xml', 'data' => $this->stylesXml()],
            ['name' => 'word/numbering.xml', 'data' => $this->numberingXml()],
            ['name' => 'word/settings.xml', 'data' => $this->settingsXml()],
            ['name' => 'word/theme/theme1.xml', 'data' => $this->themeXml()],
            ['name' => 'word/webSettings.xml', 'data' => $this->webSettingsXml()],
        ]);
    }

    public static function normalizePackagePartName(string $partName): string
    {
        $canonical = OpcPackagePath::canonicalPartNameFromUri($partName);
        if (strtolower($canonical) === '/[content_types].xml') {
            return '[Content_Types].xml';
        }

        return ltrim($canonical, '/');
    }

    /**
     * @param list<array<string, mixed>> $parts
     * @return list<array{name:string, data:string, modifiedDosTime:int, modifiedDosDate:int}>
     */
    private function normalizePackageParts(array $parts): array
    {
        $normalized = [];
        $seen = [];
        foreach ($parts as $index => $part) {
            if (!is_array($part) || !isset($part['name']) || !is_string($part['name'])) {
                throw new \InvalidArgumentException("DOCX package part {$index} is missing a string name");
            }
            if (!array_key_exists('data', $part) || !is_string($part['data'])) {
                throw new \InvalidArgumentException("DOCX package part {$part['name']} is missing string data");
            }

            $name = self::normalizePackagePartName($part['name']);
            $key = strtolower($name);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Duplicate DOCX package part after normalization: ' . $name);
            }
            $seen[$key] = true;

            $normalized[] = [
                'name' => $name,
                'data' => $part['data'],
                'modifiedDosTime' => self::GENERATED_DOS_TIME,
                'modifiedDosDate' => self::GENERATED_DOS_DATE,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftOrder = self::CORE_PART_ORDER[$left['name']] ?? 1000;
            $rightOrder = self::CORE_PART_ORDER[$right['name']] ?? 1000;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp($left['name'], $right['name']);
        });

        return $normalized;
    }

    private function resetState(): void
    {
        $this->documentRelationships = [];
        $this->orderedListOverrides = [];
        $this->nextDocumentRelationshipId = 9;
        $this->nextNumberingId = 10;
    }

    private function contentTypesXml(): string
    {
        $types = new OpcContentTypes();
        $types->addDefault('rels', self::CT_RELATIONSHIPS);
        $types->addDefault('xml', self::CT_XML);
        $types->addDefault('odttf', self::CT_OBFUSCATED_FONT);
        $types->addOverride('/docProps/core.xml', self::CT_CORE_PROPERTIES);
        $types->addOverride('/docProps/app.xml', self::CT_EXTENDED_PROPERTIES);
        $types->addOverride('/docProps/custom.xml', self::CT_CUSTOM_PROPERTIES);
        $types->addOverride('/word/document.xml', self::CT_MAIN_DOCUMENT);
        $types->addOverride('/word/comments.xml', self::CT_COMMENTS);
        $types->addOverride('/word/footnotes.xml', self::CT_FOOTNOTES);
        $types->addOverride('/word/fontTable.xml', self::CT_FONT_TABLE);
        $types->addOverride('/word/styles.xml', self::CT_STYLES);
        $types->addOverride('/word/numbering.xml', self::CT_NUMBERING);
        $types->addOverride('/word/settings.xml', self::CT_SETTINGS);
        $types->addOverride('/word/theme/theme1.xml', self::CT_THEME);
        $types->addOverride('/word/webSettings.xml', self::CT_WEB_SETTINGS);

        return self::xmlDeclaration() . $types->toXml() . "\n";
    }

    private function rootRelationshipsXml(): string
    {
        $relationships = new OpcRelationships('/');
        $relationships->add(new OpcRelationship('rId1', self::REL_OFFICE_DOCUMENT, 'word/document.xml'));
        $relationships->add(new OpcRelationship('rId3', self::REL_CORE_PROPERTIES, 'docProps/core.xml'));
        $relationships->add(new OpcRelationship('rId4', self::REL_EXTENDED_PROPERTIES, 'docProps/app.xml'));
        $relationships->add(new OpcRelationship('rId5', self::REL_CUSTOM_PROPERTIES, 'docProps/custom.xml'));

        return self::xmlDeclaration() . $relationships->toXml() . "\n";
    }

    private function corePropertiesXml(AstNode $document): string
    {
        $title = $this->metadataText($document->attr('title', $this->options['title'] ?? ''));
        $creator = $this->metadataText($document->attr(
            'creator',
            $document->attr('author', $this->options['creator'] ?? $this->options['author'] ?? 'pandoc')
        ));
        $description = $this->metadataText($document->attr('description', $this->options['description'] ?? ''));
        $created = $this->metadataText($document->attr('created', $this->options['created'] ?? self::GENERATED_TIMESTAMP));
        $modified = $this->metadataText($document->attr('modified', $this->options['modified'] ?? $created));

        return self::xmlDeclaration()
            . '<cp:coreProperties xmlns:cp="' . self::NS_CP . '" xmlns:dc="' . self::NS_DC . '" xmlns:dcterms="' . self::NS_DCTERMS . '" xmlns:xsi="' . self::NS_XSI . '">'
            . '<dc:title>' . self::escText($title) . '</dc:title>'
            . '<dc:creator>' . self::escText($creator) . '</dc:creator>'
            . '<dc:description>' . self::escText($description) . '</dc:description>'
            . '<cp:lastModifiedBy>' . self::escText($creator) . '</cp:lastModifiedBy>'
            . '<cp:revision>1</cp:revision>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . self::escText($created) . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . self::escText($modified) . '</dcterms:modified>'
            . '</cp:coreProperties>'
            . "\n";
    }

    private function extendedPropertiesXml(): string
    {
        $application = $this->metadataText($this->options['application'] ?? 'pandoc');
        $company = $this->metadataText($this->options['company'] ?? '');

        return self::xmlDeclaration()
            . '<Properties xmlns="' . self::NS_EP . '" xmlns:vt="' . self::NS_VT . '">'
            . '<Application>' . self::escText($application) . '</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Document</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Document</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>' . self::escText($company) . '</Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>16.0000</AppVersion>'
            . '</Properties>'
            . "\n";
    }

    private function customPropertiesXml(): string
    {
        return self::xmlDeclaration()
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:vt="' . self::NS_VT . '"/>'
            . "\n";
    }

    private function metadataText(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $parts[] = (string) $item;
                }
            }

            return implode('; ', array_filter($parts, static fn (string $part): bool => $part !== ''));
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    private function documentRelationshipsXml(): string
    {
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rId1', self::REL_COMMENTS, 'comments.xml'));
        $relationships->add(new OpcRelationship('rId2', self::REL_FOOTNOTES, 'footnotes.xml'));
        $relationships->add(new OpcRelationship('rId3', self::REL_THEME, 'theme/theme1.xml'));
        $relationships->add(new OpcRelationship('rId4', self::REL_FONT_TABLE, 'fontTable.xml'));
        $relationships->add(new OpcRelationship('rId5', self::REL_WEB_SETTINGS, 'webSettings.xml'));
        $relationships->add(new OpcRelationship('rId6', self::REL_SETTINGS, 'settings.xml'));
        $relationships->add(new OpcRelationship('rId7', self::REL_STYLES, 'styles.xml'));
        $relationships->add(new OpcRelationship('rId8', self::REL_NUMBERING, 'numbering.xml'));
        foreach ($this->documentRelationships as $relationship) {
            $relationships->add($relationship);
        }

        return self::xmlDeclaration() . $relationships->toXml() . "\n";
    }

    private function footnotesRelationshipsXml(): string
    {
        return self::xmlDeclaration()
            . '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>'
            . "\n";
    }

    private function commentsXml(): string
    {
        return self::xmlDeclaration()
            . '<w:comments xmlns:w="' . self::NS_W . '" xmlns:r="' . self::NS_R . '"/>'
            . "\n";
    }

    private function footnotesXml(): string
    {
        return self::xmlDeclaration()
            . '<w:footnotes xmlns:w="' . self::NS_W . '">'
            . '<w:footnote w:type="continuationSeparator" w:id="0"><w:p><w:r><w:continuationSeparator/></w:r></w:p></w:footnote>'
            . '<w:footnote w:type="separator" w:id="-1"><w:p><w:r><w:separator/></w:r></w:p></w:footnote>'
            . '</w:footnotes>'
            . "\n";
    }

    private function fontTableXml(): string
    {
        return self::xmlDeclaration()
            . '<w:fonts xmlns:w="' . self::NS_W . '">'
            . '<w:font w:name="Calibri"><w:family w:val="swiss"/><w:pitch w:val="variable"/></w:font>'
            . '<w:font w:name="Times New Roman"><w:family w:val="roman"/><w:pitch w:val="variable"/></w:font>'
            . '<w:font w:name="Cambria Math"><w:family w:val="roman"/><w:pitch w:val="variable"/></w:font>'
            . '<w:font w:name="Consolas"><w:family w:val="modern"/><w:pitch w:val="fixed"/></w:font>'
            . '<w:font w:name="Symbol"><w:family w:val="decorative"/><w:pitch w:val="variable"/></w:font>'
            . '</w:fonts>'
            . "\n";
    }

    private function themeXml(): string
    {
        return self::xmlDeclaration()
            . '<a:theme xmlns:a="' . self::NS_A . '" name="Office Theme">'
            . '<a:themeElements>'
            . '<a:clrScheme name="Office">'
            . '<a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>'
            . '<a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>'
            . '<a:dk2><a:srgbClr val="1F497D"/></a:dk2>'
            . '<a:lt2><a:srgbClr val="EEECE1"/></a:lt2>'
            . '<a:accent1><a:srgbClr val="4F81BD"/></a:accent1>'
            . '<a:accent2><a:srgbClr val="C0504D"/></a:accent2>'
            . '<a:accent3><a:srgbClr val="9BBB59"/></a:accent3>'
            . '<a:accent4><a:srgbClr val="8064A2"/></a:accent4>'
            . '<a:accent5><a:srgbClr val="4BACC6"/></a:accent5>'
            . '<a:accent6><a:srgbClr val="F79646"/></a:accent6>'
            . '<a:hlink><a:srgbClr val="0000FF"/></a:hlink>'
            . '<a:folHlink><a:srgbClr val="800080"/></a:folHlink>'
            . '</a:clrScheme>'
            . '<a:fontScheme name="Office"><a:majorFont><a:latin typeface="Cambria"/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/></a:minorFont></a:fontScheme>'
            . '<a:fmtScheme name="Office"/>'
            . '</a:themeElements>'
            . '</a:theme>'
            . "\n";
    }

    private function webSettingsXml(): string
    {
        return self::xmlDeclaration()
            . '<w:webSettings xmlns:w="' . self::NS_W . '"><w:allowPNG/><w:doNotSaveAsSingleFile/></w:webSettings>'
            . "\n";
    }

    private static function xmlDeclaration(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    }

    private function documentXml(AstNode $document): string
    {
        $blocks = [];
        foreach ($document->children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }
            foreach ($this->renderBlock($child, 0) as $blockXml) {
                if ($blockXml !== '') {
                    $blocks[] = $blockXml;
                }
            }
        }
        if ($blocks === []) {
            $blocks[] = '<w:p/>';
        }

        return self::xmlDeclaration()
            . '<w:document xmlns:w="' . self::NS_W . '" xmlns:r="' . self::NS_R . '">'
            . '<w:body>'
            . implode('', $blocks)
            . $this->sectionPropertiesXml()
            . '</w:body></w:document>'
            . "\n";
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $listLevel): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => [$this->paragraphXml($node, null, null)],
            'heading' => [$this->paragraphXml($node, 'Heading' . max(1, min(6, (int) $node->attr('level', 1))), null)],
            'bullet_list' => $this->listXml($node, false, $listLevel),
            'ordered_list' => $this->listXml($node, true, $listLevel),
            'blockquote', 'div' => $this->blockCollectionXml($node->children, $listLevel),
            'code_block' => [$this->textParagraphXml((string) $node->attr('text', ''), 'SourceCode', ['code' => true])],
            'line_block' => $this->lineBlockXml($node),
            'horizontal_rule' => [$this->horizontalRuleXml()],
            'table' => $this->tableXml($node),
            default => $this->fallbackBlockXml($node, $listLevel),
        };
    }

    /**
     * @param list<AstNode> $children
     * @return list<string>
     */
    private function blockCollectionXml(array $children, int $listLevel): array
    {
        $blocks = [];
        $inlineBuffer = [];
        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }
            if ($this->isInlineNode($child)) {
                $inlineBuffer[] = $child;
                continue;
            }
            if ($inlineBuffer !== []) {
                $blocks[] = $this->paragraphFromInlinesXml($inlineBuffer, null, null);
                $inlineBuffer = [];
            }
            foreach ($this->renderBlock($child, $listLevel) as $blockXml) {
                $blocks[] = $blockXml;
            }
        }
        if ($inlineBuffer !== []) {
            $blocks[] = $this->paragraphFromInlinesXml($inlineBuffer, null, null);
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function fallbackBlockXml(AstNode $node, int $listLevel): array
    {
        if ($node->children !== []) {
            return $this->blockCollectionXml($node->children, $listLevel);
        }

        $text = (string) $node->attr('text', $node->attr('markdown', $node->attr('html', $node->attr('tex', ''))));

        return $text === '' ? [] : [$this->textParagraphXml($text, null, [])];
    }

    /**
     * @return list<string>
     */
    private function lineBlockXml(AstNode $node): array
    {
        $runs = [];
        foreach ($node->children as $index => $line) {
            if (!$line instanceof AstNode || $line->type !== 'line') {
                continue;
            }
            if ($index > 0) {
                $runs[] = '<w:r><w:br/></w:r>';
            }
            $runs[] = $line->children === []
                ? $this->textRun((string) $line->attr('text', ''), [])
                : $this->renderInlines($line->children, []);
        }

        return ['<w:p>' . implode('', $runs) . '</w:p>'];
    }

    private function horizontalRuleXml(): string
    {
        return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="auto"/></w:pBdr></w:pPr></w:p>';
    }

    /**
     * @return list<string>
     */
    private function tableXml(AstNode $table): array
    {
        $blocks = [];
        $caption = $this->tableCaptionInlines($table);
        if ($caption !== []) {
            $blocks[] = $this->paragraphFromInlinesXml($caption, 'Caption', null);
        }

        $columnCount = TableGeometry::columnCount($table);
        if ($columnCount === 0) {
            return $blocks;
        }

        $rowsXml = '';
        foreach (TableGeometry::sectionRowEntryGroups($table, $columnCount) as $group) {
            foreach (TableGeometry::layoutRows(array_column($group['rowEntries'], 'row'), $columnCount) as $layoutRow) {
                $rowsXml .= $this->tableRowXml($layoutRow, $columnCount);
            }
        }

        if ($rowsXml === '') {
            return $blocks;
        }

        $grid = '';
        foreach ($this->tableColumnWidths($table, $columnCount) as $width) {
            $grid .= '<w:gridCol w:w="' . $width . '"/>';
        }

        $blocks[] = '<w:tbl>'
            . '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '</w:tblBorders></w:tblPr>'
            . '<w:tblGrid>' . $grid . '</w:tblGrid>'
            . $rowsXml
            . '</w:tbl>';

        return $blocks;
    }

    /**
     * @param array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int}>} $layoutRow
     */
    private function tableRowXml(array $layoutRow, int $columnCount): string
    {
        $cells = '';
        $visualColumn = 0;
        foreach ($layoutRow['cells'] as $layoutCell) {
            $column = (int) $layoutCell['column'];
            while ($visualColumn < $column && $visualColumn < $columnCount) {
                $cells .= $this->tableCellXml(null, 1);
                $visualColumn++;
            }

            $colspan = max(1, (int) $layoutCell['colspan']);
            $cells .= $this->tableCellXml($layoutCell['node'], $colspan);
            $visualColumn += $colspan;
        }

        while ($visualColumn < $columnCount) {
            $cells .= $this->tableCellXml(null, 1);
            $visualColumn++;
        }

        return '<w:tr>' . $cells . '</w:tr>';
    }

    private function tableCellXml(?AstNode $cell, int $colspan): string
    {
        $properties = '<w:tcPr><w:tcW w:w="' . (2400 * $colspan) . '" w:type="dxa"/>'
            . ($colspan > 1 ? '<w:gridSpan w:val="' . $colspan . '"/>' : '')
            . '</w:tcPr>';
        if (!$cell instanceof AstNode) {
            return '<w:tc>' . $properties . '<w:p/></w:tc>';
        }

        $content = '';
        if ($cell->children === []) {
            $text = (string) $cell->attr('text', '');
            $content = $this->textParagraphXml($text, null, []);
        } else {
            foreach ($this->blockCollectionXml($cell->children, 0) as $blockXml) {
                $content .= $blockXml;
            }
        }
        if ($content === '') {
            $content = '<w:p/>';
        }

        return '<w:tc>' . $properties . $content . '</w:tc>';
    }

    /**
     * @return list<AstNode>
     */
    private function tableCaptionInlines(AstNode $table): array
    {
        $captionInlines = $table->attr('captionInlines', []);
        if (is_array($captionInlines) && $captionInlines !== []) {
            $inlines = [];
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    return [];
                }
                $inlines[] = $inline;
            }

            return $inlines;
        }

        $captionBlocks = $table->attr('captionBlocks', []);
        if (is_array($captionBlocks) && $captionBlocks !== []) {
            $texts = [];
            foreach ($captionBlocks as $block) {
                if ($block instanceof AstNode) {
                    $text = trim($this->plainText($block));
                    if ($text !== '') {
                        $texts[] = $text;
                    }
                }
            }

            return $texts === [] ? [] : [new AstNode('text', ['text' => implode(' ', $texts)])];
        }

        $caption = trim((string) $table->attr('caption', ''));

        return $caption === '' ? [] : [new AstNode('text', ['text' => $caption])];
    }

    /**
     * @return list<int>
     */
    private function tableColumnWidths(AstNode $table, int $columnCount): array
    {
        $widths = $table->attr('widths', []);
        if (!is_array($widths) || $widths === []) {
            return array_fill(0, $columnCount, 2400);
        }

        $resolved = [];
        foreach (array_values($widths) as $width) {
            $fraction = is_int($width) || is_float($width) ? (float) $width : 0.0;
            $resolved[] = max(720, (int) round($fraction * 8640));
            if (count($resolved) >= $columnCount) {
                break;
            }
        }

        while (count($resolved) < $columnCount) {
            $resolved[] = 2400;
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function listXml(AstNode $list, bool $ordered, int $listLevel): array
    {
        $numId = $ordered ? $this->orderedListNumId($list, $listLevel) : self::BULLET_NUM_ID;
        $blocks = [];
        foreach ($list->children as $item) {
            if (!$item instanceof AstNode || $item->type !== 'list_item') {
                continue;
            }

            $itemBlocks = $item->children;
            if ($itemBlocks === [] && (string) $item->attr('text', '') !== '') {
                $itemBlocks = [new AstNode('plain', [], [new AstNode('text', ['text' => (string) $item->attr('text')])])];
            }

            $numberedParagraphEmitted = false;
            foreach ($itemBlocks as $child) {
                if (!$child instanceof AstNode) {
                    continue;
                }
                if ($child->type === 'bullet_list') {
                    foreach ($this->listXml($child, false, $listLevel + 1) as $nested) {
                        $blocks[] = $nested;
                    }
                    continue;
                }
                if ($child->type === 'ordered_list') {
                    foreach ($this->listXml($child, true, $listLevel + 1) as $nested) {
                        $blocks[] = $nested;
                    }
                    continue;
                }

                if ($child->type === 'paragraph' || $child->type === 'plain') {
                    $blocks[] = $this->paragraphXml(
                        $child,
                        null,
                        $numberedParagraphEmitted ? null : ['numId' => $numId, 'level' => $listLevel]
                    );
                    $numberedParagraphEmitted = true;
                    continue;
                }

                if ($this->isInlineNode($child)) {
                    $blocks[] = $this->paragraphFromInlinesXml(
                        [$child],
                        null,
                        $numberedParagraphEmitted ? null : ['numId' => $numId, 'level' => $listLevel]
                    );
                    $numberedParagraphEmitted = true;
                    continue;
                }

                foreach ($this->renderBlock($child, $listLevel + 1) as $blockXml) {
                    if (!$numberedParagraphEmitted && str_starts_with($blockXml, '<w:p>')) {
                        $blocks[] = $this->paragraphXml(
                            new AstNode('plain', [], [new AstNode('text', ['text' => $this->plainText($child)])]),
                            null,
                            ['numId' => $numId, 'level' => $listLevel]
                        );
                        $numberedParagraphEmitted = true;
                        continue;
                    }
                    $blocks[] = $blockXml;
                }
            }

            if (!$numberedParagraphEmitted) {
                $blocks[] = $this->paragraphFromInlinesXml([], null, ['numId' => $numId, 'level' => $listLevel]);
            }
        }

        return $blocks;
    }

    private function orderedListNumId(AstNode $list, int $listLevel): int
    {
        $start = max(1, (int) $list->attr('start', 1));
        if ($start === 1) {
            return self::ORDERED_NUM_ID;
        }

        $key = $listLevel . ':' . $start;
        if (!isset($this->orderedListOverrides[$key])) {
            $this->orderedListOverrides[$key] = [
                'numId' => $this->nextNumberingId++,
                'level' => max(0, min(8, $listLevel)),
                'start' => $start,
            ];
        }

        return $this->orderedListOverrides[$key]['numId'];
    }

    /**
     * @param array{numId:int, level:int}|null $numbering
     */
    private function paragraphXml(AstNode $node, ?string $styleId, ?array $numbering): string
    {
        $runs = $node->children === []
            ? $this->textRun((string) $node->attr('text', ''), [])
            : $this->renderInlines($node->children, []);

        return $this->paragraphWrapperXml($runs, $styleId, $numbering);
    }

    /**
     * @param list<AstNode> $inlines
     * @param array{numId:int, level:int}|null $numbering
     */
    private function paragraphFromInlinesXml(array $inlines, ?string $styleId, ?array $numbering): string
    {
        return $this->paragraphWrapperXml($this->renderInlines($inlines, []), $styleId, $numbering);
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function textParagraphXml(string $text, ?string $styleId, array $format): string
    {
        return $this->paragraphWrapperXml($this->textRun($text, $format), $styleId, null);
    }

    /**
     * @param array{numId:int, level:int}|null $numbering
     */
    private function paragraphWrapperXml(string $runs, ?string $styleId, ?array $numbering): string
    {
        $properties = [];
        if ($styleId !== null && $styleId !== '') {
            $properties[] = '<w:pStyle w:val="' . self::escAttr($styleId) . '"/>';
        }
        if ($numbering !== null) {
            $properties[] = '<w:numPr><w:ilvl w:val="' . max(0, min(8, $numbering['level'])) . '"/><w:numId w:val="' . (int) $numbering['numId'] . '"/></w:numPr>';
        }

        $pPr = $properties === [] ? '' : '<w:pPr>' . implode('', $properties) . '</w:pPr>';

        return '<w:p>' . $pPr . $runs . '</w:p>';
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, bool|string> $format
     */
    private function renderInlines(array $nodes, array $format): string
    {
        $xml = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            $xml .= $this->renderInline($node, $format);
        }

        return $xml;
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function renderInline(AstNode $node, array $format): string
    {
        return match ($node->type) {
            'text', 'str' => $this->textRun((string) $node->attr('text', $node->attr('value', '')), $format),
            'space' => $this->textRun(' ', $format),
            'softbreak' => $this->textRun(' ', $format),
            'linebreak' => $this->breakRun($format),
            'emph' => $this->renderInlines($node->children, $format + ['italic' => true]),
            'strong' => $this->renderInlines($node->children, $format + ['bold' => true]),
            'underline' => $this->renderInlines($node->children, $format + ['underline' => true]),
            'strikeout', 'strike' => $this->renderInlines($node->children, $format + ['strike' => true]),
            'superscript' => $this->renderInlines($node->children, $format + ['verticalAlign' => 'superscript']),
            'subscript' => $this->renderInlines($node->children, $format + ['verticalAlign' => 'subscript']),
            'smallcaps' => $this->renderInlines($node->children, $format + ['smallCaps' => true]),
            'code' => $this->textRun((string) $node->attr('text', $node->attr('code', '')), $format + ['code' => true]),
            'link' => $this->hyperlinkXml($node, $format),
            'image' => $this->textRun($this->imageAltText($node), $format),
            'math', 'raw_inline', 'raw_html', 'raw_tex' => $this->textRun(
                (string) $node->attr('text', $node->attr('math', $node->attr('html', $node->attr('tex', '')))),
                $format
            ),
            'note', 'span' => $this->renderInlines($node->children, $format),
            default => $node->children === []
                ? $this->textRun((string) $node->attr('text', ''), $format)
                : $this->renderInlines($node->children, $format),
        };
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function hyperlinkXml(AstNode $node, array $format): string
    {
        $target = (string) $node->attr('url', $node->attr('href', ''));
        if ($target === '') {
            return $this->renderInlines($node->children, $format);
        }

        $content = $this->renderInlines(
            $node->children === [] ? [new AstNode('text', ['text' => $target])] : $node->children,
            $format + ['runStyle' => 'Hyperlink']
        );
        if (str_starts_with($target, '#')) {
            $anchor = $this->bookmarkName(substr($target, 1));

            return '<w:hyperlink w:anchor="' . self::escAttr($anchor) . '">' . $content . '</w:hyperlink>';
        }

        $relationshipId = $this->addDocumentRelationship(
            self::REL_HYPERLINK,
            $target,
            OpcRelationship::TARGET_MODE_EXTERNAL
        );

        return '<w:hyperlink r:id="' . self::escAttr($relationshipId) . '">' . $content . '</w:hyperlink>';
    }

    private function addDocumentRelationship(string $type, string $target, string $targetMode): string
    {
        $id = 'rId' . $this->nextDocumentRelationshipId++;
        $this->documentRelationships[] = new OpcRelationship($id, $type, $target, $targetMode);

        return $id;
    }

    private function bookmarkName(string $target): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $target) ?? '';
        $name = trim($name, '_');
        if ($name === '') {
            return '_';
        }
        if (preg_match('/^[A-Za-z_]/', $name) !== 1) {
            $name = '_' . $name;
        }

        return substr($name, 0, 40);
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function textRun(string $text, array $format): string
    {
        if ($text === '') {
            $properties = $this->runPropertiesXml($format);

            return $properties === '' ? '<w:r/>' : '<w:r>' . $properties . '</w:r>';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/(\n|\t)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            $parts = [$text];
        }

        $runs = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === "\n") {
                $runs .= $this->breakRun($format);
                continue;
            }
            if ($part === "\t") {
                $runs .= '<w:r>' . $this->runPropertiesXml($format) . '<w:tab/></w:r>';
                continue;
            }
            $space = $this->preserveSpaceAttribute($part);
            $runs .= '<w:r>' . $this->runPropertiesXml($format) . '<w:t' . $space . '>' . self::escText($part) . '</w:t></w:r>';
        }

        return $runs;
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function breakRun(array $format): string
    {
        return '<w:r>' . $this->runPropertiesXml($format) . '<w:br/></w:r>';
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function runPropertiesXml(array $format): string
    {
        $props = [];
        $runStyle = (string) ($format['runStyle'] ?? '');
        if (($format['code'] ?? false) === true) {
            $runStyle = 'VerbatimChar';
        }
        if ($runStyle !== '') {
            $props[] = '<w:rStyle w:val="' . self::escAttr($runStyle) . '"/>';
        }
        if (($format['bold'] ?? false) === true) {
            $props[] = '<w:b/>';
        }
        if (($format['italic'] ?? false) === true) {
            $props[] = '<w:i/>';
        }
        if (($format['underline'] ?? false) === true) {
            $props[] = '<w:u w:val="single"/>';
        }
        if (($format['strike'] ?? false) === true) {
            $props[] = '<w:strike/>';
        }
        if (($format['smallCaps'] ?? false) === true) {
            $props[] = '<w:smallCaps/>';
        }
        if (isset($format['verticalAlign']) && is_string($format['verticalAlign'])) {
            $props[] = '<w:vertAlign w:val="' . self::escAttr($format['verticalAlign']) . '"/>';
        }

        return $props === [] ? '' : '<w:rPr>' . implode('', $props) . '</w:rPr>';
    }

    private function preserveSpaceAttribute(string $text): string
    {
        return preg_match('/(^ | $|  )/', $text) === 1 ? ' xml:space="preserve"' : '';
    }

    private function imageAltText(AstNode $node): string
    {
        if ($node->children !== []) {
            return $this->plainInlineText($node->children);
        }

        return (string) $node->attr('alt', $node->attr('title', $node->attr('url', $node->attr('src', ''))));
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'str',
            'space',
            'softbreak',
            'linebreak',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'strike',
            'superscript',
            'subscript',
            'smallcaps',
            'code',
            'link',
            'image',
            'math',
            'raw_inline',
            'raw_html',
            'raw_tex',
            'note',
            'span',
        ], true);
    }

    private function plainText(AstNode $node): string
    {
        if ($this->isInlineNode($node)) {
            return $this->plainInlineText([$node]);
        }
        if ($node->children !== []) {
            $parts = [];
            foreach ($node->children as $child) {
                if ($child instanceof AstNode) {
                    $parts[] = $this->plainText($child);
                }
            }

            return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
        }

        return (string) $node->attr('text', '');
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            $text .= match ($node->type) {
                'text', 'str' => (string) $node->attr('text', $node->attr('value', '')),
                'space', 'softbreak' => ' ',
                'linebreak' => "\n",
                'code' => (string) $node->attr('text', $node->attr('code', '')),
                'image' => $this->imageAltText($node),
                'math', 'raw_inline', 'raw_html', 'raw_tex' => (string) $node->attr('text', $node->attr('math', $node->attr('html', $node->attr('tex', '')))),
                default => $node->children === [] ? (string) $node->attr('text', '') : $this->plainInlineText($node->children),
            };
        }

        return $text;
    }

    private function sectionPropertiesXml(): string
    {
        return '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/><w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr>';
    }

    private function stylesXml(): string
    {
        $headingStyles = '';
        for ($level = 1; $level <= 6; $level++) {
            $fontSize = (string) max(22, 32 - (($level - 1) * 2));
            $headingStyles .= '<w:style w:type="paragraph" w:styleId="Heading' . $level . '">'
                . '<w:name w:val="heading ' . $level . '"/>'
                . '<w:basedOn w:val="Normal"/>'
                . '<w:next w:val="Normal"/>'
                . '<w:uiPriority w:val="' . (9 + $level) . '"/>'
                . '<w:qFormat/>'
                . '<w:pPr><w:keepNext/><w:keepLines/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="' . ($level - 1) . '"/></w:pPr>'
                . '<w:rPr><w:b/><w:sz w:val="' . $fontSize . '"/><w:szCs w:val="' . $fontSize . '"/></w:rPr>'
                . '</w:style>';
        }

        return self::xmlDeclaration()
            . '<w:styles xmlns:w="' . self::NS_W . '">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="259" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="240"/></w:pPr><w:rPr><w:b/><w:sz w:val="52"/><w:szCs w:val="52"/></w:rPr></w:style>'
            . $headingStyles
            . '<w:style w:type="paragraph" w:styleId="SourceCode"><w:name w:val="Source Code"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:before="120" w:after="120"/><w:ind w:left="360" w:right="360"/></w:pPr><w:rPr><w:rStyle w:val="VerbatimChar"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Caption"><w:name w:val="caption"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:before="120" w:after="120"/></w:pPr><w:rPr><w:i/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:style>'
            . '<w:style w:type="character" w:styleId="VerbatimChar"><w:name w:val="Verbatim Char"/><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:style>'
            . '<w:style w:type="character" w:styleId="Hyperlink"><w:name w:val="Hyperlink"/><w:rPr><w:color w:val="0563C1"/><w:u w:val="single"/></w:rPr></w:style>'
            . '</w:styles>'
            . "\n";
    }

    private function numberingXml(): string
    {
        $bulletLevels = '';
        $orderedLevels = '';
        for ($level = 0; $level < 9; $level++) {
            $left = 720 + ($level * 360);
            $text = '%' . ($level + 1) . '.';
            $bulletLevels .= '<w:lvl w:ilvl="' . $level . '"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="&#8226;"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="' . $left . '" w:hanging="360"/></w:pPr><w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr></w:lvl>';
            $orderedLevels .= '<w:lvl w:ilvl="' . $level . '"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="' . $text . '"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="' . $left . '" w:hanging="360"/></w:pPr></w:lvl>';
        }

        $numbers = '<w:num w:numId="' . self::BULLET_NUM_ID . '"><w:abstractNumId w:val="1"/></w:num>'
            . '<w:num w:numId="' . self::ORDERED_NUM_ID . '"><w:abstractNumId w:val="2"/></w:num>';
        foreach ($this->orderedListOverrides as $override) {
            $numbers .= '<w:num w:numId="' . $override['numId'] . '"><w:abstractNumId w:val="2"/><w:lvlOverride w:ilvl="' . $override['level'] . '"><w:startOverride w:val="' . $override['start'] . '"/></w:lvlOverride></w:num>';
        }

        return self::xmlDeclaration()
            . '<w:numbering xmlns:w="' . self::NS_W . '">'
            . '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>' . $bulletLevels . '</w:abstractNum>'
            . '<w:abstractNum w:abstractNumId="2"><w:multiLevelType w:val="hybridMultilevel"/>' . $orderedLevels . '</w:abstractNum>'
            . $numbers
            . '</w:numbering>'
            . "\n";
    }

    private function settingsXml(): string
    {
        return self::xmlDeclaration()
            . '<w:settings xmlns:w="' . self::NS_W . '">'
            . '<w:zoom w:percent="100"/>'
            . '<w:defaultTabStop w:val="720"/>'
            . '<w:characterSpacingControl w:val="doNotCompress"/>'
            . '<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>'
            . '<w:themeFontLang w:val="en-US"/>'
            . '</w:settings>'
            . "\n";
    }

    private static function escText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function escAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
