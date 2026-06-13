<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxOpenXmlReader
{
    private const NS_CT = 'http://schemas.openxmlformats.org/package/2006/content-types';
    private const NS_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';
    private const NS_W15 = 'http://schemas.microsoft.com/office/word/2012/wordml';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_EP = 'http://schemas.openxmlformats.org/officeDocument/2006/extended-properties';
    private const NS_CUSTOM_PROPS = 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties';
    private const NS_VT = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const NS_C = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
    private const NS_O = 'urn:schemas-microsoft-com:office:office';
    private const NS_DS = 'http://schemas.openxmlformats.org/officeDocument/2006/customXml';
    private const NS_ACTIVEX = 'http://schemas.microsoft.com/office/2006/activeX';
    private const OFFICE_DOCUMENT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const CORE_PROPERTIES_REL = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    private const EXTENDED_PROPERTIES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    private const CUSTOM_PROPERTIES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    private const STYLES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    private const NUMBERING_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    private const SETTINGS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    private const ATTACHED_TEMPLATE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate';
    private const WEB_SETTINGS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings';
    private const FONT_TABLE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable';
    private const FONT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/font';
    private const THEME_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    private const GLOSSARY_DOCUMENT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument';
    private const FOOTNOTES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    private const ENDNOTES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes';
    private const COMMENTS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    private const COMMENTS_EXTENDED_REL = 'http://schemas.microsoft.com/office/2011/relationships/commentsExtended';
    private const HEADER_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
    private const FOOTER_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';
    private const SUBDOCUMENT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument';
    private const CUSTOM_XML_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
    private const CUSTOM_XML_PROPS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';
    private const ALT_CHUNK_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk';
    private const OLE_OBJECT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';
    private const EMBEDDED_PACKAGE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
    private const CHART_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart';
    private const ACTIVEX_CONTROL_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/control';
    private const ACTIVEX_BINARY_REL = 'http://schemas.microsoft.com/office/2006/relationships/activeXControlBinary';
    private const VBA_PROJECT_REL = 'http://schemas.microsoft.com/office/2006/relationships/vbaProject';
    private const VBA_PROJECT_SIGNATURE_REL = 'http://schemas.microsoft.com/office/2006/relationships/vbaProjectSignature';
    private const VBA_DATA_REL = 'http://schemas.microsoft.com/office/2006/relationships/wordVbaData';
    private const THUMBNAIL_REL = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail';
    private const DIGITAL_SIGNATURE_ORIGIN_REL = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
    private const DIGITAL_SIGNATURE_SIGNATURE_REL = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';
    private const NS_XMLDSIG = 'http://www.w3.org/2000/09/xmldsig#';
    private const CT_WORD_DOCUMENT = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
    private const CT_WORD_TEMPLATE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml';
    private const CT_WORD_MACRO_ENABLED_DOCUMENT = 'application/vnd.ms-word.document.macroenabled.main+xml';
    private const CT_WORD_MACRO_ENABLED_TEMPLATE = 'application/vnd.ms-word.template.macroenabledtemplate.main+xml';
    private const CT_CORE_PROPERTIES = 'application/vnd.openxmlformats-package.core-properties+xml';
    private const CT_EXTENDED_PROPERTIES = 'application/vnd.openxmlformats-officedocument.extended-properties+xml';
    private const CT_CUSTOM_PROPERTIES = 'application/vnd.openxmlformats-officedocument.custom-properties+xml';
    private const CT_WORD_STYLES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml';
    private const CT_WORD_NUMBERING = 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml';
    private const CT_WORD_SETTINGS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml';
    private const CT_WORD_WEB_SETTINGS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.websettings+xml';
    private const CT_WORD_FONT_TABLE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.fonttable+xml';
    private const CT_OBFUSCATED_FONT = 'application/vnd.openxmlformats-officedocument.obfuscatedfont';
    private const CT_THEME = 'application/vnd.openxmlformats-officedocument.theme+xml';
    private const CT_WORD_GLOSSARY_DOCUMENT = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml';
    private const CT_WORD_FOOTNOTES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml';
    private const CT_WORD_ENDNOTES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml';
    private const CT_WORD_COMMENTS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml';
    private const CT_WORD_COMMENTS_EXTENDED = 'application/vnd.ms-word.commentsext+xml';
    private const CT_CUSTOM_XML_PROPERTIES = 'application/vnd.openxmlformats-officedocument.customxmlproperties+xml';
    private const CT_PACKAGE_RELATIONSHIPS = 'application/vnd.openxmlformats-package.relationships+xml';
    private const CT_CHART = 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml';
    private const CT_ACTIVEX_XML = 'application/vnd.ms-office.activex+xml';
    private const CT_ACTIVEX_BINARY = 'application/vnd.ms-office.activex';
    private const CT_VBA_PROJECT = 'application/vnd.ms-office.vbaproject';
    private const CT_VBA_PROJECT_SIGNATURE = 'application/vnd.ms-office.vbaprojectsignature';
    private const CT_VBA_DATA = 'application/vnd.ms-word.vbadata+xml';
    private const CT_DIGITAL_SIGNATURE_ORIGIN = 'application/vnd.openxmlformats-package.digital-signature-origin';
    private const CT_DIGITAL_SIGNATURE_XMLSIGNATURE = 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml';

    public function readFile(string $path): AstNode
    {
        if (!is_file($path)) {
            throw new \RuntimeException("DOCX package does not exist: {$path}");
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read DOCX package: {$path}");
        }

        return $this->readZipPackage(ZipPackage::fromString($bytes));
    }

    public function readZipPackage(ZipPackage $package): AstNode
    {
        $parts = [];
        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $parts[$entry->name] = $package->read($entry->name);
        }

        return $this->readPackage($parts, $package);
    }

    /**
     * @param array<string, string> $parts
     */
    public function readPackage(array $parts, ?ZipPackage $sourcePackage = null): AstNode
    {
        $parts = $this->normalizeParts($parts);
        $contentTypes = $this->readContentTypes($parts);
        $rootRelationships = $this->readRelationshipsPart($parts, '_rels/.rels');
        $documentPart = $this->officeDocumentPart($rootRelationships);

        if (!isset($parts[$documentPart])) {
            throw new \RuntimeException("DOCX package is missing {$documentPart}");
        }

        $documentRelationshipsPart = $this->relationshipsPartFor($documentPart);
        $documentRelationships = $this->readRelationshipsPart($parts, $documentRelationshipsPart);
        $packageProvenance = $this->packageProvenance(
            $parts,
            $contentTypes,
            $rootRelationships,
            $documentPart,
            $documentRelationshipsPart,
            $documentRelationships,
            $sourcePackage,
        );
        $packageThumbnails = $this->packageThumbnailProvenance($parts, $rootRelationships, $contentTypes);
        $packageProvenance['packageThumbnails'] = $packageThumbnails;
        $packageProvenance['summary']['packageThumbnailCount'] = $packageThumbnails['count'];
        $packageProvenance['summary']['packageThumbnailReadableCount'] = $packageThumbnails['readableCount'];
        $packageProvenance['summary']['packageThumbnailMissingCount'] = $packageThumbnails['missingCount'];
        $packageProvenance['summary']['packageThumbnailExternalCount'] = $packageThumbnails['externalCount'];
        $packageProvenance['summary']['packageThumbnailInvalidCount'] = $packageThumbnails['invalidCount'];
        $packageProvenance['summary']['packageThumbnailIssueCount'] = $packageThumbnails['issueCount'];
        $packageProvenance['summary']['packageThumbnailIssueCodes'] = $packageThumbnails['issueCodes'];
        $digitalSignatures = $this->packageDigitalSignatureProvenance($parts, $rootRelationships, $contentTypes);
        $packageProvenance['digitalSignatures'] = $digitalSignatures;
        $packageProvenance['summary']['digitalSignatureOriginCount'] = $digitalSignatures['originCount'];
        $packageProvenance['summary']['digitalSignatureExistingOriginCount'] = $digitalSignatures['existingOriginCount'];
        $packageProvenance['summary']['digitalSignatureMissingOriginCount'] = $digitalSignatures['missingOriginCount'];
        $packageProvenance['summary']['digitalSignatureExternalOriginCount'] = $digitalSignatures['externalOriginCount'];
        $packageProvenance['summary']['digitalSignatureSignatureCount'] = $digitalSignatures['signatureCount'];
        $packageProvenance['summary']['digitalSignatureExistingSignatureCount'] = $digitalSignatures['existingSignatureCount'];
        $packageProvenance['summary']['digitalSignatureMissingSignatureCount'] = $digitalSignatures['missingSignatureCount'];
        $packageProvenance['summary']['digitalSignatureExternalSignatureCount'] = $digitalSignatures['externalSignatureCount'];
        $packageProvenance['summary']['digitalSignatureInvalidXmlCount'] = $digitalSignatures['invalidSignatureXmlCount'];
        $packageProvenance['summary']['digitalSignatureUnexpectedRootCount'] = $digitalSignatures['unexpectedSignatureRootCount'];
        $packageProvenance['summary']['digitalSignatureIssueCount'] = $digitalSignatures['issueCount'];
        $packageProvenance['summary']['digitalSignatureIssueCodes'] = $digitalSignatures['issueCodes'];
        $stylesPart = $this->stylesPart($parts, $documentRelationships, $documentPart);
        $styles = $this->readStyles($stylesPart['xml'], $stylesPart['partName']);
        $numberingPart = $this->numberingPart($parts, $documentRelationships, $documentPart);
        $numbering = $this->readNumbering($numberingPart['xml'], $numberingPart['partName']);
        $settingsPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::SETTINGS_REL, 'settings.xml');
        $settings = $this->readSettings($settingsPart['xml'], $settingsPart['partName']);
        $settingsRelationshipsPart = $this->relationshipsPartFor($settingsPart['partName']);
        $settingsRelationships = $this->readRelationshipsPart($parts, $settingsRelationshipsPart);
        $attachedTemplates = $this->readAttachedTemplates(
            $parts,
            $settingsPart['xml'],
            $settingsPart['partName'],
            $settingsRelationshipsPart,
            $settingsRelationships,
            $contentTypes,
        );
        $webSettingsPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::WEB_SETTINGS_REL, 'webSettings.xml');
        $webSettings = $this->readWebSettings($webSettingsPart['xml'], $webSettingsPart['partName']);
        $fontTablePart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::FONT_TABLE_REL, 'fontTable.xml');
        $fontTable = $this->readFontTable($fontTablePart['xml'], $fontTablePart['partName'], $parts, $contentTypes);
        $themePart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::THEME_REL, 'theme/theme1.xml');
        $theme = $this->readTheme($themePart['xml'], $themePart['partName']);
        $glossaryDocumentPart = $this->relatedDocumentPart(
            $parts,
            $documentRelationships,
            $documentPart,
            self::GLOSSARY_DOCUMENT_REL,
            'glossary/document.xml',
        );
        $glossaryRelationshipsPart = $this->relationshipsPartFor($glossaryDocumentPart['partName']);
        $glossaryRelationships = $this->readRelationshipsPart($parts, $glossaryRelationshipsPart);
        $footnotesPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::FOOTNOTES_REL, 'footnotes.xml');
        $footnoteRelationshipsPart = $this->relationshipsPartFor($footnotesPart['partName']);
        $footnoteRelationships = $this->readRelationshipsPart($parts, $footnoteRelationshipsPart);
        $footnotes = $this->readNotes(
            $parts,
            $footnotesPart['xml'],
            $footnotesPart['partName'],
            $footnoteRelationshipsPart,
            'footnotes',
            'footnote',
            'footnote',
            $footnoteRelationships,
            $contentTypes,
            $styles,
            $numbering,
        );
        $endnotesPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::ENDNOTES_REL, 'endnotes.xml');
        $endnoteRelationshipsPart = $this->relationshipsPartFor($endnotesPart['partName']);
        $endnoteRelationships = $this->readRelationshipsPart($parts, $endnoteRelationshipsPart);
        $endnotes = $this->readNotes(
            $parts,
            $endnotesPart['xml'],
            $endnotesPart['partName'],
            $endnoteRelationshipsPart,
            'endnotes',
            'endnote',
            'endnote',
            $endnoteRelationships,
            $contentTypes,
            $styles,
            $numbering,
        );
        $commentsPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::COMMENTS_REL, 'comments.xml');
        $commentRelationshipsPart = $this->relationshipsPartFor($commentsPart['partName']);
        $commentRelationships = $this->readRelationshipsPart($parts, $commentRelationshipsPart);
        $commentsExtendedPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::COMMENTS_EXTENDED_REL, 'commentsExtended.xml');
        $commentsExtended = $this->readCommentsExtended($commentsExtendedPart['xml'], $commentsExtendedPart['partName']);
        $comments = $this->readComments(
            $parts,
            $commentsPart['xml'],
            $commentsPart['partName'],
            $commentRelationshipsPart,
            $commentRelationships,
            $contentTypes,
            $styles,
            $numbering,
            $commentsExtended['byParaId'],
        );
        $corePropertiesPart = $this->corePropertiesPart($parts, $rootRelationships);
        $meta = $this->readCoreProperties($corePropertiesPart['xml'], $corePropertiesPart['partName']);
        $extendedPropertiesPart = $this->rootRelatedPart($parts, $rootRelationships, self::EXTENDED_PROPERTIES_REL, 'docProps/app.xml');
        $extendedProperties = $this->readExtendedProperties($extendedPropertiesPart['xml'], $extendedPropertiesPart['partName']);
        $customPropertiesPart = $this->rootRelatedPart($parts, $rootRelationships, self::CUSTOM_PROPERTIES_REL, 'docProps/custom.xml');
        $customProperties = $this->readCustomProperties($customPropertiesPart['xml'], $customPropertiesPart['partName']);
        $media = $this->mediaMetadata($parts, $contentTypes);
        $referencedNotes = [
            'footnote' => $footnotes['nodes'],
            'endnote' => $endnotes['nodes'],
            'comment' => $comments['nodes'],
        ];
        $glossaryDocument = $this->readGlossaryDocument(
            $glossaryDocumentPart['xml'],
            $glossaryDocumentPart['partName'],
            $glossaryDocumentPart['exists'],
            $glossaryRelationshipsPart,
            $glossaryRelationships,
            $contentTypes,
            $styles,
            $numbering,
            $referencedNotes,
        );
        $sectionProperties = $this->readSectionProperties($parts[$documentPart], $documentPart);
        $headerFooterReferences = $this->readHeaderFooterReferences($parts[$documentPart], $documentPart);
        $headers = $this->readHeaderFooterParts(
            $parts,
            $documentRelationships,
            $documentPart,
            self::HEADER_REL,
            'header',
            'hdr',
            $headerFooterReferences['header'],
            $contentTypes,
            $styles,
            $numbering,
            $referencedNotes,
        );
        $footers = $this->readHeaderFooterParts(
            $parts,
            $documentRelationships,
            $documentPart,
            self::FOOTER_REL,
            'footer',
            'ftr',
            $headerFooterReferences['footer'],
            $contentTypes,
            $styles,
            $numbering,
            $referencedNotes,
        );
        $packageProvenance['summary']['headerPartCount'] = $headers['count'];
        $packageProvenance['summary']['headerExistingPartCount'] = $headers['existingCount'];
        $packageProvenance['summary']['headerMissingPartCount'] = $headers['missingCount'];
        $packageProvenance['summary']['headerExternalTargetCount'] = $headers['externalCount'];
        $packageProvenance['summary']['headerUnresolvedReferenceCount'] = $headers['unresolvedCount'];
        $packageProvenance['summary']['headerIssueCount'] = $headers['issueCount'];
        $packageProvenance['summary']['headerIssueCodes'] = $headers['issueCodes'];
        $packageProvenance['summary']['footerPartCount'] = $footers['count'];
        $packageProvenance['summary']['footerExistingPartCount'] = $footers['existingCount'];
        $packageProvenance['summary']['footerMissingPartCount'] = $footers['missingCount'];
        $packageProvenance['summary']['footerExternalTargetCount'] = $footers['externalCount'];
        $packageProvenance['summary']['footerUnresolvedReferenceCount'] = $footers['unresolvedCount'];
        $packageProvenance['summary']['footerIssueCount'] = $footers['issueCount'];
        $packageProvenance['summary']['footerIssueCodes'] = $footers['issueCodes'];
        $packageProvenance['sections'] = $sectionProperties;
        $packageProvenance['summary']['sectionCount'] = $sectionProperties['count'];
        $packageProvenance['summary']['sectionHeaderReferenceCount'] = $sectionProperties['headerReferenceCount'];
        $packageProvenance['summary']['sectionFooterReferenceCount'] = $sectionProperties['footerReferenceCount'];
        $packageProvenance['summary']['sectionLandscapeCount'] = $sectionProperties['landscapeCount'];
        $packageProvenance['summary']['sectionTitlePageCount'] = $sectionProperties['titlePageCount'];
        $packageProvenance['summary']['sectionColumnLayoutCount'] = $sectionProperties['columnLayoutCount'];
        $packageProvenance['summary']['sectionIssueCount'] = $sectionProperties['issueCount'];
        $packageProvenance['summary']['sectionIssueCodes'] = $sectionProperties['issueCodes'];
        $alternativeFormats = $this->readAlternativeFormatImports(
            $parts,
            $parts[$documentPart],
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $embeddedObjects = $this->readEmbeddedObjects(
            $parts,
            $parts[$documentPart],
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $subdocuments = $this->readSubdocuments(
            $parts,
            $parts[$documentPart],
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $packageProvenance['alternativeFormats'] = $alternativeFormats;
        $packageProvenance['summary']['alternativeFormatCount'] = $alternativeFormats['count'];
        $packageProvenance['summary']['alternativeFormatRelationshipCount'] = $alternativeFormats['relationshipCount'];
        $packageProvenance['summary']['alternativeFormatReferencedCount'] = $alternativeFormats['referencedCount'];
        $packageProvenance['summary']['alternativeFormatExistingCount'] = $alternativeFormats['existingCount'];
        $packageProvenance['summary']['alternativeFormatMissingCount'] = $alternativeFormats['missingCount'];
        $packageProvenance['summary']['alternativeFormatExternalCount'] = $alternativeFormats['externalCount'];
        $packageProvenance['summary']['alternativeFormatIssueCount'] = $alternativeFormats['issueCount'];
        $packageProvenance['summary']['alternativeFormatIssueCodes'] = $alternativeFormats['issueCodes'];
        $packageProvenance['embeddedObjects'] = $embeddedObjects;
        $packageProvenance['summary']['embeddedObjectCount'] = $embeddedObjects['count'];
        $packageProvenance['summary']['embeddedObjectRelationshipCount'] = $embeddedObjects['relationshipCount'];
        $packageProvenance['summary']['embeddedObjectReferencedCount'] = $embeddedObjects['referencedCount'];
        $packageProvenance['summary']['embeddedObjectExistingCount'] = $embeddedObjects['existingCount'];
        $packageProvenance['summary']['embeddedObjectMissingCount'] = $embeddedObjects['missingCount'];
        $packageProvenance['summary']['embeddedObjectExternalCount'] = $embeddedObjects['externalCount'];
        $packageProvenance['summary']['embeddedObjectIssueCount'] = $embeddedObjects['issueCount'];
        $packageProvenance['summary']['embeddedObjectIssueCodes'] = $embeddedObjects['issueCodes'];
        $packageProvenance['subdocuments'] = $subdocuments;
        $packageProvenance['summary']['subdocumentCount'] = $subdocuments['count'];
        $packageProvenance['summary']['subdocumentRelationshipCount'] = $subdocuments['relationshipCount'];
        $packageProvenance['summary']['subdocumentReferencedCount'] = $subdocuments['referencedCount'];
        $packageProvenance['summary']['subdocumentExistingCount'] = $subdocuments['existingCount'];
        $packageProvenance['summary']['subdocumentMissingCount'] = $subdocuments['missingCount'];
        $packageProvenance['summary']['subdocumentExternalCount'] = $subdocuments['externalCount'];
        $packageProvenance['summary']['subdocumentInternalCount'] = $subdocuments['internalCount'];
        $packageProvenance['summary']['subdocumentUnsupportedCount'] = $subdocuments['unsupportedCount'];
        $packageProvenance['summary']['subdocumentIssueCount'] = $subdocuments['issueCount'];
        $packageProvenance['summary']['subdocumentIssueCodes'] = $subdocuments['issueCodes'];
        $chartParts = $this->readChartParts(
            $parts,
            $parts[$documentPart],
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $activeXControls = $this->readActiveXControls(
            $parts,
            $parts[$documentPart],
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $vbaProjects = $this->readVbaProjects(
            $parts,
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $customXmlParts = $this->readCustomXmlParts(
            $parts,
            $documentPart,
            $documentRelationships,
            $contentTypes,
        );
        $selectedXmlParts = $this->selectedXmlPartProvenance($parts, $contentTypes, [
            $this->selectedXmlPartDefinition(
                'document',
                $documentPart,
                $parts[$documentPart],
                true,
                $this->firstRelationshipByType($rootRelationships, self::OFFICE_DOCUMENT_REL),
                '/',
                '_rels/.rels',
                true,
                self::NS_W,
                'document',
                self::CT_WORD_DOCUMENT,
                [
                    self::CT_WORD_TEMPLATE,
                    self::CT_WORD_MACRO_ENABLED_DOCUMENT,
                    self::CT_WORD_MACRO_ENABLED_TEMPLATE,
                ],
            ),
            $this->selectedXmlPartDefinition('coreProperties', $corePropertiesPart['partName'], $corePropertiesPart['xml'], $corePropertiesPart['exists'], $corePropertiesPart['relationship'], '/', '_rels/.rels', false, self::NS_CP, 'coreProperties', self::CT_CORE_PROPERTIES),
            $this->selectedXmlPartDefinition('extendedProperties', $extendedPropertiesPart['partName'], $extendedPropertiesPart['xml'], $extendedPropertiesPart['exists'], $extendedPropertiesPart['relationship'], '/', '_rels/.rels', false, self::NS_EP, 'Properties', self::CT_EXTENDED_PROPERTIES),
            $this->selectedXmlPartDefinition('customProperties', $customPropertiesPart['partName'], $customPropertiesPart['xml'], $customPropertiesPart['exists'], $customPropertiesPart['relationship'], '/', '_rels/.rels', false, self::NS_CUSTOM_PROPS, 'Properties', self::CT_CUSTOM_PROPERTIES),
            $this->selectedXmlPartDefinition('styles', $stylesPart['partName'], $stylesPart['xml'], $stylesPart['exists'], $stylesPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'styles', self::CT_WORD_STYLES),
            $this->selectedXmlPartDefinition('numbering', $numberingPart['partName'], $numberingPart['xml'], $numberingPart['exists'], $numberingPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'numbering', self::CT_WORD_NUMBERING),
            $this->selectedXmlPartDefinition('settings', $settingsPart['partName'], $settingsPart['xml'], $settingsPart['exists'], $settingsPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'settings', self::CT_WORD_SETTINGS),
            $this->selectedXmlPartDefinition('webSettings', $webSettingsPart['partName'], $webSettingsPart['xml'], $webSettingsPart['exists'], $webSettingsPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'webSettings', self::CT_WORD_WEB_SETTINGS),
            $this->selectedXmlPartDefinition('fontTable', $fontTablePart['partName'], $fontTablePart['xml'], $fontTablePart['exists'], $fontTablePart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'fonts', self::CT_WORD_FONT_TABLE),
            $this->selectedXmlPartDefinition('theme', $themePart['partName'], $themePart['xml'], $themePart['exists'], $themePart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_A, 'theme', self::CT_THEME),
            $this->selectedXmlPartDefinition('glossaryDocument', $glossaryDocumentPart['partName'], $glossaryDocumentPart['xml'], $glossaryDocumentPart['exists'], $glossaryDocumentPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'glossaryDocument', self::CT_WORD_GLOSSARY_DOCUMENT),
            $this->selectedXmlPartDefinition('footnotes', $footnotesPart['partName'], $footnotesPart['xml'], $footnotesPart['exists'], $footnotesPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'footnotes', self::CT_WORD_FOOTNOTES),
            $this->selectedXmlPartDefinition('endnotes', $endnotesPart['partName'], $endnotesPart['xml'], $endnotesPart['exists'], $endnotesPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'endnotes', self::CT_WORD_ENDNOTES),
            $this->selectedXmlPartDefinition('comments', $commentsPart['partName'], $commentsPart['xml'], $commentsPart['exists'], $commentsPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W, 'comments', self::CT_WORD_COMMENTS),
            $this->selectedXmlPartDefinition('commentsExtended', $commentsExtendedPart['partName'], $commentsExtendedPart['xml'], $commentsExtendedPart['exists'], $commentsExtendedPart['relationship'], $documentPart, $documentRelationshipsPart, false, self::NS_W15, 'commentsEx', self::CT_WORD_COMMENTS_EXTENDED),
        ]);
        $packageProvenance['selectedXmlParts'] = $selectedXmlParts;
        $packageProvenance['summary']['selectedXmlPartCount'] = $selectedXmlParts['count'];
        $packageProvenance['summary']['selectedXmlPartIssueCount'] = $selectedXmlParts['issueCount'];
        $packageProvenance['summary']['selectedXmlPartInvalidXmlCount'] = $selectedXmlParts['invalidXmlCount'];
        $packageProvenance['summary']['selectedXmlPartIssueKinds'] = $selectedXmlParts['issueKinds'];
        $packageProvenance['summary']['glossaryDocumentPart'] = $glossaryDocumentPart['partName'];
        $packageProvenance['summary']['glossaryDocumentExists'] = $glossaryDocumentPart['exists'];
        $packageProvenance['summary']['glossaryDocumentRelationshipId'] = $glossaryDocumentPart['relationship']['id'] ?? null;
        $packageProvenance['glossaryDocument'] = $glossaryDocument;
        $packageProvenance['summary']['glossaryDocumentRelationshipCount'] = $glossaryDocument['relationshipCount'];
        $packageProvenance['summary']['glossaryDocumentBuildingBlockCount'] = $glossaryDocument['count'];
        $packageProvenance['summary']['glossaryDocumentBodyBlockCount'] = $glossaryDocument['bodyBlockCount'];
        $packageProvenance['summary']['glossaryDocumentIssueCount'] = $glossaryDocument['issueCount'];
        $packageProvenance['summary']['glossaryDocumentIssueCodes'] = $glossaryDocument['issueCodes'];
        $packageProvenance['customXmlParts'] = $customXmlParts;
        $packageProvenance['summary']['customXmlPartCount'] = $customXmlParts['count'];
        $packageProvenance['summary']['customXmlIssueCount'] = $customXmlParts['issueCount'];
        $packageProvenance['summary']['customXmlDuplicateStoreItemIdCount'] = $customXmlParts['duplicateStoreItemIdCount'];
        $packageProvenance['summary']['customXmlDuplicateStoreItemIds'] = $customXmlParts['duplicateStoreItemIds'];
        $packageProvenance['summary']['attachedTemplateCount'] = $attachedTemplates['count'];
        $packageProvenance['summary']['attachedTemplateExternalCount'] = $attachedTemplates['externalCount'];
        $packageProvenance['summary']['attachedTemplateIssueCount'] = $attachedTemplates['issueCount'];
        $packageProvenance['summary']['fontTableEmbeddedFontCount'] = (int) ($fontTable['embeddedFontRelationshipCount'] ?? 0);
        $packageProvenance['summary']['fontTableEmbeddedFontExistingCount'] = (int) ($fontTable['embeddedFontExistingCount'] ?? 0);
        $packageProvenance['summary']['fontTableEmbeddedFontMissingCount'] = (int) ($fontTable['embeddedFontMissingCount'] ?? 0);
        $packageProvenance['summary']['fontTableEmbeddedFontExternalCount'] = (int) ($fontTable['embeddedFontExternalCount'] ?? 0);
        $packageProvenance['summary']['fontTableEmbeddedFontIssueCount'] = (int) ($fontTable['embeddedFontIssueCount'] ?? 0);
        $packageProvenance['summary']['fontTableEmbeddedFontIssueCodes'] = $fontTable['embeddedFontIssueCodes'] ?? [];
        $packageProvenance['chartParts'] = $chartParts;
        $packageProvenance['summary']['chartPartCount'] = $chartParts['count'];
        $packageProvenance['summary']['chartPartRelationshipCount'] = $chartParts['relationshipCount'];
        $packageProvenance['summary']['chartPartReferencedCount'] = $chartParts['referencedCount'];
        $packageProvenance['summary']['chartPartExistingCount'] = $chartParts['existingCount'];
        $packageProvenance['summary']['chartPartMissingCount'] = $chartParts['missingCount'];
        $packageProvenance['summary']['chartPartExternalCount'] = $chartParts['externalCount'];
        $packageProvenance['summary']['chartPartIssueCount'] = $chartParts['issueCount'];
        $packageProvenance['summary']['chartPartIssueCodes'] = $chartParts['issueCodes'];
        $packageProvenance['summary']['chartEmbeddedPackageCount'] = $chartParts['embeddedPackageCount'];
        $packageProvenance['summary']['chartEmbeddedPackageExistingCount'] = $chartParts['embeddedPackageExistingCount'];
        $packageProvenance['summary']['chartEmbeddedPackageMissingCount'] = $chartParts['embeddedPackageMissingCount'];
        $packageProvenance['summary']['chartEmbeddedPackageExternalCount'] = $chartParts['embeddedPackageExternalCount'];
        $packageProvenance['summary']['chartEmbeddedPackageIssueCount'] = $chartParts['embeddedPackageIssueCount'];
        $packageProvenance['summary']['chartEmbeddedPackageIssueCodes'] = $chartParts['embeddedPackageIssueCodes'];
        $packageProvenance['activeXControls'] = $activeXControls;
        $packageProvenance['summary']['activeXControlCount'] = $activeXControls['count'];
        $packageProvenance['summary']['activeXControlRelationshipCount'] = $activeXControls['relationshipCount'];
        $packageProvenance['summary']['activeXControlExistingCount'] = $activeXControls['existingCount'];
        $packageProvenance['summary']['activeXControlMissingCount'] = $activeXControls['missingCount'];
        $packageProvenance['summary']['activeXControlExternalCount'] = $activeXControls['externalCount'];
        $packageProvenance['summary']['activeXBinaryCount'] = $activeXControls['binaryCount'];
        $packageProvenance['summary']['activeXBinaryExistingCount'] = $activeXControls['existingBinaryCount'];
        $packageProvenance['summary']['activeXBinaryMissingCount'] = $activeXControls['missingBinaryCount'];
        $packageProvenance['summary']['activeXIssueCount'] = $activeXControls['issueCount'];
        $packageProvenance['summary']['activeXIssueCodes'] = $activeXControls['issueCodes'];
        $packageProvenance['vbaProjects'] = $vbaProjects;
        $packageProvenance['summary']['vbaProjectCount'] = $vbaProjects['count'];
        $packageProvenance['summary']['vbaProjectRelationshipCount'] = $vbaProjects['relationshipCount'];
        $packageProvenance['summary']['vbaProjectExistingCount'] = $vbaProjects['existingCount'];
        $packageProvenance['summary']['vbaProjectMissingCount'] = $vbaProjects['missingCount'];
        $packageProvenance['summary']['vbaProjectExternalCount'] = $vbaProjects['externalCount'];
        $packageProvenance['summary']['vbaProjectSignatureCount'] = $vbaProjects['signatureCount'];
        $packageProvenance['summary']['vbaProjectExistingSignatureCount'] = $vbaProjects['existingSignatureCount'];
        $packageProvenance['summary']['vbaProjectMissingSignatureCount'] = $vbaProjects['missingSignatureCount'];
        $packageProvenance['summary']['vbaProjectExternalSignatureCount'] = $vbaProjects['externalSignatureCount'];
        $packageProvenance['summary']['vbaDataPartCount'] = $vbaProjects['dataPartCount'];
        $packageProvenance['summary']['vbaDataExistingCount'] = $vbaProjects['existingDataPartCount'];
        $packageProvenance['summary']['vbaDataMissingCount'] = $vbaProjects['missingDataPartCount'];
        $packageProvenance['summary']['vbaDataExternalCount'] = $vbaProjects['externalDataPartCount'];
        $packageProvenance['summary']['vbaProjectIssueCount'] = $vbaProjects['issueCount'];
        $packageProvenance['summary']['vbaProjectIssueCodes'] = $vbaProjects['issueCodes'];
        $blocks = $this->readDocumentBlocks($parts[$documentPart], $documentRelationships, $contentTypes, $styles, $numbering, $referencedNotes);

        $attrs = [
            'docx' => [
                'documentPart' => $documentPart,
                'corePropertiesPart' => $corePropertiesPart['partName'],
                'contentTypes' => $contentTypes,
                'rootRelationships' => $rootRelationships,
                'documentRelationships' => $documentRelationships,
                'packageProvenance' => $packageProvenance,
                'stylesPart' => $stylesPart['partName'],
                'styles' => $styles,
                'numberingPart' => $numberingPart['partName'],
                'numbering' => $numbering,
                'settingsPart' => $settingsPart['partName'],
                'settings' => $settings,
                'settingsRelationshipsPart' => $settingsRelationshipsPart,
                'settingsRelationships' => $settingsRelationships,
                'attachedTemplates' => $attachedTemplates,
                'webSettingsPart' => $webSettingsPart['partName'],
                'webSettings' => $webSettings,
                'fontTablePart' => $fontTablePart['partName'],
                'fontTable' => $fontTable,
                'themePart' => $themePart['partName'],
                'theme' => $theme,
                'glossaryDocumentPart' => $glossaryDocumentPart['partName'],
                'glossaryDocument' => $glossaryDocument,
                'extendedPropertiesPart' => $extendedPropertiesPart['partName'],
                'extendedProperties' => $extendedProperties,
                'customPropertiesPart' => $customPropertiesPart['partName'],
                'customProperties' => $customProperties,
                'footnotesPart' => $footnotesPart['partName'],
                'footnotes' => $footnotes['summary'],
                'endnotesPart' => $endnotesPart['partName'],
                'endnotes' => $endnotes['summary'],
                'commentsPart' => $commentsPart['partName'],
                'comments' => $comments['summary'],
                'commentsExtendedPart' => $commentsExtendedPart['partName'],
                'commentsExtended' => $commentsExtended['summary'],
                'headers' => $headers,
                'footers' => $footers,
                'sections' => $sectionProperties,
                'alternativeFormats' => $alternativeFormats,
                'embeddedObjects' => $embeddedObjects,
                'subdocuments' => $subdocuments,
                'chartParts' => $chartParts,
                'activeXControls' => $activeXControls,
                'vbaProjects' => $vbaProjects,
                'customXmlParts' => $customXmlParts,
                'packageThumbnails' => $packageThumbnails,
                'digitalSignatures' => $digitalSignatures,
                'media' => $media,
            ],
        ];
        if ($corePropertiesPart['relationship'] !== null) {
            $attrs['docx']['corePropertiesRelationship'] = $this->relationshipSummary(
                $corePropertiesPart['relationship'],
                '/',
                '_rels/.rels',
                $corePropertiesPart['partName'],
                $corePropertiesPart['exists'],
                $contentTypes,
            );
        }
        if ($extendedPropertiesPart['relationship'] !== null) {
            $attrs['docx']['extendedPropertiesRelationship'] = $this->relationshipSummary(
                $extendedPropertiesPart['relationship'],
                '/',
                '_rels/.rels',
                $extendedPropertiesPart['partName'],
                $extendedPropertiesPart['exists'],
                $contentTypes,
            );
        }
        if ($customPropertiesPart['relationship'] !== null) {
            $attrs['docx']['customPropertiesRelationship'] = $this->relationshipSummary(
                $customPropertiesPart['relationship'],
                '/',
                '_rels/.rels',
                $customPropertiesPart['partName'],
                $customPropertiesPart['exists'],
                $contentTypes,
            );
        }
        if ($stylesPart['relationship'] !== null) {
            $attrs['docx']['stylesRelationship'] = $this->relationshipSummary(
                $stylesPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $stylesPart['partName'],
                $stylesPart['exists'],
                $contentTypes,
            );
        }
        if ($numberingPart['relationship'] !== null) {
            $attrs['docx']['numberingRelationship'] = $this->relationshipSummary(
                $numberingPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $numberingPart['partName'],
                $numberingPart['exists'],
                $contentTypes,
            );
        }
        if ($settingsPart['relationship'] !== null) {
            $attrs['docx']['settingsRelationship'] = $this->relationshipSummary(
                $settingsPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $settingsPart['partName'],
                $settingsPart['exists'],
                $contentTypes,
            );
        }
        if ($webSettingsPart['relationship'] !== null) {
            $attrs['docx']['webSettingsRelationship'] = $this->relationshipSummary(
                $webSettingsPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $webSettingsPart['partName'],
                $webSettingsPart['exists'],
                $contentTypes,
            );
        }
        if ($fontTablePart['relationship'] !== null) {
            $attrs['docx']['fontTableRelationship'] = $this->relationshipSummary(
                $fontTablePart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $fontTablePart['partName'],
                $fontTablePart['exists'],
                $contentTypes,
            );
        }
        if ($themePart['relationship'] !== null) {
            $attrs['docx']['themeRelationship'] = $this->relationshipSummary(
                $themePart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $themePart['partName'],
                $themePart['exists'],
                $contentTypes,
            );
        }
        if ($glossaryDocumentPart['relationship'] !== null) {
            $attrs['docx']['glossaryDocumentRelationship'] = $this->relationshipSummary(
                $glossaryDocumentPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $glossaryDocumentPart['partName'],
                $glossaryDocumentPart['exists'],
                $contentTypes,
            );
        }
        if ($footnotesPart['relationship'] !== null) {
            $attrs['docx']['footnotesRelationship'] = $this->relationshipSummary(
                $footnotesPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $footnotesPart['partName'],
                $footnotesPart['exists'],
                $contentTypes,
            );
        }
        if ($endnotesPart['relationship'] !== null) {
            $attrs['docx']['endnotesRelationship'] = $this->relationshipSummary(
                $endnotesPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $endnotesPart['partName'],
                $endnotesPart['exists'],
                $contentTypes,
            );
        }
        if ($commentsPart['relationship'] !== null) {
            $attrs['docx']['commentsRelationship'] = $this->relationshipSummary(
                $commentsPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $commentsPart['partName'],
                $commentsPart['exists'],
                $contentTypes,
            );
        }
        if ($commentsExtendedPart['relationship'] !== null) {
            $attrs['docx']['commentsExtendedRelationship'] = $this->relationshipSummary(
                $commentsExtendedPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $commentsExtendedPart['partName'],
                $commentsExtendedPart['exists'],
                $contentTypes,
            );
        }
        if ($meta !== []) {
            $attrs['meta'] = $meta;
        }
        if ($extendedProperties !== []) {
            $attrs['meta']['docxExtendedProperties'] = $extendedProperties;
        }
        if ($customProperties !== []) {
            $attrs['meta']['docxCustomProperties'] = $customProperties;
            $attrs['meta']['customProperties'] = $customProperties['byName'];
        }

        return new AstNode('document', $attrs, $blocks);
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    private function normalizeParts(array $parts): array
    {
        $normalized = [];
        foreach ($parts as $name => $contents) {
            $partName = $this->normalizePartName((string) $name);
            if ($partName !== '') {
                $normalized[$partName] = $contents;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $parts
     * @return array{defaults:array<string, string>, overrides:array<string, string>}
     */
    private function readContentTypes(array $parts): array
    {
        if (!isset($parts['[Content_Types].xml'])) {
            return ['defaults' => [], 'overrides' => []];
        }

        $dom = $this->loadXml($parts['[Content_Types].xml'], '[Content_Types].xml');
        $xpath = $this->xpath($dom);
        $defaults = [];
        $overrides = [];

        foreach ($this->elements($xpath, '/ct:Types/ct:Default') as $node) {
            $extension = strtolower($node->getAttribute('Extension'));
            if ($extension !== '') {
                $defaults[$extension] = $node->getAttribute('ContentType');
            }
        }

        foreach ($this->elements($xpath, '/ct:Types/ct:Override') as $node) {
            $partName = $this->normalizePartName($node->getAttribute('PartName'));
            if ($partName !== '') {
                $overrides[$partName] = $node->getAttribute('ContentType');
            }
        }

        return ['defaults' => $defaults, 'overrides' => $overrides];
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}>
     */
    private function readRelationshipsPart(array $parts, string $partName): array
    {
        if (!isset($parts[$partName])) {
            return [];
        }

        $relationships = [];
        foreach ($this->readRelationshipRecordsPart($parts, $partName) as $record) {
            if ($record['id'] === '' || $record['target'] === '') {
                continue;
            }

            $relationships[$record['id']] = [
                'id' => $record['id'],
                'type' => $record['type'],
                'target' => $record['target'],
                'targetMode' => $record['targetMode'],
                'resolvedTarget' => $record['resolvedTarget'],
            ];
        }

        return $relationships;
    }

    /**
     * @param array<string, string> $parts
     * @return list<array{ordinal:int, id:string, type:string, target:string, targetMode:string, resolvedTarget:string}>
     */
    private function readRelationshipRecordsPart(array $parts, string $partName): array
    {
        if (!isset($parts[$partName])) {
            return [];
        }

        $dom = $this->loadXml($parts[$partName], $partName);
        $xpath = $this->xpath($dom);
        $records = [];
        $ordinal = 0;
        foreach ($this->elements($xpath, '/rel:Relationships/rel:Relationship') as $node) {
            $recordOrdinal = $ordinal++;
            $id = $node->getAttribute('Id');
            $targetMode = $node->getAttribute('TargetMode');
            $target = $node->getAttribute('Target');
            $records[] = [
                'ordinal' => $recordOrdinal,
                'id' => $id,
                'type' => $node->getAttribute('Type'),
                'target' => $target,
                'targetMode' => $targetMode,
                'resolvedTarget' => $target === '' ? '' : $this->resolveRelationshipTarget($partName, $target, $targetMode),
            ];
        }

        return $records;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     */
    private function officeDocumentPart(array $relationships): string
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] === self::OFFICE_DOCUMENT_REL) {
                return $this->stripQueryAndFragment($relationship['resolvedTarget']);
            }
        }

        return 'word/document.xml';
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null
     */
    private function firstRelationshipByType(array $relationships, string $relationshipType): ?array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] === $relationshipType && $relationship['targetMode'] !== 'External') {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{id:string, name:string, headingLevel:int|null}>
     */
    private function readStyles(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $styles = [];
        foreach ($this->elements($xpath, '/w:styles/w:style[@w:type="paragraph"]') as $style) {
            $styleId = $style->getAttributeNS(self::NS_W, 'styleId');
            if ($styleId === '') {
                continue;
            }

            $name = $this->childAttr($style, 'name', 'val');
            $outline = $this->childElement($this->childElement($style, 'pPr'), 'outlineLvl');
            $headingLevel = null;
            if ($outline instanceof \DOMElement && is_numeric($outline->getAttributeNS(self::NS_W, 'val'))) {
                $headingLevel = min(6, max(1, ((int) $outline->getAttributeNS(self::NS_W, 'val')) + 1));
            } elseif (preg_match('/(?:^|[^a-z])heading\s*([1-6])(?:$|[^0-9])/i', $name, $match) === 1) {
                $headingLevel = (int) $match[1];
            } elseif (preg_match('/^Heading([1-6])$/', $styleId, $match) === 1) {
                $headingLevel = (int) $match[1];
            }

            $styles[$styleId] = [
                'id' => $styleId,
                'name' => $name,
                'headingLevel' => $headingLevel,
            ];
        }

        return $styles;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function corePropertiesPart(array $parts, array $relationships): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::CORE_PROPERTIES_REL || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = 'docProps/core.xml';

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function rootRelatedPart(array $parts, array $relationships, string $relationshipType, string $fallbackPart): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== $relationshipType || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->normalizePartName($fallbackPart);

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function stylesPart(array $parts, array $relationships, string $documentPart): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::STYLES_REL || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->documentSiblingPart($documentPart, 'styles.xml');

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function numberingPart(array $parts, array $relationships, string $documentPart): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::NUMBERING_REL || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->documentSiblingPart($documentPart, 'numbering.xml');

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function relatedDocumentPart(
        array $parts,
        array $relationships,
        string $documentPart,
        string $relationshipType,
        string $fallbackFileName
    ): array {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== $relationshipType || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->documentSiblingPart($documentPart, $fallbackFileName);

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readSectionProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [
                'count' => 0,
                'types' => [],
                'orientations' => [],
                'headerReferenceCount' => 0,
                'footerReferenceCount' => 0,
                'landscapeCount' => 0,
                'titlePageCount' => 0,
                'columnLayoutCount' => 0,
                'pageSizeCount' => 0,
                'pageMarginCount' => 0,
                'issueCount' => 0,
                'issueCodes' => [],
                'items' => [],
            ];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $items = [];
        $types = [];
        $orientations = [];
        $issueCodes = [];
        $headerReferenceCount = 0;
        $footerReferenceCount = 0;
        $landscapeCount = 0;
        $titlePageCount = 0;
        $columnLayoutCount = 0;
        $pageSizeCount = 0;
        $pageMarginCount = 0;
        $issueCount = 0;

        foreach ($this->elements($xpath, '//w:sectPr') as $section) {
            $item = $this->sectionPropertyItem($section, $xpath, $partName, count($items));
            $items[] = $item;

            $this->appendUniqueString($types, is_string($item['type'] ?? null) ? $item['type'] : null);
            $orientation = is_array($item['pageSize'] ?? null) ? ($item['pageSize']['orientation'] ?? null) : null;
            $this->appendUniqueString($orientations, is_string($orientation) ? $orientation : null);
            $headerReferenceCount += (int) ($item['headerReferenceCount'] ?? 0);
            $footerReferenceCount += (int) ($item['footerReferenceCount'] ?? 0);
            if (($item['landscape'] ?? false) === true) {
                ++$landscapeCount;
            }
            if (($item['titlePage'] ?? false) === true) {
                ++$titlePageCount;
            }
            if (($item['columns']['present'] ?? false) === true) {
                ++$columnLayoutCount;
            }
            if (($item['pageSize']['present'] ?? false) === true) {
                ++$pageSizeCount;
            }
            if (($item['pageMargins']['present'] ?? false) === true) {
                ++$pageMarginCount;
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                    ++$issueCount;
                }
            }
        }

        sort($types, SORT_STRING);
        sort($orientations, SORT_STRING);
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'types' => $types,
            'orientations' => $orientations,
            'headerReferenceCount' => $headerReferenceCount,
            'footerReferenceCount' => $footerReferenceCount,
            'landscapeCount' => $landscapeCount,
            'titlePageCount' => $titlePageCount,
            'columnLayoutCount' => $columnLayoutCount,
            'pageSizeCount' => $pageSizeCount,
            'pageMarginCount' => $pageMarginCount,
            'issueCount' => $issueCount,
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPropertyItem(\DOMElement $section, \DOMXPath $xpath, string $partName, int $index): array
    {
        $headerReferences = $this->sectionReferenceItems($section, $xpath, 'header');
        $footerReferences = $this->sectionReferenceItems($section, $xpath, 'footer');
        $pageSize = $this->sectionPageSize($this->firstElement($xpath, 'w:pgSz', $section));
        $pageMargins = $this->sectionPageMargins($this->firstElement($xpath, 'w:pgMar', $section));
        $columns = $this->sectionColumns($this->firstElement($xpath, 'w:cols', $section));
        $pageNumbering = $this->sectionPageNumbering($this->firstElement($xpath, 'w:pgNumType', $section));
        $documentGrid = $this->sectionDocumentGrid($this->firstElement($xpath, 'w:docGrid', $section));
        $titlePage = $this->firstElement($xpath, 'w:titlePg', $section);
        $issues = [];

        foreach (array_merge($headerReferences, $footerReferences) as $reference) {
            foreach ($reference['issues'] as $issue) {
                $issues[] = $issue;
            }
        }

        $issues = array_values(array_unique($issues));
        sort($issues, SORT_STRING);

        return [
            'index' => $index,
            'documentPart' => $partName,
            'type' => $this->emptyStringToNull($this->childAttr($section, 'type', 'val')),
            'headerReferenceCount' => count($headerReferences),
            'footerReferenceCount' => count($footerReferences),
            'headerReferences' => $headerReferences,
            'footerReferences' => $footerReferences,
            'pageSize' => $pageSize,
            'pageMargins' => $pageMargins,
            'columns' => $columns,
            'pageNumbering' => $pageNumbering,
            'documentGrid' => $documentGrid,
            'titlePage' => $titlePage instanceof \DOMElement ? $this->wordBoolean($titlePage) : false,
            'landscape' => ($pageSize['orientation'] ?? null) === 'landscape',
            'textDirection' => $this->emptyStringToNull($this->childAttr($section, 'textDirection', 'val')),
            'issues' => $issues,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sectionReferenceItems(\DOMElement $section, \DOMXPath $xpath, string $kind): array
    {
        $items = [];
        $elementName = $kind === 'footer' ? 'footerReference' : 'headerReference';
        foreach ($this->elements($xpath, 'w:' . $elementName, $section) as $reference) {
            $relationshipId = $reference->getAttributeNS(self::NS_R, 'id');
            $type = $reference->getAttributeNS(self::NS_W, 'type');
            $type = $type === '' ? 'default' : $type;
            $issues = [];
            if ($relationshipId === '') {
                $issues[] = 'missing-' . $kind . '-reference-id';
            }
            if (!in_array($type, ['default', 'even', 'first'], true)) {
                $issues[] = 'invalid-' . $kind . '-reference-type';
            }

            $items[] = [
                'index' => count($items),
                'kind' => $kind,
                'relationshipId' => $relationshipId,
                'type' => $type,
                'validType' => $issues === [] || !in_array('invalid-' . $kind . '-reference-type', $issues, true),
                'issues' => $issues,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPageSize(?\DOMElement $pageSize): array
    {
        if (!$pageSize instanceof \DOMElement) {
            return [
                'present' => false,
                'widthTwips' => null,
                'heightTwips' => null,
                'orientation' => null,
                'code' => null,
            ];
        }

        return [
            'present' => true,
            'widthTwips' => $this->wordOptionalIntAttr($pageSize, 'w'),
            'heightTwips' => $this->wordOptionalIntAttr($pageSize, 'h'),
            'orientation' => $this->emptyStringToNull($pageSize->getAttributeNS(self::NS_W, 'orient')),
            'code' => $this->wordOptionalIntAttr($pageSize, 'code'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPageMargins(?\DOMElement $pageMargins): array
    {
        $margins = ['top', 'right', 'bottom', 'left', 'header', 'footer', 'gutter'];
        $values = ['present' => $pageMargins instanceof \DOMElement];
        foreach ($margins as $margin) {
            $values[$margin . 'Twips'] = $pageMargins instanceof \DOMElement
                ? $this->wordOptionalIntAttr($pageMargins, $margin)
                : null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionColumns(?\DOMElement $columns): array
    {
        if (!$columns instanceof \DOMElement) {
            return [
                'present' => false,
                'count' => null,
                'spaceTwips' => null,
                'equalWidth' => null,
                'separator' => null,
            ];
        }

        return [
            'present' => true,
            'count' => $this->wordOptionalIntAttr($columns, 'num'),
            'spaceTwips' => $this->wordOptionalIntAttr($columns, 'space'),
            'equalWidth' => $this->wordOptionalBooleanAttr($columns, 'equalWidth'),
            'separator' => $this->wordOptionalBooleanAttr($columns, 'sep'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPageNumbering(?\DOMElement $pageNumbering): array
    {
        if (!$pageNumbering instanceof \DOMElement) {
            return [
                'present' => false,
                'start' => null,
                'format' => null,
                'chapterStyle' => null,
                'chapterSeparator' => null,
            ];
        }

        return [
            'present' => true,
            'start' => $this->wordOptionalIntAttr($pageNumbering, 'start'),
            'format' => $this->emptyStringToNull($pageNumbering->getAttributeNS(self::NS_W, 'fmt')),
            'chapterStyle' => $this->wordOptionalIntAttr($pageNumbering, 'chapStyle'),
            'chapterSeparator' => $this->emptyStringToNull($pageNumbering->getAttributeNS(self::NS_W, 'chapSep')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionDocumentGrid(?\DOMElement $documentGrid): array
    {
        if (!$documentGrid instanceof \DOMElement) {
            return [
                'present' => false,
                'type' => null,
                'linePitch' => null,
                'charSpace' => null,
            ];
        }

        return [
            'present' => true,
            'type' => $this->emptyStringToNull($documentGrid->getAttributeNS(self::NS_W, 'type')),
            'linePitch' => $this->wordOptionalIntAttr($documentGrid, 'linePitch'),
            'charSpace' => $this->wordOptionalIntAttr($documentGrid, 'charSpace'),
        ];
    }

    /**
     * @return array{header:array<string, list<string>>, footer:array<string, list<string>>}
     */
    private function readHeaderFooterReferences(string $xml, string $partName): array
    {
        if ($xml === '') {
            return ['header' => [], 'footer' => []];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $references = ['header' => [], 'footer' => []];
        foreach (['headerReference' => 'header', 'footerReference' => 'footer'] as $elementName => $sourceType) {
            foreach ($this->elements($xpath, '//w:' . $elementName) as $reference) {
                $relationshipId = $reference->getAttributeNS(self::NS_R, 'id');
                if ($relationshipId === '') {
                    continue;
                }

                $type = $reference->getAttributeNS(self::NS_W, 'type');
                $type = $type === '' ? 'default' : $type;
                if (!isset($references[$sourceType][$relationshipId])) {
                    $references[$sourceType][$relationshipId] = [];
                }
                if (!in_array($type, $references[$sourceType][$relationshipId], true)) {
                    $references[$sourceType][$relationshipId][] = $type;
                }
            }
        }

        return $references;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array<string, list<string>> $referencesByRelationshipId
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return array<string, mixed>
     */
    private function readHeaderFooterParts(
        array $parts,
        array $relationships,
        string $documentPart,
        string $relationshipType,
        string $sourceType,
        string $rootName,
        array $referencesByRelationshipId,
        array $contentTypes,
        array $styles,
        array $numbering,
        array $referencedNotes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);
        $handledRelationshipIds = [];

        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== $relationshipType) {
                continue;
            }

            $relationshipId = $relationship['id'];
            $item = $this->headerFooterPartItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $relationshipType,
                $sourceType,
                $rootName,
                $contentTypes,
                $styles,
                $numbering,
                $referencedNotes,
                $relationshipId,
                count($items),
                $referencesByRelationshipId[$relationshipId] ?? [],
            );
            $items[] = $item;
            $byRelationshipId[$relationshipId] = $item;
            $handledRelationshipIds[$relationshipId] = true;
        }

        foreach ($referencesByRelationshipId as $relationshipId => $referenceTypes) {
            if (isset($handledRelationshipIds[$relationshipId])) {
                continue;
            }

            $item = $this->headerFooterPartItem(
                $parts,
                $relationships[$relationshipId] ?? null,
                $documentPart,
                $relationshipsPart,
                $relationshipType,
                $sourceType,
                $rootName,
                $contentTypes,
                $styles,
                $numbering,
                $referencedNotes,
                $relationshipId,
                count($items),
                $referenceTypes,
            );
            $items[] = $item;
            $byRelationshipId[$relationshipId] = $item;
        }

        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $partNames = [];
        $externalTargets = [];
        $issueCodes = [];
        foreach ($items as $item) {
            $this->appendUniqueString($relationshipIds, is_string($item['relationshipId'] ?? null) ? $item['relationshipId'] : null);
            if (($item['referenced'] ?? false) === true) {
                $this->appendUniqueString($referencedRelationshipIds, is_string($item['relationshipId'] ?? null) ? $item['relationshipId'] : null);
            }
            $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === false && $item['exists'] === true && $item['relationshipType'] === $relationshipType)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-in-package', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @param list<string> $referenceTypes
     * @return array<string, mixed>
     */
    private function headerFooterPartItem(
        array $parts,
        ?array $relationship,
        string $documentPart,
        string $relationshipsPart,
        string $expectedRelationshipType,
        string $sourceType,
        string $rootName,
        array $contentTypes,
        array $styles,
        array $numbering,
        array $referencedNotes,
        string $relationshipId,
        int $index,
        array $referenceTypes,
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'sourceType' => $sourceType,
            'referenced' => $referenceTypes !== [],
            'referenceTypes' => $referenceTypes,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'validRoot' => false,
            'rootName' => null,
            'relationshipsPart' => null,
            'relationshipCount' => 0,
            'blockCount' => 0,
            'text' => '',
            'relationship' => null,
            'reviewPolicy' => 'header-footer-metadata-only',
            'issues' => [],
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';

            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';

            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];
        $partRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $partRelationships = $partRelationshipsPart === null || (bool) $summary['external']
            ? []
            : $this->readRelationshipsPart($parts, $partRelationshipsPart);

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['relationshipsPart'] = $partRelationshipsPart;
        $item['relationshipCount'] = count($partRelationships);
        $item['relationship'] = $summary;

        if ($relationship['type'] !== $expectedRelationshipType) {
            $item['issues'][] = 'unexpected-relationship-type';

            return $item;
        }

        if ($item['external'] === true) {
            $item['issues'][] = 'external-' . $sourceType;

            return $item;
        }

        if (!$exists || $targetPart === null) {
            $item['issues'][] = 'missing-in-package';

            return $item;
        }

        if (($summary['contentTypeSource'] ?? '') === 'missing') {
            $item['issues'][] = 'missing-content-type';
        }

        $part = $this->readHeaderFooterPart($parts[$targetPart], $targetPart, $rootName, $partRelationships, $contentTypes, $styles, $numbering, $referencedNotes);
        $item['validRoot'] = $part['validRoot'];
        $item['rootName'] = $part['rootName'];
        $item['blockCount'] = count($part['blocks']);
        $item['text'] = $this->plainBlockText($part['blocks']);

        return $item;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readAlternativeFormatImports(
        array $parts,
        string $xml,
        string $documentPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $referencedAltChunkRelationshipIds = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        if ($xml !== '') {
            $dom = $this->loadXml($xml, $documentPart);
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '//w:altChunk') as $chunk) {
                $relationshipId = $chunk->getAttributeNS(self::NS_R, 'id');
                $item = $this->alternativeFormatImportItem(
                    $parts,
                    $relationships[$relationshipId] ?? null,
                    $documentPart,
                    $relationshipsPart,
                    $contentTypes,
                    $relationshipId,
                    count($items),
                    true,
                );
                $items[] = $item;

                $this->appendUniqueString($relationshipIds, $relationshipId);
                $this->appendUniqueString($referencedRelationshipIds, $relationshipId);
                if ($item['relationshipType'] === self::ALT_CHUNK_REL) {
                    $this->appendUniqueString($referencedAltChunkRelationshipIds, $relationshipId);
                }
                if ($relationshipId !== '' && !isset($byRelationshipId[$relationshipId])) {
                    $byRelationshipId[$relationshipId] = $item;
                }
            }
        }

        $unreferencedRelationshipIds = [];
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::ALT_CHUNK_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            if (in_array($relationshipId, $referencedAltChunkRelationshipIds, true)) {
                continue;
            }

            $item = $this->alternativeFormatImportItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $contentTypes,
                $relationshipId,
                count($items),
                false,
            );
            $items[] = $item;

            $this->appendUniqueString($relationshipIds, $relationshipId);
            $this->appendUniqueString($unreferencedRelationshipIds, $relationshipId);
            if (!isset($byRelationshipId[$relationshipId])) {
                $byRelationshipId[$relationshipId] = $item;
            }
        }

        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        foreach ($items as $item) {
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : null;
            $this->appendUniqueString($partNames, $partName);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($relationships, static fn (array $relationship): bool => $relationship['type'] === self::ALT_CHUNK_REL)),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-in-package', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'byteExposurePolicy' => 'alternative-format-import-bytes-blocked',
            'reviewPolicy' => 'alternative-format-import-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function alternativeFormatImportItem(
        array $parts,
        ?array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        string $relationshipId,
        int $index,
        bool $referenced
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'referenced' => $referenced,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'bytes' => 0,
            'crc32' => null,
            'sha256' => null,
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'relationshipsPart' => null,
            'relationshipCount' => 0,
            'relationship' => null,
            'byteExposurePolicy' => 'alternative-format-import-bytes-blocked',
            'reviewPolicy' => 'alternative-format-import-metadata-only',
            'issues' => [],
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];
        $partRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $partRelationships = $partRelationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $partRelationshipsPart);

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['bytes'] = $exists && $targetPart !== null ? strlen($parts[$targetPart]) : 0;
        $item['crc32'] = $exists && $targetPart !== null ? sprintf('%08x', crc32($parts[$targetPart])) : null;
        $item['sha256'] = $exists && $targetPart !== null ? hash('sha256', $parts[$targetPart]) : null;
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['relationshipsPart'] = $partRelationshipsPart;
        $item['relationshipCount'] = count($partRelationships);
        $item['relationship'] = $summary;

        if ($relationship['type'] !== self::ALT_CHUNK_REL) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($item['external'] === true) {
            $item['issues'][] = 'external-altchunk';
            return $item;
        }

        if (!$exists) {
            $item['issues'][] = 'missing-in-package';
            return $item;
        }

        if ($item['contentTypeSource'] === 'missing') {
            $item['issues'][] = 'missing-content-type';
        }

        return $item;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readSubdocuments(
        array $parts,
        string $xml,
        string $documentPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $referencedSubdocumentRelationshipIds = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        if ($xml !== '') {
            $dom = $this->loadXml($xml, $documentPart);
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '//w:subDoc') as $subdocument) {
                $relationshipId = $subdocument->getAttributeNS(self::NS_R, 'id');
                $item = $this->subdocumentItem(
                    $parts,
                    $relationships[$relationshipId] ?? null,
                    $documentPart,
                    $relationshipsPart,
                    $contentTypes,
                    $relationshipId,
                    count($items),
                    true,
                );
                $items[] = $item;

                $this->appendUniqueString($relationshipIds, $relationshipId);
                $this->appendUniqueString($referencedRelationshipIds, $relationshipId);
                if ($item['relationshipType'] === self::SUBDOCUMENT_REL) {
                    $this->appendUniqueString($referencedSubdocumentRelationshipIds, $relationshipId);
                }
                if ($relationshipId !== '' && !isset($byRelationshipId[$relationshipId])) {
                    $byRelationshipId[$relationshipId] = $item;
                }
            }
        }

        $unreferencedRelationshipIds = [];
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::SUBDOCUMENT_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            if (in_array($relationshipId, $referencedSubdocumentRelationshipIds, true)) {
                continue;
            }

            $item = $this->subdocumentItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $contentTypes,
                $relationshipId,
                count($items),
                false,
            );
            $items[] = $item;

            $this->appendUniqueString($relationshipIds, $relationshipId);
            $this->appendUniqueString($unreferencedRelationshipIds, $relationshipId);
            if (!isset($byRelationshipId[$relationshipId])) {
                $byRelationshipId[$relationshipId] = $item;
            }
        }

        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        foreach ($items as $item) {
            $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($relationships, static fn (array $relationship): bool => $relationship['type'] === self::SUBDOCUMENT_REL)),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-in-package', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'internalCount' => count(array_filter(
                $items,
                static fn (array $item): bool => is_array($item['relationship']) && $item['external'] !== true,
            )),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'unsupportedCount' => count(array_filter($items, static fn (array $item): bool => $item['directReaderParity'] === false)),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'directReaderParity' => false,
            'unsupportedReason' => 'subdocument-master-document-expansion-not-implemented',
            'byteExposurePolicy' => 'subdocument-package-bytes-blocked',
            'reviewPolicy' => 'subdocument-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function subdocumentItem(
        array $parts,
        ?array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        string $relationshipId,
        int $index,
        bool $referenced
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'referenced' => $referenced,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'bytes' => 0,
            'crc32' => null,
            'sha256' => null,
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'relationship' => null,
            'directReaderParity' => false,
            'unsupportedReason' => 'subdocument-master-document-expansion-not-implemented',
            'byteExposurePolicy' => 'subdocument-package-bytes-blocked',
            'reviewPolicy' => 'subdocument-metadata-only',
            'issues' => [],
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['bytes'] = $exists && $targetPart !== null ? strlen($parts[$targetPart]) : 0;
        $item['crc32'] = $exists && $targetPart !== null ? sprintf('%08x', crc32($parts[$targetPart])) : null;
        $item['sha256'] = $exists && $targetPart !== null ? hash('sha256', $parts[$targetPart]) : null;
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['relationship'] = $summary;

        if ($relationship['type'] !== self::SUBDOCUMENT_REL) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($item['external'] !== true) {
            $item['issues'][] = 'internal-subdocument-target';
        }

        if ($item['external'] !== true && !$exists) {
            $item['issues'][] = 'missing-in-package';
        }

        if ($item['external'] !== true && $item['contentTypeSource'] === 'missing') {
            $item['issues'][] = 'missing-content-type';
        }

        return $item;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readEmbeddedObjects(
        array $parts,
        string $xml,
        string $documentPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $referencedEmbeddedRelationshipIds = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        if ($xml !== '') {
            $dom = $this->loadXml($xml, $documentPart);
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '//o:OLEObject') as $object) {
                $relationshipId = $object->getAttributeNS(self::NS_R, 'id');
                $item = $this->embeddedObjectItem(
                    $parts,
                    $relationships[$relationshipId] ?? null,
                    $documentPart,
                    $relationshipsPart,
                    $contentTypes,
                    $relationshipId,
                    count($items),
                    true,
                    $this->embeddedObjectDescriptor($object),
                );
                $items[] = $item;

                $this->appendUniqueString($relationshipIds, $relationshipId);
                $this->appendUniqueString($referencedRelationshipIds, $relationshipId);
                if ($this->isEmbeddedObjectRelationshipType((string) ($item['relationshipType'] ?? ''))) {
                    $this->appendUniqueString($referencedEmbeddedRelationshipIds, $relationshipId);
                }
                if ($relationshipId !== '' && !isset($byRelationshipId[$relationshipId])) {
                    $byRelationshipId[$relationshipId] = $item;
                }
            }
        }

        $unreferencedRelationshipIds = [];
        foreach ($relationships as $relationship) {
            if (!$this->isEmbeddedObjectRelationshipType($relationship['type'])) {
                continue;
            }

            $relationshipId = $relationship['id'];
            if (in_array($relationshipId, $referencedEmbeddedRelationshipIds, true)) {
                continue;
            }

            $item = $this->embeddedObjectItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $contentTypes,
                $relationshipId,
                count($items),
                false,
                null,
            );
            $items[] = $item;

            $this->appendUniqueString($relationshipIds, $relationshipId);
            $this->appendUniqueString($unreferencedRelationshipIds, $relationshipId);
            if (!isset($byRelationshipId[$relationshipId])) {
                $byRelationshipId[$relationshipId] = $item;
            }
        }

        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        foreach ($items as $item) {
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : null;
            $this->appendUniqueString($partNames, $partName);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($relationships, fn (array $relationship): bool => $this->isEmbeddedObjectRelationshipType($relationship['type']))),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-in-package', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'byteExposurePolicy' => 'embedded-object-bytes-blocked',
            'reviewPolicy' => 'embedded-object-metadata-only',
        ];
    }

    /**
     * @return array{progId:?string, shapeId:?string, drawAspect:?string, objectId:?string, updateMode:?string}
     */
    private function embeddedObjectDescriptor(\DOMElement $object): array
    {
        return [
            'progId' => $this->emptyStringToNull($object->getAttribute('ProgID')),
            'shapeId' => $this->emptyStringToNull($object->getAttribute('ShapeID')),
            'drawAspect' => $this->emptyStringToNull($object->getAttribute('DrawAspect')),
            'objectId' => $this->emptyStringToNull($object->getAttribute('ObjectID')),
            'updateMode' => $this->emptyStringToNull($object->getAttribute('UpdateMode')),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array{progId:?string, shapeId:?string, drawAspect:?string, objectId:?string, updateMode:?string}|null $descriptor
     * @return array<string, mixed>
     */
    private function embeddedObjectItem(
        array $parts,
        ?array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        string $relationshipId,
        int $index,
        bool $referenced,
        ?array $descriptor
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'referenced' => $referenced,
            'progId' => $descriptor['progId'] ?? null,
            'shapeId' => $descriptor['shapeId'] ?? null,
            'drawAspect' => $descriptor['drawAspect'] ?? null,
            'objectId' => $descriptor['objectId'] ?? null,
            'updateMode' => $descriptor['updateMode'] ?? null,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'bytes' => 0,
            'crc32' => null,
            'sha256' => null,
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'relationshipsPart' => null,
            'relationshipCount' => 0,
            'relationship' => null,
            'byteExposurePolicy' => 'embedded-object-bytes-blocked',
            'reviewPolicy' => 'embedded-object-metadata-only',
            'issues' => [],
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];
        $partRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $partRelationships = $partRelationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $partRelationshipsPart);

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['bytes'] = $exists && $targetPart !== null ? strlen($parts[$targetPart]) : 0;
        $item['crc32'] = $exists && $targetPart !== null ? sprintf('%08x', crc32($parts[$targetPart])) : null;
        $item['sha256'] = $exists && $targetPart !== null ? hash('sha256', $parts[$targetPart]) : null;
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['relationshipsPart'] = $partRelationshipsPart;
        $item['relationshipCount'] = count($partRelationships);
        $item['relationship'] = $summary;

        if (!$this->isEmbeddedObjectRelationshipType($relationship['type'])) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($item['external'] === true) {
            $item['issues'][] = 'external-embedded-object';
            return $item;
        }

        if (!$exists) {
            $item['issues'][] = 'missing-in-package';
        }

        if ($item['contentTypeSource'] === 'missing') {
            $item['issues'][] = 'missing-content-type';
        }

        return $item;
    }

    private function isEmbeddedObjectRelationshipType(string $relationshipType): bool
    {
        return $relationshipType === self::OLE_OBJECT_REL
            || $relationshipType === self::EMBEDDED_PACKAGE_REL;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readChartParts(
        array $parts,
        string $xml,
        string $documentPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $referencedChartRelationshipIds = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        if ($xml !== '') {
            $dom = $this->loadXml($xml, $documentPart);
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '//c:chart') as $chart) {
                $relationshipId = $chart->getAttributeNS(self::NS_R, 'id');
                $item = $this->chartPartItem(
                    $parts,
                    $relationships[$relationshipId] ?? null,
                    $documentPart,
                    $relationshipsPart,
                    $contentTypes,
                    $relationshipId,
                    count($items),
                    true,
                );
                $items[] = $item;

                $this->appendUniqueString($relationshipIds, $relationshipId);
                $this->appendUniqueString($referencedRelationshipIds, $relationshipId);
                if ($item['relationshipType'] === self::CHART_REL) {
                    $this->appendUniqueString($referencedChartRelationshipIds, $relationshipId);
                }
                if ($relationshipId !== '' && !isset($byRelationshipId[$relationshipId])) {
                    $byRelationshipId[$relationshipId] = $item;
                }
            }
        }

        $unreferencedRelationshipIds = [];
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::CHART_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            if (in_array($relationshipId, $referencedChartRelationshipIds, true)) {
                continue;
            }

            $item = $this->chartPartItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $contentTypes,
                $relationshipId,
                count($items),
                false,
            );
            $items[] = $item;

            $this->appendUniqueString($relationshipIds, $relationshipId);
            $this->appendUniqueString($unreferencedRelationshipIds, $relationshipId);
            if (!isset($byRelationshipId[$relationshipId])) {
                $byRelationshipId[$relationshipId] = $item;
            }
        }

        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        $embeddedPackageRelationshipIds = [];
        $embeddedPackagePartNames = [];
        $embeddedPackageExternalTargets = [];
        $embeddedPackageIssueCodes = [];
        $embeddedPackageCount = 0;
        $embeddedPackageExistingCount = 0;
        $embeddedPackageMissingCount = 0;
        $embeddedPackageExternalCount = 0;
        $embeddedPackageIssueCount = 0;
        foreach ($items as $item) {
            if (($item['relationshipType'] ?? null) === self::CHART_REL) {
                $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
            }
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
            $embeddedPackages = is_array($item['embeddedPackages'] ?? null) ? $item['embeddedPackages'] : [];
            $embeddedPackageCount += (int) ($embeddedPackages['count'] ?? 0);
            $embeddedPackageExistingCount += (int) ($embeddedPackages['existingCount'] ?? 0);
            $embeddedPackageMissingCount += (int) ($embeddedPackages['missingCount'] ?? 0);
            $embeddedPackageExternalCount += (int) ($embeddedPackages['externalCount'] ?? 0);
            $embeddedPackageIssueCount += (int) ($embeddedPackages['issueCount'] ?? 0);
            foreach (($embeddedPackages['relationshipIds'] ?? []) as $relationshipId) {
                $this->appendUniqueString($embeddedPackageRelationshipIds, is_string($relationshipId) ? $relationshipId : null);
            }
            foreach (($embeddedPackages['partNames'] ?? []) as $partName) {
                $this->appendUniqueString($embeddedPackagePartNames, is_string($partName) ? $partName : null);
            }
            foreach (($embeddedPackages['externalTargets'] ?? []) as $target) {
                $this->appendUniqueString($embeddedPackageExternalTargets, is_string($target) ? $target : null);
            }
            foreach (($embeddedPackages['issueCodes'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $embeddedPackageIssueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);
        ksort($embeddedPackageIssueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($relationships, static fn (array $relationship): bool => $relationship['type'] === self::CHART_REL)),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::CHART_REL && $item['external'] === false && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-chart-part', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::CHART_REL && $item['external'] === true)),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'invalidXmlCount' => count(array_filter($items, static fn (array $item): bool => in_array('invalid-chart-xml', $item['issues'], true))),
            'unexpectedRootCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-chart-root', $item['issues'], true))),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-chart-content-type', $item['issues'], true))),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-chart-content-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'embeddedPackageCount' => $embeddedPackageCount,
            'embeddedPackageExistingCount' => $embeddedPackageExistingCount,
            'embeddedPackageMissingCount' => $embeddedPackageMissingCount,
            'embeddedPackageExternalCount' => $embeddedPackageExternalCount,
            'embeddedPackageIssueCount' => $embeddedPackageIssueCount,
            'embeddedPackageRelationshipIds' => $embeddedPackageRelationshipIds,
            'embeddedPackagePartNames' => $embeddedPackagePartNames,
            'embeddedPackageExternalTargets' => $embeddedPackageExternalTargets,
            'embeddedPackageIssueCodes' => array_keys($embeddedPackageIssueCodes),
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-part-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function chartPartItem(
        array $parts,
        ?array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        string $relationshipId,
        int $index,
        bool $referenced
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'referenced' => $referenced,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'byteLength' => null,
            'crc32' => null,
            'sha256' => null,
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'expectedContentTypeBase' => self::CT_CHART,
            'relationshipsPart' => $relationshipsPart,
            'chartRelationshipsPart' => null,
            'chartRelationshipCount' => 0,
            'externalDataRelationshipIds' => [],
            'embeddedPackages' => $this->emptyChartEmbeddedPackageParts(),
            'validXml' => null,
            'xmlParseError' => null,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-part-metadata-only',
            'valid' => false,
            'issues' => [],
            'relationship' => null,
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];
        $chartRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $chartRelationships = $chartRelationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $chartRelationshipsPart);

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['byteLength'] = $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null;
        $item['crc32'] = $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null;
        $item['sha256'] = $targetPart !== null && $exists ? hash('sha256', $parts[$targetPart]) : null;
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['chartRelationshipsPart'] = $chartRelationshipsPart;
        $item['chartRelationshipCount'] = count($chartRelationships);
        $item['relationship'] = $summary;

        if ($relationship['type'] !== self::CHART_REL) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($item['external'] === true) {
            $item['issues'][] = 'external-chart-part';
            return $item;
        }

        if (!$exists) {
            $item['issues'][] = 'missing-chart-part';
        }

        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        if (($summary['contentTypeSource'] ?? '') === 'missing') {
            $item['issues'][] = 'missing-chart-content-type';
        } elseif ($contentTypeBase !== self::CT_CHART) {
            $item['issues'][] = 'unexpected-chart-content-type';
        }

        if ($exists && $targetPart !== null) {
            $root = $this->xmlRootProvenance($parts[$targetPart], $targetPart);
            $item['validXml'] = $root['validXml'];
            $item['xmlParseError'] = $root['xmlParseError'];
            $item['rootNamespace'] = $root['namespace'];
            $item['rootLocalName'] = $root['localName'];
            $item['validRoot'] = $root['namespace'] === self::NS_C && $root['localName'] === 'chartSpace';
            if ($root['validXml'] === false) {
                $item['issues'][] = 'invalid-chart-xml';
            } elseif ($item['validRoot'] === false) {
                $item['issues'][] = 'unexpected-chart-root';
            } else {
                $item['externalDataRelationshipIds'] = $this->chartExternalDataRelationshipIds($parts[$targetPart], $targetPart);
            }
        }

        $item['embeddedPackages'] = $this->chartEmbeddedPackageParts(
            $parts,
            $targetPart,
            $chartRelationshipsPart,
            $chartRelationships,
            $contentTypes,
            $item['externalDataRelationshipIds'],
        );
        $item['valid'] = $item['issues'] === [] && (int) $item['embeddedPackages']['issueCount'] === 0;

        return $item;
    }

    /**
     * @return list<string>
     */
    private function chartExternalDataRelationshipIds(string $xml, string $partName): array
    {
        $dom = $this->loadXmlForProvenance($xml, $partName);
        if (!$dom instanceof \DOMDocument) {
            return [];
        }

        $relationshipIds = [];
        $xpath = $this->xpath($dom);
        foreach ($this->elements($xpath, '//c:externalData') as $externalData) {
            $this->appendUniqueString($relationshipIds, $externalData->getAttributeNS(self::NS_R, 'id'));
        }

        return $relationshipIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChartEmbeddedPackageParts(): array
    {
        return [
            'count' => 0,
            'relationshipCount' => 0,
            'referencedCount' => 0,
            'unreferencedRelationshipCount' => 0,
            'existingCount' => 0,
            'missingCount' => 0,
            'externalCount' => 0,
            'missingContentTypeCount' => 0,
            'issueCount' => 0,
            'relationshipIds' => [],
            'referencedRelationshipIds' => [],
            'unreferencedRelationshipIds' => [],
            'partNames' => [],
            'externalTargets' => [],
            'contentTypes' => [],
            'issueCodes' => [],
            'byRelationshipId' => [],
            'items' => [],
            'byteExposurePolicy' => 'chart-embedded-package-bytes-blocked',
            'reviewPolicy' => 'chart-embedded-package-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param list<string> $referencedRelationshipIds
     * @return array<string, mixed>
     */
    private function chartEmbeddedPackageParts(
        array $parts,
        ?string $sourcePart,
        ?string $relationshipsPart,
        array $relationships,
        array $contentTypes,
        array $referencedRelationshipIds,
    ): array {
        if ($sourcePart === null || $relationshipsPart === null) {
            return $this->emptyChartEmbeddedPackageParts();
        }

        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedPackageRelationshipIds = [];
        $unreferencedRelationshipIds = [];
        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];

        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::EMBEDDED_PACKAGE_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            $referenced = in_array($relationshipId, $referencedRelationshipIds, true);
            $item = $this->chartEmbeddedPackagePartItem(
                $parts,
                $relationship,
                $sourcePart,
                $relationshipsPart,
                $contentTypes,
                count($items),
                $referenced,
            );
            $items[] = $item;
            $byRelationshipId[$relationshipId] = $item;
            $relationshipIds[] = $relationshipId;
            if ($referenced) {
                $referencedPackageRelationshipIds[] = $relationshipId;
            } else {
                $unreferencedRelationshipIds[] = $relationshipId;
            }
            $this->appendUniqueString($partNames, is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count($items),
            'referencedCount' => count($referencedPackageRelationshipIds),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === false && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-chart-embedded-package', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-chart-embedded-package-content-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedPackageRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'byteExposurePolicy' => 'chart-embedded-package-bytes-blocked',
            'reviewPolicy' => 'chart-embedded-package-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function chartEmbeddedPackagePartItem(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
        int $index,
        bool $referenced,
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $issues = [];

        if ($external) {
            $issues[] = 'external-chart-embedded-package';
        } else {
            if (!$exists) {
                $issues[] = 'missing-chart-embedded-package';
            }
            if (($summary['contentTypeSource'] ?? '') === 'missing') {
                $issues[] = 'missing-chart-embedded-package-content-type';
            }
        }

        return [
            'index' => $index,
            'source' => $sourcePart,
            'referenced' => $referenced,
            'id' => $summary['id'],
            'type' => $summary['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'external' => $external,
            'exists' => $exists,
            'relationshipsPart' => $relationshipsPart,
            'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
            'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
            'sha256' => $targetPart !== null && $exists ? hash('sha256', $parts[$targetPart]) : null,
            'byteExposurePolicy' => 'chart-embedded-package-bytes-blocked',
            'reviewPolicy' => 'chart-embedded-package-metadata-only',
            'valid' => $issues === [],
            'issues' => $issues,
            'relationship' => $summary,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readActiveXControls(
        array $parts,
        string $xml,
        string $documentPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $referencedActiveXRelationshipIds = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        if ($xml !== '') {
            $dom = $this->loadXml($xml, $documentPart);
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '//w:control') as $control) {
                $relationshipId = $control->getAttributeNS(self::NS_R, 'id');
                $item = $this->activeXControlItem(
                    $parts,
                    $relationships[$relationshipId] ?? null,
                    $documentPart,
                    $relationshipsPart,
                    $contentTypes,
                    $relationshipId,
                    count($items),
                    true,
                    $this->activeXControlDescriptor($control),
                );
                $items[] = $item;

                $this->appendUniqueString($relationshipIds, $relationshipId);
                $this->appendUniqueString($referencedRelationshipIds, $relationshipId);
                if ($item['relationshipType'] === self::ACTIVEX_CONTROL_REL) {
                    $this->appendUniqueString($referencedActiveXRelationshipIds, $relationshipId);
                }
                if ($relationshipId !== '' && !isset($byRelationshipId[$relationshipId])) {
                    $byRelationshipId[$relationshipId] = $item;
                }
            }
        }

        $unreferencedRelationshipIds = [];
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::ACTIVEX_CONTROL_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            if (in_array($relationshipId, $referencedActiveXRelationshipIds, true)) {
                continue;
            }

            $item = $this->activeXControlItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $contentTypes,
                $relationshipId,
                count($items),
                false,
                null,
            );
            $items[] = $item;

            $this->appendUniqueString($relationshipIds, $relationshipId);
            $this->appendUniqueString($unreferencedRelationshipIds, $relationshipId);
            if (!isset($byRelationshipId[$relationshipId])) {
                $byRelationshipId[$relationshipId] = $item;
            }
        }

        $partNames = [];
        $binaryPartNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        foreach ($items as $item) {
            $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
            foreach (($item['binaries']['items'] ?? []) as $binary) {
                if (!is_array($binary)) {
                    continue;
                }
                $this->appendUniqueString($binaryPartNames, is_string($binary['targetPart'] ?? null) ? $binary['targetPart'] : null);
                $this->appendUniqueString($contentTypesSeen, is_string($binary['contentType'] ?? null) ? $binary['contentType'] : null);
                if (($binary['external'] ?? false) === true) {
                    $this->appendUniqueString($externalTargets, is_string($binary['target'] ?? null) ? $binary['target'] : null);
                }
                foreach (($binary['issues'] ?? []) as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $issueCodes[$issue] = true;
                    }
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($relationships, static fn (array $relationship): bool => $relationship['type'] === self::ACTIVEX_CONTROL_REL)),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::ACTIVEX_CONTROL_REL && $item['external'] === false && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-control-part', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::ACTIVEX_CONTROL_REL && $item['external'] === true)),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'invalidXmlCount' => count(array_filter($items, static fn (array $item): bool => in_array('invalid-control-xml', $item['issues'], true))),
            'unexpectedRootCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-control-root', $item['issues'], true))),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-control-content-type', $item['issues'], true))),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-control-content-type', $item['issues'], true))),
            'binaryCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['binaries']['count'] ?? 0), $items)),
            'existingBinaryCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['binaries']['existingCount'] ?? 0), $items)),
            'missingBinaryCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['binaries']['missingCount'] ?? 0), $items)),
            'externalBinaryCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['binaries']['externalCount'] ?? 0), $items)),
            'unexpectedBinaryContentTypeCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['binaries']['unexpectedContentTypeCount'] ?? 0), $items)),
            'issueCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['issues'] !== [] || (int) ($item['binaries']['issueCount'] ?? 0) > 0,
            )),
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'binaryPartNames' => $binaryPartNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'byteExposurePolicy' => 'activex-bytes-blocked',
            'reviewPolicy' => 'activex-metadata-only',
        ];
    }

    /**
     * @return array{controlName:?string, shapeId:?string}
     */
    private function activeXControlDescriptor(\DOMElement $control): array
    {
        return [
            'controlName' => $this->emptyStringToNull($control->getAttributeNS(self::NS_W, 'name')),
            'shapeId' => $this->emptyStringToNull($control->getAttributeNS(self::NS_W, 'shapeid')),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array{controlName:?string, shapeId:?string}|null $descriptor
     * @return array<string, mixed>
     */
    private function activeXControlItem(
        array $parts,
        ?array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        string $relationshipId,
        int $index,
        bool $referenced,
        ?array $descriptor
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'referenced' => $referenced,
            'controlName' => $descriptor['controlName'] ?? null,
            'shapeId' => $descriptor['shapeId'] ?? null,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'byteLength' => null,
            'crc32' => null,
            'sha256' => null,
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'expectedContentTypeBase' => self::CT_ACTIVEX_XML,
            'relationshipsPart' => null,
            'controlRelationshipsPart' => null,
            'controlRelationshipCount' => 0,
            'validXml' => null,
            'xmlParseError' => null,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'binaries' => $this->emptyActiveXBinaryParts(),
            'byteExposurePolicy' => 'activex-control-bytes-blocked',
            'reviewPolicy' => 'activex-metadata-only',
            'valid' => false,
            'issues' => [],
            'relationship' => null,
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];
        $controlRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $controlRelationships = $controlRelationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $controlRelationshipsPart);

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['byteLength'] = $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null;
        $item['crc32'] = $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null;
        $item['sha256'] = $targetPart !== null && $exists ? hash('sha256', $parts[$targetPart]) : null;
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['relationshipsPart'] = $relationshipsPart;
        $item['controlRelationshipsPart'] = $controlRelationshipsPart;
        $item['controlRelationshipCount'] = count($controlRelationships);
        $item['relationship'] = $summary;

        if ($relationship['type'] !== self::ACTIVEX_CONTROL_REL) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($item['external'] === true) {
            $item['issues'][] = 'external-activex-control';
            return $item;
        }

        if (!$exists) {
            $item['issues'][] = 'missing-control-part';
        }

        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        if (($summary['contentTypeSource'] ?? '') === 'missing') {
            $item['issues'][] = 'missing-control-content-type';
        } elseif ($contentTypeBase !== self::CT_ACTIVEX_XML) {
            $item['issues'][] = 'unexpected-control-content-type';
        }

        if ($exists && $targetPart !== null) {
            $root = $this->xmlRootProvenance($parts[$targetPart], $targetPart);
            $item['validXml'] = $root['validXml'];
            $item['xmlParseError'] = $root['xmlParseError'];
            $item['rootNamespace'] = $root['namespace'];
            $item['rootLocalName'] = $root['localName'];
            $item['validRoot'] = $root['namespace'] === self::NS_ACTIVEX && $root['localName'] === 'ocx';
            if ($root['validXml'] === false) {
                $item['issues'][] = 'invalid-control-xml';
            } elseif ($item['validRoot'] === false) {
                $item['issues'][] = 'unexpected-control-root';
            }
        }

        $item['binaries'] = $this->activeXBinaryParts(
            $parts,
            $targetPart,
            $controlRelationshipsPart,
            $controlRelationships,
            $contentTypes,
        );
        $item['valid'] = $item['issues'] === [] && (int) $item['binaries']['issueCount'] === 0;

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyActiveXBinaryParts(): array
    {
        return [
            'count' => 0,
            'existingCount' => 0,
            'missingCount' => 0,
            'externalCount' => 0,
            'unexpectedContentTypeCount' => 0,
            'issueCount' => 0,
            'relationshipIds' => [],
            'partNames' => [],
            'externalTargets' => [],
            'contentTypes' => [],
            'issueCodes' => [],
            'byRelationshipId' => [],
            'items' => [],
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function activeXBinaryParts(
        array $parts,
        ?string $sourcePart,
        ?string $relationshipsPart,
        array $relationships,
        array $contentTypes,
    ): array {
        if ($sourcePart === null || $relationshipsPart === null) {
            return $this->emptyActiveXBinaryParts();
        }

        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::ACTIVEX_BINARY_REL) {
                continue;
            }

            $item = $this->activeXBinaryPartItem($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes, count($items));
            $items[] = $item;
            $byRelationshipId[(string) $item['id']] = $item;
            $relationshipIds[] = (string) $item['id'];
            $this->appendUniqueString($partNames, is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === false && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-activex-binary', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-binary-content-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function activeXBinaryPartItem(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
        int $index,
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        $issues = [];

        if ($external) {
            $issues[] = 'external-activex-binary';
        } elseif (!$exists) {
            $issues[] = 'missing-activex-binary';
        }
        if (!$external && ($summary['contentTypeSource'] ?? '') === 'missing') {
            $issues[] = 'missing-binary-content-type';
        } elseif (!$external && $contentTypeBase !== '' && $contentTypeBase !== self::CT_ACTIVEX_BINARY) {
            $issues[] = 'unexpected-binary-content-type';
        }

        return [
            'index' => $index,
            'source' => $sourcePart,
            'id' => $summary['id'],
            'type' => $summary['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'expectedContentTypeBase' => self::CT_ACTIVEX_BINARY,
            'external' => $external,
            'exists' => $exists,
            'relationshipsPart' => $relationshipsPart,
            'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
            'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
            'sha256' => $targetPart !== null && $exists ? hash('sha256', $parts[$targetPart]) : null,
            'byteExposurePolicy' => 'activex-binary-bytes-blocked',
            'reviewPolicy' => 'activex-metadata-only',
            'valid' => $issues === [],
            'issues' => $issues,
            'relationship' => $summary,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readVbaProjects(
        array $parts,
        string $documentPart,
        array $relationships,
        array $contentTypes,
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $partNames = [];
        $signaturePartNames = [];
        $dataPartNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::VBA_PROJECT_REL) {
                continue;
            }

            $item = $this->vbaProjectItem($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes, count($items));
            $items[] = $item;
            $byRelationshipId[(string) $item['relationshipId']] = $item;
            $relationshipIds[] = (string) $item['relationshipId'];
            $this->appendUniqueString($partNames, is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }

            foreach (($item['signatureParts']['items'] ?? []) as $signature) {
                if (!is_array($signature)) {
                    continue;
                }
                $this->appendUniqueString($signaturePartNames, is_string($signature['targetPart'] ?? null) ? $signature['targetPart'] : null);
                $this->appendUniqueString($contentTypesSeen, is_string($signature['contentType'] ?? null) ? $signature['contentType'] : null);
                if (($signature['external'] ?? false) === true) {
                    $this->appendUniqueString($externalTargets, is_string($signature['target'] ?? null) ? $signature['target'] : null);
                }
                foreach (($signature['issues'] ?? []) as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $issueCodes[$issue] = true;
                    }
                }
            }

            foreach (($item['dataParts']['items'] ?? []) as $dataPart) {
                if (!is_array($dataPart)) {
                    continue;
                }
                $this->appendUniqueString($dataPartNames, is_string($dataPart['targetPart'] ?? null) ? $dataPart['targetPart'] : null);
                $this->appendUniqueString($contentTypesSeen, is_string($dataPart['contentType'] ?? null) ? $dataPart['contentType'] : null);
                if (($dataPart['external'] ?? false) === true) {
                    $this->appendUniqueString($externalTargets, is_string($dataPart['target'] ?? null) ? $dataPart['target'] : null);
                }
                foreach (($dataPart['issues'] ?? []) as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $issueCodes[$issue] = true;
                    }
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($relationships, static fn (array $relationship): bool => $relationship['type'] === self::VBA_PROJECT_REL)),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === false && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-vba-project', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-project-content-type', $item['issues'], true))),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-project-content-type', $item['issues'], true))),
            'signatureCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['signatureParts']['count'] ?? 0), $items)),
            'existingSignatureCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['signatureParts']['existingCount'] ?? 0), $items)),
            'missingSignatureCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['signatureParts']['missingCount'] ?? 0), $items)),
            'externalSignatureCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['signatureParts']['externalCount'] ?? 0), $items)),
            'dataPartCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['dataParts']['count'] ?? 0), $items)),
            'existingDataPartCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['dataParts']['existingCount'] ?? 0), $items)),
            'missingDataPartCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['dataParts']['missingCount'] ?? 0), $items)),
            'externalDataPartCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['dataParts']['externalCount'] ?? 0), $items)),
            'issueCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['issues'] !== []
                    || (int) ($item['signatureParts']['issueCount'] ?? 0) > 0
                    || (int) ($item['dataParts']['issueCount'] ?? 0) > 0,
            )),
            'relationshipIds' => $relationshipIds,
            'partNames' => $partNames,
            'signaturePartNames' => $signaturePartNames,
            'dataPartNames' => $dataPartNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
            'byteExposurePolicy' => 'vba-project-bytes-blocked',
            'reviewPolicy' => 'vba-project-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function vbaProjectItem(
        array $parts,
        array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        int $index,
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $projectRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $projectRelationships = $projectRelationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $projectRelationshipsPart);
        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        $issues = [];

        if ($external) {
            $issues[] = 'external-vba-project';
        } else {
            if (!$exists) {
                $issues[] = 'missing-vba-project';
            }
            if (($summary['contentTypeSource'] ?? '') === 'missing') {
                $issues[] = 'missing-project-content-type';
            } elseif ($contentTypeBase !== self::CT_VBA_PROJECT) {
                $issues[] = 'unexpected-project-content-type';
            }
        }

        $signatureParts = $this->vbaProjectRelatedParts(
            $parts,
            $targetPart,
            $projectRelationshipsPart,
            $projectRelationships,
            $contentTypes,
            self::VBA_PROJECT_SIGNATURE_REL,
            self::CT_VBA_PROJECT_SIGNATURE,
            'project-signature',
        );
        $dataParts = $this->vbaProjectRelatedParts(
            $parts,
            $targetPart,
            $projectRelationshipsPart,
            $projectRelationships,
            $contentTypes,
            self::VBA_DATA_REL,
            self::CT_VBA_DATA,
            'data',
        );

        return [
            'index' => $index,
            'relationshipId' => $summary['id'],
            'relationshipType' => $summary['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'external' => $external,
            'partName' => $targetPart,
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'exists' => $exists,
            'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
            'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
            'sha256' => $targetPart !== null && $exists ? hash('sha256', $parts[$targetPart]) : null,
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'expectedContentTypeBase' => self::CT_VBA_PROJECT,
            'relationshipsPart' => $relationshipsPart,
            'projectRelationshipsPart' => $projectRelationshipsPart,
            'projectRelationshipCount' => count($projectRelationships),
            'signatureParts' => $signatureParts,
            'dataParts' => $dataParts,
            'byteExposurePolicy' => 'vba-project-bytes-blocked',
            'reviewPolicy' => 'vba-project-metadata-only',
            'valid' => $issues === [] && (int) $signatureParts['issueCount'] === 0 && (int) $dataParts['issueCount'] === 0,
            'issues' => $issues,
            'relationship' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyVbaProjectRelatedParts(): array
    {
        return [
            'count' => 0,
            'existingCount' => 0,
            'missingCount' => 0,
            'externalCount' => 0,
            'unexpectedContentTypeCount' => 0,
            'issueCount' => 0,
            'relationshipIds' => [],
            'partNames' => [],
            'externalTargets' => [],
            'contentTypes' => [],
            'issueCodes' => [],
            'byRelationshipId' => [],
            'items' => [],
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function vbaProjectRelatedParts(
        array $parts,
        ?string $sourcePart,
        ?string $relationshipsPart,
        array $relationships,
        array $contentTypes,
        string $relationshipType,
        string $expectedContentTypeBase,
        string $kind,
    ): array {
        if ($sourcePart === null || $relationshipsPart === null) {
            return $this->emptyVbaProjectRelatedParts();
        }

        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== $relationshipType) {
                continue;
            }

            $item = $this->vbaProjectRelatedPartItem(
                $parts,
                $relationship,
                $sourcePart,
                $relationshipsPart,
                $contentTypes,
                $expectedContentTypeBase,
                $kind,
                count($items),
            );
            $items[] = $item;
            $byRelationshipId[(string) $item['id']] = $item;
            $relationshipIds[] = (string) $item['id'];
            $this->appendUniqueString($partNames, is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $issueCodes[$issue] = true;
                }
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === false && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-vba-' . $kind, $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-vba-' . $kind . '-content-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function vbaProjectRelatedPartItem(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
        string $expectedContentTypeBase,
        string $kind,
        int $index,
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        $issues = [];

        if ($external) {
            $issues[] = 'external-vba-' . $kind;
        } else {
            if (!$exists) {
                $issues[] = 'missing-vba-' . $kind;
            }
            if (($summary['contentTypeSource'] ?? '') === 'missing') {
                $issues[] = 'missing-vba-' . $kind . '-content-type';
            } elseif ($contentTypeBase !== $expectedContentTypeBase) {
                $issues[] = 'unexpected-vba-' . $kind . '-content-type';
            }
        }

        return [
            'index' => $index,
            'source' => $sourcePart,
            'id' => $summary['id'],
            'type' => $summary['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'expectedContentTypeBase' => $expectedContentTypeBase,
            'external' => $external,
            'exists' => $exists,
            'relationshipsPart' => $relationshipsPart,
            'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
            'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
            'sha256' => $targetPart !== null && $exists ? hash('sha256', $parts[$targetPart]) : null,
            'byteExposurePolicy' => 'vba-' . $kind . '-bytes-blocked',
            'reviewPolicy' => 'vba-project-metadata-only',
            'valid' => $issues === [],
            'issues' => $issues,
            'relationship' => $summary,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $settingsRelationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readAttachedTemplates(
        array $parts,
        string $settingsXml,
        string $settingsPart,
        string $settingsRelationshipsPart,
        array $settingsRelationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $referencedRelationshipIds = [];
        $referencedAttachedTemplateRelationshipIds = [];

        if ($settingsXml !== '') {
            $dom = $this->loadXml($settingsXml, $settingsPart);
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '/w:settings/w:attachedTemplate') as $template) {
                $relationshipId = $template->getAttributeNS(self::NS_R, 'id');
                $item = $this->attachedTemplateItem(
                    $parts,
                    $settingsRelationships[$relationshipId] ?? null,
                    $settingsPart,
                    $settingsRelationshipsPart,
                    $contentTypes,
                    $relationshipId,
                    count($items),
                    true,
                );
                $items[] = $item;

                $this->appendUniqueString($relationshipIds, $relationshipId);
                $this->appendUniqueString($referencedRelationshipIds, $relationshipId);
                if ($item['relationshipType'] === self::ATTACHED_TEMPLATE_REL) {
                    $this->appendUniqueString($referencedAttachedTemplateRelationshipIds, $relationshipId);
                }
                if ($relationshipId !== '' && !isset($byRelationshipId[$relationshipId])) {
                    $byRelationshipId[$relationshipId] = $item;
                }
            }
        }

        $unreferencedRelationshipIds = [];
        foreach ($settingsRelationships as $relationship) {
            if ($relationship['type'] !== self::ATTACHED_TEMPLATE_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            if (in_array($relationshipId, $referencedAttachedTemplateRelationshipIds, true)) {
                continue;
            }

            $item = $this->attachedTemplateItem(
                $parts,
                $relationship,
                $settingsPart,
                $settingsRelationshipsPart,
                $contentTypes,
                $relationshipId,
                count($items),
                false,
            );
            $items[] = $item;

            $this->appendUniqueString($relationshipIds, $relationshipId);
            $this->appendUniqueString($unreferencedRelationshipIds, $relationshipId);
            if (!isset($byRelationshipId[$relationshipId])) {
                $byRelationshipId[$relationshipId] = $item;
            }
        }

        $partNames = [];
        $issueCount = 0;
        foreach ($items as $item) {
            $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
            $issueCount += count($item['issues']);
        }

        return [
            'count' => count($items),
            'relationshipCount' => count(array_filter($settingsRelationships, static fn (array $relationship): bool => $relationship['type'] === self::ATTACHED_TEMPLATE_REL)),
            'referencedCount' => count(array_filter($items, static fn (array $item): bool => $item['referenced'] === true)),
            'unreferencedRelationshipCount' => count($unreferencedRelationshipIds),
            'internalCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::ATTACHED_TEMPLATE_REL && $item['external'] === false)),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::ATTACHED_TEMPLATE_REL && $item['external'] === true)),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipType'] === self::ATTACHED_TEMPLATE_REL && $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-in-package', $item['issues'], true))),
            'unresolvedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-relationship-id', $item['issues'], true) || in_array('unknown-relationship', $item['issues'], true),
            )),
            'unexpectedRelationshipTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-relationship-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'issueCount' => $issueCount,
            'relationshipIds' => $relationshipIds,
            'referencedRelationshipIds' => $referencedRelationshipIds,
            'unreferencedRelationshipIds' => $unreferencedRelationshipIds,
            'partNames' => $partNames,
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function attachedTemplateItem(
        array $parts,
        ?array $relationship,
        string $settingsPart,
        string $settingsRelationshipsPart,
        array $contentTypes,
        string $relationshipId,
        int $index,
        bool $referenced
    ): array {
        $item = [
            'index' => $index,
            'relationshipId' => $relationshipId,
            'referenced' => $referenced,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'external' => false,
            'partName' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'exists' => false,
            'bytes' => 0,
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'settingsPart' => $settingsPart,
            'settingsRelationshipsPart' => $settingsRelationshipsPart,
            'relationship' => null,
            'issues' => [],
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $settingsPart, $settingsRelationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $exists = (bool) $summary['exists'];

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['external'] = (bool) $summary['external'];
        $item['partName'] = $targetPart;
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['exists'] = $exists;
        $item['bytes'] = $exists && $targetPart !== null ? strlen($parts[$targetPart]) : 0;
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['relationship'] = $summary;

        if ($relationship['type'] !== self::ATTACHED_TEMPLATE_REL) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($item['external'] === true) {
            return $item;
        }

        if (!$exists) {
            $item['issues'][] = 'missing-in-package';
        }

        if ($item['contentTypeSource'] === 'missing') {
            $item['issues'][] = 'missing-content-type';
        }

        return $item;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function readCustomXmlParts(
        array $parts,
        string $documentPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $partNames = [];
        $relationshipsPart = $this->relationshipsPartFor($documentPart);

        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::CUSTOM_XML_REL) {
                continue;
            }

            $relationshipId = $relationship['id'];
            $item = $this->customXmlPartItem(
                $parts,
                $relationship,
                $documentPart,
                $relationshipsPart,
                $contentTypes,
                count($items),
            );
            $items[] = $item;
        }

        $storeItemSummary = $this->annotateCustomXmlDuplicateStoreItemIds($items);
        $issueCount = 0;

        foreach ($items as $item) {
            $relationshipId = is_string($item['relationshipId'] ?? null) ? $item['relationshipId'] : '';
            if ($relationshipId === '') {
                continue;
            }
            $byRelationshipId[$relationshipId] = $item;
            $relationshipIds[] = $relationshipId;
            $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
            $issueCount += count($item['issues']);
            foreach (($item['propertiesParts']['items'] ?? []) as $propertiesItem) {
                if (is_array($propertiesItem)) {
                    $issueCount += count($propertiesItem['issues'] ?? []);
                }
            }
        }

        $propertiesPartCount = 0;
        $existingPropertiesPartCount = 0;
        $missingPropertiesPartCount = 0;
        $invalidPropertiesXmlCount = 0;
        foreach ($items as $item) {
            $propertiesParts = $item['propertiesParts'];
            $propertiesPartCount += (int) $propertiesParts['count'];
            $existingPropertiesPartCount += (int) $propertiesParts['existingCount'];
            $missingPropertiesPartCount += (int) $propertiesParts['missingCount'];
            $invalidPropertiesXmlCount += (int) $propertiesParts['invalidXmlCount'];
        }

        return [
            'count' => count($items),
            'relationshipCount' => count($relationshipIds),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-item-part', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'invalidXmlCount' => count(array_filter($items, static fn (array $item): bool => in_array('invalid-xml', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'missingPropertiesRelationshipCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-properties-relationship', $item['issues'], true))),
            'propertiesPartCount' => $propertiesPartCount,
            'existingPropertiesPartCount' => $existingPropertiesPartCount,
            'missingPropertiesPartCount' => $missingPropertiesPartCount,
            'invalidPropertiesXmlCount' => $invalidPropertiesXmlCount,
            'storeItemIds' => $storeItemSummary['storeItemIds'],
            'duplicateStoreItemIdCount' => count($storeItemSummary['duplicateStoreItemIds']),
            'duplicateStoreItemIds' => $storeItemSummary['duplicateStoreItemIds'],
            'duplicateStoreItemIdReferences' => $storeItemSummary['duplicateStoreItemIdReferences'],
            'issueCount' => $issueCount,
            'relationshipIds' => $relationshipIds,
            'partNames' => $partNames,
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{storeItemIds:list<string>, duplicateStoreItemIds:list<string>, duplicateStoreItemIdReferences:array<string, list<array<string, mixed>>>}
     */
    private function annotateCustomXmlDuplicateStoreItemIds(array &$items): array
    {
        $references = [];
        foreach ($items as $itemIndex => $item) {
            $propertiesItems = $item['propertiesParts']['items'] ?? [];
            if (!is_array($propertiesItems)) {
                continue;
            }

            foreach ($propertiesItems as $propertiesIndex => $propertiesItem) {
                if (!is_array($propertiesItem)) {
                    continue;
                }

                $itemId = is_string($propertiesItem['itemId'] ?? null) ? trim($propertiesItem['itemId']) : '';
                if ($itemId === '') {
                    continue;
                }

                $references[$itemId][] = [
                    'customXmlIndex' => (int) ($item['index'] ?? $itemIndex),
                    'customXmlRelationshipId' => is_string($item['relationshipId'] ?? null) ? $item['relationshipId'] : '',
                    'customXmlPartName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                    'propertiesIndex' => (int) ($propertiesItem['index'] ?? $propertiesIndex),
                    'propertiesRelationshipId' => is_string($propertiesItem['relationshipId'] ?? null) ? $propertiesItem['relationshipId'] : '',
                    'propertiesPartName' => is_string($propertiesItem['partName'] ?? null) ? $propertiesItem['partName'] : null,
                ];
            }
        }

        ksort($references);
        $duplicateStoreItemIds = [];
        $duplicateStoreItemIdReferences = [];
        foreach ($references as $itemId => $itemReferences) {
            if (count($itemReferences) < 2) {
                continue;
            }

            $duplicateStoreItemIds[] = $itemId;
            $duplicateStoreItemIdReferences[$itemId] = $itemReferences;
        }

        if ($duplicateStoreItemIds !== []) {
            $duplicates = array_fill_keys($duplicateStoreItemIds, true);
            foreach ($items as $itemIndex => $item) {
                $propertiesItems = $item['propertiesParts']['items'] ?? [];
                if (!is_array($propertiesItems)) {
                    continue;
                }

                $itemHasDuplicate = false;
                foreach ($propertiesItems as $propertiesIndex => $propertiesItem) {
                    if (!is_array($propertiesItem)) {
                        continue;
                    }

                    $itemId = is_string($propertiesItem['itemId'] ?? null) ? trim($propertiesItem['itemId']) : '';
                    if ($itemId === '' || !isset($duplicates[$itemId])) {
                        continue;
                    }

                    $itemHasDuplicate = true;
                    $this->appendUniqueString($items[$itemIndex]['propertiesParts']['items'][$propertiesIndex]['issues'], 'duplicate-store-item-id');
                    $propertiesRelationshipId = is_string($propertiesItem['relationshipId'] ?? null) ? $propertiesItem['relationshipId'] : '';
                    if ($propertiesRelationshipId !== '' && isset($items[$itemIndex]['propertiesParts']['byRelationshipId'][$propertiesRelationshipId])) {
                        $this->appendUniqueString($items[$itemIndex]['propertiesParts']['byRelationshipId'][$propertiesRelationshipId]['issues'], 'duplicate-store-item-id');
                    }
                }

                if ($itemHasDuplicate) {
                    $this->appendUniqueString($items[$itemIndex]['issues'], 'duplicate-store-item-id');
                }
            }
        }

        return [
            'storeItemIds' => array_keys($references),
            'duplicateStoreItemIds' => $duplicateStoreItemIds,
            'duplicateStoreItemIdReferences' => $duplicateStoreItemIdReferences,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function customXmlPartItem(
        array $parts,
        array $relationship,
        string $documentPart,
        string $relationshipsPart,
        array $contentTypes,
        int $index
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $documentPart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) $summary['external'];
        $exists = (bool) $summary['exists'];
        $partRelationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $partRelationships = $partRelationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $partRelationshipsPart);
        $propertiesParts = $this->customXmlPropertiesParts(
            $parts,
            $targetPart,
            $partRelationshipsPart,
            $partRelationships,
            $contentTypes,
        );
        $issues = [];

        if ($external) {
            $issues[] = 'external-custom-xml';
        } elseif (!$exists) {
            $issues[] = 'missing-item-part';
        }
        if (!$external && $summary['contentTypeSource'] === 'missing') {
            $issues[] = 'missing-content-type';
        }
        if ($exists && $propertiesParts['count'] === 0) {
            $issues[] = 'missing-properties-relationship';
        }

        $root = ['validXml' => null, 'xmlParseError' => null, 'rootNamespace' => null, 'rootLocalName' => null];
        if ($exists && $targetPart !== null) {
            $root = $this->xmlRootPreflight($parts[$targetPart], $targetPart);
            if ($root['validXml'] === false) {
                $issues[] = 'invalid-xml';
            }
        }

        return [
            'index' => $index,
            'relationshipId' => $relationship['id'],
            'relationshipType' => $relationship['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'external' => $external,
            'partName' => $targetPart,
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'exists' => $exists,
            'bytes' => $exists && $targetPart !== null ? strlen($parts[$targetPart]) : 0,
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'validXml' => $root['validXml'],
            'xmlParseError' => $root['xmlParseError'],
            'rootNamespace' => $root['rootNamespace'],
            'rootLocalName' => $root['rootLocalName'],
            'relationshipsPart' => $partRelationshipsPart,
            'relationshipCount' => count($partRelationships),
            'propertiesParts' => $propertiesParts,
            'relationship' => $summary,
            'issues' => $issues,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function customXmlPropertiesParts(
        array $parts,
        ?string $sourcePart,
        ?string $relationshipsPart,
        array $relationships,
        array $contentTypes
    ): array {
        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $partNames = [];

        if ($sourcePart === null || $relationshipsPart === null) {
            return [
                'count' => 0,
                'existingCount' => 0,
                'missingCount' => 0,
                'invalidXmlCount' => 0,
                'invalidRootCount' => 0,
                'missingContentTypeCount' => 0,
                'unexpectedContentTypeCount' => 0,
                'relationshipIds' => [],
                'partNames' => [],
                'byRelationshipId' => [],
                'items' => [],
            ];
        }

        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::CUSTOM_XML_PROPS_REL) {
                continue;
            }

            $item = $this->customXmlPropertiesPartItem(
                $parts,
                $relationship,
                $sourcePart,
                $relationshipsPart,
                $contentTypes,
                count($items),
            );
            $items[] = $item;
            $byRelationshipId[$relationship['id']] = $item;
            $relationshipIds[] = $relationship['id'];
            $this->appendUniqueString($partNames, is_string($item['partName'] ?? null) ? $item['partName'] : null);
        }

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-properties-part', $item['issues'], true))),
            'invalidXmlCount' => count(array_filter($items, static fn (array $item): bool => in_array('invalid-xml', $item['issues'], true))),
            'invalidRootCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-root', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-content-type', $item['issues'], true))),
            'relationshipIds' => $relationshipIds,
            'partNames' => $partNames,
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function customXmlPropertiesPartItem(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
        int $index
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) $summary['external'];
        $exists = (bool) $summary['exists'];
        $contentTypeMatchesExpected = null;
        $issues = [];

        if ($targetPart !== null) {
            $contentTypeMatchesExpected = $summary['contentTypeBase'] === self::CT_CUSTOM_XML_PROPERTIES;
            if (!$contentTypeMatchesExpected) {
                $issues[] = $summary['contentTypeSource'] === 'missing'
                    ? 'missing-content-type'
                    : 'unexpected-content-type';
            }
        }
        if ($external) {
            $issues[] = 'external-properties';
        } elseif (!$exists) {
            $issues[] = 'missing-properties-part';
        }

        $metadata = [
            'validXml' => null,
            'xmlParseError' => null,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'itemId' => null,
            'schemaRefs' => [],
        ];
        if ($exists && $targetPart !== null) {
            $metadata = $this->customXmlPropertiesMetadata($parts[$targetPart], $targetPart);
            if ($metadata['validXml'] === false) {
                $issues[] = 'invalid-xml';
            } elseif ($metadata['validRoot'] === false) {
                $issues[] = 'unexpected-root';
            }
        }

        return [
            'index' => $index,
            'relationshipId' => $relationship['id'],
            'relationshipType' => $relationship['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'external' => $external,
            'partName' => $targetPart,
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'exists' => $exists,
            'bytes' => $exists && $targetPart !== null ? strlen($parts[$targetPart]) : 0,
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'expectedContentTypeBase' => self::CT_CUSTOM_XML_PROPERTIES,
            'contentTypeMatchesExpected' => $contentTypeMatchesExpected,
            'validXml' => $metadata['validXml'],
            'xmlParseError' => $metadata['xmlParseError'],
            'rootNamespace' => $metadata['rootNamespace'],
            'rootLocalName' => $metadata['rootLocalName'],
            'validRoot' => $metadata['validRoot'],
            'itemId' => $metadata['itemId'],
            'schemaRefs' => $metadata['schemaRefs'],
            'relationship' => $summary,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{validXml:bool, xmlParseError:?string, rootNamespace:?string, rootLocalName:?string}
     */
    private function xmlRootPreflight(string $xml, string $partName): array
    {
        $dom = $this->loadXmlForProvenance($xml, $partName);
        if (!$dom instanceof \DOMDocument) {
            return [
                'validXml' => false,
                'xmlParseError' => $this->lastXmlPreflightError($xml, $partName),
                'rootNamespace' => null,
                'rootLocalName' => null,
            ];
        }

        $root = $dom->documentElement;

        return [
            'validXml' => $root instanceof \DOMElement,
            'xmlParseError' => null,
            'rootNamespace' => $root instanceof \DOMElement ? $root->namespaceURI : null,
            'rootLocalName' => $root instanceof \DOMElement ? $root->localName : null,
        ];
    }

    /**
     * @return array{validXml:bool, xmlParseError:?string, rootNamespace:?string, rootLocalName:?string, validRoot:bool, itemId:?string, schemaRefs:list<string>}
     */
    private function customXmlPropertiesMetadata(string $xml, string $partName): array
    {
        $dom = $this->loadXmlForProvenance($xml, $partName);
        if (!$dom instanceof \DOMDocument) {
            return [
                'validXml' => false,
                'xmlParseError' => $this->lastXmlPreflightError($xml, $partName),
                'rootNamespace' => null,
                'rootLocalName' => null,
                'validRoot' => false,
                'itemId' => null,
                'schemaRefs' => [],
            ];
        }

        $root = $dom->documentElement;
        $validRoot = $root instanceof \DOMElement
            && $root->namespaceURI === self::NS_DS
            && $root->localName === 'datastoreItem';
        $schemaRefs = [];
        if ($validRoot) {
            $xpath = $this->xpath($dom);
            foreach ($this->elements($xpath, '/ds:datastoreItem/ds:schemaRefs/ds:schemaRef') as $schemaRef) {
                $uri = $schemaRef->getAttributeNS(self::NS_DS, 'uri');
                if ($uri !== '') {
                    $schemaRefs[] = $uri;
                }
            }
        }

        return [
            'validXml' => $root instanceof \DOMElement,
            'xmlParseError' => null,
            'rootNamespace' => $root instanceof \DOMElement ? $root->namespaceURI : null,
            'rootLocalName' => $root instanceof \DOMElement ? $root->localName : null,
            'validRoot' => $validRoot,
            'itemId' => $validRoot && $root instanceof \DOMElement ? $this->emptyStringToNull($root->getAttributeNS(self::NS_DS, 'itemID')) : null,
            'schemaRefs' => $schemaRefs,
        ];
    }

    private function loadXmlForProvenance(string $xml, string $partName): ?\DOMDocument
    {
        if ($xml === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $dom : null;
    }

    private function lastXmlPreflightError(string $xml, string $partName): string
    {
        if ($xml === '') {
            return 'empty XML part';
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($ok) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return '';
        }
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $errors === [] ? "Invalid DOCX XML part {$partName}" : trim($errors[0]->message);
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function relatedPartRelationshipDiagnostics(
        array $parts,
        array $relationships,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes
    ): array {
        $items = [];
        $byId = [];
        $relationshipIds = [];
        $targetParts = [];
        $externalTargets = [];
        $targetReferenceSuffixes = [];
        $issueCodes = [];
        $internalCount = 0;
        $externalCount = 0;
        $existingCount = 0;
        $missingCount = 0;
        $missingContentTypeCount = 0;
        $issueCount = 0;

        foreach ($relationships as $relationship) {
            $item = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
            $issues = [];
            $this->appendUniqueString($relationshipIds, is_string($item['id'] ?? null) ? $item['id'] : null);
            if (($item['external'] ?? false) === true) {
                ++$externalCount;
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            } else {
                ++$internalCount;
                $targetPart = is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null;
                $this->appendUniqueString($targetParts, $targetPart);
                if (($item['exists'] ?? false) === true) {
                    ++$existingCount;
                } else {
                    ++$missingCount;
                    $issues[] = 'missing-target-part';
                }
                if (($item['contentTypeSource'] ?? 'missing') === 'missing') {
                    ++$missingContentTypeCount;
                    $issues[] = 'missing-target-content-type';
                }
            }

            $targetReferenceSuffix = is_string($item['targetReferenceSuffix'] ?? null) ? $item['targetReferenceSuffix'] : '';
            $this->appendUniqueString($targetReferenceSuffixes, $targetReferenceSuffix);
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            if ($issues !== []) {
                ++$issueCount;
            }
            $item['issues'] = $issues;
            $items[] = $item;
            if (is_string($item['id'] ?? null) && $item['id'] !== '') {
                $byId[$item['id']] = $item;
            }
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'sourcePart' => $sourcePart,
            'relationshipsPart' => $relationshipsPart,
            'relationshipCount' => count($items),
            'internalRelationshipCount' => $internalCount,
            'externalRelationshipCount' => $externalCount,
            'existingTargetCount' => $existingCount,
            'missingTargetCount' => $missingCount,
            'missingContentTypeCount' => $missingContentTypeCount,
            'targetReferenceSuffixCount' => count($targetReferenceSuffixes),
            'relationshipIds' => $relationshipIds,
            'targetParts' => $targetParts,
            'externalTargets' => $externalTargets,
            'targetReferenceSuffixes' => $targetReferenceSuffixes,
            'issueCount' => $issueCount,
            'issueCodes' => array_keys($issueCodes),
            'byId' => $byId,
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, mixed> $relationshipDiagnostics
     * @return array<string, mixed>
     */
    private function noteCollectionSummary(array $items, array $byId, array $relationshipDiagnostics): array
    {
        return [
            'count' => count($items),
            'ids' => array_column($items, 'id'),
            'byId' => $byId,
            'items' => $items,
            'relationshipsPart' => $relationshipDiagnostics['relationshipsPart'],
            'relationshipCount' => $relationshipDiagnostics['relationshipCount'],
            'internalRelationshipCount' => $relationshipDiagnostics['internalRelationshipCount'],
            'externalRelationshipCount' => $relationshipDiagnostics['externalRelationshipCount'],
            'existingRelationshipTargetCount' => $relationshipDiagnostics['existingTargetCount'],
            'missingRelationshipTargetCount' => $relationshipDiagnostics['missingTargetCount'],
            'missingRelationshipContentTypeCount' => $relationshipDiagnostics['missingContentTypeCount'],
            'relationshipTargetReferenceSuffixCount' => $relationshipDiagnostics['targetReferenceSuffixCount'],
            'relationshipIds' => $relationshipDiagnostics['relationshipIds'],
            'relationshipTargetParts' => $relationshipDiagnostics['targetParts'],
            'relationshipExternalTargets' => $relationshipDiagnostics['externalTargets'],
            'relationshipIssueCount' => $relationshipDiagnostics['issueCount'],
            'relationshipIssueCodes' => $relationshipDiagnostics['issueCodes'],
            'relationships' => $relationshipDiagnostics['byId'],
            'relationshipDiagnostics' => $relationshipDiagnostics,
        ];
    }

    /**
     * @param list<string> $relationshipIds
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return list<string>
     */
    private function missingRelationshipIds(array $relationshipIds, array $relationships): array
    {
        return array_values(array_filter(
            $relationshipIds,
            static fn (string $relationshipId): bool => !isset($relationships[$relationshipId]),
        ));
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return array{validRoot:bool, rootName:?string, blocks:list<AstNode>}
     */
    private function readHeaderFooterPart(
        string $xml,
        string $partName,
        string $expectedRootName,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering,
        array $referencedNotes
    ): array {
        if ($xml === '') {
            return ['validRoot' => false, 'rootName' => null, 'blocks' => []];
        }

        $dom = $this->loadXml($xml, $partName);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return ['validRoot' => false, 'rootName' => null, 'blocks' => []];
        }

        $rootName = $root->localName;
        if ($root->namespaceURI !== self::NS_W || $rootName !== $expectedRootName) {
            return ['validRoot' => false, 'rootName' => $rootName, 'blocks' => []];
        }

        return [
            'validRoot' => true,
            'rootName' => $rootName,
            'blocks' => $this->readNoteBlocks($root, $this->xpath($dom), $relationships, $contentTypes, $styles, $numbering, $referencedNotes),
        ];
    }

    /**
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array{id:string, type:string, sourcePart:string, relationshipsPart:string, target:string, targetMode:string, resolvedTarget:string, targetPart:string, targetQuery:?string, targetFragment:?string, targetReferenceSuffix:string, exists:bool, contentType:string, contentTypeSource:string, defaultExtension:?string, overridePartName:?string}
     */
    private function relationshipSummary(array $relationship, string $sourcePart, string $relationshipsPart, string $targetPart, bool $exists, array $contentTypes): array
    {
        $targetReferenceSuffix = $this->targetReferenceSuffix($relationship['resolvedTarget']);
        $contentTypeResolution = $this->contentTypeResolutionForPart($targetPart, $contentTypes);

        return [
            'id' => $relationship['id'],
            'type' => $relationship['type'],
            'sourcePart' => $sourcePart,
            'relationshipsPart' => $relationshipsPart,
            'target' => $relationship['target'],
            'targetMode' => $relationship['targetMode'],
            'resolvedTarget' => $relationship['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $targetReferenceSuffix['query'],
            'targetFragment' => $targetReferenceSuffix['fragment'],
            'targetReferenceSuffix' => $targetReferenceSuffix['suffix'],
            'exists' => $exists,
            'contentType' => $contentTypeResolution['contentType'],
            'contentTypeBase' => $contentTypeResolution['contentTypeBase'],
            'contentTypeHasParameters' => $contentTypeResolution['contentTypeHasParameters'],
            'contentTypeParameterCount' => $contentTypeResolution['contentTypeParameterCount'],
            'contentTypeParameters' => $contentTypeResolution['contentTypeParameters'],
            'contentTypeParameterMap' => $contentTypeResolution['contentTypeParameterMap'],
            'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
            'defaultExtension' => $contentTypeResolution['defaultExtension'],
            'overridePartName' => $contentTypeResolution['overridePartName'],
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $documentRelationships
     * @return array<string, mixed>
     */
    private function packageProvenance(
        array $parts,
        array $contentTypes,
        array $rootRelationships,
        string $documentPart,
        string $documentRelationshipsPart,
        array $documentRelationships,
        ?ZipPackage $sourcePackage = null,
    ): array {
        $contentTypesPart = $this->contentTypesPartProvenance($parts, $contentTypes);
        $relationshipParts = $this->packageRelationshipPartsProvenance(
            $parts,
            $contentTypes,
            $documentPart,
            $documentRelationshipsPart,
            $rootRelationships,
            $documentRelationships,
        );
        $partInventory = $this->packagePartInventory(
            $parts,
            $contentTypes,
            $rootRelationships,
            $documentPart,
            $documentRelationshipsPart,
            $documentRelationships,
        );
        $zipPackage = $this->zipPackageProvenance($sourcePackage, $parts);
        $partInventory = $this->packagePartInventoryWithZipProvenance($partInventory, $zipPackage);
        $summary = $this->packageProvenanceSummary($contentTypesPart, $relationshipParts, $partInventory);
        $summary['zipPackagePresent'] = $zipPackage['present'];
        $summary['zipEntryCount'] = $zipPackage['entryCount'];
        $summary['zipFileEntryCount'] = $zipPackage['fileEntryCount'];
        $summary['zipDirectoryEntryCount'] = $zipPackage['directoryEntryCount'];
        $summary['zipLoadedPartCount'] = $zipPackage['loadedPartCount'];
        $summary['zipUnsupportedCompressionMethodCount'] = $zipPackage['unsupportedCompressionMethodCount'];
        $summary['zipCentralDirectoryOrderMatchesLocalHeaderOrder'] = $zipPackage['centralDirectoryOrderMatchesLocalHeaderOrder'];
        $summary['zipCompressionMethods'] = $zipPackage['compressionMethods']['methodBuckets'] ?? [];

        return [
            'contentTypesPart' => $contentTypesPart,
            'relationshipParts' => $relationshipParts,
            'relationshipTypes' => $this->relationshipTypeProvenance($relationshipParts),
            'documentPart' => $documentPart,
            'documentRelationshipsPart' => $documentRelationshipsPart,
            'parts' => $partInventory,
            'zipPackage' => $zipPackage,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, mixed>
     */
    private function zipPackageProvenance(?ZipPackage $sourcePackage, array $parts): array
    {
        if (!$sourcePackage instanceof ZipPackage) {
            return [
                'present' => false,
                'entryCount' => 0,
                'fileEntryCount' => 0,
                'directoryEntryCount' => 0,
                'loadedPartCount' => 0,
                'unsupportedCompressionMethodCount' => 0,
                'centralDirectoryOrderMatchesLocalHeaderOrder' => null,
                'centralDirectoryOrderNames' => [],
                'localHeaderOrderNames' => [],
                'directoryPackagePaths' => [],
                'loadedPartNames' => [],
                'compressionMethods' => [
                    'entryCount' => 0,
                    'supportedEntryCount' => 0,
                    'unsupportedCompressionMethodCount' => 0,
                    'storedEntryCount' => 0,
                    'deflatedEntryCount' => 0,
                    'storedCompressedBytes' => 0,
                    'storedUncompressedBytes' => 0,
                    'deflatedCompressedBytes' => 0,
                    'deflatedUncompressedBytes' => 0,
                    'unsupportedCompressedBytes' => 0,
                    'unsupportedUncompressedBytes' => 0,
                    'unsupportedEntries' => [],
                    'methodBuckets' => [],
                    'entries' => [],
                ],
                'localHeaderOrder' => [
                    'entryCount' => 0,
                    'centralDirectoryOffset' => null,
                    'centralDirectoryOrderNames' => [],
                    'localHeaderOrderNames' => [],
                    'hasCentralDirectoryOrderMismatch' => false,
                    'mismatchedEntryCount' => 0,
                    'mismatchedEntries' => [],
                    'entries' => [],
                ],
                'byteExposurePolicy' => 'docx-zip-entry-metadata-only',
                'canExposeBytes' => false,
                'entries' => [],
                'byPackagePath' => [],
            ];
        }

        $localHeaderOrder = $sourcePackage->localHeaderOrderPreflight();
        $compressionMethods = $sourcePackage->compressionMethodPreflight();
        $localOrderByName = [];
        foreach ($localHeaderOrder['entries'] as $entry) {
            $localOrderByName[(string) $entry['name']] = $entry;
        }

        $entries = [];
        $byPackagePath = [];
        $directoryPackagePaths = [];
        $loadedPartNames = [];
        $fileEntryCount = 0;
        $directoryEntryCount = 0;
        $loadedPartCount = 0;
        foreach ($sourcePackage->entries() as $centralDirectoryIndex => $entry) {
            $isDirectory = $entry->isDirectory();
            $loadedPart = !$isDirectory && array_key_exists($entry->name, $parts);
            if ($isDirectory) {
                ++$directoryEntryCount;
                $directoryPackagePaths[] = $entry->name;
            } else {
                ++$fileEntryCount;
            }
            if ($loadedPart) {
                ++$loadedPartCount;
                $loadedPartNames[] = $entry->name;
            }

            $localOrder = $localOrderByName[$entry->name] ?? null;
            $summary = [
                'packagePath' => $entry->name,
                'partName' => $isDirectory ? null : $entry->name,
                'roles' => $this->zipPackageEntryRoles($entry, $loadedPart),
                'centralDirectoryIndex' => $centralDirectoryIndex,
                'localHeaderOrder' => is_array($localOrder) ? $localOrder['localHeaderOrder'] : null,
                'localHeaderOffset' => $entry->localHeaderOffset,
                'matchesCentralDirectoryOrder' => is_array($localOrder)
                    ? $localOrder['matchesCentralDirectoryOrder']
                    : null,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::zipCompressionMethodName($entry->compressionMethod),
                'compressionSupported' => $entry->compressionMethod === 0 || $entry->compressionMethod === 8,
                'byteLength' => $entry->uncompressedSize,
                'compressedByteLength' => $entry->compressedSize,
                'crc32' => $entry->crc32Hex(),
                'isDirectory' => $isDirectory,
                'loadedPart' => $loadedPart,
                'canExposeBytes' => false,
                'byteExposurePolicy' => 'docx-zip-entry-metadata-only',
            ];
            $entries[] = $summary;
            $byPackagePath[$entry->name] = $summary;
        }

        return [
            'present' => true,
            'entryCount' => count($entries),
            'fileEntryCount' => $fileEntryCount,
            'directoryEntryCount' => $directoryEntryCount,
            'loadedPartCount' => $loadedPartCount,
            'unsupportedCompressionMethodCount' => (int) $compressionMethods['unsupportedCompressionMethodCount'],
            'centralDirectoryOrderMatchesLocalHeaderOrder' => !$localHeaderOrder['hasCentralDirectoryOrderMismatch'],
            'centralDirectoryOrderNames' => $localHeaderOrder['centralDirectoryOrderNames'],
            'localHeaderOrderNames' => $localHeaderOrder['localHeaderOrderNames'],
            'directoryPackagePaths' => $directoryPackagePaths,
            'loadedPartNames' => $loadedPartNames,
            'compressionMethods' => $compressionMethods,
            'localHeaderOrder' => $localHeaderOrder,
            'byteExposurePolicy' => 'docx-zip-entry-metadata-only',
            'canExposeBytes' => false,
            'entries' => $entries,
            'byPackagePath' => $byPackagePath,
        ];
    }

    private function zipPackageEntryRoles(ZipPackageEntry $entry, bool $loadedPart): array
    {
        if ($entry->isDirectory()) {
            return ['zip-directory'];
        }

        $roles = ['zip-file-entry'];
        if ($loadedPart) {
            $roles[] = 'loaded-package-part';
        }
        if ($entry->name === '[Content_Types].xml') {
            $roles[] = 'content-types';
        }
        if ($this->isRelationshipPartName($entry->name)) {
            $roles[] = 'relationship-part';
        }

        return $roles;
    }

    /**
     * @param array<string, array<string, mixed>> $partInventory
     * @param array<string, mixed> $zipPackage
     * @return array<string, array<string, mixed>>
     */
    private function packagePartInventoryWithZipProvenance(array $partInventory, array $zipPackage): array
    {
        if (($zipPackage['present'] ?? false) !== true || !is_array($zipPackage['byPackagePath'] ?? null)) {
            return $partInventory;
        }

        foreach ($zipPackage['byPackagePath'] as $partName => $entry) {
            if (!is_string($partName) || !is_array($entry) || !isset($partInventory[$partName])) {
                continue;
            }

            $partInventory[$partName]['zipEntryPresent'] = true;
            $partInventory[$partName]['centralDirectoryIndex'] = $entry['centralDirectoryIndex'] ?? null;
            $partInventory[$partName]['localHeaderOrder'] = $entry['localHeaderOrder'] ?? null;
            $partInventory[$partName]['localHeaderOffset'] = $entry['localHeaderOffset'] ?? null;
            $partInventory[$partName]['matchesCentralDirectoryOrder'] = $entry['matchesCentralDirectoryOrder'] ?? null;
            $partInventory[$partName]['compressionMethod'] = $entry['compressionMethod'] ?? null;
            $partInventory[$partName]['compressionMethodName'] = $entry['compressionMethodName'] ?? null;
            $partInventory[$partName]['compressionSupported'] = $entry['compressionSupported'] ?? null;
            $partInventory[$partName]['compressedByteLength'] = $entry['compressedByteLength'] ?? null;
            $partInventory[$partName]['zipCrc32'] = $entry['crc32'] ?? null;
            $partInventory[$partName]['zipByteExposurePolicy'] = $entry['byteExposurePolicy'] ?? null;
            $partInventory[$partName]['zipCanExposeBytes'] = $entry['canExposeBytes'] ?? null;
        }

        return $partInventory;
    }

    private static function zipCompressionMethodName(int $method): string
    {
        return match ($method) {
            0 => 'stored',
            8 => 'deflated',
            default => 'unsupported',
        };
    }

    /**
     * @param array<string, mixed> $contentTypesPart
     * @param array<string, array<string, mixed>> $relationshipParts
     * @param array<string, array<string, mixed>> $partInventory
     * @return array<string, mixed>
     */
    private function packageProvenanceSummary(array $contentTypesPart, array $relationshipParts, array $partInventory): array
    {
        $roleCounts = [];
        $contentTypeSourceCounts = [];
        $packageByteLength = 0;
        $relationshipPartCount = 0;
        foreach ($partInventory as $part) {
            $packageByteLength += (int) ($part['bytes'] ?? 0);
            $source = (string) ($part['contentTypeSource'] ?? 'missing');
            $contentTypeSourceCounts[$source] = ($contentTypeSourceCounts[$source] ?? 0) + 1;
            foreach (($part['roles'] ?? []) as $role) {
                $role = (string) $role;
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            }
            if (($part['isRelationshipPart'] ?? false) === true) {
                ++$relationshipPartCount;
            }
        }

        ksort($roleCounts);
        ksort($contentTypeSourceCounts);

        $partDirectories = $this->packagePartDirectorySummary($partInventory);
        $partExtensions = $this->packagePartExtensionSummary($partInventory);
        $largestParts = $this->largestPackagePartSummary($partInventory);
        $largestPart = $largestParts[0] ?? null;
        $relationshipCount = 0;
        $internalRelationshipCount = 0;
        $externalRelationshipCount = 0;
        $existingRelationshipTargetCount = 0;
        $missingRelationshipTargetCount = 0;
        $relationshipTypeCounts = [];
        $missingRelationshipTargets = [];
        $externalRelationshipTargets = [];
        $relationshipPartsWithMissingTargets = [];
        $relationshipPartsWithMissingContentTypes = [];
        $relationshipTargetsWithoutContentType = [];
        $relationshipPartMissingSourceCount = 0;
        $relationshipFromMissingSourceCount = 0;
        $relationshipPartsWithMissingSources = [];
        $relationshipsFromMissingSources = [];
        $relationshipTargetReferenceSuffixCount = 0;
        $relationshipTargetQueryCount = 0;
        $relationshipTargetFragmentCount = 0;
        $relationshipTargetParentTraversalSegmentCount = 0;
        $relationshipPartsWithTargetReferenceSuffix = [];
        $relationshipPartsWithParentTraversalTargets = [];
        $relationshipPartsWithSameSourceTargets = [];
        $relationshipTargetsWithReferenceSuffix = [];
        $relationshipTargetsWithParentTraversal = [];
        $relationshipsWithSameSourceTargets = [];
        $relationshipRecordCount = 0;
        $duplicateRelationshipIdCount = 0;
        $duplicateRelationshipRecordCount = 0;
        $invalidRelationshipRecordCount = 0;
        $relationshipRecordIssueCount = 0;
        $relationshipRecordTargetModeCounts = [];
        $relationshipRecordImplicitInternalTargetModeCount = 0;
        $relationshipRecordExplicitInternalTargetModeCount = 0;
        $relationshipRecordExplicitExternalTargetModeCount = 0;
        $relationshipRecordUnexpectedTargetModeCount = 0;
        $relationshipPartsWithDuplicateRelationshipIds = [];
        $relationshipPartsWithInvalidRecords = [];
        $relationshipPartsWithExplicitInternalTargetMode = [];
        $relationshipPartsWithUnexpectedTargetMode = [];
        $duplicateRelationshipIds = [];
        $duplicateRelationshipIdItems = [];
        $invalidRelationshipRecords = [];
        $relationshipsWithExplicitInternalTargetMode = [];
        $relationshipsWithUnexpectedTargetMode = [];
        $relationshipRecordIssueCodes = [];
        $targetParts = [];
        $partsWithoutContentType = [];

        foreach ($partInventory as $part) {
            if (($part['contentTypeSource'] ?? '') !== 'missing') {
                continue;
            }

            $partsWithoutContentType[] = [
                'partName' => (string) ($part['partName'] ?? ''),
                'bytes' => (int) ($part['bytes'] ?? 0),
                'crc32' => is_string($part['crc32'] ?? null) ? $part['crc32'] : null,
                'sha256' => is_string($part['sha256'] ?? null) ? $part['sha256'] : null,
                'defaultExtension' => $part['defaultExtension'] ?? null,
                'roles' => $part['roles'] ?? [],
                'isRelationshipPart' => (bool) ($part['isRelationshipPart'] ?? false),
            ];
        }

        foreach ($relationshipParts as $relationshipsPart => $relationshipPart) {
            if (($relationshipPart['sourceExists'] ?? true) === false) {
                ++$relationshipPartMissingSourceCount;
                $relationshipPartsWithMissingSources[] = (string) $relationshipsPart;
                $missingSourceSummary = $this->missingRelationshipSourceSummary((string) $relationshipsPart, $relationshipPart);
                $relationshipFromMissingSourceCount += (int) $missingSourceSummary['relationshipCount'];
                $relationshipsFromMissingSources[] = $missingSourceSummary;
            }

            foreach (($relationshipPart['relationships'] ?? []) as $relationship) {
                ++$relationshipCount;
                $type = (string) ($relationship['type'] ?? '');
                $typeKey = $type === '' ? '(missing-type)' : $type;
                $relationshipTypeCounts[$typeKey] = ($relationshipTypeCounts[$typeKey] ?? 0) + 1;

                $targetReferenceSuffix = is_string($relationship['targetReferenceSuffix'] ?? null)
                    ? $relationship['targetReferenceSuffix']
                    : '';
                if ($targetReferenceSuffix !== '') {
                    ++$relationshipTargetReferenceSuffixCount;
                    $relationshipPartsWithTargetReferenceSuffix[(string) $relationshipsPart] = true;
                    $relationshipTargetsWithReferenceSuffix[] = $this->relationshipProvenanceSummaryItem($relationship);
                }
                if (array_key_exists('targetQuery', $relationship) && $relationship['targetQuery'] !== null) {
                    ++$relationshipTargetQueryCount;
                }
                if (array_key_exists('targetFragment', $relationship) && $relationship['targetFragment'] !== null) {
                    ++$relationshipTargetFragmentCount;
                }

                if (($relationship['external'] ?? false) === true) {
                    ++$externalRelationshipCount;
                    $externalRelationshipTargets[] = $this->relationshipProvenanceSummaryItem($relationship);
                    continue;
                }

                ++$internalRelationshipCount;
                if (($relationship['targetHasParentTraversal'] ?? false) === true) {
                    $relationshipTargetsWithParentTraversal[] = $this->relationshipProvenanceSummaryItem($relationship);
                    $relationshipPartsWithParentTraversalTargets[(string) $relationshipsPart] = true;
                    $relationshipTargetParentTraversalSegmentCount += (int) ($relationship['targetParentTraversalCount'] ?? 0);
                }
                if (($relationship['sameSourcePart'] ?? false) === true) {
                    $relationshipsWithSameSourceTargets[] = $this->relationshipProvenanceSummaryItem($relationship);
                    $relationshipPartsWithSameSourceTargets[(string) $relationshipsPart] = true;
                }
                $targetPart = $relationship['targetPart'] ?? null;
                if (is_string($targetPart) && $targetPart !== '') {
                    $targetParts[$targetPart] = true;
                }

                if (($relationship['exists'] ?? false) === true) {
                    ++$existingRelationshipTargetCount;
                } else {
                    ++$missingRelationshipTargetCount;
                    $relationshipPartsWithMissingTargets[(string) $relationshipsPart] = true;
                    $missingRelationshipTargets[] = $this->relationshipProvenanceSummaryItem($relationship);
                }

                if (($relationship['contentTypeSource'] ?? '') === 'missing') {
                    $relationshipPartsWithMissingContentTypes[(string) $relationshipsPart] = true;
                    $relationshipTargetsWithoutContentType[] = $this->relationshipProvenanceSummaryItem($relationship);
                }
            }

            $relationshipRecordCount += (int) ($relationshipPart['relationshipRecordCount'] ?? 0);
            $duplicateRelationshipIdCount += (int) ($relationshipPart['duplicateRelationshipIdCount'] ?? 0);
            $duplicateRelationshipRecordCount += (int) ($relationshipPart['duplicateRelationshipRecordCount'] ?? 0);
            $invalidRelationshipRecordCount += (int) ($relationshipPart['invalidRelationshipRecordCount'] ?? 0);
            $relationshipRecordIssueCount += (int) ($relationshipPart['relationshipRecordIssueCount'] ?? 0);
            foreach (($relationshipPart['relationshipRecords'] ?? []) as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $targetMode = is_string($record['targetMode'] ?? null) ? $record['targetMode'] : '';
                $targetModeKey = $targetMode === '' ? '(implicit-internal)' : $targetMode;
                $relationshipRecordTargetModeCounts[$targetModeKey] = ($relationshipRecordTargetModeCounts[$targetModeKey] ?? 0) + 1;

                if ($targetMode === '') {
                    ++$relationshipRecordImplicitInternalTargetModeCount;
                    continue;
                }

                if ($targetMode === 'Internal') {
                    ++$relationshipRecordExplicitInternalTargetModeCount;
                    $relationshipPartsWithExplicitInternalTargetMode[(string) $relationshipsPart] = true;
                    $relationshipsWithExplicitInternalTargetMode[] = $this->relationshipProvenanceSummaryItem($record) + [
                        'ordinal' => $record['ordinal'] ?? null,
                        'valid' => (bool) ($record['valid'] ?? false),
                        'issues' => $record['issues'] ?? [],
                    ];
                    continue;
                }

                if ($targetMode === 'External') {
                    ++$relationshipRecordExplicitExternalTargetModeCount;
                    continue;
                }

                ++$relationshipRecordUnexpectedTargetModeCount;
                $relationshipPartsWithUnexpectedTargetMode[(string) $relationshipsPart] = true;
                $relationshipsWithUnexpectedTargetMode[] = $this->relationshipProvenanceSummaryItem($record) + [
                    'ordinal' => $record['ordinal'] ?? null,
                    'valid' => (bool) ($record['valid'] ?? false),
                    'issues' => $record['issues'] ?? [],
                ];
            }
            if (($relationshipPart['duplicateRelationshipIdCount'] ?? 0) > 0) {
                $relationshipPartsWithDuplicateRelationshipIds[] = (string) $relationshipsPart;
            }
            if (($relationshipPart['invalidRelationshipRecordCount'] ?? 0) > 0) {
                $relationshipPartsWithInvalidRecords[] = (string) $relationshipsPart;
            }
            foreach (($relationshipPart['duplicateRelationshipIds'] ?? []) as $id) {
                $this->appendUniqueString($duplicateRelationshipIds, is_string($id) ? $id : null);
            }
            foreach (($relationshipPart['duplicateRelationshipIdItems'] ?? []) as $item) {
                if (is_array($item)) {
                    $duplicateRelationshipIdItems[] = $item;
                }
            }
            foreach (($relationshipPart['relationshipRecordIssueCodes'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $relationshipRecordIssueCodes[$issue] = true;
                }
            }
            foreach (($relationshipPart['invalidRelationshipRecords'] ?? []) as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $invalidRelationshipRecords[] = $this->relationshipProvenanceSummaryItem($record) + [
                    'ordinal' => $record['ordinal'] ?? null,
                    'valid' => (bool) ($record['valid'] ?? false),
                    'issues' => $record['issues'] ?? [],
                ];
            }
        }

        ksort($relationshipTypeCounts);
        ksort($relationshipRecordTargetModeCounts);
        ksort($relationshipRecordIssueCodes);

        return [
            'partCount' => count($partInventory),
            'partDirectoryCount' => count($partDirectories),
            'partExtensionCount' => count($partExtensions),
            'packageByteLength' => $packageByteLength,
            'largestPartCount' => count($largestParts),
            'largestPartName' => $largestPart['partName'] ?? null,
            'largestPartBytes' => $largestPart['bytes'] ?? 0,
            'relationshipPartCount' => $relationshipPartCount,
            'relationshipCount' => $relationshipCount,
            'relationshipRecordCount' => $relationshipRecordCount,
            'internalRelationshipCount' => $internalRelationshipCount,
            'externalRelationshipCount' => $externalRelationshipCount,
            'existingRelationshipTargetCount' => $existingRelationshipTargetCount,
            'missingRelationshipTargetCount' => $missingRelationshipTargetCount,
            'uniqueRelationshipTargetPartCount' => count($targetParts),
            'duplicateRelationshipIdCount' => $duplicateRelationshipIdCount,
            'duplicateRelationshipRecordCount' => $duplicateRelationshipRecordCount,
            'duplicateRelationshipIds' => $duplicateRelationshipIds,
            'invalidRelationshipRecordCount' => $invalidRelationshipRecordCount,
            'relationshipRecordIssueCount' => $relationshipRecordIssueCount,
            'relationshipRecordIssueCodes' => array_keys($relationshipRecordIssueCodes),
            'relationshipRecordTargetModeCounts' => $relationshipRecordTargetModeCounts,
            'relationshipRecordImplicitInternalTargetModeCount' => $relationshipRecordImplicitInternalTargetModeCount,
            'relationshipRecordExplicitInternalTargetModeCount' => $relationshipRecordExplicitInternalTargetModeCount,
            'relationshipRecordExplicitExternalTargetModeCount' => $relationshipRecordExplicitExternalTargetModeCount,
            'relationshipRecordUnexpectedTargetModeCount' => $relationshipRecordUnexpectedTargetModeCount,
            'missingContentTypePartCount' => count($partsWithoutContentType),
            'relationshipTargetMissingContentTypeCount' => count($relationshipTargetsWithoutContentType),
            'relationshipPartMissingSourceCount' => $relationshipPartMissingSourceCount,
            'relationshipFromMissingSourceCount' => $relationshipFromMissingSourceCount,
            'relationshipTargetReferenceSuffixCount' => $relationshipTargetReferenceSuffixCount,
            'relationshipTargetQueryCount' => $relationshipTargetQueryCount,
            'relationshipTargetFragmentCount' => $relationshipTargetFragmentCount,
            'relationshipTargetParentTraversalCount' => count($relationshipTargetsWithParentTraversal),
            'relationshipTargetParentTraversalSegmentCount' => $relationshipTargetParentTraversalSegmentCount,
            'sameSourceRelationshipCount' => count($relationshipsWithSameSourceTargets),
            'contentTypeDefaultCount' => (int) ($contentTypesPart['defaultCount'] ?? 0),
            'contentTypeOverrideCount' => (int) ($contentTypesPart['overrideCount'] ?? 0),
            'contentTypeRecordCount' => (int) ($contentTypesPart['recordCount'] ?? 0),
            'contentTypeInvalidRecordCount' => (int) ($contentTypesPart['invalidRecordCount'] ?? 0),
            'contentTypeDeclaredDefaultRecordCount' => (int) ($contentTypesPart['declaredDefaultRecordCount'] ?? 0),
            'contentTypeDeclaredOverrideRecordCount' => (int) ($contentTypesPart['declaredOverrideRecordCount'] ?? 0),
            'contentTypeRecordIssueCounts' => $contentTypesPart['issueCounts'] ?? [],
            'contentTypeRecordIssueCodes' => $contentTypesPart['issues'] ?? [],
            'contentTypeDuplicateDefaultExtensionCount' => (int) ($contentTypesPart['duplicateDefaultExtensionCount'] ?? 0),
            'contentTypeDuplicateOverridePartNameCount' => (int) ($contentTypesPart['duplicateOverridePartNameCount'] ?? 0),
            'contentTypeDuplicateDefaultExtensions' => $contentTypesPart['duplicateDefaultExtensions'] ?? [],
            'contentTypeDuplicateOverridePartNames' => $contentTypesPart['duplicateOverridePartNames'] ?? [],
            'contentTypeDuplicateDefaultExtensionGroups' => $contentTypesPart['duplicateDefaultExtensionGroups'] ?? [],
            'contentTypeDuplicateOverridePartNameGroups' => $contentTypesPart['duplicateOverridePartNameGroups'] ?? [],
            'invalidContentTypeRecordIssueCounts' => $contentTypesPart['invalidContentTypeRecordIssueCounts'] ?? [],
            'invalidContentTypeRecordIssueCodes' => $contentTypesPart['invalidContentTypeRecordIssueCodes'] ?? [],
            'invalidContentTypeRecordIssueBuckets' => $contentTypesPart['invalidContentTypeRecordIssueBuckets'] ?? [],
            'invalidContentTypeRecords' => $contentTypesPart['invalidContentTypeRecords'] ?? [],
            'contentTypeOverrideDeclarationCount' => (int) ($contentTypesPart['overrideDeclarationCount'] ?? 0),
            'contentTypeUsedOverrideDeclarationCount' => (int) ($contentTypesPart['usedOverrideDeclarationCount'] ?? 0),
            'contentTypeUnusedOverrideDeclarationCount' => (int) ($contentTypesPart['unusedOverrideDeclarationCount'] ?? 0),
            'contentTypeInvalidOverrideDeclarationCount' => (int) ($contentTypesPart['invalidOverrideDeclarationCount'] ?? 0),
            'contentTypeUnusedOverridePartNames' => $contentTypesPart['unusedOverridePartNames'] ?? [],
            'contentTypeOverrideDeclarationIssueCounts' => $contentTypesPart['overrideDeclarationIssueCounts'] ?? [],
            'contentTypeOverrideDeclarationIssues' => $contentTypesPart['overrideDeclarationIssues'] ?? [],
            'contentTypeSourceCounts' => $contentTypeSourceCounts,
            'roleCounts' => $roleCounts,
            'relationshipTypeCounts' => $relationshipTypeCounts,
            'partDirectories' => $partDirectories,
            'partExtensions' => $partExtensions,
            'largestPart' => $largestPart,
            'largestParts' => $largestParts,
            'relationshipPartsWithMissingTargets' => array_keys($relationshipPartsWithMissingTargets),
            'relationshipPartsWithMissingContentTypes' => array_keys($relationshipPartsWithMissingContentTypes),
            'relationshipPartsWithMissingSources' => $relationshipPartsWithMissingSources,
            'relationshipPartsWithTargetReferenceSuffix' => array_keys($relationshipPartsWithTargetReferenceSuffix),
            'relationshipPartsWithParentTraversalTargets' => array_keys($relationshipPartsWithParentTraversalTargets),
            'relationshipPartsWithSameSourceTargets' => array_keys($relationshipPartsWithSameSourceTargets),
            'relationshipPartsWithDuplicateRelationshipIds' => $relationshipPartsWithDuplicateRelationshipIds,
            'relationshipPartsWithInvalidRecords' => $relationshipPartsWithInvalidRecords,
            'relationshipPartsWithExplicitInternalTargetMode' => array_keys($relationshipPartsWithExplicitInternalTargetMode),
            'relationshipPartsWithUnexpectedTargetMode' => array_keys($relationshipPartsWithUnexpectedTargetMode),
            'partsWithoutContentType' => $partsWithoutContentType,
            'missingRelationshipTargets' => $missingRelationshipTargets,
            'relationshipTargetsWithoutContentType' => $relationshipTargetsWithoutContentType,
            'relationshipsFromMissingSources' => $relationshipsFromMissingSources,
            'relationshipTargetsWithReferenceSuffix' => $relationshipTargetsWithReferenceSuffix,
            'relationshipTargetsWithParentTraversal' => $relationshipTargetsWithParentTraversal,
            'relationshipsWithSameSourceTargets' => $relationshipsWithSameSourceTargets,
            'externalRelationshipTargets' => $externalRelationshipTargets,
            'duplicateRelationshipIdItems' => $duplicateRelationshipIdItems,
            'invalidRelationshipRecords' => $invalidRelationshipRecords,
            'relationshipsWithExplicitInternalTargetMode' => $relationshipsWithExplicitInternalTargetMode,
            'relationshipsWithUnexpectedTargetMode' => $relationshipsWithUnexpectedTargetMode,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $partInventory
     * @return list<array<string, mixed>>
     */
    private function largestPackagePartSummary(array $partInventory, int $limit = 5): array
    {
        $items = [];
        foreach ($partInventory as $partName => $part) {
            $items[] = [
                'partName' => (string) ($part['partName'] ?? $partName),
                'directory' => is_string($part['directory'] ?? null) ? $part['directory'] : $this->packagePartDirectory((string) $partName),
                'baseName' => is_string($part['baseName'] ?? null) ? $part['baseName'] : $this->packagePartBaseName((string) $partName),
                'partExtension' => is_string($part['partExtension'] ?? null) ? $part['partExtension'] : null,
                'bytes' => (int) ($part['bytes'] ?? 0),
                'crc32' => is_string($part['crc32'] ?? null) ? $part['crc32'] : null,
                'sha256' => is_string($part['sha256'] ?? null) ? $part['sha256'] : null,
                'contentType' => is_string($part['contentType'] ?? null) ? $part['contentType'] : '',
                'contentTypeBase' => is_string($part['contentTypeBase'] ?? null) ? $part['contentTypeBase'] : '',
                'contentTypeSource' => is_string($part['contentTypeSource'] ?? null) ? $part['contentTypeSource'] : 'missing',
                'defaultExtension' => is_string($part['defaultExtension'] ?? null) ? $part['defaultExtension'] : null,
                'overridePartName' => is_string($part['overridePartName'] ?? null) ? $part['overridePartName'] : null,
                'isRelationshipPart' => (bool) ($part['isRelationshipPart'] ?? false),
                'roles' => array_values(array_map('strval', $part['roles'] ?? [])),
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => ($right['bytes'] <=> $left['bytes'])
                ?: strcmp((string) $left['partName'], (string) $right['partName']),
        );

        return array_slice($items, 0, max(0, $limit));
    }

    /**
     * @param array<string, array<string, mixed>> $partInventory
     * @return list<array<string, mixed>>
     */
    private function packagePartDirectorySummary(array $partInventory): array
    {
        $directories = [];
        foreach ($partInventory as $partName => $part) {
            $directory = is_string($part['directory'] ?? null)
                ? $part['directory']
                : $this->packagePartDirectory((string) $partName);
            if (!isset($directories[$directory])) {
                $directories[$directory] = [
                    'directory' => $directory,
                    'partCount' => 0,
                    'byteLength' => 0,
                    'relationshipPartCount' => 0,
                    'missingContentTypePartCount' => 0,
                    'contentTypeSourceCounts' => [],
                    'roleCounts' => [],
                    'partNames' => [],
                ];
            }

            ++$directories[$directory]['partCount'];
            $directories[$directory]['byteLength'] += (int) ($part['bytes'] ?? 0);
            $directories[$directory]['partNames'][] = (string) $partName;
            if (($part['isRelationshipPart'] ?? false) === true) {
                ++$directories[$directory]['relationshipPartCount'];
            }

            $contentTypeSource = (string) ($part['contentTypeSource'] ?? 'missing');
            if ($contentTypeSource === 'missing') {
                ++$directories[$directory]['missingContentTypePartCount'];
            }
            $directories[$directory]['contentTypeSourceCounts'][$contentTypeSource] =
                ($directories[$directory]['contentTypeSourceCounts'][$contentTypeSource] ?? 0) + 1;

            foreach (($part['roles'] ?? []) as $role) {
                $role = (string) $role;
                $directories[$directory]['roleCounts'][$role] =
                    ($directories[$directory]['roleCounts'][$role] ?? 0) + 1;
            }
        }

        ksort($directories, SORT_STRING);
        foreach ($directories as $directory => $summary) {
            ksort($summary['contentTypeSourceCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $directories[$directory] = $summary;
        }

        return array_values($directories);
    }

    /**
     * @param array<string, array<string, mixed>> $partInventory
     * @return list<array<string, mixed>>
     */
    private function packagePartExtensionSummary(array $partInventory): array
    {
        $extensions = [];
        foreach ($partInventory as $partName => $part) {
            $extension = is_string($part['partExtension'] ?? null) ? $part['partExtension'] : null;
            $key = $extension ?? '';
            if (!isset($extensions[$key])) {
                $extensions[$key] = [
                    'extension' => $extension,
                    'defaultDeclared' => (bool) ($part['partExtensionDefaultDeclared'] ?? false),
                    'defaultContentType' => $part['partExtensionDefaultContentType'] ?? null,
                    'partCount' => 0,
                    'byteLength' => 0,
                    'relationshipPartCount' => 0,
                    'missingContentTypePartCount' => 0,
                    'contentTypeSourceCounts' => [],
                    'roleCounts' => [],
                    'partNames' => [],
                ];
            }

            ++$extensions[$key]['partCount'];
            $extensions[$key]['byteLength'] += (int) ($part['bytes'] ?? 0);
            $extensions[$key]['partNames'][] = (string) $partName;
            if (($part['isRelationshipPart'] ?? false) === true) {
                ++$extensions[$key]['relationshipPartCount'];
            }

            $contentTypeSource = (string) ($part['contentTypeSource'] ?? 'missing');
            if ($contentTypeSource === 'missing') {
                ++$extensions[$key]['missingContentTypePartCount'];
            }
            $extensions[$key]['contentTypeSourceCounts'][$contentTypeSource] =
                ($extensions[$key]['contentTypeSourceCounts'][$contentTypeSource] ?? 0) + 1;

            foreach (($part['roles'] ?? []) as $role) {
                $role = (string) $role;
                $extensions[$key]['roleCounts'][$role] =
                    ($extensions[$key]['roleCounts'][$role] ?? 0) + 1;
            }
        }

        ksort($extensions, SORT_STRING);
        foreach ($extensions as $extension => $summary) {
            ksort($summary['contentTypeSourceCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $extensions[$extension] = $summary;
        }

        return array_values($extensions);
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function packageThumbnailProvenance(array $parts, array $rootRelationships, array $contentTypes): array
    {
        $thumbnailRelationships = [];
        foreach ($rootRelationships as $relationship) {
            if ($relationship['type'] === self::THUMBNAIL_REL) {
                $thumbnailRelationships[] = $relationship;
            }
        }

        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $targetParts = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];
        $rootHasMultipleThumbnails = count($thumbnailRelationships) > 1;
        foreach ($thumbnailRelationships as $relationship) {
            $summary = $this->relationshipInventorySummary($parts, $relationship, '/', '_rels/.rels', $contentTypes);
            $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
            $external = (bool) ($summary['external'] ?? false);
            $exists = (bool) ($summary['exists'] ?? false);
            $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
            $relationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
            $targetHasRelationships = $relationshipsPart !== null && isset($parts[$relationshipsPart]);
            $issues = [];

            if ($rootHasMultipleThumbnails) {
                $issues[] = 'multiple-thumbnail-relationships-for-source';
            }
            if ($external) {
                $issues[] = 'external-thumbnail-target';
            } elseif (!$exists) {
                $issues[] = 'missing-in-package';
            }
            if (!$external && ($summary['contentTypeSource'] ?? '') === 'missing') {
                $issues[] = 'missing-content-type';
            }
            if ($contentTypeBase !== '' && !$this->isImageContentType($contentTypeBase)) {
                $issues[] = 'invalid-thumbnail-content-type';
            }
            if ($targetHasRelationships) {
                $issues[] = 'thumbnail-target-has-relationships';
            }

            $issues = array_values(array_unique($issues));
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $item = [
                'source' => '/',
                'id' => $summary['id'],
                'type' => $summary['type'],
                'target' => $summary['target'],
                'targetMode' => $summary['targetMode'],
                'resolvedTarget' => $summary['resolvedTarget'],
                'targetPart' => $targetPart,
                'targetQuery' => $summary['targetQuery'],
                'targetFragment' => $summary['targetFragment'],
                'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
                'contentType' => $summary['contentType'],
                'contentTypeBase' => $summary['contentTypeBase'],
                'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
                'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
                'contentTypeParameters' => $summary['contentTypeParameters'],
                'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
                'contentTypeSource' => $summary['contentTypeSource'],
                'defaultExtension' => $summary['defaultExtension'],
                'overridePartName' => $summary['overridePartName'],
                'expectedContentTypePrefix' => 'image/',
                'external' => $external,
                'exists' => $exists,
                'relationshipsPart' => '_rels/.rels',
                'targetRelationshipsPart' => $relationshipsPart,
                'targetHasRelationships' => $targetHasRelationships,
                'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
                'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
                'storedByteLength' => null,
                'storedCrc32' => null,
                'canExposeAsDocumentMedia' => false,
                'reviewPolicy' => 'package-thumbnail-metadata-only',
                'valid' => $issues === [],
                'issues' => $issues,
            ];

            $items[] = $item;
            $byRelationshipId[(string) $item['id']] = $item;
            $relationshipIds[] = (string) $item['id'];
            $this->appendUniqueString($targetParts, $targetPart);
            if ($external) {
                $this->appendUniqueString($externalTargets, is_string($summary['target'] ?? null) ? $summary['target'] : null);
            }
            $this->appendUniqueString($contentTypesSeen, is_string($summary['contentType'] ?? null) ? $summary['contentType'] : null);
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'missingCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === false,
            )),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'invalidCount' => count(array_filter($items, static fn (array $item): bool => $item['valid'] !== true)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'targetParts' => $targetParts,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    private function isImageContentType(string $contentType): bool
    {
        return str_starts_with(strtolower(trim(explode(';', $contentType, 2)[0])), 'image/');
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function packageDigitalSignatureProvenance(array $parts, array $rootRelationships, array $contentTypes): array
    {
        $originRelationships = [];
        foreach ($rootRelationships as $relationship) {
            if ($relationship['type'] === self::DIGITAL_SIGNATURE_ORIGIN_REL) {
                $originRelationships[] = $relationship;
            }
        }

        $origins = [];
        $signatures = [];
        $byOriginRelationshipId = [];
        $bySignatureRelationshipId = [];
        $originRelationshipIds = [];
        $signatureRelationshipIds = [];
        $originParts = [];
        $signatureParts = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];

        foreach ($originRelationships as $relationship) {
            $origin = $this->digitalSignatureOriginItem($parts, $relationship, $contentTypes, count($origins));
            $origins[] = $origin;
            $byOriginRelationshipId[(string) $origin['id']] = $origin;
            $originRelationshipIds[] = (string) $origin['id'];
            $this->appendUniqueString($originParts, is_string($origin['targetPart'] ?? null) ? $origin['targetPart'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($origin['contentType'] ?? null) ? $origin['contentType'] : null);
            if (($origin['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($origin['target'] ?? null) ? $origin['target'] : null);
            }
            foreach (($origin['issues'] ?? []) as $issue) {
                $issueCodes[(string) $issue] = true;
            }

            foreach (($origin['signatures']['items'] ?? []) as $signature) {
                if (!is_array($signature)) {
                    continue;
                }
                $signatures[] = $signature;
                $bySignatureRelationshipId[(string) $signature['id']] = $signature;
                $signatureRelationshipIds[] = (string) $signature['id'];
                $this->appendUniqueString($signatureParts, is_string($signature['targetPart'] ?? null) ? $signature['targetPart'] : null);
                $this->appendUniqueString($contentTypesSeen, is_string($signature['contentType'] ?? null) ? $signature['contentType'] : null);
                if (($signature['external'] ?? false) === true) {
                    $this->appendUniqueString($externalTargets, is_string($signature['target'] ?? null) ? $signature['target'] : null);
                }
                foreach (($signature['issues'] ?? []) as $issue) {
                    $issueCodes[(string) $issue] = true;
                }
            }
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'present' => $origins !== [],
            'originCount' => count($origins),
            'existingOriginCount' => count(array_filter(
                $origins,
                static fn (array $origin): bool => $origin['external'] === false && $origin['exists'] === true,
            )),
            'missingOriginCount' => count(array_filter(
                $origins,
                static fn (array $origin): bool => $origin['external'] === false && $origin['exists'] === false,
            )),
            'externalOriginCount' => count(array_filter($origins, static fn (array $origin): bool => $origin['external'] === true)),
            'signatureCount' => count($signatures),
            'existingSignatureCount' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => $signature['external'] === false && $signature['exists'] === true,
            )),
            'missingSignatureCount' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => $signature['external'] === false && $signature['exists'] === false,
            )),
            'externalSignatureCount' => count(array_filter($signatures, static fn (array $signature): bool => $signature['external'] === true)),
            'invalidSignatureXmlCount' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => in_array('invalid-signature-xml', $signature['issues'], true),
            )),
            'unexpectedSignatureRootCount' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => in_array('unexpected-signature-root', $signature['issues'], true),
            )),
            'issueCount' => count(array_filter(
                array_merge($origins, $signatures),
                static fn (array $item): bool => $item['issues'] !== [],
            )),
            'originRelationshipIds' => $originRelationshipIds,
            'signatureRelationshipIds' => $signatureRelationshipIds,
            'originParts' => $originParts,
            'signatureParts' => $signatureParts,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byOriginRelationshipId' => $byOriginRelationshipId,
            'bySignatureRelationshipId' => $bySignatureRelationshipId,
            'origins' => $origins,
            'signatures' => $signatures,
            'cryptographicValidation' => false,
            'reviewPolicy' => 'digital-signature-metadata-only',
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function digitalSignatureOriginItem(array $parts, array $relationship, array $contentTypes, int $index): array
    {
        $summary = $this->relationshipInventorySummary($parts, $relationship, '/', '_rels/.rels', $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $relationshipsPart = $targetPart === null ? null : $this->relationshipsPartFor($targetPart);
        $relationships = $relationshipsPart === null ? [] : $this->readRelationshipsPart($parts, $relationshipsPart);
        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        $issues = [];

        if ($external) {
            $issues[] = 'external-signature-origin';
        } elseif (!$exists) {
            $issues[] = 'missing-origin-part';
        }
        if (!$external && ($summary['contentTypeSource'] ?? '') === 'missing') {
            $issues[] = 'missing-origin-content-type';
        } elseif (!$external && $contentTypeBase !== '' && $contentTypeBase !== self::CT_DIGITAL_SIGNATURE_ORIGIN) {
            $issues[] = 'unexpected-origin-content-type';
        }
        if (!$external && $exists && $relationshipsPart !== null && !isset($parts[$relationshipsPart])) {
            $issues[] = 'missing-origin-relationships';
        }

        $signatures = $this->digitalSignatureParts(
            $parts,
            $targetPart,
            $relationshipsPart,
            $relationships,
            $contentTypes,
        );

        return [
            'index' => $index,
            'source' => '/',
            'id' => $summary['id'],
            'type' => $summary['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'expectedContentTypeBase' => self::CT_DIGITAL_SIGNATURE_ORIGIN,
            'external' => $external,
            'exists' => $exists,
            'relationshipsPart' => '_rels/.rels',
            'originRelationshipsPart' => $relationshipsPart,
            'originRelationshipCount' => count($relationships),
            'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
            'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
            'signatures' => $signatures,
            'signatureCount' => $signatures['count'],
            'signatureIssueCount' => $signatures['issueCount'],
            'cryptographicValidation' => false,
            'reviewPolicy' => 'digital-signature-metadata-only',
            'valid' => $issues === [],
            'issues' => $issues,
            'relationship' => $summary,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function digitalSignatureParts(
        array $parts,
        ?string $sourcePart,
        ?string $relationshipsPart,
        array $relationships,
        array $contentTypes,
    ): array {
        if ($sourcePart === null || $relationshipsPart === null) {
            return [
                'count' => 0,
                'existingCount' => 0,
                'missingCount' => 0,
                'externalCount' => 0,
                'invalidXmlCount' => 0,
                'unexpectedRootCount' => 0,
                'issueCount' => 0,
                'relationshipIds' => [],
                'partNames' => [],
                'externalTargets' => [],
                'contentTypes' => [],
                'issueCodes' => [],
                'byRelationshipId' => [],
                'items' => [],
            ];
        }

        $items = [];
        $byRelationshipId = [];
        $relationshipIds = [];
        $partNames = [];
        $externalTargets = [];
        $contentTypesSeen = [];
        $issueCodes = [];

        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::DIGITAL_SIGNATURE_SIGNATURE_REL) {
                continue;
            }

            $item = $this->digitalSignaturePartItem(
                $parts,
                $relationship,
                $sourcePart,
                $relationshipsPart,
                $contentTypes,
                count($items),
            );
            $items[] = $item;
            $byRelationshipId[(string) $item['id']] = $item;
            $relationshipIds[] = (string) $item['id'];
            $this->appendUniqueString($partNames, is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null);
            $this->appendUniqueString($contentTypesSeen, is_string($item['contentType'] ?? null) ? $item['contentType'] : null);
            if (($item['external'] ?? false) === true) {
                $this->appendUniqueString($externalTargets, is_string($item['target'] ?? null) ? $item['target'] : null);
            }
            foreach (($item['issues'] ?? []) as $issue) {
                $issueCodes[(string) $issue] = true;
            }
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'existingCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === true,
            )),
            'missingCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === false,
            )),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'invalidXmlCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('invalid-signature-xml', $item['issues'], true),
            )),
            'unexpectedRootCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('unexpected-signature-root', $item['issues'], true),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'relationshipIds' => $relationshipIds,
            'partNames' => $partNames,
            'externalTargets' => $externalTargets,
            'contentTypes' => $contentTypesSeen,
            'issueCodes' => array_keys($issueCodes),
            'byRelationshipId' => $byRelationshipId,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function digitalSignaturePartItem(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
        int $index,
    ): array {
        $summary = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';
        $issues = [];

        if ($external) {
            $issues[] = 'external-signature-target';
        } elseif (!$exists) {
            $issues[] = 'missing-signature-part';
        }
        if (!$external && ($summary['contentTypeSource'] ?? '') === 'missing') {
            $issues[] = 'missing-signature-content-type';
        } elseif (!$external && $contentTypeBase !== '' && $contentTypeBase !== self::CT_DIGITAL_SIGNATURE_XMLSIGNATURE) {
            $issues[] = 'unexpected-signature-content-type';
        }

        $metadata = $this->emptyDigitalSignatureXmlMetadata();
        if ($exists && $targetPart !== null) {
            $metadata = $this->digitalSignatureXmlMetadata($parts[$targetPart], $targetPart);
            if ($metadata['validXml'] === false) {
                $issues[] = 'invalid-signature-xml';
            } elseif ($metadata['validRoot'] === false) {
                $issues[] = 'unexpected-signature-root';
            }
        }

        return [
            'index' => $index,
            'source' => $sourcePart,
            'id' => $summary['id'],
            'type' => $summary['type'],
            'target' => $summary['target'],
            'targetMode' => $summary['targetMode'],
            'resolvedTarget' => $summary['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $summary['targetQuery'],
            'targetFragment' => $summary['targetFragment'],
            'targetReferenceSuffix' => $summary['targetReferenceSuffix'],
            'contentType' => $summary['contentType'],
            'contentTypeBase' => $summary['contentTypeBase'],
            'contentTypeHasParameters' => $summary['contentTypeHasParameters'],
            'contentTypeParameterCount' => $summary['contentTypeParameterCount'],
            'contentTypeParameters' => $summary['contentTypeParameters'],
            'contentTypeParameterMap' => $summary['contentTypeParameterMap'],
            'contentTypeSource' => $summary['contentTypeSource'],
            'defaultExtension' => $summary['defaultExtension'],
            'overridePartName' => $summary['overridePartName'],
            'expectedContentTypeBase' => self::CT_DIGITAL_SIGNATURE_XMLSIGNATURE,
            'external' => $external,
            'exists' => $exists,
            'relationshipsPart' => $relationshipsPart,
            'byteLength' => $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null,
            'crc32' => $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null,
            'validXml' => $metadata['validXml'],
            'xmlParseError' => $metadata['xmlParseError'],
            'rootNamespace' => $metadata['rootNamespace'],
            'rootLocalName' => $metadata['rootLocalName'],
            'validRoot' => $metadata['validRoot'],
            'referenceCount' => $metadata['referenceCount'],
            'referenceUris' => $metadata['referenceUris'],
            'references' => $metadata['references'],
            'referenceUriKindCounts' => $metadata['referenceUriKindCounts'],
            'packageReferenceCount' => $metadata['packageReferenceCount'],
            'sameDocumentReferenceCount' => $metadata['sameDocumentReferenceCount'],
            'externalReferenceCount' => $metadata['externalReferenceCount'],
            'relativeReferenceCount' => $metadata['relativeReferenceCount'],
            'emptyReferenceCount' => $metadata['emptyReferenceCount'],
            'referenceTransformCount' => $metadata['referenceTransformCount'],
            'referenceTransformAlgorithms' => $metadata['referenceTransformAlgorithms'],
            'referenceDigestValueCount' => $metadata['referenceDigestValueCount'],
            'referenceDigestValueMissingCount' => $metadata['referenceDigestValueMissingCount'],
            'digestMethodAlgorithms' => $metadata['digestMethodAlgorithms'],
            'signatureMethodAlgorithms' => $metadata['signatureMethodAlgorithms'],
            'canonicalizationMethodAlgorithms' => $metadata['canonicalizationMethodAlgorithms'],
            'hasSignatureValue' => $metadata['hasSignatureValue'],
            'cryptographicValidation' => false,
            'reviewPolicy' => 'digital-signature-metadata-only',
            'valid' => $issues === [],
            'issues' => $issues,
            'relationship' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDigitalSignatureXmlMetadata(): array
    {
        return [
            'validXml' => null,
            'xmlParseError' => null,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'referenceCount' => 0,
            'referenceUris' => [],
            'references' => [],
            'referenceUriKindCounts' => [],
            'packageReferenceCount' => 0,
            'sameDocumentReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'relativeReferenceCount' => 0,
            'emptyReferenceCount' => 0,
            'referenceTransformCount' => 0,
            'referenceTransformAlgorithms' => [],
            'referenceDigestValueCount' => 0,
            'referenceDigestValueMissingCount' => 0,
            'digestMethodAlgorithms' => [],
            'signatureMethodAlgorithms' => [],
            'canonicalizationMethodAlgorithms' => [],
            'hasSignatureValue' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function digitalSignatureXmlMetadata(string $xml, string $partName): array
    {
        $metadata = $this->emptyDigitalSignatureXmlMetadata();
        $dom = $this->loadXmlForProvenance($xml, $partName);
        if (!$dom instanceof \DOMDocument) {
            $metadata['validXml'] = false;
            $metadata['xmlParseError'] = $this->lastXmlPreflightError($xml, $partName);

            return $metadata;
        }

        $root = $dom->documentElement;
        $metadata['validXml'] = $root instanceof \DOMElement;
        $metadata['xmlParseError'] = null;
        $metadata['rootNamespace'] = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $metadata['rootLocalName'] = $root instanceof \DOMElement ? $root->localName : null;
        $metadata['validRoot'] = $root instanceof \DOMElement
            && $root->namespaceURI === self::NS_XMLDSIG
            && $root->localName === 'Signature';

        if ($metadata['validRoot'] !== true || !$root instanceof \DOMElement) {
            return $metadata;
        }

        $metadata['referenceCount'] = 0;
        foreach ($root->getElementsByTagNameNS(self::NS_XMLDSIG, 'Reference') as $reference) {
            if (!$reference instanceof \DOMElement) {
                continue;
            }

            $item = $this->digitalSignatureReferenceMetadata($reference, $metadata['referenceCount']);
            ++$metadata['referenceCount'];
            $metadata['references'][] = $item;
            $metadata['referenceUriKindCounts'][$item['uriKind']] =
                ($metadata['referenceUriKindCounts'][$item['uriKind']] ?? 0) + 1;
            $this->appendUniqueString($metadata['referenceUris'], $item['uri']);
            if ($item['uriKind'] === 'package-part') {
                ++$metadata['packageReferenceCount'];
            } elseif ($item['uriKind'] === 'same-document') {
                ++$metadata['sameDocumentReferenceCount'];
            } elseif ($item['uriKind'] === 'external') {
                ++$metadata['externalReferenceCount'];
            } elseif ($item['uriKind'] === 'relative') {
                ++$metadata['relativeReferenceCount'];
            } elseif ($item['uriKind'] === 'empty') {
                ++$metadata['emptyReferenceCount'];
            }
            $metadata['referenceTransformCount'] += $item['transformCount'];
            foreach ($item['transformAlgorithms'] as $algorithm) {
                $this->appendUniqueString($metadata['referenceTransformAlgorithms'], $algorithm);
            }
            if ($item['digestMethodAlgorithm'] !== null) {
                $this->appendUniqueString($metadata['digestMethodAlgorithms'], $item['digestMethodAlgorithm']);
            }
            if ($item['digestValuePresent']) {
                ++$metadata['referenceDigestValueCount'];
            } else {
                ++$metadata['referenceDigestValueMissingCount'];
            }
        }
        ksort($metadata['referenceUriKindCounts'], SORT_STRING);

        foreach ($root->getElementsByTagNameNS(self::NS_XMLDSIG, 'SignatureMethod') as $signatureMethod) {
            if ($signatureMethod instanceof \DOMElement) {
                $this->appendUniqueString($metadata['signatureMethodAlgorithms'], $signatureMethod->getAttribute('Algorithm'));
            }
        }

        foreach ($root->getElementsByTagNameNS(self::NS_XMLDSIG, 'CanonicalizationMethod') as $canonicalizationMethod) {
            if ($canonicalizationMethod instanceof \DOMElement) {
                $this->appendUniqueString($metadata['canonicalizationMethodAlgorithms'], $canonicalizationMethod->getAttribute('Algorithm'));
            }
        }

        $metadata['hasSignatureValue'] = false;
        foreach ($root->getElementsByTagNameNS(self::NS_XMLDSIG, 'SignatureValue') as $signatureValue) {
            if ($signatureValue instanceof \DOMElement && trim($signatureValue->textContent) !== '') {
                $metadata['hasSignatureValue'] = true;
                break;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function digitalSignatureReferenceMetadata(\DOMElement $reference, int $index): array
    {
        $uri = $reference->getAttribute('URI');
        $uriKind = $this->digitalSignatureReferenceUriKind($uri);
        $suffix = $this->targetReferenceSuffix($uri);
        $transforms = [];
        foreach ($reference->getElementsByTagNameNS(self::NS_XMLDSIG, 'Transform') as $transform) {
            if ($transform instanceof \DOMElement) {
                $this->appendUniqueString($transforms, $transform->getAttribute('Algorithm'));
            }
        }

        $digestMethod = $this->firstChildElementByNamespace($reference, self::NS_XMLDSIG, 'DigestMethod');
        $digestValue = $this->firstChildElementByNamespace($reference, self::NS_XMLDSIG, 'DigestValue');
        $digestValueText = $digestValue instanceof \DOMElement ? trim($digestValue->textContent) : '';

        return [
            'index' => $index,
            'uri' => $uri,
            'uriKind' => $uriKind,
            'targetPart' => $uriKind === 'package-part' ? $this->stripQueryAndFragment($uri) : null,
            'targetQuery' => $suffix['query'],
            'targetFragment' => $suffix['fragment'],
            'targetReferenceSuffix' => $suffix['suffix'],
            'external' => $uriKind === 'external',
            'sameDocument' => $uriKind === 'same-document' || $uriKind === 'empty',
            'startsAtPackageRoot' => str_starts_with($uri, '/'),
            'transformCount' => count($transforms),
            'transformAlgorithms' => $transforms,
            'digestMethodAlgorithm' => $digestMethod instanceof \DOMElement
                ? $this->emptyStringToNull($digestMethod->getAttribute('Algorithm'))
                : null,
            'digestValuePresent' => $digestValueText !== '',
            'digestValueLength' => strlen($digestValueText),
        ];
    }

    private function digitalSignatureReferenceUriKind(string $uri): string
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return 'empty';
        }
        if (str_starts_with($trimmed, '#')) {
            return 'same-document';
        }
        if (
            str_starts_with($trimmed, '//')
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) === 1
        ) {
            return 'external';
        }
        if (str_starts_with($trimmed, '/')) {
            return 'package-part';
        }

        return 'relative';
    }

    private function firstChildElementByNamespace(\DOMElement $parent, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @return array<string, mixed>
     */
    private function selectedXmlPartDefinition(
        string $kind,
        string $partName,
        string $xml,
        bool $exists,
        ?array $relationship,
        string $relationshipSourcePart,
        string $relationshipsPart,
        bool $required,
        string $expectedRootNamespace,
        string $expectedRootLocalName,
        string $expectedContentTypeBase,
        array $expectedAlternativeContentTypeBases = [],
    ): array {
        $expectedContentTypeBases = array_values(array_unique(array_filter(
            array_merge([$expectedContentTypeBase], $expectedAlternativeContentTypeBases),
            static fn (string $contentTypeBase): bool => $contentTypeBase !== '',
        )));

        return [
            'kind' => $kind,
            'partName' => $partName,
            'xml' => $xml,
            'exists' => $exists,
            'relationship' => $relationship,
            'relationshipSourcePart' => $relationshipSourcePart,
            'relationshipsPart' => $relationshipsPart,
            'required' => $required,
            'expectedRootNamespace' => $expectedRootNamespace,
            'expectedRootLocalName' => $expectedRootLocalName,
            'expectedContentTypeBase' => $expectedContentTypeBase,
            'expectedContentTypeBases' => $expectedContentTypeBases,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param list<array<string, mixed>> $definitions
     * @return array<string, mixed>
     */
    private function selectedXmlPartProvenance(array $parts, array $contentTypes, array $definitions): array
    {
        $items = [];
        $byKind = [];
        $issueKinds = [];
        $issueCount = 0;

        foreach ($definitions as $definition) {
            $item = $this->selectedXmlPartProvenanceItem($parts, $contentTypes, $definition);
            $items[] = $item;
            $byKind[$item['kind']] = $item;
            if ($item['issues'] !== []) {
                $issueKinds[] = $item['kind'];
                $issueCount += count($item['issues']);
            }
        }

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'relationshipSelectedCount' => count(array_filter($items, static fn (array $item): bool => $item['relationshipId'] !== null)),
            'missingRequiredOrReferencedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('missing-required-part', $item['issues'], true)
                    || in_array('missing-relationship-target', $item['issues'], true),
            )),
            'validRootCount' => count(array_filter($items, static fn (array $item): bool => $item['validRoot'] === true)),
            'invalidRootCount' => count(array_filter($items, static fn (array $item): bool => $item['validRoot'] === false)),
            'invalidXmlCount' => count(array_filter($items, static fn (array $item): bool => in_array('invalid-xml', $item['issues'], true))),
            'unexpectedContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('unexpected-content-type', $item['issues'], true))),
            'missingContentTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-content-type', $item['issues'], true))),
            'issueCount' => $issueCount,
            'issueKinds' => $issueKinds,
            'byKind' => $byKind,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function selectedXmlPartProvenanceItem(array $parts, array $contentTypes, array $definition): array
    {
        $kind = (string) $definition['kind'];
        $partName = (string) $definition['partName'];
        $exists = (bool) $definition['exists'];
        $required = (bool) $definition['required'];
        $relationship = is_array($definition['relationship']) ? $definition['relationship'] : null;
        $expectedRootNamespace = (string) $definition['expectedRootNamespace'];
        $expectedRootLocalName = (string) $definition['expectedRootLocalName'];
        $expectedContentTypeBase = (string) $definition['expectedContentTypeBase'];
        $expectedContentTypeBases = is_array($definition['expectedContentTypeBases'] ?? null)
            ? array_values(array_filter(
                array_map('strval', $definition['expectedContentTypeBases']),
                static fn (string $contentTypeBase): bool => $contentTypeBase !== '',
            ))
            : ($expectedContentTypeBase === '' ? [] : [$expectedContentTypeBase]);
        $contentTypeResolution = $this->contentTypeResolutionForPart($partName, $contentTypes);
        $validateContentType = $exists || $required || $relationship !== null;
        $contentTypeMatchesExpected = null;
        $issues = [];

        if ($expectedContentTypeBases !== [] && $validateContentType) {
            $contentTypeMatchesExpected = in_array($contentTypeResolution['contentTypeBase'], $expectedContentTypeBases, true);
            if (!$contentTypeMatchesExpected) {
                $issues[] = $contentTypeResolution['contentTypeSource'] === 'missing'
                    ? 'missing-content-type'
                    : 'unexpected-content-type';
            }
        }

        $item = [
            'kind' => $kind,
            'partName' => $partName,
            'selectionSource' => $relationship !== null ? 'relationship' : ($exists ? 'conventional-part' : 'conventional-fallback'),
            'required' => $required,
            'exists' => $exists,
            'bytes' => $exists && isset($parts[$partName]) ? strlen($parts[$partName]) : 0,
            'expectedRootNamespace' => $expectedRootNamespace,
            'expectedRootLocalName' => $expectedRootLocalName,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'xmlParseError' => null,
            'expectedContentTypeBase' => $expectedContentTypeBase,
            'expectedContentTypeBases' => $expectedContentTypeBases,
            'contentTypeMatchesExpected' => $contentTypeMatchesExpected,
            'relationshipId' => null,
            'relationshipType' => null,
            'relationshipSourcePart' => null,
            'relationshipsPart' => null,
            'relationshipTarget' => null,
            'relationshipTargetMode' => null,
            'relationshipResolvedTarget' => null,
            'targetReferenceSuffix' => '',
            'targetQuery' => null,
            'targetFragment' => null,
            'issues' => $issues,
        ] + $contentTypeResolution;

        if ($relationship !== null) {
            $suffix = $this->targetReferenceSuffix($relationship['resolvedTarget']);
            $item['relationshipId'] = $relationship['id'];
            $item['relationshipType'] = $relationship['type'];
            $item['relationshipSourcePart'] = (string) $definition['relationshipSourcePart'];
            $item['relationshipsPart'] = (string) $definition['relationshipsPart'];
            $item['relationshipTarget'] = $relationship['target'];
            $item['relationshipTargetMode'] = $relationship['targetMode'];
            $item['relationshipResolvedTarget'] = $relationship['resolvedTarget'];
            $item['targetReferenceSuffix'] = $suffix['suffix'];
            $item['targetQuery'] = $suffix['query'];
            $item['targetFragment'] = $suffix['fragment'];
        }

        if (!$exists) {
            if ($required) {
                $item['issues'][] = 'missing-required-part';
            }
            if ($relationship !== null) {
                $item['issues'][] = 'missing-relationship-target';
            }

            return $item;
        }

        $xml = is_string($definition['xml']) ? $definition['xml'] : ($parts[$partName] ?? '');
        if ($xml === '') {
            $item['validRoot'] = false;
            $item['issues'][] = 'empty-xml-part';

            return $item;
        }

        $root = $this->xmlRootProvenance($xml, $partName);
        if ($root['validXml'] === false) {
            $item['validRoot'] = false;
            $item['xmlParseError'] = $root['xmlParseError'];
            $item['issues'][] = 'invalid-xml';

            return $item;
        }

        $item['rootNamespace'] = $root['namespace'];
        $item['rootLocalName'] = $root['localName'];
        $item['validRoot'] = $root['namespace'] === $expectedRootNamespace && $root['localName'] === $expectedRootLocalName;
        if ($item['validRoot'] === false) {
            $item['issues'][] = 'unexpected-root';
        }

        return $item;
    }

    /**
     * @return array{validXml:bool, xmlParseError:?string, namespace:?string, localName:?string}
     */
    private function xmlRootProvenance(string $xml, string $partName): array
    {
        $dom = $this->loadXmlForProvenance($xml, $partName);
        if (!$dom instanceof \DOMDocument) {
            return [
                'validXml' => false,
                'xmlParseError' => $this->lastXmlPreflightError($xml, $partName),
                'namespace' => null,
                'localName' => null,
            ];
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return [
                'validXml' => false,
                'xmlParseError' => 'missing XML document element',
                'namespace' => null,
                'localName' => null,
            ];
        }

        return [
            'validXml' => true,
            'xmlParseError' => null,
            'namespace' => $root->namespaceURI,
            'localName' => $root->localName,
        ];
    }

    /**
     * @param array<string, mixed> $relationship
     * @return array<string, mixed>
     */
    private function relationshipProvenanceSummaryItem(array $relationship): array
    {
        return [
            'id' => $relationship['id'] ?? '',
            'type' => $relationship['type'] ?? '',
            'sourcePart' => $relationship['sourcePart'] ?? '',
            'relationshipsPart' => $relationship['relationshipsPart'] ?? '',
            'target' => $relationship['target'] ?? '',
            'targetMode' => $relationship['targetMode'] ?? '',
            'resolvedTarget' => $relationship['resolvedTarget'] ?? '',
            'external' => (bool) ($relationship['external'] ?? false),
            'targetPart' => $relationship['targetPart'] ?? null,
            'targetReferenceSuffix' => $relationship['targetReferenceSuffix'] ?? '',
            'targetQuery' => $relationship['targetQuery'] ?? null,
            'targetFragment' => $relationship['targetFragment'] ?? null,
            'targetParentTraversalCount' => (int) ($relationship['targetParentTraversalCount'] ?? 0),
            'targetHasParentTraversal' => (bool) ($relationship['targetHasParentTraversal'] ?? false),
            'targetStartsAtPackageRoot' => (bool) ($relationship['targetStartsAtPackageRoot'] ?? false),
            'sameSourcePart' => (bool) ($relationship['sameSourcePart'] ?? false),
            'exists' => (bool) ($relationship['exists'] ?? false),
            'contentType' => $relationship['contentType'] ?? null,
            'contentTypeBase' => $relationship['contentTypeBase'] ?? null,
            'contentTypeHasParameters' => (bool) ($relationship['contentTypeHasParameters'] ?? false),
            'contentTypeParameterCount' => (int) ($relationship['contentTypeParameterCount'] ?? 0),
            'contentTypeParameters' => $relationship['contentTypeParameters'] ?? [],
            'contentTypeParameterMap' => $relationship['contentTypeParameterMap'] ?? [],
            'contentTypeSource' => $relationship['contentTypeSource'] ?? 'missing',
            'defaultExtension' => $relationship['defaultExtension'] ?? null,
            'overridePartName' => $relationship['overridePartName'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $relationshipPart
     * @return array<string, mixed>
     */
    private function missingRelationshipSourceSummary(string $relationshipsPart, array $relationshipPart): array
    {
        $relationships = [];
        $relationshipIds = [];
        $targetParts = [];
        $existingTargetParts = [];
        $missingTargetParts = [];
        $externalTargets = [];
        $missingContentTypeTargetParts = [];
        $targetReferenceSuffixes = [];
        $internalTargetCount = 0;
        $externalTargetCount = 0;
        $existingTargetCount = 0;
        $missingTargetCount = 0;
        $missingContentTypeCount = 0;

        foreach (($relationshipPart['relationships'] ?? []) as $relationship) {
            if (!is_array($relationship)) {
                continue;
            }

            $item = $this->relationshipProvenanceSummaryItem($relationship);
            $relationships[] = $item;
            $this->appendUniqueString($relationshipIds, is_string($item['id'] ?? null) ? $item['id'] : null);

            $targetReferenceSuffix = is_string($item['targetReferenceSuffix'] ?? null) ? $item['targetReferenceSuffix'] : '';
            if ($targetReferenceSuffix !== '') {
                $this->appendUniqueString($targetReferenceSuffixes, $targetReferenceSuffix);
            }

            if (($item['external'] ?? false) === true) {
                ++$externalTargetCount;
                $this->appendUniqueString($externalTargets, is_string($item['resolvedTarget'] ?? null) ? $item['resolvedTarget'] : null);
                continue;
            }

            ++$internalTargetCount;
            $targetPart = is_string($item['targetPart'] ?? null) ? $item['targetPart'] : null;
            $this->appendUniqueString($targetParts, $targetPart);

            if (($item['exists'] ?? false) === true) {
                ++$existingTargetCount;
                $this->appendUniqueString($existingTargetParts, $targetPart);
            } else {
                ++$missingTargetCount;
                $this->appendUniqueString($missingTargetParts, $targetPart);
            }

            if (($item['contentTypeSource'] ?? '') === 'missing') {
                ++$missingContentTypeCount;
                $this->appendUniqueString($missingContentTypeTargetParts, $targetPart);
            }
        }

        return [
            'relationshipsPart' => $relationshipsPart,
            'sourcePart' => is_string($relationshipPart['sourcePart'] ?? null) ? $relationshipPart['sourcePart'] : '',
            'sourceExists' => false,
            'relationshipCount' => count($relationships),
            'relationshipRecordCount' => (int) ($relationshipPart['relationshipRecordCount'] ?? count($relationships)),
            'internalTargetCount' => $internalTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'existingTargetCount' => $existingTargetCount,
            'missingTargetCount' => $missingTargetCount,
            'missingContentTypeCount' => $missingContentTypeCount,
            'relationshipIds' => $relationshipIds,
            'targetParts' => $targetParts,
            'existingTargetParts' => $existingTargetParts,
            'missingTargetParts' => $missingTargetParts,
            'externalTargets' => $externalTargets,
            'missingContentTypeTargetParts' => $missingContentTypeTargetParts,
            'targetReferenceSuffixes' => $targetReferenceSuffixes,
            'relationships' => $relationships,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $documentRelationships
     * @return array<string, mixed>
     */
    private function packageRelationshipPartsProvenance(
        array $parts,
        array $contentTypes,
        string $documentPart,
        string $documentRelationshipsPart,
        array $rootRelationships,
        array $documentRelationships,
    ): array {
        $relationshipParts = [
            '_rels/.rels' => $this->relationshipPartProvenance($parts, '_rels/.rels', '/', $rootRelationships, $contentTypes),
            $documentRelationshipsPart => $this->relationshipPartProvenance(
                $parts,
                $documentRelationshipsPart,
                $documentPart,
                $documentRelationships,
                $contentTypes,
            ),
        ];

        foreach ($parts as $partName => $_contents) {
            if (!$this->isRelationshipPartName($partName) || isset($relationshipParts[$partName])) {
                continue;
            }

            $relationshipParts[$partName] = $this->relationshipPartProvenance(
                $parts,
                $partName,
                $this->relationshipSourcePartForInventory($partName),
                $this->readRelationshipsPart($parts, $partName),
                $contentTypes,
            );
        }

        return $relationshipParts;
    }

    /**
     * @param array<string, array<string, mixed>> $relationshipParts
     * @return array<string, array<string, mixed>>
     */
    private function relationshipTypeProvenance(array $relationshipParts): array
    {
        $types = [];
        foreach ($relationshipParts as $relationshipPart) {
            $relationships = $relationshipPart['relationships'] ?? [];
            if (!is_array($relationships)) {
                continue;
            }

            foreach ($relationships as $relationship) {
                if (!is_array($relationship)) {
                    continue;
                }

                $type = is_string($relationship['type'] ?? null) ? $relationship['type'] : '';
                $typeKey = $type === '' ? '(missing-type)' : $type;
                if (!isset($types[$typeKey])) {
                    $types[$typeKey] = [
                        'type' => $type,
                        'label' => $this->relationshipTypeLabel($type),
                        'count' => 0,
                        'internalCount' => 0,
                        'externalCount' => 0,
                        'existingTargetCount' => 0,
                        'missingTargetCount' => 0,
                        'parentTraversalTargetCount' => 0,
                        'sameSourceTargetCount' => 0,
                        'relationshipParts' => [],
                        'sourceParts' => [],
                        'targetParts' => [],
                        'existingTargetParts' => [],
                        'missingTargetParts' => [],
                        'externalTargets' => [],
                        'contentTypes' => [],
                        'relationships' => [],
                    ];
                }

                $external = (bool) ($relationship['external'] ?? false);
                $exists = (bool) ($relationship['exists'] ?? false);
                $targetPart = is_string($relationship['targetPart'] ?? null) ? $relationship['targetPart'] : null;
                $relationshipsPart = is_string($relationship['relationshipsPart'] ?? null) ? $relationship['relationshipsPart'] : '';
                $sourcePart = is_string($relationship['sourcePart'] ?? null) ? $relationship['sourcePart'] : '';
                $target = is_string($relationship['target'] ?? null) ? $relationship['target'] : '';
                $resolvedTarget = is_string($relationship['resolvedTarget'] ?? null) ? $relationship['resolvedTarget'] : '';
                $contentType = is_string($relationship['contentType'] ?? null) ? $relationship['contentType'] : '';
                $contentTypeParameters = is_array($relationship['contentTypeParameters'] ?? null) ? $relationship['contentTypeParameters'] : [];
                $contentTypeParameterMap = is_array($relationship['contentTypeParameterMap'] ?? null) ? $relationship['contentTypeParameterMap'] : [];
                $contentTypeParameterCount = is_int($relationship['contentTypeParameterCount'] ?? null)
                    ? $relationship['contentTypeParameterCount']
                    : count($contentTypeParameters);

                $types[$typeKey]['count']++;
                if ($external) {
                    $types[$typeKey]['externalCount']++;
                    $this->appendUniqueString($types[$typeKey]['externalTargets'], $target);
                } else {
                    $types[$typeKey]['internalCount']++;
                    if (($relationship['targetHasParentTraversal'] ?? false) === true) {
                        $types[$typeKey]['parentTraversalTargetCount']++;
                    }
                    if (($relationship['sameSourcePart'] ?? false) === true) {
                        $types[$typeKey]['sameSourceTargetCount']++;
                    }
                    $this->appendUniqueString($types[$typeKey]['targetParts'], $targetPart);
                    if ($exists) {
                        $types[$typeKey]['existingTargetCount']++;
                        $this->appendUniqueString($types[$typeKey]['existingTargetParts'], $targetPart);
                    } else {
                        $types[$typeKey]['missingTargetCount']++;
                        $this->appendUniqueString($types[$typeKey]['missingTargetParts'], $targetPart);
                    }
                }

                $this->appendUniqueString($types[$typeKey]['relationshipParts'], $relationshipsPart);
                $this->appendUniqueString($types[$typeKey]['sourceParts'], $sourcePart);
                $this->appendUniqueString($types[$typeKey]['contentTypes'], $contentType);
                $types[$typeKey]['relationships'][] = [
                    'id' => is_string($relationship['id'] ?? null) ? $relationship['id'] : '',
                    'sourcePart' => $sourcePart,
                    'relationshipsPart' => $relationshipsPart,
                    'target' => $target,
                    'targetMode' => is_string($relationship['targetMode'] ?? null) ? $relationship['targetMode'] : '',
                    'resolvedTarget' => $resolvedTarget,
                    'external' => $external,
                    'targetPart' => $targetPart,
                    'exists' => $exists,
                    'contentType' => $contentType,
                    'contentTypeBase' => is_string($relationship['contentTypeBase'] ?? null) ? $relationship['contentTypeBase'] : '',
                    'contentTypeHasParameters' => (bool) ($relationship['contentTypeHasParameters'] ?? false),
                    'contentTypeParameterCount' => $contentTypeParameterCount,
                    'contentTypeParameters' => $contentTypeParameters,
                    'contentTypeParameterMap' => $contentTypeParameterMap,
                    'contentTypeSource' => is_string($relationship['contentTypeSource'] ?? null) ? $relationship['contentTypeSource'] : '',
                    'defaultExtension' => is_string($relationship['defaultExtension'] ?? null) ? $relationship['defaultExtension'] : null,
                    'overridePartName' => is_string($relationship['overridePartName'] ?? null) ? $relationship['overridePartName'] : null,
                    'targetReferenceSuffix' => is_string($relationship['targetReferenceSuffix'] ?? null) ? $relationship['targetReferenceSuffix'] : '',
                    'targetQuery' => is_string($relationship['targetQuery'] ?? null) ? $relationship['targetQuery'] : null,
                    'targetFragment' => is_string($relationship['targetFragment'] ?? null) ? $relationship['targetFragment'] : null,
                    'targetParentTraversalCount' => (int) ($relationship['targetParentTraversalCount'] ?? 0),
                    'targetHasParentTraversal' => (bool) ($relationship['targetHasParentTraversal'] ?? false),
                    'targetStartsAtPackageRoot' => (bool) ($relationship['targetStartsAtPackageRoot'] ?? false),
                    'sameSourcePart' => (bool) ($relationship['sameSourcePart'] ?? false),
                ];
            }
        }

        ksort($types);

        return $types;
    }

    private function relationshipTypeLabel(string $type): string
    {
        if ($type === '') {
            return 'missing-type';
        }

        $trimmed = rtrim($type, '/#');
        $slash = strrpos($trimmed, '/');
        $hash = strrpos($trimmed, '#');
        $position = max($slash === false ? -1 : $slash, $hash === false ? -1 : $hash);

        return $position >= 0 && $position < strlen($trimmed) - 1
            ? substr($trimmed, $position + 1)
            : $trimmed;
    }

    /**
     * @param list<string> $values
     */
    private function appendUniqueString(array &$values, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function contentTypesPartProvenance(array $parts, array $contentTypes): array
    {
        $preflight = isset($parts['[Content_Types].xml'])
            ? OpcContentTypes::preflightXml($parts['[Content_Types].xml'])
            : null;
        $defaults = [];
        foreach ($contentTypes['defaults'] as $extension => $contentType) {
            $defaults[$extension] = [
                'extension' => $extension,
            ] + $this->contentTypeReport($contentType);
        }

        $overrides = [];
        foreach ($contentTypes['overrides'] as $partName => $contentType) {
            $overrides[$partName] = [
                'partName' => $partName,
                'exists' => isset($parts[$partName]),
            ] + $this->contentTypeReport($contentType);
        }
        $parameterizedContentTypes = $this->parameterizedContentTypeDeclarations($defaults, $overrides);
        $overrideDeclarationSummary = $this->contentTypeOverrideDeclarationSummary($parts, $overrides);
        $invalidContentTypeRecords = $this->invalidContentTypeRecordSnapshots($preflight);
        $invalidContentTypeRecordIssueBuckets = $this->contentTypeRecordIssueBuckets($invalidContentTypeRecords);

        return [
            'partName' => '[Content_Types].xml',
            'exists' => isset($parts['[Content_Types].xml']),
            'bytes' => isset($parts['[Content_Types].xml']) ? strlen($parts['[Content_Types].xml']) : 0,
            'defaultCount' => count($defaults),
            'overrideCount' => count($overrides),
            'defaults' => $defaults,
            'overrides' => $overrides,
            'overrideDeclarationCount' => $overrideDeclarationSummary['declarationCount'],
            'usedOverrideDeclarationCount' => $overrideDeclarationSummary['usedDeclarationCount'],
            'unusedOverrideDeclarationCount' => $overrideDeclarationSummary['unusedDeclarationCount'],
            'invalidOverrideDeclarationCount' => $overrideDeclarationSummary['invalidDeclarationCount'],
            'unusedOverridePartNames' => $overrideDeclarationSummary['unusedPartNames'],
            'overrideDeclarationIssueCounts' => $overrideDeclarationSummary['issueCounts'],
            'overrideDeclarationIssues' => $overrideDeclarationSummary['issues'],
            'overrideDeclarations' => $overrideDeclarationSummary['declarations'],
            'parameterizedContentTypeCount' => count($parameterizedContentTypes),
            'parameterizedContentTypes' => $parameterizedContentTypes,
            'preflight' => $preflight,
            'valid' => $preflight === null ? false : $preflight['valid'],
            'issues' => $preflight === null ? ['missing-content-types-part'] : $preflight['issues'],
            'issueCounts' => $preflight === null ? ['missing-content-types-part' => 1] : $preflight['issueCounts'],
            'recordCount' => $preflight === null ? 0 : $preflight['recordCount'],
            'invalidRecordCount' => $preflight === null ? 0 : $preflight['invalidCount'],
            'declaredDefaultRecordCount' => $preflight === null ? 0 : $preflight['defaultCount'],
            'declaredOverrideRecordCount' => $preflight === null ? 0 : $preflight['overrideCount'],
            'duplicateDefaultExtensionCount' => $preflight === null ? 0 : $preflight['duplicateDefaultExtensionCount'],
            'duplicateOverridePartNameCount' => $preflight === null ? 0 : $preflight['duplicateOverridePartNameCount'],
            'duplicateDefaultExtensions' => $preflight === null ? [] : $preflight['duplicateDefaultExtensions'],
            'duplicateOverridePartNames' => $preflight === null ? [] : $preflight['duplicateOverridePartNames'],
            'duplicateDefaultExtensionGroups' => $preflight === null ? [] : $preflight['duplicateDefaultExtensionGroups'],
            'duplicateOverridePartNameGroups' => $preflight === null ? [] : $preflight['duplicateOverridePartNameGroups'],
            'invalidContentTypeRecordIssueCounts' => $this->issueBucketCounts($invalidContentTypeRecordIssueBuckets),
            'invalidContentTypeRecordIssueCodes' => array_keys($invalidContentTypeRecordIssueBuckets),
            'invalidContentTypeRecordIssueBuckets' => $invalidContentTypeRecordIssueBuckets,
            'invalidContentTypeRecords' => $invalidContentTypeRecords,
        ];
    }

    /**
     * @param ?array<string, mixed> $preflight
     * @return list<array<string, mixed>>
     */
    private function invalidContentTypeRecordSnapshots(?array $preflight): array
    {
        if ($preflight === null || !is_array($preflight['records'] ?? null)) {
            return [];
        }

        $records = [];
        foreach ($preflight['records'] as $record) {
            if (!is_array($record) || ($record['valid'] ?? true) === true) {
                continue;
            }

            $records[] = [
                'recordIndex' => (int) ($record['recordIndex'] ?? count($records)),
                'kind' => is_string($record['kind'] ?? null) ? $record['kind'] : '',
                'extension' => is_string($record['extension'] ?? null) ? $record['extension'] : null,
                'normalizedExtension' => is_string($record['normalizedExtension'] ?? null) ? $record['normalizedExtension'] : null,
                'partName' => is_string($record['partName'] ?? null) ? $record['partName'] : null,
                'normalizedPartName' => is_string($record['normalizedPartName'] ?? null) ? $record['normalizedPartName'] : null,
                'contentType' => is_string($record['contentType'] ?? null) ? $record['contentType'] : null,
                'equivalenceKey' => is_string($record['equivalenceKey'] ?? null) ? $record['equivalenceKey'] : null,
                'issues' => is_array($record['issues'] ?? null)
                    ? array_values(array_map('strval', $record['issues']))
                    : [],
            ];
        }

        usort($records, static fn (array $left, array $right): int => $left['recordIndex'] <=> $right['recordIndex']);

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array<string, list<array<string, mixed>>>
     */
    private function contentTypeRecordIssueBuckets(array $records): array
    {
        $buckets = [];
        foreach ($records as $record) {
            $issues = is_array($record['issues'] ?? null) ? $record['issues'] : [];
            foreach ($issues as $issue) {
                $issue = (string) $issue;
                if ($issue === '') {
                    continue;
                }

                $buckets[$issue][] = [
                    'recordIndex' => (int) ($record['recordIndex'] ?? 0),
                    'kind' => is_string($record['kind'] ?? null) ? $record['kind'] : '',
                    'extension' => is_string($record['extension'] ?? null) ? $record['extension'] : null,
                    'normalizedExtension' => is_string($record['normalizedExtension'] ?? null) ? $record['normalizedExtension'] : null,
                    'partName' => is_string($record['partName'] ?? null) ? $record['partName'] : null,
                    'normalizedPartName' => is_string($record['normalizedPartName'] ?? null) ? $record['normalizedPartName'] : null,
                    'contentType' => is_string($record['contentType'] ?? null) ? $record['contentType'] : null,
                ];
            }
        }

        ksort($buckets, SORT_STRING);

        return $buckets;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $buckets
     * @return array<string, int>
     */
    private function issueBucketCounts(array $buckets): array
    {
        $counts = [];
        foreach ($buckets as $issue => $records) {
            $counts[(string) $issue] = count($records);
        }

        return $counts;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array<string, mixed>> $overrides
     * @return array{declarationCount:int, usedDeclarationCount:int, unusedDeclarationCount:int, invalidDeclarationCount:int, unusedPartNames:list<string>, issueCounts:array<string, int>, issues:list<string>, declarations:list<array<string, mixed>>}
     */
    private function contentTypeOverrideDeclarationSummary(array $parts, array $overrides): array
    {
        $declarations = [];
        $unusedPartNames = [];
        $issueCounts = [];
        $usedDeclarationCount = 0;
        $invalidDeclarationCount = 0;

        foreach ($overrides as $partName => $override) {
            $exists = isset($parts[$partName]);
            $relationshipPart = $this->isRelationshipPartName($partName);
            $relationshipSource = null;
            $relationshipSourceExists = null;
            $issues = [];

            if ($exists) {
                ++$usedDeclarationCount;
            } else {
                $issues[] = 'override-target-missing-part';
                $unusedPartNames[] = $partName;
            }

            $contentTypeBase = is_string($override['contentTypeBase'] ?? null)
                ? $override['contentTypeBase']
                : '';

            if ($partName === '[Content_Types].xml') {
                $issues[] = 'content-types-override-target';
            }

            if ($relationshipPart) {
                $relationshipSource = $this->relationshipSourcePartForInventory($partName);
                $relationshipSourceExists = $this->relationshipSourceExists($parts, $relationshipSource);

                if ($contentTypeBase !== self::CT_PACKAGE_RELATIONSHIPS) {
                    $issues[] = 'invalid-relationship-content-type';
                }
                if (!$relationshipSourceExists) {
                    $issues[] = 'relationship-override-source-missing';
                }
            } elseif ($contentTypeBase === self::CT_PACKAGE_RELATIONSHIPS) {
                $issues[] = 'relationship-content-type-on-non-relationship-part';
            }

            $issues = array_values(array_unique($issues));
            sort($issues, SORT_STRING);
            if ($issues !== []) {
                ++$invalidDeclarationCount;
                foreach ($issues as $issue) {
                    $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
                }
            }

            $declarations[] = [
                'partName' => $partName,
                'contentType' => is_string($override['contentType'] ?? null) ? $override['contentType'] : '',
                'contentTypeBase' => $contentTypeBase,
                'exists' => $exists,
                'matchKind' => $exists ? 'exact' : 'missing',
                'relationshipPart' => $relationshipPart,
                'relationshipSource' => $relationshipSource,
                'relationshipSourceExists' => $relationshipSourceExists,
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        sort($unusedPartNames, SORT_STRING);
        ksort($issueCounts, SORT_STRING);

        return [
            'declarationCount' => count($declarations),
            'usedDeclarationCount' => $usedDeclarationCount,
            'unusedDeclarationCount' => count($unusedPartNames),
            'invalidDeclarationCount' => $invalidDeclarationCount,
            'unusedPartNames' => $unusedPartNames,
            'issueCounts' => $issueCounts,
            'issues' => array_keys($issueCounts),
            'declarations' => $declarations,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function relationshipPartProvenance(
        array $parts,
        string $relationshipsPart,
        string $sourcePart,
        array $relationships,
        array $contentTypes,
    ): array {
        $relationshipSummaries = [];
        foreach ($relationships as $id => $relationship) {
            $relationshipSummaries[$id] = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        }
        $relationshipRecords = $this->relationshipRecordProvenance(
            $parts,
            $relationshipsPart,
            $sourcePart,
            $contentTypes,
        );
        $duplicateIds = [];
        foreach ($relationshipRecords as $record) {
            if (($record['duplicateId'] ?? false) === true) {
                $duplicateIds[(string) $record['id']] = true;
            }
        }
        $duplicateItems = $this->duplicateRelationshipIdItems($relationshipRecords);
        $duplicateRecordCount = count(array_filter($relationshipRecords, static fn (array $record): bool => ($record['duplicateId'] ?? false) === true));
        $invalidRecords = array_values(array_filter($relationshipRecords, static fn (array $record): bool => ($record['valid'] ?? true) !== true));
        $recordIssueCodes = [];
        $recordIssueCount = 0;
        foreach ($invalidRecords as $record) {
            foreach (($record['issues'] ?? []) as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $recordIssueCodes[$issue] = true;
                    ++$recordIssueCount;
                }
            }
        }
        ksort($recordIssueCodes);

        return [
            'partName' => $relationshipsPart,
            'sourcePart' => $sourcePart,
            'sourceExists' => $this->relationshipSourceExists($parts, $sourcePart),
            'exists' => isset($parts[$relationshipsPart]),
            'bytes' => isset($parts[$relationshipsPart]) ? strlen($parts[$relationshipsPart]) : 0,
            'relationshipCount' => count($relationshipSummaries),
            'relationshipRecordCount' => count($relationshipRecords),
            'duplicateRelationshipIdCount' => count($duplicateIds),
            'duplicateRelationshipRecordCount' => $duplicateRecordCount,
            'duplicateRelationshipIds' => array_keys($duplicateIds),
            'duplicateRelationshipIdItems' => $duplicateItems,
            'invalidRelationshipRecordCount' => count($invalidRecords),
            'relationshipRecordIssueCount' => $recordIssueCount,
            'relationshipRecordIssueCodes' => array_keys($recordIssueCodes),
            'invalidRelationshipRecords' => $invalidRecords,
            'relationships' => $relationshipSummaries,
            'relationshipRecords' => $relationshipRecords,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<array<string, mixed>>
     */
    private function relationshipRecordProvenance(
        array $parts,
        string $relationshipsPart,
        string $sourcePart,
        array $contentTypes,
    ): array {
        $records = $this->readRelationshipRecordsPart($parts, $relationshipsPart);
        $idCounts = [];
        foreach ($records as $record) {
            $idCounts[$record['id']] = ($idCounts[$record['id']] ?? 0) + 1;
        }

        $items = [];
        foreach ($records as $record) {
            $issues = $this->relationshipRecordIssues($record);
            $summary = $this->relationshipInventorySummary($parts, $record, $sourcePart, $relationshipsPart, $contentTypes);
            $summary['ordinal'] = $record['ordinal'];
            $summary['valid'] = $issues === [];
            $summary['issues'] = $issues;
            $summary['duplicateId'] = $record['id'] !== '' && ($idCounts[$record['id']] ?? 0) > 1;
            $items[] = $summary;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<string>
     */
    private function relationshipRecordIssues(array $record): array
    {
        $issues = [];
        if (($record['id'] ?? '') === '') {
            $issues[] = 'missing-relationship-id';
        }
        if (($record['type'] ?? '') === '') {
            $issues[] = 'missing-relationship-type';
        }
        if (($record['target'] ?? '') === '') {
            $issues[] = 'missing-relationship-target';
        }

        $targetMode = is_string($record['targetMode'] ?? null) ? $record['targetMode'] : '';
        if ($targetMode !== '' && $targetMode !== 'Internal' && $targetMode !== 'External') {
            $issues[] = 'unexpected-relationship-target-mode';
        }

        return $issues;
    }

    /**
     * @param list<array<string, mixed>> $relationshipRecords
     * @return list<array<string, mixed>>
     */
    private function duplicateRelationshipIdItems(array $relationshipRecords): array
    {
        $byId = [];
        foreach ($relationshipRecords as $record) {
            $id = is_string($record['id'] ?? null) ? $record['id'] : '';
            if ($id === '') {
                continue;
            }

            $byId[$id][] = $record;
        }

        $items = [];
        foreach ($byId as $id => $records) {
            if (count($records) < 2) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'relationshipsPart' => is_string($records[0]['relationshipsPart'] ?? null) ? $records[0]['relationshipsPart'] : '',
                'sourcePart' => is_string($records[0]['sourcePart'] ?? null) ? $records[0]['sourcePart'] : '',
                'ordinals' => array_column($records, 'ordinal'),
                'types' => array_column($records, 'type'),
                'targets' => array_column($records, 'target'),
                'targetModes' => array_column($records, 'targetMode'),
                'resolvedTargets' => array_column($records, 'resolvedTarget'),
                'targetParts' => array_column($records, 'targetPart'),
                'targetReferenceSuffixes' => array_column($records, 'targetReferenceSuffix'),
                'externalValues' => array_column($records, 'external'),
                'existsValues' => array_column($records, 'exists'),
                'contentTypes' => array_column($records, 'contentType'),
                'relationships' => $records,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function relationshipInventorySummary(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
    ): array {
        $external = $this->isExternalRelationshipTarget($relationship);
        $targetPart = $external || $relationship['target'] === ''
            ? null
            : $this->stripQueryAndFragment($relationship['resolvedTarget']);
        $suffix = $this->targetReferenceSuffix($relationship['resolvedTarget']);
        $contentTypeResolution = $targetPart === null
            ? $this->missingContentTypeResolution(null)
            : $this->contentTypeResolutionForPart($targetPart, $contentTypes);
        $targetPath = $this->relationshipTargetPathProvenance(
            $relationship['target'],
            $relationship['targetMode'],
            $sourcePart,
            $targetPart,
            $external,
        );

        return [
            'id' => $relationship['id'],
            'type' => $relationship['type'],
            'sourcePart' => $sourcePart,
            'relationshipsPart' => $relationshipsPart,
            'target' => $relationship['target'],
            'targetMode' => $relationship['targetMode'],
            'external' => $external,
            'resolvedTarget' => $relationship['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $suffix['query'],
            'targetFragment' => $suffix['fragment'],
            'targetReferenceSuffix' => $suffix['suffix'],
            'targetParentTraversalCount' => $targetPath['parentTraversalCount'],
            'targetHasParentTraversal' => $targetPath['hasParentTraversal'],
            'targetStartsAtPackageRoot' => $targetPath['startsAtPackageRoot'],
            'sameSourcePart' => $targetPath['sameSourcePart'],
            'exists' => $targetPart !== null && isset($parts[$targetPart]),
            'contentType' => $contentTypeResolution['contentType'],
            'contentTypeBase' => $contentTypeResolution['contentTypeBase'],
            'contentTypeHasParameters' => $contentTypeResolution['contentTypeHasParameters'],
            'contentTypeParameterCount' => $contentTypeResolution['contentTypeParameterCount'],
            'contentTypeParameters' => $contentTypeResolution['contentTypeParameters'],
            'contentTypeParameterMap' => $contentTypeResolution['contentTypeParameterMap'],
            'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
            'defaultExtension' => $contentTypeResolution['defaultExtension'],
            'overridePartName' => $contentTypeResolution['overridePartName'],
        ];
    }

    /**
     * @return array{parentTraversalCount:int, hasParentTraversal:bool, startsAtPackageRoot:bool, sameSourcePart:bool}
     */
    private function relationshipTargetPathProvenance(
        string $target,
        string $targetMode,
        string $sourcePart,
        ?string $targetPart,
        bool $external,
    ): array {
        if ($external || $target === '' || $targetMode === 'External') {
            return [
                'parentTraversalCount' => 0,
                'hasParentTraversal' => false,
                'startsAtPackageRoot' => false,
                'sameSourcePart' => false,
            ];
        }

        $rawTargetPart = preg_replace('/[#?].*$/', '', $target) ?? $target;
        $rawTargetPart = str_replace('\\', '/', $rawTargetPart);
        $segments = array_values(array_filter(
            explode('/', ltrim($rawTargetPart, '/')),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.',
        ));
        $parentTraversalCount = count(array_filter($segments, static fn (string $segment): bool => $segment === '..'));
        $normalizedSourcePart = $this->normalizePartName($sourcePart);

        return [
            'parentTraversalCount' => $parentTraversalCount,
            'hasParentTraversal' => $parentTraversalCount > 0,
            'startsAtPackageRoot' => str_starts_with($rawTargetPart, '/'),
            'sameSourcePart' => $targetPart !== null
                && $normalizedSourcePart !== ''
                && $targetPart === $normalizedSourcePart,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $documentRelationships
     * @return array<string, array<string, mixed>>
     */
    private function packagePartInventory(
        array $parts,
        array $contentTypes,
        array $rootRelationships,
        string $documentPart,
        string $documentRelationshipsPart,
        array $documentRelationships,
    ): array {
        $rolesByPart = [];
        $this->addPartRole($rolesByPart, '[Content_Types].xml', 'content-types');
        $this->addPartRole($rolesByPart, '_rels/.rels', 'package-relationships');
        $this->addPartRole($rolesByPart, $documentPart, 'office-document');
        $this->addPartRole($rolesByPart, $documentRelationshipsPart, 'office-document-relationships');
        foreach ($parts as $partName => $_contents) {
            if ($this->isRelationshipPartName($partName)) {
                $this->addPartRole($rolesByPart, $partName, 'relationship-part');
            }
        }

        foreach ($rootRelationships as $relationship) {
            if ($this->isExternalRelationshipTarget($relationship)) {
                continue;
            }
            $targetPart = $this->stripQueryAndFragment($relationship['resolvedTarget']);
            $this->addPartRole($rolesByPart, $targetPart, 'root-relationship-target');
            $this->addRelationshipTargetInventoryRole($rolesByPart, $targetPart, $relationship['type']);
        }
        foreach ($documentRelationships as $relationship) {
            if ($this->isExternalRelationshipTarget($relationship)) {
                continue;
            }
            $targetPart = $this->stripQueryAndFragment($relationship['resolvedTarget']);
            $this->addPartRole($rolesByPart, $targetPart, 'document-relationship-target');
            $this->addRelationshipTargetInventoryRole($rolesByPart, $targetPart, $relationship['type']);
        }
        foreach ($parts as $relationshipPart => $_contents) {
            if (
                !$this->isRelationshipPartName($relationshipPart)
                || $relationshipPart === '_rels/.rels'
                || $relationshipPart === $documentRelationshipsPart
            ) {
                continue;
            }

            foreach ($this->readRelationshipsPart($parts, $relationshipPart) as $relationship) {
                if ($this->isExternalRelationshipTarget($relationship)) {
                    continue;
                }
                $targetPart = $this->stripQueryAndFragment($relationship['resolvedTarget']);
                $this->addPartRole($rolesByPart, $targetPart, 'relationship-target');
                $this->addRelationshipTargetInventoryRole($rolesByPart, $targetPart, $relationship['type']);
            }
        }

        $inventory = [];
        foreach ($parts as $partName => $contents) {
            $contentTypeResolution = $this->contentTypeResolutionForPart($partName, $contentTypes);
            $partExtension = $this->packagePartExtension($partName);
            $roles = array_keys($rolesByPart[$partName] ?? []);
            if ($roles === []) {
                $roles = ['package-part'];
            }

            $entry = [
                'partName' => $partName,
                'directory' => $this->packagePartDirectory($partName),
                'baseName' => $this->packagePartBaseName($partName),
                'partExtension' => $partExtension,
                'partExtensionDefaultDeclared' => $partExtension !== null && isset($contentTypes['defaults'][$partExtension]),
                'partExtensionDefaultContentType' => $partExtension === null ? null : ($contentTypes['defaults'][$partExtension] ?? null),
                'bytes' => strlen($contents),
                'crc32' => sprintf('%08x', crc32($contents)),
                'sha256' => hash('sha256', $contents),
                'contentType' => $contentTypeResolution['contentType'],
                'contentTypeBase' => $contentTypeResolution['contentTypeBase'],
                'contentTypeHasParameters' => $contentTypeResolution['contentTypeHasParameters'],
                'contentTypeParameterCount' => $contentTypeResolution['contentTypeParameterCount'],
                'contentTypeParameters' => $contentTypeResolution['contentTypeParameters'],
                'contentTypeParameterMap' => $contentTypeResolution['contentTypeParameterMap'],
                'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
                'defaultExtension' => $contentTypeResolution['defaultExtension'],
                'overridePartName' => $contentTypeResolution['overridePartName'],
                'isRelationshipPart' => $this->isRelationshipPartName($partName),
                'roles' => $roles,
            ];
            if ($entry['isRelationshipPart']) {
                $relationshipSourcePart = $this->relationshipSourcePartForInventory($partName);
                $entry['relationshipSourcePart'] = $relationshipSourcePart;
                $entry['relationshipSourceExists'] = $this->relationshipSourceExists($parts, $relationshipSourcePart);
            }
            $inventory[$partName] = $entry;
        }

        return $inventory;
    }

    private function packagePartDirectory(string $partName): string
    {
        $position = strrpos($partName, '/');

        return $position === false ? '/' : substr($partName, 0, $position);
    }

    private function packagePartBaseName(string $partName): string
    {
        $position = strrpos($partName, '/');

        return $position === false ? $partName : substr($partName, $position + 1);
    }

    private function packagePartExtension(string $partName): ?string
    {
        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));

        return $extension === '' ? null : $extension;
    }

    /**
     * @param array<string, array<string, true>> $rolesByPart
     */
    private function addPartRole(array &$rolesByPart, ?string $partName, string $role): void
    {
        if ($partName === null || $partName === '') {
            return;
        }

        $rolesByPart[$partName][$role] = true;
    }

    /**
     * @param array<string, array<string, true>> $rolesByPart
     */
    private function addRelationshipTargetInventoryRole(array &$rolesByPart, string $partName, string $relationshipType): void
    {
        $role = $this->relationshipTargetInventoryRole($relationshipType);
        if ($role === null) {
            return;
        }

        $this->addPartRole($rolesByPart, $partName, $role);
    }

    private function relationshipTargetInventoryRole(string $relationshipType): ?string
    {
        return match ($relationshipType) {
            self::FOOTNOTES_REL => 'footnotes',
            self::ENDNOTES_REL => 'endnotes',
            self::COMMENTS_REL => 'comments',
            self::COMMENTS_EXTENDED_REL => 'comments-extended',
            self::SETTINGS_REL => 'settings',
            self::FONT_TABLE_REL => 'font-table',
            self::FONT_REL => 'embedded-font',
            self::HEADER_REL => 'header-part',
            self::FOOTER_REL => 'footer-part',
            self::SUBDOCUMENT_REL => 'subdocument',
            self::ALT_CHUNK_REL => 'alternative-format-import',
            self::GLOSSARY_DOCUMENT_REL => 'glossary-document',
            self::OLE_OBJECT_REL => 'embedded-object',
            self::EMBEDDED_PACKAGE_REL => 'embedded-package',
            self::CHART_REL => 'chart-part',
            self::ACTIVEX_CONTROL_REL => 'activex-control',
            self::ACTIVEX_BINARY_REL => 'activex-binary',
            self::VBA_PROJECT_REL => 'vba-project',
            self::VBA_PROJECT_SIGNATURE_REL => 'vba-project-signature',
            self::VBA_DATA_REL => 'vba-data',
            default => null,
        };
    }

    /**
     * @param array<string, string> $parts
     */
    private function relationshipSourceExists(array $parts, string $sourcePart): bool
    {
        return $sourcePart === '/' || ($sourcePart !== '' && isset($parts[$sourcePart]));
    }

    /**
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     */
    private function isExternalRelationshipTarget(array $relationship): bool
    {
        return $relationship['targetMode'] === 'External'
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $relationship['resolvedTarget']) === 1;
    }

    /**
     * @return array{query:?string, fragment:?string, suffix:string}
     */
    private function targetReferenceSuffix(string $resolvedTarget): array
    {
        $fragment = null;
        $query = null;
        $suffix = '';
        $beforeFragment = $resolvedTarget;
        $fragmentPosition = strpos($resolvedTarget, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($resolvedTarget, $fragmentPosition + 1);
            $beforeFragment = substr($resolvedTarget, 0, $fragmentPosition);
            $suffix = '#' . $fragment;
        }

        $queryPosition = strpos($beforeFragment, '?');
        if ($queryPosition !== false) {
            $query = substr($beforeFragment, $queryPosition + 1);
            $suffix = '?' . $query . ($fragment === null ? '' : '#' . $fragment);
        }

        return [
            'query' => $query,
            'fragment' => $fragment,
            'suffix' => $suffix,
        ];
    }

    /**
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function contentTypeResolutionForPart(string $partName, array $contentTypes): array
    {
        $partName = $this->stripQueryAndFragment($partName);
        if (isset($contentTypes['overrides'][$partName])) {
            return [
                'contentType' => $contentTypes['overrides'][$partName],
                'contentTypeSource' => 'override',
                'defaultExtension' => null,
                'overridePartName' => $partName,
            ] + $this->contentTypeReport($contentTypes['overrides'][$partName]);
        }

        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
        if ($extension !== '' && isset($contentTypes['defaults'][$extension])) {
            return [
                'contentType' => $contentTypes['defaults'][$extension],
                'contentTypeSource' => 'default',
                'defaultExtension' => $extension,
                'overridePartName' => null,
            ] + $this->contentTypeReport($contentTypes['defaults'][$extension]);
        }

        return $this->missingContentTypeResolution($extension === '' ? null : $extension);
    }

    /**
     * @return array<string, mixed>
     */
    private function missingContentTypeResolution(?string $defaultExtension): array
    {
        return [
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => $defaultExtension,
            'overridePartName' => null,
        ];
    }

    /**
     * @return array{contentType:string, contentTypeBase:string, contentTypeHasParameters:bool, contentTypeParameterCount:int, contentTypeParameters:list<array{name:string, value:string, raw:string}>, contentTypeParameterMap:array<string, string>}
     */
    private function contentTypeReport(string $contentType): array
    {
        $segments = explode(';', $contentType);
        $base = strtolower(trim((string) array_shift($segments)));
        $parameters = [];
        $parameterMap = [];

        foreach ($segments as $segment) {
            $raw = trim($segment);
            if ($raw === '') {
                continue;
            }

            $equals = strpos($raw, '=');
            $name = $equals === false ? strtolower(trim($raw)) : strtolower(trim(substr($raw, 0, $equals)));
            if ($name === '') {
                continue;
            }

            $value = $equals === false ? '' : trim(substr($raw, $equals + 1));
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $value) ?? $value;
            }

            $parameters[] = [
                'name' => $name,
                'value' => $value,
                'raw' => $raw,
            ];
            $parameterMap[$name] = $value;
        }

        return [
            'contentType' => $contentType,
            'contentTypeBase' => $base,
            'contentTypeHasParameters' => $parameters !== [],
            'contentTypeParameterCount' => count($parameters),
            'contentTypeParameters' => $parameters,
            'contentTypeParameterMap' => $parameterMap,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $defaults
     * @param array<string, array<string, mixed>> $overrides
     * @return list<array<string, mixed>>
     */
    private function parameterizedContentTypeDeclarations(array $defaults, array $overrides): array
    {
        $items = [];
        foreach ($defaults as $extension => $default) {
            if (($default['contentTypeHasParameters'] ?? false) !== true) {
                continue;
            }

            $items[] = [
                'kind' => 'default',
                'extension' => $extension,
                'contentType' => $default['contentType'],
                'contentTypeBase' => $default['contentTypeBase'],
                'contentTypeParameterCount' => $default['contentTypeParameterCount'],
                'contentTypeParameters' => $default['contentTypeParameters'],
                'contentTypeParameterMap' => $default['contentTypeParameterMap'],
            ];
        }

        foreach ($overrides as $partName => $override) {
            if (($override['contentTypeHasParameters'] ?? false) !== true) {
                continue;
            }

            $items[] = [
                'kind' => 'override',
                'partName' => $partName,
                'exists' => $override['exists'],
                'contentType' => $override['contentType'],
                'contentTypeBase' => $override['contentTypeBase'],
                'contentTypeParameterCount' => $override['contentTypeParameterCount'],
                'contentTypeParameters' => $override['contentTypeParameters'],
                'contentTypeParameterMap' => $override['contentTypeParameterMap'],
            ];
        }

        return $items;
    }

    private function isRelationshipPartName(string $partName): bool
    {
        return $partName === '_rels/.rels'
            || (str_ends_with($partName, '.rels') && str_contains($partName, '/_rels/'));
    }

    private function relationshipSourcePartForInventory(string $relationshipPart): string
    {
        if ($relationshipPart === '_rels/.rels') {
            return '/';
        }

        return $this->sourcePartForRelationshipsPart($relationshipPart);
    }

    /**
     * @return array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}>
     */
    private function readNumbering(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $abstracts = [];
        foreach ($this->elements($xpath, '/w:numbering/w:abstractNum') as $abstract) {
            $abstractId = $abstract->getAttributeNS(self::NS_W, 'abstractNumId');
            if ($abstractId === '') {
                continue;
            }

            $levels = [];
            foreach ($this->elements($xpath, 'w:lvl', $abstract) as $level) {
                $ilvl = (int) $level->getAttributeNS(self::NS_W, 'ilvl');
                $levels[$ilvl] = [
                    'format' => $this->childAttr($level, 'numFmt', 'val') ?: 'decimal',
                    'text' => $this->childAttr($level, 'lvlText', 'val') ?: '%' . ($ilvl + 1) . '.',
                    'start' => max(1, (int) ($this->childAttr($level, 'start', 'val') ?: '1')),
                ];
            }
            $abstracts[$abstractId] = $levels;
        }

        $numbering = [];
        foreach ($this->elements($xpath, '/w:numbering/w:num') as $num) {
            $numId = $num->getAttributeNS(self::NS_W, 'numId');
            $abstractNumId = $this->childAttr($num, 'abstractNumId', 'val');
            if ($numId === '' || $abstractNumId === '' || !isset($abstracts[$abstractNumId])) {
                continue;
            }

            $levels = $abstracts[$abstractNumId];
            foreach ($this->elements($xpath, 'w:lvlOverride', $num) as $override) {
                $ilvl = (int) $override->getAttributeNS(self::NS_W, 'ilvl');
                $startOverride = $this->childAttr($override, 'startOverride', 'val');
                if ($startOverride !== '') {
                    $levels[$ilvl]['start'] = max(1, (int) $startOverride);
                }
            }

            $numbering[$numId] = [
                'abstractNumId' => $abstractNumId,
                'levels' => $levels,
            ];
        }

        return $numbering;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCoreProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $meta = [];
        foreach ([
            'title' => 'string(/cp:coreProperties/dc:title)',
            'creator' => 'string(/cp:coreProperties/dc:creator)',
            'description' => 'string(/cp:coreProperties/dc:description)',
            'subject' => 'string(/cp:coreProperties/dc:subject)',
            'keywords' => 'string(/cp:coreProperties/cp:keywords)',
            'created' => 'string(/cp:coreProperties/dcterms:created)',
            'modified' => 'string(/cp:coreProperties/dcterms:modified)',
        ] as $name => $query) {
            $value = trim((string) $xpath->evaluate($query));
            if ($value !== '') {
                $meta[$name] = $value;
            }
        }

        if (isset($meta['creator'])) {
            $meta['author'] = [$meta['creator']];
            $meta['authors'] = [$meta['creator']];
        }
        if (isset($meta['title'])) {
            $meta['titleInlines'] = [new AstNode('text', ['text' => $meta['title']])];
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExtendedProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $properties = [];

        foreach ([
            'Template' => 'template',
            'Manager' => 'manager',
            'Company' => 'company',
            'Application' => 'application',
            'AppVersion' => 'appVersion',
            'HyperlinkBase' => 'hyperlinkBase',
        ] as $source => $target) {
            $value = $this->extendedPropertyText($xpath, $source);
            if ($value !== '') {
                $properties[$target] = $value;
            }
        }

        foreach ([
            'Pages' => 'pages',
            'Words' => 'words',
            'Characters' => 'characters',
            'CharactersWithSpaces' => 'charactersWithSpaces',
            'Lines' => 'lines',
            'Paragraphs' => 'paragraphs',
            'DocSecurity' => 'docSecurity',
        ] as $source => $target) {
            $value = $this->extendedPropertyText($xpath, $source);
            if ($value !== '' && is_numeric($value)) {
                $properties[$target] = (int) $value;
            }
        }

        foreach ([
            'ScaleCrop' => 'scaleCrop',
            'LinksUpToDate' => 'linksUpToDate',
            'SharedDoc' => 'sharedDoc',
            'HyperlinksChanged' => 'hyperlinksChanged',
        ] as $source => $target) {
            $element = $this->firstElement($xpath, '/ep:Properties/ep:' . $source, $dom);
            if ($element instanceof \DOMElement) {
                $properties[$target] = $this->openXmlBooleanText($element->textContent);
            }
        }

        $headingPairs = $this->extendedHeadingPairs($xpath, $dom);
        if ($headingPairs !== []) {
            $properties['headingPairs'] = $headingPairs;
        }

        $titlesOfParts = $this->extendedTitlesOfParts($xpath, $dom);
        if ($titlesOfParts !== []) {
            $properties['titlesOfParts'] = $titlesOfParts;
        }

        return $properties;
    }

    private function extendedPropertyText(\DOMXPath $xpath, string $localName): string
    {
        return trim((string) $xpath->evaluate('string(/ep:Properties/ep:' . $localName . ')'));
    }

    /**
     * @return list<array{name:string, count:int}>
     */
    private function extendedHeadingPairs(\DOMXPath $xpath, \DOMDocument $dom): array
    {
        $vector = $this->firstElement($xpath, '/ep:Properties/ep:HeadingPairs/vt:vector', $dom);
        if (!$vector instanceof \DOMElement) {
            return [];
        }

        $values = $this->docPropsVectorValues($vector);
        $pairs = [];
        for ($index = 0; $index + 1 < count($values); $index += 2) {
            $name = trim((string) $values[$index]);
            $count = $values[$index + 1];
            if ($name !== '' && is_int($count)) {
                $pairs[] = ['name' => $name, 'count' => $count];
            }
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function extendedTitlesOfParts(\DOMXPath $xpath, \DOMDocument $dom): array
    {
        $vector = $this->firstElement($xpath, '/ep:Properties/ep:TitlesOfParts/vt:vector', $dom);
        if (!$vector instanceof \DOMElement) {
            return [];
        }

        $titles = [];
        foreach ($this->docPropsVectorValues($vector) as $value) {
            $title = trim((string) $value);
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return $titles;
    }

    /**
     * @return array{count:int, duplicateNameCount:int, duplicateNames:list<string>, items:list<array<string, mixed>>, byName:array<string, mixed>}
     */
    private function readCustomProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $items = [];
        $byName = [];
        $seenNames = [];
        $duplicateNames = [];

        foreach ($this->elements($xpath, '/cust:Properties/cust:property') as $property) {
            $name = trim($property->getAttribute('name'));
            if ($name === '') {
                continue;
            }

            $valueElement = $this->firstDocPropsValueElement($property);
            if (!$valueElement instanceof \DOMElement) {
                continue;
            }

            $value = $this->docPropsTypedValue($valueElement);
            $duplicate = isset($seenNames[$name]);
            $seenNames[$name] = true;
            if ($duplicate) {
                $duplicateNames[$name] = true;
            } else {
                $byName[$name] = $value;
            }

            $item = [
                'name' => $name,
                'valueType' => $valueElement->localName,
                'value' => $value,
                'duplicate' => $duplicate,
            ] + $this->docPropsAggregateValueMetadata($valueElement);
            $pid = $property->getAttribute('pid');
            if ($pid !== '' && is_numeric($pid)) {
                $item['pid'] = (int) $pid;
            }
            $fmtid = trim($property->getAttribute('fmtid'));
            if ($fmtid !== '') {
                $item['fmtid'] = $fmtid;
            }

            $items[] = $item;
        }

        if ($items === []) {
            return [];
        }

        return [
            'count' => count($items),
            'duplicateNameCount' => count($duplicateNames),
            'duplicateNames' => array_keys($duplicateNames),
            'items' => $items,
            'byName' => $byName,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function docPropsVectorValues(\DOMElement $vector): array
    {
        $values = [];
        foreach ($vector->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_VT) {
                continue;
            }

            if ($child->localName === 'variant') {
                $valueElement = $this->firstDocPropsValueElement($child);
                if ($valueElement instanceof \DOMElement) {
                    $values[] = $this->docPropsTypedValue($valueElement);
                }
                continue;
            }

            $values[] = $this->docPropsTypedValue($child);
        }

        return $values;
    }

    private function firstDocPropsValueElement(\DOMElement $parent): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::NS_VT) {
                return $child;
            }
        }

        return null;
    }

    private function docPropsTypedValue(\DOMElement $valueElement): mixed
    {
        $value = trim($valueElement->textContent);

        return match ($valueElement->localName) {
            'array', 'vector' => $this->docPropsVectorValues($valueElement),
            'bool' => $this->openXmlBooleanText($value),
            'i1', 'i2', 'i4', 'i8', 'int', 'ui1', 'ui2', 'ui4', 'ui8', 'uint' => is_numeric($value) ? (int) $value : 0,
            'r4', 'r8', 'decimal' => is_numeric($value) ? (float) $value : 0.0,
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function docPropsAggregateValueMetadata(\DOMElement $valueElement): array
    {
        if (!in_array($valueElement->localName, ['array', 'vector'], true)) {
            return [];
        }

        $metadata = [
            'valueCount' => count($this->docPropsVectorValues($valueElement)),
        ];

        $size = trim($valueElement->getAttribute('size'));
        if ($size !== '' && is_numeric($size)) {
            $metadata['declaredValueCount'] = (int) $size;
        }

        $baseType = trim($valueElement->getAttribute('baseType'));
        if ($baseType !== '') {
            $metadata['valueBaseType'] = $baseType;
        }

        if ($valueElement->localName === 'array') {
            $lowerBound = trim($valueElement->getAttribute('lBound'));
            if ($lowerBound === '') {
                $lowerBound = trim($valueElement->getAttribute('lbound'));
            }
            if ($lowerBound !== '' && is_numeric($lowerBound)) {
                $metadata['lowerBound'] = (int) $lowerBound;
            }
        }

        $itemTypes = $this->docPropsVectorValueTypes($valueElement);
        if ($itemTypes !== []) {
            $metadata['valueItemTypes'] = $itemTypes;
        }

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private function docPropsVectorValueTypes(\DOMElement $vector): array
    {
        $types = [];
        foreach ($vector->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_VT) {
                continue;
            }

            if ($child->localName === 'variant') {
                $valueElement = $this->firstDocPropsValueElement($child);
                if ($valueElement instanceof \DOMElement) {
                    $types[] = $valueElement->localName;
                }
                continue;
            }

            $types[] = $child->localName;
        }

        return $types;
    }

    private function openXmlBooleanText(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $settings = [];

        foreach ([
            'trackRevisions' => 'trackRevisions',
            'doNotTrackMoves' => 'doNotTrackMoves',
            'doNotTrackFormatting' => 'doNotTrackFormatting',
            'evenAndOddHeaders' => 'evenAndOddHeaders',
            'updateFields' => 'updateFields',
        ] as $key => $localName) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement) {
                $settings[$key] = $this->wordBoolean($element);
            }
        }

        $defaultTabStop = $this->firstElement($xpath, '/w:settings/w:defaultTabStop', $dom);
        if ($defaultTabStop instanceof \DOMElement && is_numeric($defaultTabStop->getAttributeNS(self::NS_W, 'val'))) {
            $settings['defaultTabStopTwips'] = (int) $defaultTabStop->getAttributeNS(self::NS_W, 'val');
        }

        $zoom = $this->firstElement($xpath, '/w:settings/w:zoom', $dom);
        if ($zoom instanceof \DOMElement) {
            $settings['zoom'] = array_filter([
                'percent' => is_numeric($zoom->getAttributeNS(self::NS_W, 'percent'))
                    ? (int) $zoom->getAttributeNS(self::NS_W, 'percent')
                    : null,
                'value' => $zoom->getAttributeNS(self::NS_W, 'val') ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        foreach ([
            'decimalSymbol' => 'decimalSymbol',
            'listSeparator' => 'listSeparator',
        ] as $key => $localName) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement) {
                $value = $element->getAttributeNS(self::NS_W, 'val');
                if ($value !== '') {
                    $settings[$key] = $value;
                }
            }
        }

        $protection = $this->firstElement($xpath, '/w:settings/w:documentProtection', $dom);
        if ($protection instanceof \DOMElement) {
            $settings['documentProtection'] = $this->wordAttributeMap($protection, [
                'edit',
                'enforcement',
                'algorithmName',
                'hashValue',
                'saltValue',
                'spinCount',
                'cryptProviderType',
                'cryptAlgorithmClass',
                'cryptAlgorithmType',
                'cryptAlgorithmSid',
                'cryptSpinCount',
            ]);
            if (isset($settings['documentProtection']['enforcement'])) {
                $settings['documentProtection']['enforcement'] = $this->wordBoolean($protection, 'enforcement');
            }
            foreach (['spinCount', 'cryptAlgorithmSid', 'cryptSpinCount'] as $numericKey) {
                if (isset($settings['documentProtection'][$numericKey]) && is_numeric($settings['documentProtection'][$numericKey])) {
                    $settings['documentProtection'][$numericKey] = (int) $settings['documentProtection'][$numericKey];
                }
            }
        }

        $writeProtection = $this->firstElement($xpath, '/w:settings/w:writeProtection', $dom);
        if ($writeProtection instanceof \DOMElement) {
            $settings['writeProtection'] = $this->wordAttributeMap($writeProtection, [
                'recommended',
                'algorithmName',
                'hashValue',
                'saltValue',
                'spinCount',
                'cryptProviderType',
                'cryptAlgorithmClass',
                'cryptAlgorithmType',
                'cryptAlgorithmSid',
                'cryptSpinCount',
            ]);
            if (isset($settings['writeProtection']['recommended'])) {
                $settings['writeProtection']['recommended'] = $this->wordBoolean($writeProtection, 'recommended');
            }
            foreach (['spinCount', 'cryptAlgorithmSid', 'cryptSpinCount'] as $numericKey) {
                if (isset($settings['writeProtection'][$numericKey]) && is_numeric($settings['writeProtection'][$numericKey])) {
                    $settings['writeProtection'][$numericKey] = (int) $settings['writeProtection'][$numericKey];
                }
            }
        }

        $revisionView = $this->firstElement($xpath, '/w:settings/w:revisionView', $dom);
        if ($revisionView instanceof \DOMElement) {
            $revisionViewSettings = [];
            foreach ([
                'markup',
                'comments',
                'insDel',
                'formatting',
                'inkAnnotations',
            ] as $attribute) {
                if ($revisionView->getAttributeNS(self::NS_W, $attribute) !== '') {
                    $revisionViewSettings[$attribute] = $this->wordBoolean($revisionView, $attribute);
                }
            }
            if ($revisionViewSettings !== []) {
                $settings['revisionView'] = $revisionViewSettings;
            }
        }

        $proofState = $this->firstElement($xpath, '/w:settings/w:proofState', $dom);
        if ($proofState instanceof \DOMElement) {
            $proofing = $this->wordAttributeMap($proofState, ['spelling', 'grammar']);
            if ($proofing !== []) {
                $settings['proofing'] = $proofing;
            }
        }

        $hyphenation = [];
        foreach ([
            'autoHyphenation' => 'autoHyphenation',
            'doNotHyphenateCaps' => 'doNotHyphenateCaps',
        ] as $localName => $key) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement) {
                $hyphenation[$key] = $this->wordBoolean($element);
            }
        }
        foreach ([
            'consecutiveHyphenLimit' => 'consecutiveHyphenLimit',
            'hyphenationZone' => 'hyphenationZoneTwips',
        ] as $localName => $key) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement && is_numeric($element->getAttributeNS(self::NS_W, 'val'))) {
                $hyphenation[$key] = (int) $element->getAttributeNS(self::NS_W, 'val');
            }
        }
        if ($hyphenation !== []) {
            $settings['hyphenation'] = $hyphenation;
        }

        $savePolicy = [];
        foreach ([
            'saveFormsData' => 'saveFormsData',
            'savePreviewPicture' => 'savePreviewPicture',
            'doNotEmbedSmartTags' => 'doNotEmbedSmartTags',
            'embedTrueTypeFonts' => 'embedTrueTypeFonts',
            'embedSystemFonts' => 'embedSystemFonts',
            'saveSubsetFonts' => 'saveSubsetFonts',
        ] as $localName => $key) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement) {
                $savePolicy[$key] = $this->wordBoolean($element);
            }
        }
        if ($savePolicy !== []) {
            $settings['savePolicy'] = $savePolicy;
        }

        $compatibility = [];
        foreach ($this->elements($xpath, '/w:settings/w:compat/w:compatSetting') as $setting) {
            $compatibility[] = array_filter([
                'name' => $setting->getAttributeNS(self::NS_W, 'name') ?: null,
                'uri' => $setting->getAttributeNS(self::NS_W, 'uri') ?: null,
                'value' => $setting->getAttributeNS(self::NS_W, 'val') ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }
        if ($compatibility !== []) {
            $settings['compatibility'] = $compatibility;
        }

        $documentVariables = [];
        foreach ($this->elements($xpath, '/w:settings/w:docVars/w:docVar') as $variable) {
            $name = $variable->getAttributeNS(self::NS_W, 'name');
            if ($name !== '') {
                $documentVariables[$name] = $variable->getAttributeNS(self::NS_W, 'val');
            }
        }
        if ($documentVariables !== []) {
            $settings['documentVariables'] = $documentVariables;
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function readWebSettings(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $webSettings = [];

        foreach ([
            'optimizeForBrowser' => 'optimizeForBrowser',
            'allowPNG' => 'allowPng',
            'relyOnVML' => 'relyOnVml',
            'doNotRelyOnCSS' => 'doNotRelyOnCss',
            'doNotSaveAsSingleFile' => 'doNotSaveAsSingleFile',
            'doNotOrganizeInFolder' => 'doNotOrganizeInFolder',
            'doNotUseLongFileNames' => 'doNotUseLongFileNames',
        ] as $source => $target) {
            $element = $this->firstElement($xpath, '/w:webSettings/w:' . $source, $dom);
            if ($element instanceof \DOMElement) {
                $webSettings[$target] = $this->wordBoolean($element);
            }
        }

        foreach ([
            'encoding' => 'encoding',
            'targetScreenSz' => 'targetScreenSize',
        ] as $source => $target) {
            $element = $this->firstElement($xpath, '/w:webSettings/w:' . $source, $dom);
            if ($element instanceof \DOMElement) {
                $value = $element->getAttributeNS(self::NS_W, 'val');
                if ($value !== '') {
                    $webSettings[$target] = $value;
                }
            }
        }

        $pixelsPerInch = $this->firstElement($xpath, '/w:webSettings/w:pixelsPerInch', $dom);
        if ($pixelsPerInch instanceof \DOMElement && is_numeric($pixelsPerInch->getAttributeNS(self::NS_W, 'val'))) {
            $webSettings['pixelsPerInch'] = (int) $pixelsPerInch->getAttributeNS(self::NS_W, 'val');
        }

        return $webSettings;
    }

    /**
     * @return array<string, mixed>
     */
    private function readFontTable(string $xml, string $partName, array $parts, array $contentTypes): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $relationshipsPart = $this->relationshipsPartFor($partName);
        $relationships = $this->readRelationshipsPart($parts, $relationshipsPart);
        $fonts = [];
        $byName = [];
        $embeddedFontRelationshipCount = 0;
        $embeddedFontExistingCount = 0;
        $embeddedFontMissingCount = 0;
        $embeddedFontExternalCount = 0;
        $embeddedFontIssueCount = 0;
        $embeddedFontIssueCodes = [];
        foreach ($this->elements($xpath, '/w:fonts/w:font') as $font) {
            $name = $font->getAttributeNS(self::NS_W, 'name');
            if ($name === '') {
                continue;
            }

            $record = array_filter([
                'name' => $name,
                'alternateName' => $this->childAttr($font, 'altName', 'val') ?: null,
                'charset' => $this->childAttr($font, 'charset', 'val') ?: null,
                'family' => $this->childAttr($font, 'family', 'val') ?: null,
                'pitch' => $this->childAttr($font, 'pitch', 'val') ?: null,
                'panose1' => $this->childAttr($font, 'panose1', 'val') ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
            $embeddedFonts = $this->fontTableEmbeddedFonts($font, $parts, $relationships, $partName, $relationshipsPart, $contentTypes);
            $record['embeddedFontCount'] = count($embeddedFonts);
            $record['embeddedFonts'] = $embeddedFonts;
            $embeddedFontRelationshipCount += count($embeddedFonts);
            foreach ($embeddedFonts as $embeddedFont) {
                if (($embeddedFont['external'] ?? false) === true) {
                    ++$embeddedFontExternalCount;
                }
                if (($embeddedFont['external'] ?? false) === false && ($embeddedFont['exists'] ?? false) === true) {
                    ++$embeddedFontExistingCount;
                }
                if (in_array('missing-in-package', $embeddedFont['issues'], true)) {
                    ++$embeddedFontMissingCount;
                }
                if ($embeddedFont['issues'] !== []) {
                    ++$embeddedFontIssueCount;
                }
                foreach ($embeddedFont['issues'] as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $embeddedFontIssueCodes[$issue] = true;
                    }
                }
            }

            $fonts[] = $record;
            $byName[$name] = $record;
        }
        ksort($embeddedFontIssueCodes, SORT_STRING);

        return [
            'relationshipsPart' => $relationshipsPart,
            'relationshipCount' => count($relationships),
            'fontCount' => count($fonts),
            'embeddedFontRelationshipCount' => $embeddedFontRelationshipCount,
            'embeddedFontExistingCount' => $embeddedFontExistingCount,
            'embeddedFontMissingCount' => $embeddedFontMissingCount,
            'embeddedFontExternalCount' => $embeddedFontExternalCount,
            'embeddedFontIssueCount' => $embeddedFontIssueCount,
            'embeddedFontIssueCodes' => array_keys($embeddedFontIssueCodes),
            'declaredNames' => array_column($fonts, 'name'),
            'fonts' => $fonts,
            'byName' => $byName,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<array<string, mixed>>
     */
    private function fontTableEmbeddedFonts(
        \DOMElement $font,
        array $parts,
        array $relationships,
        string $fontTablePart,
        string $relationshipsPart,
        array $contentTypes
    ): array {
        $embeddedFonts = [];
        foreach ([
            'embedRegular' => 'regular',
            'embedBold' => 'bold',
            'embedItalic' => 'italic',
            'embedBoldItalic' => 'bold-italic',
        ] as $localName => $style) {
            $embedded = $this->childElement($font, $localName);
            if (!$embedded instanceof \DOMElement) {
                continue;
            }

            $relationshipId = $embedded->getAttributeNS(self::NS_R, 'id');
            $embeddedFonts[] = $this->fontTableEmbeddedFontItem(
                $parts,
                $relationships[$relationshipId] ?? null,
                $fontTablePart,
                $relationshipsPart,
                $contentTypes,
                $relationshipId,
                $style,
                $embedded->getAttributeNS(self::NS_W, 'fontKey'),
            );
        }

        return $embeddedFonts;
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function fontTableEmbeddedFontItem(
        array $parts,
        ?array $relationship,
        string $fontTablePart,
        string $relationshipsPart,
        array $contentTypes,
        string $relationshipId,
        string $style,
        string $fontKey
    ): array {
        $item = [
            'style' => $style,
            'id' => $relationshipId,
            'sourcePart' => $fontTablePart,
            'relationshipsPart' => $relationshipsPart,
            'relationshipType' => null,
            'target' => null,
            'targetMode' => null,
            'resolvedTarget' => null,
            'targetPart' => null,
            'targetQuery' => null,
            'targetFragment' => null,
            'targetReferenceSuffix' => '',
            'contentType' => '',
            'contentTypeBase' => '',
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'expectedContentTypeBase' => self::CT_OBFUSCATED_FONT,
            'external' => false,
            'exists' => false,
            'byteLength' => null,
            'crc32' => null,
            'fontKeyPresent' => $fontKey !== '',
            'fontKeySha256' => $fontKey !== '' ? hash('sha256', $fontKey) : null,
            'byteExposurePolicy' => 'embedded-font-bytes-blocked',
            'reviewPolicy' => 'embedded-font-metadata-only',
            'valid' => false,
            'issues' => [],
            'relationship' => null,
        ];

        if ($relationshipId === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!is_array($relationship)) {
            $item['issues'][] = 'missing-relationship';
            return $item;
        }

        $summary = $this->relationshipInventorySummary($parts, $relationship, $fontTablePart, $relationshipsPart, $contentTypes);
        $targetPart = is_string($summary['targetPart'] ?? null) ? $summary['targetPart'] : null;
        $external = (bool) ($summary['external'] ?? false);
        $exists = (bool) ($summary['exists'] ?? false);
        $contentTypeBase = is_string($summary['contentTypeBase'] ?? null) ? $summary['contentTypeBase'] : '';

        $item['relationshipType'] = $summary['type'];
        $item['target'] = $summary['target'];
        $item['targetMode'] = $summary['targetMode'];
        $item['resolvedTarget'] = $summary['resolvedTarget'];
        $item['targetPart'] = $targetPart;
        $item['targetQuery'] = $summary['targetQuery'];
        $item['targetFragment'] = $summary['targetFragment'];
        $item['targetReferenceSuffix'] = $summary['targetReferenceSuffix'];
        $item['contentType'] = $summary['contentType'];
        $item['contentTypeBase'] = $summary['contentTypeBase'];
        $item['contentTypeHasParameters'] = $summary['contentTypeHasParameters'];
        $item['contentTypeParameterCount'] = $summary['contentTypeParameterCount'];
        $item['contentTypeParameters'] = $summary['contentTypeParameters'];
        $item['contentTypeParameterMap'] = $summary['contentTypeParameterMap'];
        $item['contentTypeSource'] = $summary['contentTypeSource'];
        $item['defaultExtension'] = $summary['defaultExtension'];
        $item['overridePartName'] = $summary['overridePartName'];
        $item['external'] = $external;
        $item['exists'] = $exists;
        $item['byteLength'] = $targetPart !== null && $exists ? strlen($parts[$targetPart]) : null;
        $item['crc32'] = $targetPart !== null && $exists ? sprintf('%08x', crc32($parts[$targetPart])) : null;
        $item['relationship'] = $summary;

        if ($relationship['type'] !== self::FONT_REL) {
            $item['issues'][] = 'unexpected-relationship-type';
        }

        if ($external) {
            $item['issues'][] = 'external-embedded-font';
        } else {
            if (!$exists) {
                $item['issues'][] = 'missing-in-package';
            }
            if (($summary['contentTypeSource'] ?? '') === 'missing') {
                $item['issues'][] = 'missing-content-type';
            } elseif ($contentTypeBase !== self::CT_OBFUSCATED_FONT) {
                $item['issues'][] = 'unexpected-embedded-font-content-type';
            }
        }

        $item['issues'] = array_values(array_unique($item['issues']));
        $item['valid'] = $item['issues'] === [];

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function readTheme(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_A || $root->localName !== 'theme') {
            return [];
        }

        $theme = [];
        $name = trim($root->getAttribute('name'));
        if ($name !== '') {
            $theme['name'] = $name;
        }

        $fonts = $this->themeFontScheme($xpath, $root);
        if ($fonts !== []) {
            $theme['fonts'] = $fonts;
        }

        $colors = $this->themeColorScheme($xpath, $root);
        if ($colors !== []) {
            $theme['colors'] = $colors;
        }

        return $theme;
    }

    /**
     * @return array<string, string>
     */
    private function themeFontScheme(\DOMXPath $xpath, \DOMElement $theme): array
    {
        $fontScheme = $this->firstElement($xpath, 'a:themeElements/a:fontScheme', $theme);
        if (!$fontScheme instanceof \DOMElement) {
            return [];
        }

        $fonts = [];
        $schemeName = trim($fontScheme->getAttribute('name'));
        if ($schemeName !== '') {
            $fonts['schemeName'] = $schemeName;
        }

        foreach ([
            'majorFont' => 'major',
            'minorFont' => 'minor',
        ] as $fontElementName => $prefix) {
            $fontElement = $this->firstElement($xpath, 'a:' . $fontElementName, $fontScheme);
            if (!$fontElement instanceof \DOMElement) {
                continue;
            }

            foreach ([
                'latin' => 'Latin',
                'ea' => 'EastAsia',
                'cs' => 'ComplexScript',
            ] as $source => $target) {
                $sourceElement = $this->firstElement($xpath, 'a:' . $source, $fontElement);
                if (!$sourceElement instanceof \DOMElement) {
                    continue;
                }

                $typeface = trim($sourceElement->getAttribute('typeface'));
                if ($typeface !== '') {
                    $fonts[$prefix . $target] = $typeface;
                }
            }
        }

        return $fonts;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeColorScheme(\DOMXPath $xpath, \DOMElement $theme): array
    {
        $colorScheme = $this->firstElement($xpath, 'a:themeElements/a:clrScheme', $theme);
        if (!$colorScheme instanceof \DOMElement) {
            return [];
        }

        $items = [];
        $byName = [];
        foreach ($colorScheme->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_A) {
                continue;
            }

            $name = $child->localName;
            if (!in_array($name, ['dk1', 'lt1', 'dk2', 'lt2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'], true)) {
                continue;
            }

            $color = $this->themeColorSchemeEntry($child);
            if ($color === null) {
                continue;
            }

            $item = ['name' => $name] + $color;
            $items[] = $item;
            if (is_string($item['rgb'] ?? null) && $item['rgb'] !== '') {
                $byName[$name] = $item['rgb'];
                foreach ($this->themeColorAliases($name) as $alias) {
                    $byName[$alias] = $item['rgb'];
                }
            }
        }

        if ($items === []) {
            return [];
        }

        $scheme = [
            'count' => count($items),
            'items' => $items,
            'byName' => $byName,
        ];
        $schemeName = trim($colorScheme->getAttribute('name'));
        if ($schemeName !== '') {
            $scheme = ['schemeName' => $schemeName] + $scheme;
        }

        return $scheme;
    }

    /**
     * @return array{kind:string, value:?string, rgb:?string}|null
     */
    private function themeColorSchemeEntry(\DOMElement $container): ?array
    {
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_A) {
                continue;
            }

            if ($child->localName === 'srgbClr') {
                $rgb = $this->normalizedRgb($child->getAttribute('val'));
                if ($rgb === null) {
                    continue;
                }

                return [
                    'kind' => 'srgb',
                    'value' => $rgb,
                    'rgb' => $rgb,
                ];
            }

            if ($child->localName === 'sysClr') {
                $value = trim($child->getAttribute('val'));

                return [
                    'kind' => 'system',
                    'value' => $value !== '' ? $value : null,
                    'rgb' => $this->normalizedRgb($child->getAttribute('lastClr')),
                ];
            }

            if ($child->localName === 'prstClr') {
                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                return [
                    'kind' => 'preset',
                    'value' => $value,
                    'rgb' => null,
                ];
            }

            if ($child->localName === 'schemeClr') {
                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                return [
                    'kind' => 'scheme',
                    'value' => $value,
                    'rgb' => null,
                ];
            }
        }

        return null;
    }

    private function normalizedRgb(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{6}$/D', $value) === 1 ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function themeColorAliases(string $name): array
    {
        return match ($name) {
            'dk1' => ['dark1', 'text1'],
            'lt1' => ['light1', 'background1'],
            'dk2' => ['dark2', 'text2'],
            'lt2' => ['light2', 'background2'],
            'hlink' => ['hyperlink'],
            'folHlink' => ['followedHyperlink', 'followed-hyperlink'],
            default => [],
        };
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return array<string, mixed>
     */
    private function readGlossaryDocument(
        string $xml,
        string $partName,
        bool $exists,
        string $relationshipsPart,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering,
        array $referencedNotes
    ): array {
        $summary = [
            'partName' => $partName,
            'exists' => $exists,
            'relationshipsPart' => $relationshipsPart,
            'relationshipCount' => count($relationships),
            'validXml' => null,
            'xmlParseError' => null,
            'validRoot' => null,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'count' => 0,
            'bodyBlockCount' => 0,
            'names' => [],
            'galleries' => [],
            'categories' => [],
            'types' => [],
            'behaviors' => [],
            'relationshipIds' => [],
            'byName' => [],
            'items' => [],
            'issueCount' => 0,
            'issueCodes' => [],
            'reviewPolicy' => 'glossary-document-metadata-only',
        ];

        if (!$exists || $xml === '') {
            return $summary;
        }

        $dom = $this->loadXmlForProvenance($xml, $partName);
        if (!$dom instanceof \DOMDocument) {
            $summary['validXml'] = false;
            $summary['xmlParseError'] = $this->lastXmlPreflightError($xml, $partName);
            $summary['issueCount'] = 1;
            $summary['issueCodes'] = ['invalid-xml'];

            return $summary;
        }

        $root = $dom->documentElement;
        $summary['validXml'] = $root instanceof \DOMElement;
        $summary['rootNamespace'] = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $summary['rootLocalName'] = $root instanceof \DOMElement ? $root->localName : null;
        $summary['validRoot'] = $root instanceof \DOMElement
            && $root->namespaceURI === self::NS_W
            && $root->localName === 'glossaryDocument';

        if ($summary['validRoot'] !== true) {
            $summary['issueCount'] = 1;
            $summary['issueCodes'] = ['unexpected-root'];

            return $summary;
        }

        $xpath = $this->xpath($dom);
        $issueCodes = [];
        foreach ($this->elements($xpath, '/w:glossaryDocument/w:docParts/w:docPart') as $docPart) {
            $item = $this->glossaryDocPartItem(
                $docPart,
                $xpath,
                $relationships,
                $contentTypes,
                $styles,
                $numbering,
                $referencedNotes,
                count($summary['items']),
            );
            $summary['items'][] = $item;
            $summary['bodyBlockCount'] += $item['bodyBlockCount'];

            $this->appendUniqueString($summary['names'], $item['name']);
            $this->appendUniqueString($summary['galleries'], $item['gallery']);
            $this->appendUniqueString($summary['categories'], $item['category']);
            foreach ($item['types'] as $type) {
                $this->appendUniqueString($summary['types'], $type);
            }
            foreach ($item['behaviors'] as $behavior) {
                $this->appendUniqueString($summary['behaviors'], $behavior);
            }
            foreach ($item['relationshipIds'] as $relationshipId) {
                $this->appendUniqueString($summary['relationshipIds'], $relationshipId);
            }
            foreach ($item['issues'] as $issue) {
                $issueCodes[$issue] = true;
            }

            if (is_string($item['name']) && $item['name'] !== '' && !isset($summary['byName'][$item['name']])) {
                $summary['byName'][$item['name']] = $item;
            }
        }

        ksort($issueCodes, SORT_STRING);
        $summary['count'] = count($summary['items']);
        $summary['issueCount'] = count(array_filter($summary['items'], static fn (array $item): bool => $item['issues'] !== []));
        $summary['issueCodes'] = array_keys($issueCodes);

        return $summary;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return array<string, mixed>
     */
    private function glossaryDocPartItem(
        \DOMElement $docPart,
        \DOMXPath $xpath,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering,
        array $referencedNotes,
        int $index
    ): array {
        $properties = $this->childElement($docPart, 'docPartPr');
        $body = $this->childElement($docPart, 'docPartBody');
        $types = $this->glossaryDocPartValues($xpath, 'w:types/w:type', $properties);
        $behaviors = $this->glossaryDocPartValues($xpath, 'w:behaviors/w:behavior', $properties);
        $blocks = $body instanceof \DOMElement
            ? $this->readWordBlockChildren($body->childNodes, $xpath, $relationships, $contentTypes, $styles, $numbering, $referencedNotes)
            : [];
        $relationshipIds = $body instanceof \DOMElement ? $this->relationshipIdsInElement($body) : [];
        $issues = [];
        if (!$body instanceof \DOMElement) {
            $issues[] = 'missing-body';
        }

        return [
            'index' => $index,
            'name' => $this->glossaryDocPartValue('name', $properties),
            'styleId' => $this->glossaryDocPartValue('style', $properties),
            'category' => $this->glossaryDocPartNestedValue('category', 'name', $properties),
            'gallery' => $this->glossaryDocPartNestedValue('category', 'gallery', $properties),
            'description' => $this->glossaryDocPartValue('description', $properties),
            'guid' => $this->glossaryDocPartValue('guid', $properties),
            'types' => $types,
            'behaviors' => $behaviors,
            'relationshipIds' => $relationshipIds,
            'bodyBlockCount' => count($blocks),
            'text' => $this->plainBlockText($blocks),
            'issues' => $issues,
        ];
    }

    private function glossaryDocPartValue(string $childLocalName, ?\DOMElement $properties): ?string
    {
        if (!$properties instanceof \DOMElement) {
            return null;
        }

        return $this->emptyStringToNull($this->childAttr($properties, $childLocalName, 'val'));
    }

    private function glossaryDocPartNestedValue(string $parentLocalName, string $childLocalName, ?\DOMElement $properties): ?string
    {
        $parent = $this->childElement($properties, $parentLocalName);
        if (!$parent instanceof \DOMElement) {
            return null;
        }

        return $this->glossaryDocPartValue($childLocalName, $parent);
    }

    /**
     * @return list<string>
     */
    private function glossaryDocPartValues(\DOMXPath $xpath, string $query, ?\DOMElement $properties): array
    {
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $values = [];
        foreach ($this->elements($xpath, $query, $properties) as $element) {
            $this->appendUniqueString($values, $this->emptyStringToNull($element->getAttributeNS(self::NS_W, 'val')));
        }

        return $values;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return array{summary:array{count:int, ids:list<string>, byId:array<string, array{id:string, sourceType:string, type:string, blockCount:int, text:string}>, items:list<array{id:string, sourceType:string, type:string, blockCount:int, text:string}>}, nodes:array<string, AstNode>}
     */
    private function readNotes(
        array $parts,
        string $xml,
        string $partName,
        string $relationshipsPart,
        string $rootName,
        string $itemName,
        string $sourceType,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering
    ): array {
        $relationshipDiagnostics = $this->relatedPartRelationshipDiagnostics(
            $parts,
            $relationships,
            $partName,
            $relationshipsPart,
            $contentTypes,
        );
        if ($xml === '') {
            return [
                'summary' => $this->noteCollectionSummary([], [], $relationshipDiagnostics),
                'nodes' => [],
            ];
        }

        $dom = $this->loadXml($xml, $partName);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_W || $root->localName !== $rootName) {
            return [
                'summary' => $this->noteCollectionSummary([], [], $relationshipDiagnostics),
                'nodes' => [],
            ];
        }

        $xpath = $this->xpath($dom);
        $items = [];
        $byId = [];
        $nodes = [];
        foreach ($root->childNodes as $note) {
            if (!$note instanceof \DOMElement || $note->namespaceURI !== self::NS_W || $note->localName !== $itemName) {
                continue;
            }

            $id = $note->getAttributeNS(self::NS_W, 'id');
            $type = strtolower($note->getAttributeNS(self::NS_W, 'type'));
            if ($id === '' || str_starts_with($id, '-') || in_array($type, ['separator', 'continuationseparator', 'continuationnotice'], true)) {
                continue;
            }

            $blocks = $this->readNoteBlocks($note, $xpath, $relationships, $contentTypes, $styles, $numbering);
            $relationshipIds = $this->relationshipIdsInElement($note);
            $item = [
                'id' => $id,
                'sourceType' => $sourceType,
                'type' => $type === '' ? 'normal' : $type,
                'blockCount' => count($blocks),
                'text' => $this->plainBlockText($blocks),
                'relationshipCount' => count($relationshipIds),
                'relationshipIds' => $relationshipIds,
                'missingRelationshipIds' => $this->missingRelationshipIds($relationshipIds, $relationships),
            ];
            $items[] = $item;
            $byId[$id] = $item;
            $nodes[$id] = new AstNode('note', [
                'id' => $id,
                'sourceType' => $sourceType,
            ], $blocks);
        }

        return [
            'summary' => $this->noteCollectionSummary($items, $byId, $relationshipDiagnostics),
            'nodes' => $nodes,
        ];
    }

    /**
     * @return array{summary:array{count:int, resolvedCount:int, threadedCount:int, paraIds:list<string>, byParaId:array<string, array{paraId:string, parentParaId:?string, resolved:?bool, partName:string}>, items:list<array{paraId:string, parentParaId:?string, resolved:?bool, partName:string}>}, byParaId:array<string, array{paraId:string, parentParaId:?string, resolved:?bool, partName:string}>}
     */
    private function readCommentsExtended(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [
                'summary' => ['count' => 0, 'resolvedCount' => 0, 'threadedCount' => 0, 'paraIds' => [], 'byParaId' => [], 'items' => []],
                'byParaId' => [],
            ];
        }

        $dom = $this->loadXml($xml, $partName);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_W15 || $root->localName !== 'commentsEx') {
            return [
                'summary' => ['count' => 0, 'resolvedCount' => 0, 'threadedCount' => 0, 'paraIds' => [], 'byParaId' => [], 'items' => []],
                'byParaId' => [],
            ];
        }

        $items = [];
        $byParaId = [];
        foreach ($root->childNodes as $comment) {
            if (!$comment instanceof \DOMElement || $comment->namespaceURI !== self::NS_W15 || $comment->localName !== 'commentEx') {
                continue;
            }

            $paraId = $this->wordExtensionAttr($comment, 'paraId');
            if ($paraId === null || $paraId === '') {
                continue;
            }

            $parentParaId = $this->wordExtensionAttr($comment, 'paraIdParent');
            if ($parentParaId === '') {
                $parentParaId = null;
            }
            $resolved = $this->onOffStringValue($this->wordExtensionAttr($comment, 'done'));
            $item = [
                'paraId' => $paraId,
                'parentParaId' => $parentParaId,
                'resolved' => $resolved,
                'partName' => $partName,
            ];
            $items[] = $item;
            $byParaId[$paraId] = $item;
        }

        return [
            'summary' => [
                'count' => count($items),
                'resolvedCount' => count(array_filter($items, static fn (array $item): bool => $item['resolved'] === true)),
                'threadedCount' => count(array_filter($items, static fn (array $item): bool => is_string($item['parentParaId']) && $item['parentParaId'] !== '')),
                'paraIds' => array_column($items, 'paraId'),
                'byParaId' => $byParaId,
                'items' => $items,
            ],
            'byParaId' => $byParaId,
        ];
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array{paraId:string, parentParaId:?string, resolved:?bool, partName:string}> $commentsExtended
     * @return array{summary:array{count:int, ids:list<string>, byId:array<string, array{id:string, sourceType:string, type:string, blockCount:int, text:string, author:?string, initials:?string, date:?string, commentParaId:?string, commentParentParaId:?string, commentResolved:?bool, commentsExtendedPart:?string}>, items:list<array{id:string, sourceType:string, type:string, blockCount:int, text:string, author:?string, initials:?string, date:?string, commentParaId:?string, commentParentParaId:?string, commentResolved:?bool, commentsExtendedPart:?string}>}, nodes:array<string, AstNode>}
     */
    private function readComments(
        array $parts,
        string $xml,
        string $partName,
        string $relationshipsPart,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering,
        array $commentsExtended = []
    ): array {
        $relationshipDiagnostics = $this->relatedPartRelationshipDiagnostics(
            $parts,
            $relationships,
            $partName,
            $relationshipsPart,
            $contentTypes,
        );
        if ($xml === '') {
            return [
                'summary' => $this->noteCollectionSummary([], [], $relationshipDiagnostics),
                'nodes' => [],
            ];
        }

        $dom = $this->loadXml($xml, $partName);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_W || $root->localName !== 'comments') {
            return [
                'summary' => $this->noteCollectionSummary([], [], $relationshipDiagnostics),
                'nodes' => [],
            ];
        }

        $xpath = $this->xpath($dom);
        $items = [];
        $byId = [];
        $nodes = [];
        foreach ($root->childNodes as $comment) {
            if (!$comment instanceof \DOMElement || $comment->namespaceURI !== self::NS_W || $comment->localName !== 'comment') {
                continue;
            }

            $id = $comment->getAttributeNS(self::NS_W, 'id');
            if ($id === '') {
                continue;
            }

            $blocks = $this->readNoteBlocks($comment, $xpath, $relationships, $contentTypes, $styles, $numbering);
            $relationshipIds = $this->relationshipIdsInElement($comment);
            $attrs = [
                'id' => $id,
                'sourceType' => 'comment',
            ];
            $author = $this->emptyStringToNull($comment->getAttributeNS(self::NS_W, 'author'));
            $initials = $this->emptyStringToNull($comment->getAttributeNS(self::NS_W, 'initials'));
            $date = $this->emptyStringToNull($comment->getAttributeNS(self::NS_W, 'date'));
            if ($author !== null) {
                $attrs['author'] = $author;
            }
            if ($initials !== null) {
                $attrs['initials'] = $initials;
            }
            if ($date !== null) {
                $attrs['date'] = $date;
            }
            foreach ($this->commentExtendedAttrs($comment, $commentsExtended) as $key => $value) {
                $attrs[$key] = $value;
            }

            $item = [
                'id' => $id,
                'sourceType' => 'comment',
                'type' => 'normal',
                'blockCount' => count($blocks),
                'text' => $this->plainBlockText($blocks),
                'author' => $author,
                'initials' => $initials,
                'date' => $date,
                'commentParaId' => $attrs['commentParaId'] ?? null,
                'commentParentParaId' => $attrs['commentParentParaId'] ?? null,
                'commentResolved' => $attrs['commentResolved'] ?? null,
                'commentsExtendedPart' => $attrs['commentsExtendedPart'] ?? null,
                'relationshipCount' => count($relationshipIds),
                'relationshipIds' => $relationshipIds,
                'missingRelationshipIds' => $this->missingRelationshipIds($relationshipIds, $relationships),
            ];
            $items[] = $item;
            $byId[$id] = $item;
            $nodes[$id] = new AstNode('note', $attrs, $blocks);
        }

        return [
            'summary' => $this->noteCollectionSummary($items, $byId, $relationshipDiagnostics),
            'nodes' => $nodes,
        ];
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>>|null $referencedNotes
     * @return list<AstNode>
     */
    private function readNoteBlocks(
        \DOMElement $note,
        \DOMXPath $xpath,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering,
        ?array $referencedNotes = null
    ): array {
        $blocks = [];
        $currentList = null;
        $referencedNotes ??= ['footnote' => [], 'endnote' => [], 'comment' => []];
        foreach ($note->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_W) {
                continue;
            }

            if ($child->localName === 'p') {
                $paragraph = $this->readParagraph($child, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                if ($paragraph === null) {
                    $this->flushCurrentList($currentList, $blocks);
                    continue;
                }

                $list = $this->paragraphListAttrs($child, $numbering);
                if ($list !== null && $paragraph->type === 'paragraph') {
                    $key = $list['type'] . ':' . $list['numId'] . ':' . $list['level'] . ':' . ($list['style'] ?? '') . ':' . ($list['delimiter'] ?? '') . ':' . $list['start'];
                    if (!is_array($currentList) || $currentList['key'] !== $key) {
                        $this->flushCurrentList($currentList, $blocks);
                        $attrs = [
                            'docxNumId' => $list['numId'],
                            'docxLevel' => $list['level'],
                        ];
                        if ($list['type'] === 'ordered_list') {
                            $attrs['start'] = $list['start'];
                            $attrs['style'] = $list['style'];
                            $attrs['delimiter'] = $list['delimiter'];
                        } else {
                            $attrs['bulletChar'] = $list['text'];
                        }
                        $currentList = [
                            'key' => $key,
                            'type' => $list['type'],
                            'attrs' => $attrs,
                            'items' => [],
                        ];
                    }

                    $currentList['items'][] = new AstNode('list_item', [], [$paragraph]);
                    continue;
                }

                $this->flushCurrentList($currentList, $blocks);
                $blocks[] = $paragraph;
                continue;
            }

            if ($child->localName === 'tbl') {
                $this->flushCurrentList($currentList, $blocks);
                $table = $this->readTable($child, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                if ($table !== null) {
                    $blocks[] = $table;
                }
            }
        }
        $this->flushCurrentList($currentList, $blocks);

        return $blocks;
    }

    /**
     * @param array<string, array<string, AstNode>> $referencedNotes
     */
    private function referencedNoteNode(array $referencedNotes, string $sourceType, \DOMElement $reference): ?AstNode
    {
        $id = $reference->getAttributeNS(self::NS_W, 'id');
        if ($id === '') {
            return null;
        }

        $attrs = [
            'id' => $id,
            'sourceType' => $sourceType,
        ];
        $customMarkFollows = strtolower($reference->getAttributeNS(self::NS_W, 'customMarkFollows'));
        if ($customMarkFollows !== '' && !in_array($customMarkFollows, ['0', 'false', 'off'], true)) {
            $attrs['customMarkFollows'] = true;
        }

        $note = $referencedNotes[$sourceType][$id] ?? null;
        if ($note instanceof AstNode) {
            return new AstNode('note', array_replace($note->attrs, $attrs), $note->children);
        }

        return new AstNode('note', ['missing' => true] + $attrs);
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, array{path:string, contentType:string, size:int, sha1:string}>
     */
    private function mediaMetadata(array $parts, array $contentTypes): array
    {
        $media = [];
        foreach ($parts as $name => $contents) {
            if (!str_starts_with($name, 'word/media/')) {
                continue;
            }
            $media[$name] = [
                'path' => $name,
                'contentType' => $this->contentTypeFor($name, $contentTypes),
                'size' => strlen($contents),
                'sha1' => sha1($contents),
            ];
        }

        return $media;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readDocumentBlocks(string $xml, array $relationships, array $contentTypes, array $styles, array $numbering, array $referencedNotes): array
    {
        $dom = $this->loadXml($xml, 'word/document.xml');
        $xpath = $this->xpath($dom);
        $body = $this->firstElement($xpath, '/w:document/w:body', $dom);
        if (!$body instanceof \DOMElement) {
            return [];
        }

        return $this->readWordBlockChildren($body->childNodes, $xpath, $relationships, $contentTypes, $styles, $numbering, $referencedNotes);
    }

    /**
     * @param iterable<\DOMNode> $children
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readWordBlockChildren(iterable $children, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles, array $numbering, array $referencedNotes): array
    {
        $blocks = [];
        $currentList = null;

        foreach ($children as $bodyChild) {
            if (!$bodyChild instanceof \DOMElement) {
                continue;
            }

            if ($bodyChild->namespaceURI === self::NS_W && $bodyChild->localName === 'p') {
                $paragraph = $this->readParagraph($bodyChild, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                if ($paragraph === null) {
                    $this->flushCurrentList($currentList, $blocks);
                    continue;
                }

                $list = $this->paragraphListAttrs($bodyChild, $numbering);
                if ($list !== null && $paragraph->type === 'paragraph') {
                    $key = $list['type'] . ':' . $list['numId'] . ':' . $list['level'] . ':' . ($list['style'] ?? '') . ':' . ($list['delimiter'] ?? '') . ':' . $list['start'];
                    if (!is_array($currentList) || $currentList['key'] !== $key) {
                        $this->flushCurrentList($currentList, $blocks);
                        $attrs = [
                            'docxNumId' => $list['numId'],
                            'docxLevel' => $list['level'],
                        ];
                        if ($list['type'] === 'ordered_list') {
                            $attrs['start'] = $list['start'];
                            $attrs['style'] = $list['style'];
                            $attrs['delimiter'] = $list['delimiter'];
                        } else {
                            $attrs['bulletChar'] = $list['text'];
                        }
                        $currentList = [
                            'key' => $key,
                            'type' => $list['type'],
                            'attrs' => $attrs,
                            'items' => [],
                        ];
                    }

                    $currentList['items'][] = new AstNode('list_item', [], [$paragraph]);
                    continue;
                }

                $this->flushCurrentList($currentList, $blocks);
                $blocks[] = $paragraph;
                continue;
            }

            if ($bodyChild->namespaceURI === self::NS_W && $bodyChild->localName === 'tbl') {
                $this->flushCurrentList($currentList, $blocks);
                $table = $this->readTable($bodyChild, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                if ($table !== null) {
                    $blocks[] = $table;
                }
            }
        }
        $this->flushCurrentList($currentList, $blocks);

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function relationshipIdsInElement(\DOMElement $element): array
    {
        $relationshipIds = [];
        $stack = [$element];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (!$current instanceof \DOMElement) {
                continue;
            }

            foreach (['id', 'embed', 'link'] as $attribute) {
                $this->appendUniqueString($relationshipIds, $this->emptyStringToNull($current->getAttributeNS(self::NS_R, $attribute)));
            }

            foreach ($current->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $stack[] = $child;
                }
            }
        }

        sort($relationshipIds, SORT_STRING);

        return $relationshipIds;
    }

    /**
     * @param array{key:string, type:string, attrs:array<string, mixed>, items:list<AstNode>}|null $currentList
     * @param list<AstNode> $blocks
     */
    private function flushCurrentList(?array &$currentList, array &$blocks): void
    {
        if (!is_array($currentList)) {
            return;
        }

        $blocks[] = new AstNode($currentList['type'], $currentList['attrs'], $currentList['items']);
        $currentList = null;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array<string, AstNode>> $referencedNotes
     */
    private function readParagraph(\DOMElement $paragraph, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles, array $referencedNotes): ?AstNode
    {
        $inlines = $this->readParagraphInlines($paragraph, $xpath, $relationships, $contentTypes, $referencedNotes);
        $text = $this->plainInlineText($inlines);
        if ($inlines === [] && $text === '') {
            return null;
        }

        $attrs = ['text' => $text];
        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId !== '') {
            $attrs['docxStyleId'] = $styleId;
            $style = $styles[$styleId] ?? null;
            if (is_array($style)) {
                $attrs['docxStyleName'] = $style['name'];
                if ($style['headingLevel'] !== null) {
                    $attrs['level'] = $style['headingLevel'];
                    $attrs['id'] = $this->identifierFromText($text);

                    return new AstNode('heading', $attrs, $inlines);
                }
            }
        }

        return new AstNode('paragraph', $attrs, $inlines);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readParagraphInlines(\DOMElement $paragraph, \DOMXPath $xpath, array $relationships, array $contentTypes, array $referencedNotes): array
    {
        $inlines = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 'r') {
                array_push($inlines, ...$this->readRun($child, $xpath, $relationships, $contentTypes, $referencedNotes));
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 'hyperlink') {
                $linkInlines = [];
                foreach ($this->elements($xpath, 'w:r', $child) as $run) {
                    array_push($linkInlines, ...$this->readRun($run, $xpath, $relationships, $contentTypes, $referencedNotes));
                }
                if ($linkInlines !== []) {
                    $inlines[] = new AstNode('link', $this->hyperlinkAttrs($child, $relationships), $linkInlines);
                }
                continue;
            }

            if (in_array($child->localName, ['ins', 'smartTag', 'sdt'], true)) {
                foreach ($this->elements($xpath, './/w:r', $child) as $run) {
                    array_push($inlines, ...$this->readRun($run, $xpath, $relationships, $contentTypes, $referencedNotes));
                }
            }
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array<string, mixed>
     */
    private function hyperlinkAttrs(\DOMElement $hyperlink, array $relationships): array
    {
        $relationshipId = $hyperlink->getAttributeNS(self::NS_R, 'id');
        $anchor = $hyperlink->getAttributeNS(self::NS_W, 'anchor');
        $attrs = ['url' => ''];
        if ($relationshipId !== '' && isset($relationships[$relationshipId])) {
            $relationship = $relationships[$relationshipId];
            $attrs['url'] = $relationship['targetMode'] === 'External'
                ? $relationship['target']
                : $relationship['resolvedTarget'];
            $attrs['relationshipId'] = $relationshipId;
            $attrs['relationshipType'] = $relationship['type'];
            $attrs['targetMode'] = $relationship['targetMode'];
        } elseif ($anchor !== '') {
            $attrs['url'] = '#' . $anchor;
        }

        if ($anchor !== '' && $relationshipId !== '') {
            $attrs['url'] .= '#' . $anchor;
        }

        return $attrs;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readRun(\DOMElement $run, \DOMXPath $xpath, array $relationships, array $contentTypes, array $referencedNotes): array
    {
        $inlines = [];
        foreach ($run->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 't') {
                $this->appendText($inlines, $child->textContent);
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'tab') {
                $this->appendText($inlines, "\t");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && in_array($child->localName, ['br', 'cr'], true)) {
                $inlines[] = new AstNode('linebreak');
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'noBreakHyphen') {
                $this->appendText($inlines, "\u{2011}");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'softHyphen') {
                $this->appendText($inlines, "\u{00AD}");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'sym') {
                $hex = $child->getAttributeNS(self::NS_W, 'char');
                if ($hex !== '' && ctype_xdigit($hex)) {
                    $this->appendText($inlines, html_entity_decode('&#x' . $hex . ';', ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'drawing') {
                array_push($inlines, ...$this->readDrawingImages($child, $xpath, $relationships, $contentTypes));
                continue;
            }
            if ($child->namespaceURI === self::NS_W && in_array($child->localName, ['footnoteRef', 'endnoteRef'], true)) {
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'footnoteReference') {
                $note = $this->referencedNoteNode($referencedNotes, 'footnote', $child);
                if ($note !== null) {
                    $inlines[] = $note;
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'endnoteReference') {
                $note = $this->referencedNoteNode($referencedNotes, 'endnote', $child);
                if ($note !== null) {
                    $inlines[] = $note;
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'commentReference') {
                $note = $this->referencedNoteNode($referencedNotes, 'comment', $child);
                if ($note !== null) {
                    $inlines[] = $note;
                }
            }
        }

        return $this->wrapRunInlines($run, $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function wrapRunInlines(\DOMElement $run, array $inlines): array
    {
        if ($inlines === []) {
            return [];
        }

        $rPr = $this->childElement($run, 'rPr');
        if (!$rPr instanceof \DOMElement) {
            return $inlines;
        }

        $wrappers = [];
        $vertical = $this->childAttr($rPr, 'vertAlign', 'val');
        if ($vertical === 'superscript') {
            $wrappers[] = 'superscript';
        } elseif ($vertical === 'subscript') {
            $wrappers[] = 'subscript';
        }
        if ($this->runPropertyEnabled($rPr, 'smallCaps')) {
            $wrappers[] = 'small_caps';
        }
        if ($this->runPropertyEnabled($rPr, 'u')) {
            $wrappers[] = 'underline';
        }
        if ($this->runPropertyEnabled($rPr, 'strike') || $this->runPropertyEnabled($rPr, 'dstrike')) {
            $wrappers[] = 'strikeout';
        }
        if ($this->runPropertyEnabled($rPr, 'i')) {
            $wrappers[] = 'emph';
        }
        if ($this->runPropertyEnabled($rPr, 'b')) {
            $wrappers[] = 'strong';
        }

        foreach ($wrappers as $type) {
            $inlines = [new AstNode($type, [], $inlines)];
        }

        return $inlines;
    }

    private function runPropertyEnabled(\DOMElement $rPr, string $name): bool
    {
        $property = $this->childElement($rPr, $name);
        if (!$property instanceof \DOMElement) {
            return false;
        }

        $value = strtolower($property->getAttributeNS(self::NS_W, 'val'));
        if ($name === 'u' && $value === 'none') {
            return false;
        }

        return !in_array($value, ['0', 'false', 'off'], true);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<AstNode>
     */
    private function readDrawingImages(\DOMElement $drawing, \DOMXPath $xpath, array $relationships, array $contentTypes): array
    {
        $images = [];
        $docPr = $this->firstElement($xpath, './/wp:docPr', $drawing);
        foreach ($this->elements($xpath, './/a:blip', $drawing) as $blip) {
            $relationshipId = $blip->getAttributeNS(self::NS_R, 'embed');
            if ($relationshipId === '') {
                $relationshipId = $blip->getAttributeNS(self::NS_R, 'link');
            }

            $relationship = $relationships[$relationshipId] ?? null;
            if (!is_array($relationship)) {
                continue;
            }

            $isExternal = $relationship['targetMode'] === 'External';
            $url = $isExternal ? $relationship['target'] : $relationship['resolvedTarget'];
            $alt = $docPr instanceof \DOMElement ? trim($docPr->getAttribute('descr')) : '';
            $title = $docPr instanceof \DOMElement ? trim($docPr->getAttribute('title') ?: $docPr->getAttribute('name')) : '';
            $attrs = [
                'url' => $url,
                'relationshipId' => $relationshipId,
                'relationshipType' => $relationship['type'],
                'targetMode' => $relationship['targetMode'],
            ];
            if (!$isExternal) {
                $attrs['mediaPath'] = $relationship['resolvedTarget'];
                $attrs['contentType'] = $this->contentTypeFor($relationship['resolvedTarget'], $contentTypes);
            }
            if ($alt !== '') {
                $attrs['alt'] = $alt;
            }
            if ($title !== '') {
                $attrs['title'] = $title;
            }

            $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
            $images[] = new AstNode('image', $attrs, $children);
        }

        return $images;
    }

    /**
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return array{type:string, numId:string, level:int, start:int, style:string, delimiter:string, text:string}|null
     */
    private function paragraphListAttrs(\DOMElement $paragraph, array $numbering): ?array
    {
        $pPr = $this->childElement($paragraph, 'pPr');
        $numPr = $pPr instanceof \DOMElement ? $this->childElement($pPr, 'numPr') : null;
        if (!$numPr instanceof \DOMElement) {
            return null;
        }

        $numId = $this->childAttr($numPr, 'numId', 'val');
        if ($numId === '' || !isset($numbering[$numId])) {
            return null;
        }

        $ilvl = (int) ($this->childAttr($numPr, 'ilvl', 'val') ?: '0');
        $level = $numbering[$numId]['levels'][$ilvl] ?? ['format' => 'decimal', 'text' => '%' . ($ilvl + 1) . '.', 'start' => 1];
        $format = $level['format'];
        $type = $format === 'bullet' ? 'bullet_list' : 'ordered_list';

        return [
            'type' => $type,
            'numId' => $numId,
            'level' => $ilvl,
            'start' => $level['start'],
            'style' => $this->listStyleForFormat($format),
            'delimiter' => $this->listDelimiterForText($level['text']),
            'text' => $level['text'],
        ];
    }

    private function paragraphStyleId(\DOMElement $paragraph): string
    {
        $pPr = $this->childElement($paragraph, 'pPr');
        if (!$pPr instanceof \DOMElement) {
            return '';
        }

        return $this->childAttr($pPr, 'pStyle', 'val');
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array<string, AstNode>> $referencedNotes
     */
    private function readTable(\DOMElement $table, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles, array $referencedNotes): ?AstNode
    {
        $rows = [];
        foreach ($this->elements($xpath, 'w:tr', $table) as $row) {
            $cells = [];
            foreach ($this->elements($xpath, 'w:tc', $row) as $cell) {
                $blocks = [];
                foreach ($this->elements($xpath, 'w:p', $cell) as $paragraph) {
                    $node = $this->readParagraph($paragraph, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                    if ($node !== null) {
                        $blocks[] = $node;
                    }
                }
                $gridSpan = $this->firstElement($xpath, 'w:tcPr/w:gridSpan', $cell);
                $attrs = ['text' => $this->plainBlockText($blocks)];
                if ($gridSpan instanceof \DOMElement) {
                    $attrs['colspan'] = max(1, (int) ($gridSpan->getAttributeNS(self::NS_W, 'val') ?: '1'));
                }
                $cells[] = new AstNode('table_cell', $attrs, $blocks);
            }
            if ($cells !== []) {
                $rows[] = new AstNode('table_row', [], $cells);
            }
        }

        if ($rows === []) {
            return null;
        }

        return new AstNode('table', ['caption' => '', 'sourceFormat' => 'docx'], [
            new AstNode('table_body', [], $rows),
        ]);
    }

    private function listStyleForFormat(string $format): string
    {
        return match ($format) {
            'lowerLetter' => 'lower_alpha',
            'upperLetter' => 'upper_alpha',
            'lowerRoman' => 'lower_roman',
            'upperRoman' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function listDelimiterForText(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            return 'two_parens';
        }
        if (str_ends_with($trimmed, ')')) {
            return 'one_paren';
        }

        return 'period';
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $merged !== [] && end($merged)->type === 'text') {
                $previous = array_pop($merged);
                $merged[] = new AstNode('text', ['text' => (string) $previous->attr('text', '') . (string) $node->attr('text', '')]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendText(array &$nodes, string $text): void
    {
        if ($text === '') {
            return;
        }

        if ($nodes !== [] && end($nodes)->type === 'text') {
            $previous = array_pop($nodes);
            $nodes[] = new AstNode('text', ['text' => (string) $previous->attr('text', '') . $text]);
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    private function emptyStringToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'image' => (string) $node->attr('alt', ''),
                'note' => '',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $parts[] = $block->attr('text', $this->plainInlineText($block->children));
        }

        return trim(implode(' ', array_map(static fn (mixed $part): string => (string) $part, $parts)));
    }

    private function identifierFromText(string $text): string
    {
        $id = strtolower(trim(preg_replace('/[^\pL\pN]+/u', '-', $text) ?? $text, '-'));

        return $id === '' ? 'section' : $id;
    }

    /**
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     */
    private function contentTypeFor(string $partName, array $contentTypes): string
    {
        $partName = $this->normalizePartName($partName);
        if (isset($contentTypes['overrides'][$partName])) {
            return $contentTypes['overrides'][$partName];
        }

        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
        return $contentTypes['defaults'][$extension] ?? '';
    }

    private function relationshipsPartFor(string $partName): string
    {
        $partName = $this->normalizePartName($partName);
        $directory = dirname($partName);
        $baseName = basename($partName);

        return ($directory === '.' ? '' : $directory . '/') . '_rels/' . $baseName . '.rels';
    }

    private function documentSiblingPart(string $documentPart, string $fileName): string
    {
        $directory = dirname($this->normalizePartName($documentPart));

        return ($directory === '.' || $directory === '' ? '' : $directory . '/') . $fileName;
    }

    private function resolveRelationshipTarget(string $relsPartName, string $target, string $targetMode): string
    {
        if ($targetMode === 'External' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            return $target;
        }

        if (str_starts_with($target, '/')) {
            return $this->normalizePartName($target);
        }

        $sourcePart = $this->sourcePartForRelationshipsPart($relsPartName);
        $base = $sourcePart === '' ? '' : dirname($sourcePart);
        $path = ($base === '' || $base === '.' ? '' : $base . '/') . $target;

        return $this->normalizePartName($path);
    }

    private function sourcePartForRelationshipsPart(string $relsPartName): string
    {
        if ($relsPartName === '_rels/.rels') {
            return '';
        }

        if (preg_match('~^(.*)/_rels/([^/]+)\.rels$~', $relsPartName, $match) === 1) {
            return ($match[1] === '' ? '' : $match[1] . '/') . $match[2];
        }

        return '';
    }

    private function normalizePartName(string $partName): string
    {
        $partName = str_replace('\\', '/', $partName);
        $partName = ltrim($partName, '/');
        $segments = [];
        foreach (explode('/', $partName) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function stripQueryAndFragment(string $partName): string
    {
        $partName = preg_replace('/[#?].*$/', '', $partName) ?? $partName;

        return $this->normalizePartName($partName);
    }

    private function loadXml(string $xml, string $partName): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \RuntimeException("Invalid DOCX XML part {$partName}: {$message}");
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function xpath(\DOMDocument $dom): \DOMXPath
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ct', self::NS_CT);
        $xpath->registerNamespace('rel', self::NS_REL);
        $xpath->registerNamespace('w', self::NS_W);
        $xpath->registerNamespace('r', self::NS_R);
        $xpath->registerNamespace('dc', self::NS_DC);
        $xpath->registerNamespace('cp', self::NS_CP);
        $xpath->registerNamespace('dcterms', self::NS_DCTERMS);
        $xpath->registerNamespace('ep', self::NS_EP);
        $xpath->registerNamespace('cust', self::NS_CUSTOM_PROPS);
        $xpath->registerNamespace('vt', self::NS_VT);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('wp', self::NS_WP);
        $xpath->registerNamespace('c', self::NS_C);
        $xpath->registerNamespace('o', self::NS_O);
        $xpath->registerNamespace('ds', self::NS_DS);

        return $xpath;
    }

    /**
     * @return list<\DOMElement>
     */
    private function elements(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $nodes = $context instanceof \DOMNode ? $xpath->query($query, $context) : $xpath->query($query);
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function firstElement(\DOMXPath $xpath, string $query, \DOMNode $context): ?\DOMElement
    {
        $elements = $this->elements($xpath, $query, $context);

        return $elements[0] ?? null;
    }

    /**
     * @param array<string, array{paraId:string, parentParaId:?string, resolved:?bool, partName:string}> $commentsExtended
     * @return array<string, mixed>
     */
    private function commentExtendedAttrs(\DOMElement $comment, array $commentsExtended): array
    {
        $paraId = $this->commentParagraphId($comment);
        if ($paraId === null) {
            return [];
        }

        $attrs = [
            'commentParaId' => $paraId,
        ];
        $extended = $commentsExtended[$paraId] ?? null;
        if (!is_array($extended)) {
            return $attrs;
        }

        $attrs['commentsExtendedPart'] = $extended['partName'];
        if (is_string($extended['parentParaId']) && $extended['parentParaId'] !== '') {
            $attrs['commentParentParaId'] = $extended['parentParaId'];
        }
        if (is_bool($extended['resolved'])) {
            $attrs['commentResolved'] = $extended['resolved'];
        }

        return $attrs;
    }

    private function commentParagraphId(\DOMElement $comment): ?string
    {
        foreach ($comment->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_W || $child->localName !== 'p') {
                continue;
            }

            return $this->wordExtensionAttr($child, 'paraId');
        }

        return null;
    }

    private function wordExtensionAttr(\DOMElement $element, string $localName): ?string
    {
        foreach ([self::NS_W15, self::NS_W14] as $namespace) {
            $value = $element->getAttributeNS($namespace, $localName);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function onOffStringValue(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        if (in_array($value, ['1', 'true', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'off'], true)) {
            return false;
        }

        return null;
    }

    private function childElement(?\DOMElement $parent, string $localName): ?\DOMElement
    {
        if (!$parent instanceof \DOMElement) {
            return null;
        }

        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::NS_W && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function childAttr(\DOMElement $parent, string $childLocalName, string $attrLocalName): string
    {
        $child = $this->childElement($parent, $childLocalName);

        return $child instanceof \DOMElement ? $child->getAttributeNS(self::NS_W, $attrLocalName) : '';
    }

    private function wordBoolean(\DOMElement $element, string $attribute = 'val'): bool
    {
        $value = strtolower($element->getAttributeNS(self::NS_W, $attribute));

        return !in_array($value, ['0', 'false', 'off'], true);
    }

    private function wordOptionalIntAttr(\DOMElement $element, string $attribute): ?int
    {
        $value = trim($element->getAttributeNS(self::NS_W, $attribute));
        if ($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function wordOptionalBooleanAttr(\DOMElement $element, string $attribute): ?bool
    {
        $value = $element->getAttributeNS(self::NS_W, $attribute);
        if ($value === '') {
            return null;
        }

        return $this->onOffStringValue($value);
    }

    /**
     * @param list<string> $names
     * @return array<string, string|int|bool>
     */
    private function wordAttributeMap(\DOMElement $element, array $names): array
    {
        $attributes = [];
        foreach ($names as $name) {
            $value = $element->getAttributeNS(self::NS_W, $name);
            if ($value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }
}
