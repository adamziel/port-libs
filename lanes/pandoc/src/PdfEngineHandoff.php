<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PdfEngineHandoff
{
    private const MAX_SOURCE_MAP_BYTES = 1048576;
    private const MAX_DEPENDENCY_FILE_BYTES = 1048576;
    private const MAX_PDF_OUTPUT_INSPECTION_BYTES = 1048576;
    private const MAX_XMP_METADATA_BYTES = 262144;
    private const MAX_OUTPUT_INTENT_PROFILE_BYTES = 262144;
    private const MAX_EMBEDDED_FILE_STREAM_BYTES = 262144;
    private const MAX_SIGNATURE_CONTENTS_BYTES = 262144;
    private const MAX_PIECE_INFO_PRIVATE_STREAM_BYTES = 262144;
    private const MAX_LEGAL_ATTESTATION_STREAM_BYTES = 262144;
    private const MAX_EMBEDDED_FONT_STREAM_BYTES = 262144;
    private const MAX_IMAGE_STREAM_BYTES = 262144;
    private const MAX_FORM_XOBJECT_STREAM_BYTES = 262144;
    private const MAX_ANNOTATION_APPEARANCE_STREAM_BYTES = 262144;
    private const MAX_PAGE_CONTENT_STREAM_BYTES = 262144;
    private const MAX_XREF_STREAM_BYTES = 262144;
    private const MAX_OBJECT_STREAM_BYTES = 262144;
    private const MAX_TRANSCRIPT_BYTES = 1048576;
    private const XMP_PDF_A_ID_NAMESPACE = 'http://www.aiim.org/pdfa/ns/id/';
    private const XMP_PDF_UA_ID_NAMESPACE = 'http://www.aiim.org/pdfua/ns/id/';
    private const XMP_PDF_A_EXTENSION_NAMESPACE = 'http://www.aiim.org/pdfa/ns/extension/';
    private const XMP_PDF_A_SCHEMA_NAMESPACE = 'http://www.aiim.org/pdfa/ns/schema#';
    private const XMP_PDF_A_PROPERTY_NAMESPACE = 'http://www.aiim.org/pdfa/ns/property#';

    /**
     * @var array<string, array{family:string, intermediate:string, extension:string, defaultArgs:list<string>}>
     */
    private const ENGINE_PROFILES = [
        'pdflatex' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-halt-on-error', '-interaction=nonstopmode']],
        'lualatex' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-halt-on-error', '-interaction=nonstopmode']],
        'xelatex' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-halt-on-error', '-interaction=nonstopmode']],
        'latexmk' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => ['-pdf', '-halt-on-error', '-interaction=nonstopmode']],
        'tectonic' => ['family' => 'latex', 'intermediate' => 'latex', 'extension' => 'tex', 'defaultArgs' => []],
        'context' => ['family' => 'context', 'intermediate' => 'context', 'extension' => 'tex', 'defaultArgs' => []],
        'pdfroff' => ['family' => 'roff', 'intermediate' => 'ms', 'extension' => 'ms', 'defaultArgs' => []],
        'wkhtmltopdf' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'weasyprint' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'prince' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'pagedjs-cli' => ['family' => 'html', 'intermediate' => 'html5', 'extension' => 'html', 'defaultArgs' => []],
        'typst' => ['family' => 'typst', 'intermediate' => 'typst', 'extension' => 'typ', 'defaultArgs' => []],
    ];

    /**
     * @param array{
     *     engine?: string,
     *     outputPath?: string,
     *     output?: string,
     *     sourcePath?: string,
     *     source?: string,
     *     engineOptions?: list<string>|string,
     *     templatePath?: string,
     *     includeInHeader?: list<string>|string,
     *     resourcePaths?: list<string>|string,
     *     resourceFiles?: list<string>|string,
     *     variables?: array<string, mixed>
     * } $options
     * @return array{
     *     kind: string,
     *     target: string,
     *     willExecute: bool,
     *     engineProgram: string,
     *     engine: string,
     *     engineFamily: string,
     *     intermediateFormat: string,
     *     sourceFile: string,
     *     outputFile: string,
     *     argv: list<string>,
     *     engineOptions: list<string>,
     *     sourceBytes: string|null,
     *     sourceSha256: string|null,
     *     templateFile: string|null,
     *     includeInHeaderFiles: list<string>,
     *     resourcePaths: list<string>,
     *     resourceFiles: list<string>,
     *     resourceFileManifest: list<array{path:string, kind:string, sources:list<string>, title:string|null, alt:string|null}>,
     *     remoteResourceReferences: list<string>,
     *     skippedResourceReferences: list<string>,
     *     templateVariables: array<string, mixed>,
     *     writerArguments: list<string>,
     *     sourceArtifacts: list<string>,
     *     engineLogFile: string|null,
     *     expectedEngineArtifacts: list<string>,
     *     metadata: array<string, mixed>,
     *     diagnostics: list<string>
     * }
     */
    public function plan(AstNode $document, array $options = []): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('PDF engine handoff expects a document node');
        }

        $engineProgram = $this->requireString($options['engine'] ?? 'pdflatex', 'PDF engine');
        $engine = $this->engineName($engineProgram);
        if (!isset(self::ENGINE_PROFILES[$engine])) {
            throw new \InvalidArgumentException('Unsupported PDF engine: ' . $engineProgram);
        }

        $profile = self::ENGINE_PROFILES[$engine];
        $outputFile = $this->normalizeRelativePath((string) ($options['outputPath'] ?? $options['output'] ?? 'document.pdf'), 'PDF output path');
        if (!preg_match('/\.pdf\z/i', $outputFile)) {
            throw new \InvalidArgumentException('PDF output path must end with .pdf');
        }

        $sourceFile = isset($options['sourcePath'])
            ? $this->normalizeRelativePath($this->requireString($options['sourcePath'], 'PDF source path'), 'PDF source path')
            : $this->deriveSourcePath($outputFile, $profile['extension']);
        $engineOptions = $this->normalizeStringList($options['engineOptions'] ?? []);
        $sourceBytes = array_key_exists('source', $options)
            ? $this->requireString($options['source'], 'PDF intermediate source')
            : $this->renderIntermediateSource($document, $profile['intermediate']);
        $templateFile = array_key_exists('templatePath', $options)
            ? $this->normalizeRelativePath($this->requireString($options['templatePath'], 'PDF template path'), 'PDF template path')
            : null;
        $includeInHeaderFiles = $this->normalizeRelativePathList($options['includeInHeader'] ?? [], 'PDF include-in-header path');
        $resourcePaths = $this->normalizeRelativePathList($options['resourcePaths'] ?? [], 'PDF resource path');
        $resourceInventory = $this->resourceInventoryFor($document, $options['resourceFiles'] ?? []);
        $metadata = $this->metadataSummary($document);
        $variables = array_key_exists('variables', $options)
            ? $this->normalizeVariables($options['variables'])
            : [];
        $templateVariables = array_replace($metadata, $variables);
        $writerArguments = $this->writerArgumentsFor($templateFile, $includeInHeaderFiles, $resourcePaths, $variables);
        $sourceArtifacts = array_values(array_filter(
            array_merge([$templateFile], $includeInHeaderFiles),
            static fn (?string $path): bool => $path !== null && $path !== ''
        ));
        $expectedEngineArtifacts = $this->expectedEngineArtifactsFor($engine, $profile['family'], $sourceFile);
        $recorderFile = $this->recorderFileFor($engine, $profile['family'], $sourceFile, $engineOptions);
        if ($recorderFile !== null && !in_array($recorderFile, $expectedEngineArtifacts, true)) {
            $expectedEngineArtifacts[] = $recorderFile;
        }
        $sourceMapFile = $this->sourceMapFileFor($profile['family'], $sourceFile, $engineOptions);
        if ($sourceMapFile !== null && !in_array($sourceMapFile, $expectedEngineArtifacts, true)) {
            $expectedEngineArtifacts[] = $sourceMapFile;
        }
        $engineLogFile = $this->firstEngineLogFile($expectedEngineArtifacts);

        $diagnostics = ['pdf-engine-not-executed'];
        if ($sourceBytes === null) {
            $diagnostics[] = 'intermediate-writer-pending:' . $profile['intermediate'];
        } elseif (array_key_exists('source', $options)) {
            $diagnostics[] = 'intermediate-source-supplied';
        } else {
            $diagnostics[] = 'intermediate-source-rendered:' . $profile['intermediate'];
        }
        if ($templateFile !== null) {
            $diagnostics[] = 'pdf-template-supplied';
        }
        if ($includeInHeaderFiles !== []) {
            $diagnostics[] = 'pdf-include-in-header:' . count($includeInHeaderFiles);
        }
        if ($resourcePaths !== []) {
            $diagnostics[] = 'pdf-resource-paths:' . count($resourcePaths);
        }
        if ($resourceInventory['files'] !== []) {
            $diagnostics[] = 'pdf-resource-files:' . count($resourceInventory['files']);
        }
        if ($resourceInventory['remote'] !== []) {
            $diagnostics[] = 'pdf-remote-resources:' . count($resourceInventory['remote']);
        }
        if ($resourceInventory['skipped'] !== []) {
            $diagnostics[] = 'pdf-skipped-resources:' . count($resourceInventory['skipped']);
        }
        if ($variables !== []) {
            $diagnostics[] = 'pdf-template-variables:' . count($this->flattenVariableArguments($variables));
        }
        if ($engineLogFile !== null) {
            $diagnostics[] = 'pdf-engine-log:' . $engineLogFile;
        }
        if ($recorderFile !== null) {
            $diagnostics[] = 'pdf-engine-recorder:' . $recorderFile;
        }
        if ($sourceMapFile !== null) {
            $diagnostics[] = 'pdf-source-map:' . $sourceMapFile;
        }
        if ($expectedEngineArtifacts !== []) {
            $diagnostics[] = 'pdf-engine-artifacts:' . count($expectedEngineArtifacts);
        }

        return [
            'kind' => 'pandoc-pdf-engine-handoff',
            'target' => 'pdf',
            'willExecute' => false,
            'engineProgram' => $engineProgram,
            'engine' => $engine,
            'engineFamily' => $profile['family'],
            'intermediateFormat' => $profile['intermediate'],
            'sourceFile' => $sourceFile,
            'outputFile' => $outputFile,
            'argv' => $this->argvFor($engineProgram, $engine, $profile, $sourceFile, $outputFile, $engineOptions),
            'engineOptions' => $engineOptions,
            'sourceBytes' => $sourceBytes,
            'sourceSha256' => $sourceBytes === null ? null : hash('sha256', $sourceBytes),
            'templateFile' => $templateFile,
            'includeInHeaderFiles' => $includeInHeaderFiles,
            'resourcePaths' => $resourcePaths,
            'resourceFiles' => $resourceInventory['files'],
            'resourceFileManifest' => $resourceInventory['manifest'],
            'remoteResourceReferences' => $resourceInventory['remote'],
            'skippedResourceReferences' => $resourceInventory['skipped'],
            'templateVariables' => $templateVariables,
            'writerArguments' => $writerArguments,
            'sourceArtifacts' => $sourceArtifacts,
            'engineLogFile' => $engineLogFile,
            'expectedEngineArtifacts' => $expectedEngineArtifacts,
            'metadata' => $metadata,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param array{exitCode?: int, stdout?: string, stderr?: string, missingProgram?: bool|string, files?: array<string, string>} $result
     * @return array{
     *     ok: bool,
     *     status: string,
     *     reason: string|null,
     *     engine: string,
     *     engineMissingProgram: bool,
     *     engineMissingProgramName: string|null,
     *     outputFile: string,
     *     exitCode: int,
     *     bytes: int,
     *     sourceSha256: string|null,
     *     sourceArtifactsSha256: array<string, string>,
     *     resourceArtifactsSha256: array<string, string>,
     *     missingResourceFiles: list<string>,
     *     producedArtifactsSha256: array<string, string>,
     *     engineDependencyArtifactsSha256: array<string, string>,
     *     engineInputFiles: list<string>,
     *     engineExternalInputFiles: list<string>,
     *     engineOutputFiles: list<string>,
     *     engineTranscriptInputFiles: list<string>,
     *     engineTranscriptExternalInputFiles: list<string>,
     *     missingEngineInputFiles: list<string>,
     *     missingEngineTranscriptInputFiles: list<string>,
     *     bibliographyArtifactsSha256: array<string, string>,
     *     bibliographyLogFiles: list<string>,
     *     sourceMapArtifactsSha256: array<string, string>,
     *     sourceMapFiles: list<string>,
     *     sourceMapInputs: list<array{tag:int, path:string}>,
     *     sourceMapInputFiles: list<string>,
     *     sourceMapExternalInputs: list<string>,
     *     sourceMapLineRanges: list<array{tag:int, path:string, minLine:int, maxLine:int, references:int}>,
     *     engineLogFiles: list<string>,
     *     engineWarnings: list<string>,
     *     engineErrors: list<string>,
     *     bibliographyWarnings: list<string>,
     *     bibliographyErrors: list<string>,
     *     bibliographyNeeded: bool,
     *     rerunNeeded: bool,
     *     declaredOutputFile: string|null,
     *     declaredOutputPages: int|null,
     *     declaredOutputBytes: int|null,
     *     pdfHeaderVersion: string|null,
     *     pdfCatalogVersion: string|null,
     *     pdfEffectiveVersion: string|null,
     *     pdfLinearization: array{object:string|null, linearizedVersion:float|null, fileLength:int|null, primaryHintOffset:int|null, primaryHintLength:int|null, firstPageObject:int|null, firstPageEndOffset:int|null, pageCount:int|null, mainXrefOffset:int|null, hintTables:list<array{offset:int, length:int}>, lengthMatches:bool|null}|array{},
     *     pdfExtensionMetadata: list<array{prefix:string, baseVersion:string|null, extensionLevel:int|null}>,
     *     pdfTrailerComplete: bool,
     *     pdfTrailerCount: int,
     *     pdfTrailerRevisions: list<array{revision:int, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, startxref:int|null, id:list<string>}>,
     *     pdfStartXrefOffsets: list<int>,
     *     pdfIncrementalUpdates: bool,
     *     pdfXrefStreams: list<array{object:string, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, index:list<int>, w:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfXrefStreamFilters: array<string, int>,
     *     pdfObjectStreams: list<array{object:string, objectCount:int|null, firstByteOffset:int|null, extends:string|null, objectNumbers:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfObjectStreamFilters: array<string, int>,
     *     pdfPageCount: int|null,
     *     pdfPageBoxes: list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}>,
     *     pdfPageRotations: array<int, int>,
     *     pdfPageProductionMetadata: list<array{page:int, pageObject:string|null, boxColorInfoObject:string|null, boxColorInfo:list<array{box:string, color:list<float>|null, width:float|null, style:string|null}>, separationInfoObject:string|null, separationPages:list<string>, separationDeviceColorant:string|null, separationColorSpace:string|null, presStepsObject:string|null, presStepsSubtype:string|null, presStepsNext:list<string>}>,
     *     pdfPageDisplayMetadata: list<array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}>,
     *     pdfPageLabels: list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>,
     *     pdfPageTimings: list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, directionLabel:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>,
     *     pdfPageViewports: list<array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}>,
     *     pdfPageContentStreams: list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}>,
     *     pdfPageContentResourceUsage: array<string, int>,
     *     pdfPageResourceSources: list<array{page:int, pageObject:string|null, resourceSourceObject:string|null, inherited:bool, categories:list<string>, fontNames:list<string>, xobjectNames:list<string>, colorSpaceNames:list<string>, graphicsStateNames:list<string>, propertyNames:list<string>}>,
     *     pdfFonts: list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>,
     *     pdfFontSubtypes: array<string, int>,
     *     pdfImages: list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfImageColorSpaces: array<string, int>,
     *     pdfImageFilters: array<string, int>,
     *     pdfColorSpaces: list<array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}>,
     *     pdfColorSpaceFamilies: array<string, int>,
     *     pdfFormXObjects: list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfFormXObjectFilters: array<string, int>,
     *     pdfGraphicsStates: list<array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}>,
     *     pdfGraphicsStateBlendModes: array<string, int>,
     *     pdfOutlineTitles: list<string>,
     *     pdfOutlines: list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}>,
     *     pdfOutlineDisplayMetadata: list<array{object:string, title:string, color:list<float>|null, flags:int, flagNames:list<string>}>,
     *     pdfDocumentInfo: array<string, string>,
     *     pdfDocumentInfoDateMetadata: list<array{key:string, source:string, raw:string, normalized:string|null, precision:string|null, timezone:string|null, timezoneOffsetMinutes:int|null, year:int|null, month:int|null, day:int|null, hour:int|null, minute:int|null, second:int|null, valid:bool}>,
     *     pdfXmpMetadata: array<string, mixed>,
     *     pdfPageMetadata: list<array<string, mixed>>,
     *     pdfPieceInfo: list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}>,
     *     pdfWebCaptureMetadata: list<array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}>,
     *     pdfOutputIntents: list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     pdfPageOutputIntents: list<array{page:int, pageObject:string|null, source:string, type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     pdfLanguage: string|null,
     *     pdfPageLayout: string|null,
     *     pdfPageMode: string|null,
     *     pdfOpenAction: array<string, mixed>|null,
     *     pdfNamedDestinations: list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>,
     *     pdfDestinationOptions: list<array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}>,
     *     pdfNameTrees: list<array{category:string, source:string, entryCount:int, names:list<string>, valueKinds:array<string, int>, valueReferences:list<string>, kidCount:int, limits:list<string>}>,
     *     pdfUriBase: string|null,
     *     pdfViewerPreferences: array<string, bool|int|string|list<int>|list<string>>,
     *     pdfNeedsRendering: bool|null,
     *     pdfCatalogRequirements: list<array{object:string|null, type:string|null, subtype:string|null, handlerObject:string|null, handlerType:string|null, handlerName:string|null, handlerCode:string|null, handlerVersion:string|null, keys:list<string>}>,
     *     pdfLegalAttestationMetadata: array{object:string|null, type:string|null, language:string|null, status:string|null, jurisdiction:string|null, attestation:string|null, attestationObject:string|null, attestationBytes:int|null, attestationSha256:string|null, attestationSkipped:string|null, associatedFiles:list<string>, keys:list<string>}|array{},
     *     pdfTaggingMetadata: array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{},
     *     pdfStructureElements: list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>,
     *     pdfMarkedContentProperties: list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}>,
     *     pdfMarkedContentArtifacts: list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>,
     *     pdfOptionalContentGroups: list<array{object:string, name:string|null, intent:list<string>, usageViewState:string|null, usagePrintState:string|null, usageExportState:string|null, usageCreator:string|null, usageCreatorSubtype:string|null, usageLanguage:string|null, usageLanguagePreferred:bool|null, usageZoomMin:float|null, usageZoomMax:float|null}>,
     *     pdfOptionalContentConfig: array{name:string|null, creator:string|null, baseState:string|null, listMode:string|null, on:list<string>, off:list<string>, order:list<string>, orderLabels:list<string>}|array{},
     *     pdfOptionalContentMemberships: list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, type:string|null, groups:list<string>, policy:string|null, visibilityExpressionOperators:list<string>, visibilityExpressionGroups:list<string>}>,
     *     pdfCollectionMetadata: array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{},
     *     pdfAcroFormMetadata: array{fieldReferences:list<string>, fieldCount:int, needAppearances:bool|null, sigFlags:int|null, sigFlagNames:list<string>, defaultResourcesPresent:bool, defaultAppearance:string|null, quadding:int|null, calculationOrder:list<string>, xfaPresent:bool, xfaPacketNames:list<string>}|array{},
     *     pdfAcroFormCalculationOrder: list<array{order:int, fieldObject:string, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null, alternateName:string|null, mappingName:string|null, flags:int|null, flagNames:list<string>, missing:bool}>,
     *     pdfThreads: list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>,
     *     pdfCatalogPermissions: list<array{permission:string, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     pdfSignatures: list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     pdfSignatureSubFilters: array<string, int>,
     *     pdfActiveActions: list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     pdfActiveActionTypes: array<string, int>,
     *     pdfRichMediaAnnotations: list<array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}>,
     *     pdfRichMediaActivationModes: array<string, int>,
     *     pdfAnnotations: list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}>,
     *     pdfAnnotationReviewMetadata: list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}>,
     *     pdfAnnotationAppearances: list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfAnnotationTypes: array<string, int>,
     *     pdfLinkTargets: list<string>,
     *     pdfEmbeddedFileNames: list<string>,
     *     pdfEmbeddedFiles: list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}>,
     *     pdfFormFields: list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}>,
     *     pdfFormFieldTypes: array<string, int>,
     *     pdfFormFieldActions: list<array{fieldName:string|null, fieldObject:string|null, fieldType:string|null, fieldTypeLabel:string|null, trigger:string, source:string, actionType:string, actionTarget:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     pdfFormFieldActionTypes: array<string, int>,
     *     pdfEncrypted: bool,
     *     pdfEncryptionFilter: string|null,
     *     pdfEncryptionVersion: int|null,
     *     pdfEncryptionRevision: int|null,
     *     pdfEncryptionLength: int|null,
     *     pdfPermissionInteger: int|null,
     *     pdfPermissionFlags: array<string, bool>,
     *     pdfEncryptMetadata: bool|null,
     *     pdfSha256: string|null,
     *     stdout: string,
     *     stderr: string,
     *     diagnostics: list<string>
     * }
     */
    public function fakeRun(array $plan, array $result = []): array
    {
        $sourceFile = $this->requirePlanString($plan, 'sourceFile');
        $outputFile = $this->requirePlanString($plan, 'outputFile');
        $engine = $this->requirePlanString($plan, 'engine');
        $engineProgram = isset($plan['engineProgram']) && is_string($plan['engineProgram']) && $plan['engineProgram'] !== ''
            ? $plan['engineProgram']
            : $engine;
        $exitCode = (int) ($result['exitCode'] ?? 0);
        $files = $this->normalizeFileMap($result['files'] ?? []);
        $planDiagnostics = $plan['diagnostics'] ?? [];
        if (!is_array($planDiagnostics)) {
            $planDiagnostics = [];
        }
        $diagnostics = array_values(array_filter(
            $planDiagnostics,
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        ));
        $diagnostics[] = 'fake-runner-no-execution';

        $sourceBytes = $plan['sourceBytes'] ?? null;
        $sourceSha256 = null;
        $sourceArtifactsSha256 = [];
        $resourceArtifactsSha256 = [];
        $missingResourceFiles = [];
        $producedArtifactsSha256 = [];
        $engineDependencyArtifactsSha256 = [];
        $engineInputFiles = [];
        $engineExternalInputFiles = [];
        $engineOutputFiles = [];
        $missingEngineInputFiles = [];
        $engineTranscriptInputFiles = [];
        $engineTranscriptExternalInputFiles = [];
        $missingEngineTranscriptInputFiles = [];
        $bibliographyArtifactsSha256 = [];
        $bibliographyLogFiles = [];
        $bibliographyLogTexts = [];
        $sourceMapArtifactsSha256 = [];
        $sourceMapFiles = [];
        $sourceMapInputsByKey = [];
        $sourceMapInputFiles = [];
        $sourceMapExternalInputs = [];
        $sourceMapLineRangesByKey = [];
        $engineLogFiles = [];
        $engineLogTexts = [];
        $status = 'ok';
        $reason = null;

        if (is_string($sourceBytes)) {
            if (!array_key_exists($sourceFile, $files)) {
                $files[$sourceFile] = $sourceBytes;
                $diagnostics[] = 'staged-source-from-plan';
            } elseif ($files[$sourceFile] !== $sourceBytes) {
                $status = 'failed';
                $reason = 'source-mismatch';
            }
        }

        if (array_key_exists($sourceFile, $files)) {
            $sourceSha256 = hash('sha256', $files[$sourceFile]);
        }

        foreach ($this->normalizePlanStringList($plan['sourceArtifacts'] ?? [], 'PDF source artifact') as $artifactPath) {
            if (!array_key_exists($artifactPath, $files)) {
                if ($reason === null) {
                    $status = 'failed';
                    $reason = 'missing-source-artifact';
                }
                $diagnostics[] = 'missing-source-artifact:' . $artifactPath;
                continue;
            }

            $sourceArtifactsSha256[$artifactPath] = hash('sha256', $files[$artifactPath]);
        }
        if ($sourceArtifactsSha256 !== [] && $reason === null) {
            $diagnostics[] = 'source-artifacts-validated:' . count($sourceArtifactsSha256);
        }

        $resourceFiles = $this->normalizeResourceFileList($plan['resourceFiles'] ?? [], 'PDF resource file');
        foreach ($resourceFiles as $resourcePath) {
            if (!array_key_exists($resourcePath, $files)) {
                $missingResourceFiles[] = $resourcePath;
                if ($reason === null) {
                    $status = 'failed';
                    $reason = 'missing-resource-file';
                }
                $diagnostics[] = 'missing-resource-file:' . $resourcePath;
                continue;
            }

            $resourceArtifactsSha256[$resourcePath] = hash('sha256', $files[$resourcePath]);
        }
        if ($resourceArtifactsSha256 !== [] && $reason === null) {
            $diagnostics[] = 'resource-files-validated:' . count($resourceArtifactsSha256);
        }

        $expectedEngineArtifacts = $this->normalizePlanStringList($plan['expectedEngineArtifacts'] ?? [], 'PDF expected engine artifact');
        $reservedFiles = array_fill_keys(array_merge([$sourceFile, $outputFile], array_keys($sourceArtifactsSha256), $resourceFiles), true);
        foreach ($files as $path => $bytes) {
            if (isset($reservedFiles[$path])) {
                continue;
            }
            if (!in_array($path, $expectedEngineArtifacts, true) && !$this->isKnownEngineArtifact($path, $sourceFile)) {
                continue;
            }

            $producedArtifactsSha256[$path] = hash('sha256', $bytes);
            if ($this->isBibliographyArtifactPath($path)) {
                $bibliographyArtifactsSha256[$path] = hash('sha256', $bytes);
                if ($this->isBibliographyLogPath($path)) {
                    $bibliographyLogFiles[] = $path;
                    $bibliographyLogTexts[] = $bytes;
                }
            }
            if ($this->isEngineDependencyArtifactPath($path)) {
                $engineDependencyArtifactsSha256[$path] = hash('sha256', $bytes);
                try {
                    $dependencies = $this->extractEngineDependencyArtifact($path, $bytes);
                } catch (\RuntimeException $exception) {
                    if ($reason === null) {
                        $status = 'failed';
                        $reason = 'engine-dependency-decode-error';
                    }
                    $diagnostics[] = 'engine-dependency-decode-error:' . $path . ':' . $exception->getMessage();
                    continue;
                }

                foreach ($dependencies['inputFiles'] as $inputFile) {
                    $engineInputFiles[$inputFile] = true;
                }
                foreach ($dependencies['externalInputFiles'] as $externalInputFile) {
                    $engineExternalInputFiles[$externalInputFile] = true;
                }
                foreach ($dependencies['outputFiles'] as $outputFilePath) {
                    $engineOutputFiles[$outputFilePath] = true;
                }
            }
            if ($this->isSourceMapArtifactPath($path)) {
                $sourceMapFiles[] = $path;
                $sourceMapArtifactsSha256[$path] = hash('sha256', $bytes);
                try {
                    $sourceMap = $this->extractSourceMapArtifact($path, $bytes);
                } catch (\RuntimeException $exception) {
                    if ($reason === null) {
                        $status = 'failed';
                        $reason = 'source-map-decode-error';
                    }
                    $diagnostics[] = 'source-map-decode-error:' . $path . ':' . $exception->getMessage();
                    continue;
                }

                foreach ($sourceMap['inputs'] as $input) {
                    $sourceMapInputsByKey[$input['tag'] . ':' . $input['path']] = $input;
                }
                foreach ($sourceMap['inputFiles'] as $inputFile) {
                    $sourceMapInputFiles[$inputFile] = true;
                }
                foreach ($sourceMap['externalInputs'] as $externalInput) {
                    $sourceMapExternalInputs[$externalInput] = true;
                }
                foreach ($sourceMap['lineRanges'] as $range) {
                    $key = $range['tag'] . ':' . $range['path'];
                    if (!isset($sourceMapLineRangesByKey[$key])) {
                        $sourceMapLineRangesByKey[$key] = $range;
                        continue;
                    }
                    $sourceMapLineRangesByKey[$key]['minLine'] = min($sourceMapLineRangesByKey[$key]['minLine'], $range['minLine']);
                    $sourceMapLineRangesByKey[$key]['maxLine'] = max($sourceMapLineRangesByKey[$key]['maxLine'], $range['maxLine']);
                    $sourceMapLineRangesByKey[$key]['references'] += $range['references'];
                }
            }
            if ($this->isEngineLogPath($path)) {
                $engineLogFiles[] = $path;
                $engineLogTexts[] = $bytes;
            }
        }
        ksort($producedArtifactsSha256);
        ksort($engineDependencyArtifactsSha256);
        ksort($bibliographyArtifactsSha256);
        ksort($sourceMapArtifactsSha256);
        sort($engineLogFiles);
        sort($bibliographyLogFiles);
        sort($sourceMapFiles);
        $engineInputFileList = array_keys($engineInputFiles);
        sort($engineInputFileList);
        $engineExternalInputFileList = array_keys($engineExternalInputFiles);
        sort($engineExternalInputFileList);
        $engineOutputFileList = array_keys($engineOutputFiles);
        sort($engineOutputFileList);
        foreach ($engineInputFileList as $inputFile) {
            if (array_key_exists($inputFile, $files)) {
                continue;
            }

            $missingEngineInputFiles[] = $inputFile;
            if ($reason === null) {
                $status = 'failed';
                $reason = 'missing-engine-input-file';
            }
            $diagnostics[] = 'missing-engine-input-file:' . $inputFile;
        }
        $sourceMapInputs = array_values($sourceMapInputsByKey);
        usort($sourceMapInputs, static fn (array $a, array $b): int => [$a['tag'], $a['path']] <=> [$b['tag'], $b['path']]);
        $sourceMapInputFileList = array_keys($sourceMapInputFiles);
        sort($sourceMapInputFileList);
        $sourceMapExternalInputList = array_keys($sourceMapExternalInputs);
        sort($sourceMapExternalInputList);
        $sourceMapLineRanges = array_values($sourceMapLineRangesByKey);
        usort($sourceMapLineRanges, static fn (array $a, array $b): int => [$a['tag'], $a['path']] <=> [$b['tag'], $b['path']]);
        if ($producedArtifactsSha256 !== []) {
            $diagnostics[] = 'produced-engine-artifacts:' . count($producedArtifactsSha256);
        }
        if ($engineDependencyArtifactsSha256 !== []) {
            $diagnostics[] = 'engine-dependency-artifacts:' . count($engineDependencyArtifactsSha256);
        }
        if ($engineInputFileList !== []) {
            $diagnostics[] = 'engine-dependency-files:' . count($engineInputFileList);
        }
        if ($engineExternalInputFileList !== []) {
            $diagnostics[] = 'engine-external-input-files:' . count($engineExternalInputFileList);
        }
        if ($engineOutputFileList !== []) {
            $diagnostics[] = 'engine-output-files:' . count($engineOutputFileList);
        }
        if ($bibliographyArtifactsSha256 !== []) {
            $diagnostics[] = 'bibliography-sidecars:' . count($bibliographyArtifactsSha256);
        }
        if ($bibliographyLogFiles !== []) {
            $diagnostics[] = 'bibliography-log-files:' . count($bibliographyLogFiles);
        }
        if ($sourceMapFiles !== []) {
            $diagnostics[] = 'source-map-files:' . count($sourceMapFiles);
        }
        if ($sourceMapInputs !== []) {
            $diagnostics[] = 'source-map-inputs:' . count($sourceMapInputs);
        }
        if ($sourceMapLineRanges !== []) {
            $diagnostics[] = 'source-map-line-ranges:' . count($sourceMapLineRanges);
        }
        if ($sourceMapExternalInputList !== []) {
            $diagnostics[] = 'source-map-external-inputs:' . count($sourceMapExternalInputList);
        }
        if ($sourceMapFiles !== [] && $sourceMapInputs === [] && $reason === null) {
            $status = 'failed';
            $reason = 'source-map-empty';
            $diagnostics[] = 'source-map-empty';
        }
        if (
            $sourceMapInputs !== []
            && !$this->sourceMapReferencesSource($sourceFile, $sourceMapInputs)
            && $reason === null
        ) {
            $status = 'failed';
            $reason = 'source-map-source-missing';
            $diagnostics[] = 'source-map-source-missing:' . $sourceFile;
        }

        $engineTexts = array_merge(
            [(string) ($result['stdout'] ?? ''), (string) ($result['stderr'] ?? '')],
            $engineLogTexts
        );
        try {
            $transcriptInputs = $this->extractEngineTranscriptInputs($engineTexts);
            foreach ($transcriptInputs['inputFiles'] as $inputFile) {
                $engineTranscriptInputFiles[$inputFile] = true;
            }
            foreach ($transcriptInputs['externalInputFiles'] as $externalInputFile) {
                $engineTranscriptExternalInputFiles[$externalInputFile] = true;
            }
        } catch (\RuntimeException $exception) {
            if ($reason === null) {
                $status = 'failed';
                $reason = 'engine-transcript-decode-error';
            }
            $diagnostics[] = 'engine-transcript-decode-error:' . $exception->getMessage();
        }
        $engineTranscriptInputFileList = array_keys($engineTranscriptInputFiles);
        sort($engineTranscriptInputFileList);
        $engineTranscriptExternalInputFileList = array_keys($engineTranscriptExternalInputFiles);
        sort($engineTranscriptExternalInputFileList);
        foreach ($engineTranscriptInputFileList as $inputFile) {
            if (array_key_exists($inputFile, $files)) {
                continue;
            }

            $missingEngineTranscriptInputFiles[] = $inputFile;
            if ($reason === null) {
                $status = 'failed';
                $reason = 'missing-engine-transcript-input-file';
            }
            $diagnostics[] = 'missing-engine-transcript-input-file:' . $inputFile;
        }
        if ($engineTranscriptInputFileList !== []) {
            $diagnostics[] = 'engine-transcript-input-files:' . count($engineTranscriptInputFileList);
        }
        if ($engineTranscriptExternalInputFileList !== []) {
            $diagnostics[] = 'engine-transcript-external-input-files:' . count($engineTranscriptExternalInputFileList);
        }

        $missingProgram = $this->extractMissingEngineProgram(
            $engineProgram,
            $exitCode,
            $engineTexts,
            $result['missingProgram'] ?? null
        );
        if ($missingProgram['missing']) {
            $diagnostics[] = 'engine-program-missing:' . $missingProgram['program'];
        }
        $engineMessages = $this->extractEngineMessages($engineTexts);
        $bibliographyMessages = $this->extractBibliographyMessages(array_merge($engineTexts, $bibliographyLogTexts));
        $declaredOutput = $this->extractDeclaredOutput($engineTexts);
        if ($engineMessages['warnings'] !== []) {
            $diagnostics[] = 'engine-log-warnings:' . count($engineMessages['warnings']);
        }
        if ($engineMessages['errors'] !== []) {
            $diagnostics[] = 'engine-log-errors:' . count($engineMessages['errors']);
        }
        if ($bibliographyMessages['warnings'] !== []) {
            $diagnostics[] = 'bibliography-warnings:' . count($bibliographyMessages['warnings']);
        }
        if ($bibliographyMessages['errors'] !== []) {
            $diagnostics[] = 'bibliography-errors:' . count($bibliographyMessages['errors']);
        }
        if ($engineMessages['rerunNeeded']) {
            $diagnostics[] = 'engine-rerun-needed';
        }
        if ($bibliographyMessages['needed']) {
            $diagnostics[] = 'bibliography-run-needed';
        }
        if ($declaredOutput['file'] !== null) {
            $diagnostics[] = 'engine-output-file:' . $declaredOutput['file'];
        }
        if ($declaredOutput['pages'] !== null) {
            $diagnostics[] = 'engine-output-pages:' . $declaredOutput['pages'];
        }
        if ($declaredOutput['bytes'] !== null) {
            $diagnostics[] = 'engine-output-bytes:' . $declaredOutput['bytes'];
        }

        $pdfBytes = array_key_exists($outputFile, $files) ? $files[$outputFile] : null;
        $pdfTrailerComplete = is_string($pdfBytes) && $this->hasCompletePdfTrailer($pdfBytes);
        $pdfHeaderVersion = null;
        $pdfCatalogVersion = null;
        $pdfEffectiveVersion = null;
        $pdfLinearization = [];
        $pdfExtensionMetadata = [];
        $pdfTrailerCount = 0;
        $pdfTrailerRevisions = [];
        $pdfStartXrefOffsets = [];
        $pdfIncrementalUpdates = false;
        $pdfXrefStreams = [];
        $pdfXrefStreamFilters = [];
        $pdfObjectStreams = [];
        $pdfObjectStreamFilters = [];
        $pdfPageCount = null;
        $pdfPageBoxes = [];
        $pdfPageRotations = [];
        $pdfPageProductionMetadata = [];
        $pdfPageDisplayMetadata = [];
        $pdfPageLabels = [];
        $pdfPageTimings = [];
        $pdfPageViewports = [];
        $pdfPageContentStreams = [];
        $pdfPageContentResourceUsage = [];
        $pdfPageResourceSources = [];
        $pdfFonts = [];
        $pdfFontSubtypes = [];
        $pdfImages = [];
        $pdfImageColorSpaces = [];
        $pdfImageFilters = [];
        $pdfColorSpaces = [];
        $pdfColorSpaceFamilies = [];
        $pdfFormXObjects = [];
        $pdfFormXObjectFilters = [];
        $pdfGraphicsStates = [];
        $pdfGraphicsStateBlendModes = [];
        $pdfOutlineTitles = [];
        $pdfOutlines = [];
        $pdfOutlineDisplayMetadata = [];
        $pdfDocumentInfo = [];
        $pdfDocumentInfoDateMetadata = [];
        $pdfXmpMetadata = [];
        $pdfPageMetadata = [];
        $pdfPieceInfo = [];
        $pdfWebCaptureMetadata = [];
        $pdfOutputIntents = [];
        $pdfPageOutputIntents = [];
        $pdfLanguage = null;
        $pdfPageLayout = null;
        $pdfPageMode = null;
        $pdfOpenAction = null;
        $pdfNamedDestinations = [];
        $pdfDestinationOptions = [];
        $pdfNameTrees = [];
        $pdfUriBase = null;
        $pdfViewerPreferences = [];
        $pdfNeedsRendering = null;
        $pdfCatalogRequirements = [];
        $pdfLegalAttestationMetadata = [];
        $pdfTaggingMetadata = [];
        $pdfStructureElements = [];
        $pdfMarkedContentProperties = [];
        $pdfMarkedContentArtifacts = [];
        $pdfOptionalContentGroups = [];
        $pdfOptionalContentConfig = [];
        $pdfOptionalContentMemberships = [];
        $pdfCollectionMetadata = [];
        $pdfAcroFormMetadata = [];
        $pdfAcroFormCalculationOrder = [];
        $pdfThreads = [];
        $pdfCatalogPermissions = [];
        $pdfSignatures = [];
        $pdfSignatureSubFilters = [];
        $pdfActiveActions = [];
        $pdfActiveActionTypes = [];
        $pdfRichMediaAnnotations = [];
        $pdfRichMediaActivationModes = [];
        $pdfAnnotations = [];
        $pdfAnnotationReviewMetadata = [];
        $pdfAnnotationAppearances = [];
        $pdfAnnotationTypes = [];
        $pdfLinkTargets = [];
        $pdfEmbeddedFileNames = [];
        $pdfEmbeddedFiles = [];
        $pdfFormFields = [];
        $pdfFormFieldTypes = [];
        $pdfFormFieldActions = [];
        $pdfFormFieldActionTypes = [];
        $pdfEncrypted = false;
        $pdfEncryptionFilter = null;
        $pdfEncryptionVersion = null;
        $pdfEncryptionRevision = null;
        $pdfEncryptionLength = null;
        $pdfPermissionInteger = null;
        $pdfPermissionFlags = [];
        $pdfEncryptMetadata = null;
        if (is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-')) {
            if (strlen($pdfBytes) > self::MAX_PDF_OUTPUT_INSPECTION_BYTES) {
                $diagnostics[] = 'pdf-byte-inspection-skipped:too-large';
            } else {
                $pdfInspection = $this->inspectPdfOutput($pdfBytes);
                $pdfHeaderVersion = $pdfInspection['headerVersion'];
                $pdfCatalogVersion = $pdfInspection['catalogVersion'];
                $pdfEffectiveVersion = $pdfInspection['effectiveVersion'];
                $pdfLinearization = $pdfInspection['linearization'];
                $pdfExtensionMetadata = $pdfInspection['extensionMetadata'];
                $pdfTrailerCount = $pdfInspection['trailerCount'];
                $pdfTrailerRevisions = $pdfInspection['trailerRevisions'];
                $pdfStartXrefOffsets = $pdfInspection['startXrefOffsets'];
                $pdfIncrementalUpdates = $pdfInspection['incrementalUpdates'];
                $pdfXrefStreams = $pdfInspection['xrefStreams'];
                $pdfXrefStreamFilters = $pdfInspection['xrefStreamFilters'];
                $pdfObjectStreams = $pdfInspection['objectStreams'];
                $pdfObjectStreamFilters = $pdfInspection['objectStreamFilters'];
                $pdfPageCount = $pdfInspection['pageCount'];
                $pdfPageBoxes = $pdfInspection['pageBoxes'];
                $pdfPageRotations = $pdfInspection['pageRotations'];
                $pdfPageProductionMetadata = $pdfInspection['pageProductionMetadata'];
                $pdfPageDisplayMetadata = $pdfInspection['pageDisplayMetadata'];
                $pdfPageLabels = $pdfInspection['pageLabels'];
                $pdfPageTimings = $pdfInspection['pageTimings'];
                $pdfPageViewports = $pdfInspection['pageViewports'];
                $pdfPageContentStreams = $pdfInspection['pageContentStreams'];
                $pdfPageContentResourceUsage = $pdfInspection['pageContentResourceUsage'];
                $pdfPageResourceSources = $pdfInspection['pageResourceSources'];
                $pdfFonts = $pdfInspection['fonts'];
                $pdfFontSubtypes = $pdfInspection['fontSubtypes'];
                $pdfImages = $pdfInspection['images'];
                $pdfImageColorSpaces = $pdfInspection['imageColorSpaces'];
                $pdfImageFilters = $pdfInspection['imageFilters'];
                $pdfColorSpaces = $pdfInspection['colorSpaces'];
                $pdfColorSpaceFamilies = $pdfInspection['colorSpaceFamilies'];
                $pdfFormXObjects = $pdfInspection['formXObjects'];
                $pdfFormXObjectFilters = $pdfInspection['formXObjectFilters'];
                $pdfGraphicsStates = $pdfInspection['graphicsStates'];
                $pdfGraphicsStateBlendModes = $pdfInspection['graphicsStateBlendModes'];
                $pdfOutlineTitles = $pdfInspection['outlineTitles'];
                $pdfOutlines = $pdfInspection['outlines'];
                $pdfOutlineDisplayMetadata = $pdfInspection['outlineDisplayMetadata'];
                $pdfDocumentInfo = $pdfInspection['documentInfo'];
                $pdfDocumentInfoDateMetadata = $pdfInspection['documentInfoDateMetadata'];
                $pdfXmpMetadata = $pdfInspection['xmpMetadata'];
                $pdfPageMetadata = $pdfInspection['pageMetadata'];
                $pdfPieceInfo = $pdfInspection['pieceInfo'];
                $pdfWebCaptureMetadata = $pdfInspection['webCaptureMetadata'];
                $pdfOutputIntents = $pdfInspection['outputIntents'];
                $pdfPageOutputIntents = $pdfInspection['pageOutputIntents'];
                $pdfLanguage = $pdfInspection['language'];
                $pdfPageLayout = $pdfInspection['pageLayout'];
                $pdfPageMode = $pdfInspection['pageMode'];
                $pdfOpenAction = $pdfInspection['openAction'];
                $pdfNamedDestinations = $pdfInspection['namedDestinations'];
                $pdfDestinationOptions = $pdfInspection['destinationOptions'];
                $pdfNameTrees = $pdfInspection['nameTrees'];
                $pdfUriBase = $pdfInspection['uriBase'];
                $pdfViewerPreferences = $pdfInspection['viewerPreferences'];
                $pdfNeedsRendering = $pdfInspection['needsRendering'];
                $pdfCatalogRequirements = $pdfInspection['catalogRequirements'];
                $pdfLegalAttestationMetadata = $pdfInspection['legalAttestationMetadata'];
                $pdfTaggingMetadata = $pdfInspection['taggingMetadata'];
                $pdfStructureElements = $pdfInspection['structureElements'];
                $pdfMarkedContentProperties = $pdfInspection['markedContentProperties'];
                $pdfMarkedContentArtifacts = $pdfInspection['markedContentArtifacts'];
                $pdfOptionalContentGroups = $pdfInspection['optionalContentGroups'];
                $pdfOptionalContentConfig = $pdfInspection['optionalContentConfig'];
                $pdfOptionalContentMemberships = $pdfInspection['optionalContentMemberships'];
                $pdfCollectionMetadata = $pdfInspection['collectionMetadata'];
                $pdfAcroFormMetadata = $pdfInspection['acroFormMetadata'];
                $pdfAcroFormCalculationOrder = $pdfInspection['acroFormCalculationOrder'];
                $pdfThreads = $pdfInspection['threads'];
                $pdfCatalogPermissions = $pdfInspection['catalogPermissions'];
                $pdfSignatures = $pdfInspection['signatures'];
                $pdfSignatureSubFilters = $pdfInspection['signatureSubFilters'];
                $pdfActiveActions = $pdfInspection['activeActions'];
                $pdfActiveActionTypes = $pdfInspection['activeActionTypes'];
                $pdfRichMediaAnnotations = $pdfInspection['richMediaAnnotations'];
                $pdfRichMediaActivationModes = $pdfInspection['richMediaActivationModes'];
                $pdfAnnotations = $pdfInspection['annotations'];
                $pdfAnnotationReviewMetadata = $pdfInspection['annotationReviewMetadata'];
                $pdfAnnotationAppearances = $pdfInspection['annotationAppearances'];
                $pdfAnnotationTypes = $pdfInspection['annotationTypes'];
                $pdfLinkTargets = $pdfInspection['linkTargets'];
                $pdfEmbeddedFileNames = $pdfInspection['embeddedFileNames'];
                $pdfEmbeddedFiles = $pdfInspection['embeddedFiles'];
                $pdfFormFields = $pdfInspection['formFields'];
                $pdfFormFieldTypes = $pdfInspection['formFieldTypes'];
                $pdfFormFieldActions = $pdfInspection['formFieldActions'];
                $pdfFormFieldActionTypes = $pdfInspection['formFieldActionTypes'];
                $pdfEncryption = $pdfInspection['encryption'];
                $pdfEncrypted = $pdfEncryption['encrypted'];
                $pdfEncryptionFilter = $pdfEncryption['filter'];
                $pdfEncryptionVersion = $pdfEncryption['version'];
                $pdfEncryptionRevision = $pdfEncryption['revision'];
                $pdfEncryptionLength = $pdfEncryption['length'];
                $pdfPermissionInteger = $pdfEncryption['permissions'];
                $pdfPermissionFlags = $pdfEncryption['permissionFlags'];
                $pdfEncryptMetadata = $pdfEncryption['encryptMetadata'];
                if ($pdfHeaderVersion !== null) {
                    $diagnostics[] = 'pdf-byte-header-version:' . $pdfHeaderVersion;
                }
                if ($pdfCatalogVersion !== null) {
                    $diagnostics[] = 'pdf-byte-catalog-version:' . $pdfCatalogVersion;
                }
                if ($pdfEffectiveVersion !== null) {
                    $diagnostics[] = 'pdf-byte-effective-version:' . $pdfEffectiveVersion;
                }
                if ($pdfLinearization !== []) {
                    $diagnostics[] = 'pdf-byte-linearized';
                    if (($pdfLinearization['linearizedVersion'] ?? null) !== null) {
                        $diagnostics[] = 'pdf-byte-linearized-version:' . $pdfLinearization['linearizedVersion'];
                    }
                    if (($pdfLinearization['pageCount'] ?? null) !== null) {
                        $diagnostics[] = 'pdf-byte-linearized-page-count:' . $pdfLinearization['pageCount'];
                    }
                    if (isset($pdfLinearization['hintTables']) && is_array($pdfLinearization['hintTables']) && $pdfLinearization['hintTables'] !== []) {
                        $diagnostics[] = 'pdf-byte-linearized-hint-tables:' . count($pdfLinearization['hintTables']);
                    }
                    if (($pdfLinearization['lengthMatches'] ?? null) === false && ($pdfLinearization['fileLength'] ?? null) !== null) {
                        $diagnostics[] = 'pdf-byte-linearized-length-mismatch:' . $pdfLinearization['fileLength'] . ':' . strlen($pdfBytes);
                    }
                }
                if ($pdfExtensionMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-extension-metadata:' . count($pdfExtensionMetadata);
                }
                if ($pdfPageCount !== null) {
                    $diagnostics[] = 'pdf-byte-page-count:' . $pdfPageCount;
                }
                if ($pdfPageBoxes !== []) {
                    $diagnostics[] = 'pdf-byte-page-boxes:' . count($pdfPageBoxes);
                }
                if ($pdfPageRotations !== []) {
                    $diagnostics[] = 'pdf-byte-page-rotations:' . count($pdfPageRotations);
                }
                if ($pdfPageProductionMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-page-production-metadata:' . count($pdfPageProductionMetadata);
                    $boxColorInfoCount = 0;
                    $separationInfoCount = 0;
                    $presentationStepsCount = 0;
                    foreach ($pdfPageProductionMetadata as $productionMetadata) {
                        if (isset($productionMetadata['boxColorInfo']) && is_array($productionMetadata['boxColorInfo'])) {
                            $boxColorInfoCount += count($productionMetadata['boxColorInfo']);
                        }
                        if (($productionMetadata['separationInfoObject'] ?? null) !== null) {
                            $separationInfoCount++;
                        }
                        if (($productionMetadata['presStepsObject'] ?? null) !== null) {
                            $presentationStepsCount++;
                        }
                    }
                    if ($boxColorInfoCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-box-color-info:' . $boxColorInfoCount;
                    }
                    if ($separationInfoCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-separation-info:' . $separationInfoCount;
                    }
                    if ($presentationStepsCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-presentation-steps:' . $presentationStepsCount;
                    }
                }
                if ($pdfPageDisplayMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-page-display-metadata:' . count($pdfPageDisplayMetadata);
                    $pageUserUnitCount = 0;
                    $pageTabOrderCount = 0;
                    $pageGroupCount = 0;
                    $pageThumbnailCount = 0;
                    $pageLastModifiedCount = 0;
                    foreach ($pdfPageDisplayMetadata as $pageDisplay) {
                        if (($pageDisplay['userUnit'] ?? null) !== null) {
                            $pageUserUnitCount++;
                        }
                        if (($pageDisplay['tabOrder'] ?? null) !== null) {
                            $pageTabOrderCount++;
                        }
                        if (($pageDisplay['groupSubtype'] ?? null) !== null) {
                            $pageGroupCount++;
                        }
                        if (($pageDisplay['thumbnailObject'] ?? null) !== null) {
                            $pageThumbnailCount++;
                        }
                        if (($pageDisplay['lastModified'] ?? null) !== null) {
                            $pageLastModifiedCount++;
                        }
                    }
                    if ($pageUserUnitCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-user-units:' . $pageUserUnitCount;
                    }
                    if ($pageTabOrderCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-tab-orders:' . $pageTabOrderCount;
                    }
                    foreach ($this->summarizePdfPageTabOrders($pdfPageDisplayMetadata) as $tabOrder => $tabOrderCount) {
                        $diagnostics[] = 'pdf-byte-page-tab-order:' . $tabOrder . ':' . $tabOrderCount;
                    }
                    if ($pageGroupCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-groups:' . $pageGroupCount;
                    }
                    if ($pageThumbnailCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-thumbnails:' . $pageThumbnailCount;
                    }
                    if ($pageLastModifiedCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-last-modified:' . $pageLastModifiedCount;
                    }
                }
                if ($pdfPageLabels !== []) {
                    $diagnostics[] = 'pdf-byte-page-labels:' . count($pdfPageLabels);
                }
                if ($pdfPageTimings !== []) {
                    $diagnostics[] = 'pdf-byte-page-timings:' . count($pdfPageTimings);
                    $pageDurationCount = 0;
                    $pageTransitionCount = 0;
                    foreach ($pdfPageTimings as $pageTiming) {
                        if (($pageTiming['duration'] ?? null) !== null) {
                            $pageDurationCount++;
                        }
                        if ($this->pdfPageTimingHasTransition($pageTiming)) {
                            $pageTransitionCount++;
                        }
                    }
                    if ($pageDurationCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-durations:' . $pageDurationCount;
                    }
                    if ($pageTransitionCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-transitions:' . $pageTransitionCount;
                    }
                    foreach ($this->summarizePdfPageTransitionTypes($pdfPageTimings) as $transitionType => $transitionCount) {
                        $diagnostics[] = 'pdf-byte-page-transition-type:' . $transitionType . ':' . $transitionCount;
                    }
                    foreach ($this->summarizePdfPageTransitionDirectionLabels($pdfPageTimings) as $directionLabel => $directionCount) {
                        $diagnostics[] = 'pdf-byte-page-transition-direction:' . $directionLabel . ':' . $directionCount;
                    }
                }
                if ($pdfPageViewports !== []) {
                    $diagnostics[] = 'pdf-byte-page-viewports:' . count($pdfPageViewports);
                    $viewportMeasureCount = 0;
                    $viewportUnitFormatCount = 0;
                    foreach ($pdfPageViewports as $pageViewport) {
                        if (($pageViewport['measureSubtype'] ?? null) !== null) {
                            $viewportMeasureCount++;
                        }
                        foreach (['xUnits', 'yUnits', 'distanceUnits', 'areaUnits', 'angleUnits'] as $unitKey) {
                            if (isset($pageViewport[$unitKey]) && is_array($pageViewport[$unitKey])) {
                                $viewportUnitFormatCount += count($pageViewport[$unitKey]);
                            }
                        }
                    }
                    if ($viewportMeasureCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-viewport-measures:' . $viewportMeasureCount;
                    }
                    if ($viewportUnitFormatCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-viewport-unit-formats:' . $viewportUnitFormatCount;
                    }
                }
                if ($pdfPageContentStreams !== []) {
                    $diagnostics[] = 'pdf-byte-page-content-streams:' . count($pdfPageContentStreams);
                    $pageContentTextObjects = 0;
                    $pageContentImages = 0;
                    $pageContentForms = 0;
                    $pageContentMarkedBegins = 0;
                    $pageContentStreamSkips = [];
                    foreach ($pdfPageContentStreams as $contentStream) {
                        $pageContentTextObjects += $contentStream['textObjectCount'] ?? 0;
                        $pageContentImages += $contentStream['imagePaintCount'] ?? 0;
                        $pageContentForms += $contentStream['formPaintCount'] ?? 0;
                        $pageContentMarkedBegins += $contentStream['markedContentBeginCount'] ?? 0;
                        if (is_string($contentStream['streamSkipped'] ?? null) && $contentStream['streamSkipped'] !== '') {
                            $pageContentStreamSkips[$contentStream['streamSkipped']] = true;
                        }
                    }
                    if ($pageContentTextObjects > 0) {
                        $diagnostics[] = 'pdf-byte-page-content-text-objects:' . $pageContentTextObjects;
                    }
                    if ($pageContentImages > 0) {
                        $diagnostics[] = 'pdf-byte-page-content-image-paints:' . $pageContentImages;
                    }
                    if ($pageContentForms > 0) {
                        $diagnostics[] = 'pdf-byte-page-content-form-paints:' . $pageContentForms;
                    }
                    if ($pageContentMarkedBegins > 0) {
                        $diagnostics[] = 'pdf-byte-page-content-marked-begins:' . $pageContentMarkedBegins;
                    }
                    foreach (array_keys($pageContentStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-page-content-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfPageContentResourceUsage !== []) {
                    $diagnostics[] = 'pdf-byte-page-content-resources:' . count($pdfPageContentResourceUsage);
                    foreach ($pdfPageContentResourceUsage as $resourceName => $resourceUseCount) {
                        $diagnostics[] = 'pdf-byte-page-content-resource:' . $resourceName . ':' . $resourceUseCount;
                    }
                }
                if ($pdfPageResourceSources !== []) {
                    $diagnostics[] = 'pdf-byte-page-resource-sources:' . count($pdfPageResourceSources);
                    $inheritedPageResourceSources = 0;
                    $pageResourceCategories = [];
                    foreach ($pdfPageResourceSources as $pageResourceSource) {
                        if (($pageResourceSource['inherited'] ?? false) === true) {
                            $inheritedPageResourceSources++;
                        }
                        foreach (($pageResourceSource['categories'] ?? []) as $category) {
                            if (!is_string($category) || $category === '') {
                                continue;
                            }
                            $pageResourceCategories[$category] = ($pageResourceCategories[$category] ?? 0) + 1;
                        }
                    }
                    if ($inheritedPageResourceSources > 0) {
                        $diagnostics[] = 'pdf-byte-page-resource-inherited:' . $inheritedPageResourceSources;
                    }
                    ksort($pageResourceCategories);
                    foreach ($pageResourceCategories as $category => $count) {
                        $diagnostics[] = 'pdf-byte-page-resource-category:' . $category . ':' . $count;
                    }
                }
                if ($pdfFonts !== []) {
                    $diagnostics[] = 'pdf-byte-font-resources:' . count($pdfFonts);
                    $embeddedFonts = 0;
                    $fontStreamSkips = [];
                    foreach ($pdfFonts as $font) {
                        if (($font['embedded'] ?? false) === true) {
                            $embeddedFonts++;
                        }
                        if (is_string($font['embeddedFileSkipped'] ?? null) && $font['embeddedFileSkipped'] !== '') {
                            $fontStreamSkips[$font['embeddedFileSkipped']] = true;
                        }
                    }
                    if ($embeddedFonts > 0) {
                        $diagnostics[] = 'pdf-byte-embedded-fonts:' . $embeddedFonts;
                    }
                    foreach (array_keys($fontStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-font-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfFontSubtypes !== []) {
                    $diagnostics[] = 'pdf-byte-font-subtypes:' . count($pdfFontSubtypes);
                }
                if ($pdfImages !== []) {
                    $diagnostics[] = 'pdf-byte-image-xobjects:' . count($pdfImages);
                    $imageStreams = 0;
                    $imageStreamSkips = [];
                    foreach ($pdfImages as $image) {
                        if (($image['streamBytes'] ?? null) !== null) {
                            $imageStreams++;
                        }
                        if (is_string($image['streamSkipped'] ?? null) && $image['streamSkipped'] !== '') {
                            $imageStreamSkips[$image['streamSkipped']] = true;
                        }
                    }
                    if ($imageStreams > 0) {
                        $diagnostics[] = 'pdf-byte-image-streams:' . $imageStreams;
                    }
                    foreach (array_keys($imageStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-image-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfImageColorSpaces !== []) {
                    $diagnostics[] = 'pdf-byte-image-color-spaces:' . count($pdfImageColorSpaces);
                }
                if ($pdfImageFilters !== []) {
                    $diagnostics[] = 'pdf-byte-image-filters:' . count($pdfImageFilters);
                    foreach ($pdfImageFilters as $filter => $filterCount) {
                        $diagnostics[] = 'pdf-byte-image-filter:' . $filter . ':' . $filterCount;
                    }
                }
                if ($pdfColorSpaces !== []) {
                    $diagnostics[] = 'pdf-byte-color-spaces:' . count($pdfColorSpaces);
                    $colorSpaceProfiles = 0;
                    $colorSpaceTintTransforms = 0;
                    $colorSpaceProfileSkips = [];
                    foreach ($pdfColorSpaces as $colorSpace) {
                        if (
                            ($colorSpace['profileComponents'] ?? null) !== null
                            || ($colorSpace['profileAlternate'] ?? null) !== null
                            || ($colorSpace['profileBytes'] ?? null) !== null
                            || ($colorSpace['profileSkipped'] ?? null) !== null
                        ) {
                            $colorSpaceProfiles++;
                        }
                        if (($colorSpace['tintTransform'] ?? null) !== null) {
                            $colorSpaceTintTransforms++;
                        }
                        if (is_string($colorSpace['profileSkipped'] ?? null) && $colorSpace['profileSkipped'] !== '') {
                            $colorSpaceProfileSkips[$colorSpace['profileSkipped']] = true;
                        }
                    }
                    if ($colorSpaceProfiles > 0) {
                        $diagnostics[] = 'pdf-byte-color-space-profiles:' . $colorSpaceProfiles;
                    }
                    if ($colorSpaceTintTransforms > 0) {
                        $diagnostics[] = 'pdf-byte-color-space-tint-transforms:' . $colorSpaceTintTransforms;
                    }
                    foreach (array_keys($colorSpaceProfileSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-color-space-profile-skipped:' . $skipReason;
                    }
                }
                if ($pdfColorSpaceFamilies !== []) {
                    $diagnostics[] = 'pdf-byte-color-space-families:' . count($pdfColorSpaceFamilies);
                    foreach ($pdfColorSpaceFamilies as $family => $familyCount) {
                        $diagnostics[] = 'pdf-byte-color-space-family:' . $family . ':' . $familyCount;
                    }
                }
                if ($pdfFormXObjects !== []) {
                    $diagnostics[] = 'pdf-byte-form-xobjects:' . count($pdfFormXObjects);
                    $formStreams = 0;
                    $formGroups = 0;
                    $formStreamSkips = [];
                    foreach ($pdfFormXObjects as $formXObject) {
                        if (($formXObject['streamBytes'] ?? null) !== null) {
                            $formStreams++;
                        }
                        if (
                            ($formXObject['groupSubtype'] ?? null) !== null
                            || ($formXObject['groupColorSpace'] ?? null) !== null
                            || ($formXObject['groupIsolated'] ?? null) !== null
                            || ($formXObject['groupKnockout'] ?? null) !== null
                        ) {
                            $formGroups++;
                        }
                        if (is_string($formXObject['streamSkipped'] ?? null) && $formXObject['streamSkipped'] !== '') {
                            $formStreamSkips[$formXObject['streamSkipped']] = true;
                        }
                    }
                    if ($formStreams > 0) {
                        $diagnostics[] = 'pdf-byte-form-xobject-streams:' . $formStreams;
                    }
                    if ($formGroups > 0) {
                        $diagnostics[] = 'pdf-byte-form-xobject-groups:' . $formGroups;
                    }
                    foreach (array_keys($formStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-form-xobject-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfFormXObjectFilters !== []) {
                    $diagnostics[] = 'pdf-byte-form-xobject-filters:' . count($pdfFormXObjectFilters);
                    foreach ($pdfFormXObjectFilters as $filter => $filterCount) {
                        $diagnostics[] = 'pdf-byte-form-xobject-filter:' . $filter . ':' . $filterCount;
                    }
                }
                if ($pdfGraphicsStates !== []) {
                    $diagnostics[] = 'pdf-byte-graphics-states:' . count($pdfGraphicsStates);
                    $graphicsStateAlphaCount = 0;
                    $graphicsStateSoftMaskCount = 0;
                    $graphicsStateOverprintCount = 0;
                    foreach ($pdfGraphicsStates as $graphicsState) {
                        if (
                            ($graphicsState['strokingAlpha'] ?? null) !== null
                            || ($graphicsState['nonstrokingAlpha'] ?? null) !== null
                        ) {
                            $graphicsStateAlphaCount++;
                        }
                        if (($graphicsState['softMask'] ?? null) !== null) {
                            $graphicsStateSoftMaskCount++;
                        }
                        if (
                            ($graphicsState['overprintStroking'] ?? null) !== null
                            || ($graphicsState['overprintNonstroking'] ?? null) !== null
                            || ($graphicsState['overprintMode'] ?? null) !== null
                        ) {
                            $graphicsStateOverprintCount++;
                        }
                    }
                    if ($graphicsStateAlphaCount > 0) {
                        $diagnostics[] = 'pdf-byte-graphics-state-alpha:' . $graphicsStateAlphaCount;
                    }
                    if ($pdfGraphicsStateBlendModes !== []) {
                        $diagnostics[] = 'pdf-byte-graphics-state-blend-modes:' . array_sum($pdfGraphicsStateBlendModes);
                        foreach ($pdfGraphicsStateBlendModes as $blendMode => $blendModeCount) {
                            $diagnostics[] = 'pdf-byte-graphics-state-blend-mode:' . $blendMode . ':' . $blendModeCount;
                        }
                    }
                    if ($graphicsStateSoftMaskCount > 0) {
                        $diagnostics[] = 'pdf-byte-graphics-state-soft-masks:' . $graphicsStateSoftMaskCount;
                    }
                    if ($graphicsStateOverprintCount > 0) {
                        $diagnostics[] = 'pdf-byte-graphics-state-overprint:' . $graphicsStateOverprintCount;
                    }
                }
                if ($pdfTrailerCount > 0) {
                    $diagnostics[] = 'pdf-byte-trailers:' . $pdfTrailerCount;
                }
                if ($pdfStartXrefOffsets !== []) {
                    $diagnostics[] = 'pdf-byte-startxref:' . count($pdfStartXrefOffsets);
                }
                if ($pdfIncrementalUpdates) {
                    $diagnostics[] = 'pdf-byte-incremental-updates';
                }
                if ($pdfXrefStreams !== []) {
                    $diagnostics[] = 'pdf-byte-xref-streams:' . count($pdfXrefStreams);
                    $xrefStreamSkips = [];
                    foreach ($pdfXrefStreams as $xrefStream) {
                        if (is_string($xrefStream['streamSkipped'] ?? null) && $xrefStream['streamSkipped'] !== '') {
                            $xrefStreamSkips[$xrefStream['streamSkipped']] = true;
                        }
                    }
                    foreach (array_keys($xrefStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-xref-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfXrefStreamFilters !== []) {
                    $diagnostics[] = 'pdf-byte-xref-stream-filters:' . count($pdfXrefStreamFilters);
                    foreach ($pdfXrefStreamFilters as $filter => $filterCount) {
                        $diagnostics[] = 'pdf-byte-xref-stream-filter:' . $filter . ':' . $filterCount;
                    }
                }
                if ($pdfObjectStreams !== []) {
                    $diagnostics[] = 'pdf-byte-object-streams:' . count($pdfObjectStreams);
                    $objectStreamObjectCount = 0;
                    $objectStreamSkips = [];
                    foreach ($pdfObjectStreams as $objectStream) {
                        if (is_array($objectStream['objectNumbers'] ?? null)) {
                            $objectStreamObjectCount += count($objectStream['objectNumbers']);
                        }
                        if (is_string($objectStream['streamSkipped'] ?? null) && $objectStream['streamSkipped'] !== '') {
                            $objectStreamSkips[$objectStream['streamSkipped']] = true;
                        }
                    }
                    if ($objectStreamObjectCount > 0) {
                        $diagnostics[] = 'pdf-byte-object-stream-objects:' . $objectStreamObjectCount;
                    }
                    foreach (array_keys($objectStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-object-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfObjectStreamFilters !== []) {
                    $diagnostics[] = 'pdf-byte-object-stream-filters:' . count($pdfObjectStreamFilters);
                    foreach ($pdfObjectStreamFilters as $filter => $filterCount) {
                        $diagnostics[] = 'pdf-byte-object-stream-filter:' . $filter . ':' . $filterCount;
                    }
                }
                if ($pdfOutlineTitles !== []) {
                    $diagnostics[] = 'pdf-byte-outline-items:' . count($pdfOutlineTitles);
                }
                if ($pdfOutlines !== []) {
                    $diagnostics[] = 'pdf-byte-outline-metadata:' . count($pdfOutlines);
                    $outlineOpenCount = 0;
                    $outlineClosedCount = 0;
                    $outlineDestinationCount = 0;
                    $outlineActionCount = 0;
                    foreach ($pdfOutlines as $outline) {
                        if (($outline['open'] ?? null) === true) {
                            $outlineOpenCount++;
                        } elseif (($outline['open'] ?? null) === false) {
                            $outlineClosedCount++;
                        }
                        if (($outline['destPageObject'] ?? null) !== null || ($outline['destFit'] ?? null) !== null) {
                            $outlineDestinationCount++;
                        }
                        if (($outline['actionType'] ?? null) !== null) {
                            $outlineActionCount++;
                        }
                    }
                    if ($outlineOpenCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-open:' . $outlineOpenCount;
                    }
                    if ($outlineClosedCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-closed:' . $outlineClosedCount;
                    }
                    if ($outlineDestinationCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-destinations:' . $outlineDestinationCount;
                    }
                    if ($outlineActionCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-actions:' . $outlineActionCount;
                    }
                }
                if ($pdfOutlineDisplayMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-outline-display-metadata:' . count($pdfOutlineDisplayMetadata);
                    $outlineColorCount = 0;
                    $outlineFlagCount = 0;
                    $outlineBoldCount = 0;
                    $outlineItalicCount = 0;
                    foreach ($pdfOutlineDisplayMetadata as $outlineDisplay) {
                        if (($outlineDisplay['color'] ?? null) !== null) {
                            $outlineColorCount++;
                        }
                        $flags = is_int($outlineDisplay['flags'] ?? null) ? $outlineDisplay['flags'] : 0;
                        if ($flags !== 0) {
                            $outlineFlagCount++;
                        }
                        if (($flags & 2) !== 0) {
                            $outlineBoldCount++;
                        }
                        if (($flags & 1) !== 0) {
                            $outlineItalicCount++;
                        }
                    }
                    if ($outlineColorCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-display-colors:' . $outlineColorCount;
                    }
                    if ($outlineFlagCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-display-flags:' . $outlineFlagCount;
                    }
                    if ($outlineBoldCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-display-bold:' . $outlineBoldCount;
                    }
                    if ($outlineItalicCount > 0) {
                        $diagnostics[] = 'pdf-byte-outline-display-italic:' . $outlineItalicCount;
                    }
                }
                if ($pdfDocumentInfo !== []) {
                    $diagnostics[] = 'pdf-byte-document-info:' . count($pdfDocumentInfo);
                }
                if ($pdfDocumentInfoDateMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-document-info-dates:' . count($pdfDocumentInfoDateMetadata);
                    $normalizedDateCount = 0;
                    $timezoneDateCount = 0;
                    $invalidDateCount = 0;
                    foreach ($pdfDocumentInfoDateMetadata as $dateMetadata) {
                        if (($dateMetadata['valid'] ?? false) === true && is_string($dateMetadata['normalized'] ?? null)) {
                            $normalizedDateCount++;
                        }
                        if (is_string($dateMetadata['timezone'] ?? null)) {
                            $timezoneDateCount++;
                        }
                        if (($dateMetadata['valid'] ?? true) !== true) {
                            $invalidDateCount++;
                        }
                    }
                    if ($normalizedDateCount > 0) {
                        $diagnostics[] = 'pdf-byte-document-info-date-normalized:' . $normalizedDateCount;
                    }
                    if ($timezoneDateCount > 0) {
                        $diagnostics[] = 'pdf-byte-document-info-date-timezones:' . $timezoneDateCount;
                    }
                    if ($invalidDateCount > 0) {
                        $diagnostics[] = 'pdf-byte-document-info-date-invalid:' . $invalidDateCount;
                    }
                }
                if ($pdfXmpMetadata !== []) {
                    if (($pdfXmpMetadata['skipped'] ?? null) === 'filtered') {
                        $diagnostics[] = 'pdf-byte-xmp-metadata-skipped:filtered';
                    } elseif (($pdfXmpMetadata['skipped'] ?? null) === 'too-large') {
                        $diagnostics[] = 'pdf-byte-xmp-metadata-skipped:too-large';
                    } else {
                        $diagnostics[] = 'pdf-byte-xmp-metadata:' . count($pdfXmpMetadata);
                        if (isset($pdfXmpMetadata['pdfaIdentification']) && is_array($pdfXmpMetadata['pdfaIdentification'])) {
                            $part = is_string($pdfXmpMetadata['pdfaIdentification']['part'] ?? null)
                                ? $pdfXmpMetadata['pdfaIdentification']['part']
                                : '';
                            $conformance = is_string($pdfXmpMetadata['pdfaIdentification']['conformance'] ?? null)
                                ? $pdfXmpMetadata['pdfaIdentification']['conformance']
                                : '';
                            if ($part !== '' || $conformance !== '') {
                                $diagnostics[] = 'pdf-byte-pdfa:' . $part . ':' . $conformance;
                            }
                        }
                        if (isset($pdfXmpMetadata['pdfuaIdentification']) && is_array($pdfXmpMetadata['pdfuaIdentification'])) {
                            $part = is_string($pdfXmpMetadata['pdfuaIdentification']['part'] ?? null)
                                ? $pdfXmpMetadata['pdfuaIdentification']['part']
                                : '';
                            if ($part !== '') {
                                $diagnostics[] = 'pdf-byte-pdfua:' . $part;
                            }
                            $amendment = is_string($pdfXmpMetadata['pdfuaIdentification']['amendment'] ?? null)
                                ? $pdfXmpMetadata['pdfuaIdentification']['amendment']
                                : '';
                            if ($amendment !== '') {
                                $diagnostics[] = 'pdf-byte-pdfua-amendment:' . $amendment;
                            }
                            $corrigendum = is_string($pdfXmpMetadata['pdfuaIdentification']['corrigendum'] ?? null)
                                ? $pdfXmpMetadata['pdfuaIdentification']['corrigendum']
                                : '';
                            if ($corrigendum !== '') {
                                $diagnostics[] = 'pdf-byte-pdfua-corrigendum:' . $corrigendum;
                            }
                        }
                        if (isset($pdfXmpMetadata['pdfaExtensionSchemas']) && is_array($pdfXmpMetadata['pdfaExtensionSchemas'])) {
                            $schemaPropertyCount = 0;
                            $schemaPrefixes = [];
                            foreach ($pdfXmpMetadata['pdfaExtensionSchemas'] as $schema) {
                                if (isset($schema['properties']) && is_array($schema['properties'])) {
                                    $schemaPropertyCount += count($schema['properties']);
                                }
                                if (is_string($schema['prefix'] ?? null) && $schema['prefix'] !== '') {
                                    $schemaPrefixes[$schema['prefix']] = true;
                                }
                            }
                            $diagnostics[] = 'pdf-byte-pdfa-extension-schemas:' . count($pdfXmpMetadata['pdfaExtensionSchemas']);
                            if ($schemaPropertyCount > 0) {
                                $diagnostics[] = 'pdf-byte-pdfa-extension-properties:' . $schemaPropertyCount;
                            }
                            foreach (array_keys($schemaPrefixes) as $schemaPrefix) {
                                $diagnostics[] = 'pdf-byte-pdfa-extension-prefix:' . $schemaPrefix;
                            }
                        }
                    }
                }
                if ($pdfPageMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-page-metadata:' . count($pdfPageMetadata);
                    $pageMetadataSkips = [];
                    $pageMetadataTitleCount = 0;
                    foreach ($pdfPageMetadata as $metadata) {
                        if (is_string($metadata['skipped'] ?? null) && $metadata['skipped'] !== '') {
                            $pageMetadataSkips[$metadata['skipped']] = true;
                        }
                        if (is_string($metadata['title'] ?? null) && $metadata['title'] !== '') {
                            $pageMetadataTitleCount++;
                        }
                    }
                    foreach (array_keys($pageMetadataSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-page-metadata-skipped:' . $skipReason;
                    }
                    if ($pageMetadataTitleCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-metadata-titles:' . $pageMetadataTitleCount;
                    }
                }
                if ($pdfPieceInfo !== []) {
                    $diagnostics[] = 'pdf-byte-piece-info:' . count($pdfPieceInfo);
                    $pieceInfoPageCount = 0;
                    $pieceInfoPrivateStreamCount = 0;
                    $pieceInfoStreamSkips = [];
                    foreach ($pdfPieceInfo as $pieceInfo) {
                        if (($pieceInfo['page'] ?? null) !== null) {
                            $pieceInfoPageCount++;
                        }
                        if (($pieceInfo['privateStreamBytes'] ?? null) !== null) {
                            $pieceInfoPrivateStreamCount++;
                        }
                        if (is_string($pieceInfo['privateStreamSkipped'] ?? null) && $pieceInfo['privateStreamSkipped'] !== '') {
                            $pieceInfoStreamSkips[$pieceInfo['privateStreamSkipped']] = true;
                        }
                    }
                    if ($pieceInfoPageCount > 0) {
                        $diagnostics[] = 'pdf-byte-piece-info-pages:' . $pieceInfoPageCount;
                    }
                    if ($pieceInfoPrivateStreamCount > 0) {
                        $diagnostics[] = 'pdf-byte-piece-info-private-streams:' . $pieceInfoPrivateStreamCount;
                    }
                    foreach (array_keys($pieceInfoStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-piece-info-private-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfWebCaptureMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-web-capture:' . count($pdfWebCaptureMetadata);
                    $webCaptureCommandCount = 0;
                    $webCapturePageCount = 0;
                    $webCaptureUrls = [];
                    foreach ($pdfWebCaptureMetadata as $webCapture) {
                        $webCaptureCommandCount += (int) ($webCapture['commandCount'] ?? 0);
                        if (($webCapture['page'] ?? null) !== null) {
                            $webCapturePageCount++;
                        }
                        if (isset($webCapture['sourceUrls']) && is_array($webCapture['sourceUrls'])) {
                            foreach ($webCapture['sourceUrls'] as $sourceUrl) {
                                if (is_string($sourceUrl) && $sourceUrl !== '') {
                                    $webCaptureUrls[$sourceUrl] = true;
                                }
                            }
                        }
                    }
                    if ($webCaptureCommandCount > 0) {
                        $diagnostics[] = 'pdf-byte-web-capture-commands:' . $webCaptureCommandCount;
                    }
                    if ($webCapturePageCount > 0) {
                        $diagnostics[] = 'pdf-byte-web-capture-pages:' . $webCapturePageCount;
                    }
                    if ($webCaptureUrls !== []) {
                        $diagnostics[] = 'pdf-byte-web-capture-urls:' . count($webCaptureUrls);
                    }
                }
                if ($pdfOutputIntents !== []) {
                    $diagnostics[] = 'pdf-byte-output-intents:' . count($pdfOutputIntents);
                    $profileCount = 0;
                    $profileSkips = [];
                    foreach ($pdfOutputIntents as $intent) {
                        if (($intent['destOutputProfile'] ?? null) !== null) {
                            $profileCount++;
                        }
                        if (is_string($intent['profileSkipped'] ?? null) && $intent['profileSkipped'] !== '') {
                            $profileSkips[$intent['profileSkipped']] = true;
                        }
                    }
                    if ($profileCount > 0) {
                        $diagnostics[] = 'pdf-byte-output-profiles:' . $profileCount;
                    }
                    foreach (array_keys($profileSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-output-profile-skipped:' . $skipReason;
                    }
                }
                if ($pdfPageOutputIntents !== []) {
                    $diagnostics[] = 'pdf-byte-page-output-intents:' . count($pdfPageOutputIntents);
                    $profileCount = 0;
                    $profileSkips = [];
                    foreach ($pdfPageOutputIntents as $intent) {
                        if (($intent['destOutputProfile'] ?? null) !== null) {
                            $profileCount++;
                        }
                        if (is_string($intent['profileSkipped'] ?? null) && $intent['profileSkipped'] !== '') {
                            $profileSkips[$intent['profileSkipped']] = true;
                        }
                    }
                    if ($profileCount > 0) {
                        $diagnostics[] = 'pdf-byte-page-output-profiles:' . $profileCount;
                    }
                    foreach (array_keys($profileSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-page-output-profile-skipped:' . $skipReason;
                    }
                }
                if ($pdfLanguage !== null) {
                    $diagnostics[] = 'pdf-byte-language:' . $pdfLanguage;
                }
                if ($pdfPageLayout !== null) {
                    $diagnostics[] = 'pdf-byte-page-layout:' . $pdfPageLayout;
                }
                if ($pdfPageMode !== null) {
                    $diagnostics[] = 'pdf-byte-page-mode:' . $pdfPageMode;
                }
                if ($pdfOpenAction !== null) {
                    $diagnostics[] = 'pdf-byte-open-action:' . ($pdfOpenAction['type'] ?? 'unknown');
                }
                if ($pdfNamedDestinations !== []) {
                    $diagnostics[] = 'pdf-byte-named-destinations:' . count($pdfNamedDestinations);
                }
                if ($pdfDestinationOptions !== []) {
                    $diagnostics[] = 'pdf-byte-destination-options:' . count($pdfDestinationOptions);
                    $destinationOptionFitArguments = 0;
                    $destinationOptionNamedTargets = 0;
                    foreach ($pdfDestinationOptions as $destinationOption) {
                        if (isset($destinationOption['arguments']) && is_array($destinationOption['arguments']) && $destinationOption['arguments'] !== []) {
                            $destinationOptionFitArguments++;
                        }
                        if (is_string($destinationOption['target'] ?? null) && $destinationOption['target'] !== '') {
                            $destinationOptionNamedTargets++;
                        }
                    }
                    if ($destinationOptionFitArguments > 0) {
                        $diagnostics[] = 'pdf-byte-destination-fit-arguments:' . $destinationOptionFitArguments;
                    }
                    if ($destinationOptionNamedTargets > 0) {
                        $diagnostics[] = 'pdf-byte-destination-named-targets:' . $destinationOptionNamedTargets;
                    }
                    foreach ($this->summarizePdfDestinationFits($pdfDestinationOptions) as $fit => $count) {
                        $diagnostics[] = 'pdf-byte-destination-fit:' . $fit . ':' . $count;
                    }
                }
                if ($pdfNameTrees !== []) {
                    $diagnostics[] = 'pdf-byte-name-trees:' . count($pdfNameTrees);
                    $nameTreeKidCount = 0;
                    foreach ($pdfNameTrees as $nameTree) {
                        $category = is_string($nameTree['category'] ?? null) && $nameTree['category'] !== ''
                            ? $nameTree['category']
                            : 'unknown';
                        $entryCount = is_int($nameTree['entryCount'] ?? null) ? $nameTree['entryCount'] : 0;
                        $diagnostics[] = 'pdf-byte-name-tree:' . $category . ':' . $entryCount;
                        $nameTreeKidCount += is_int($nameTree['kidCount'] ?? null) ? $nameTree['kidCount'] : 0;
                    }
                    if ($nameTreeKidCount > 0) {
                        $diagnostics[] = 'pdf-byte-name-tree-kids:' . $nameTreeKidCount;
                    }
                }
                if ($pdfUriBase !== null) {
                    $diagnostics[] = 'pdf-byte-uri-base:' . $pdfUriBase;
                }
                if ($pdfViewerPreferences !== []) {
                    $diagnostics[] = 'pdf-byte-viewer-preferences:' . count($pdfViewerPreferences);
                    if (isset($pdfViewerPreferences['PrintPageRange']) && is_array($pdfViewerPreferences['PrintPageRange'])) {
                        $diagnostics[] = 'pdf-byte-viewer-print-page-ranges:' . intdiv(count($pdfViewerPreferences['PrintPageRange']), 2);
                    }
                    if (isset($pdfViewerPreferences['Enforce']) && is_array($pdfViewerPreferences['Enforce'])) {
                        $diagnostics[] = 'pdf-byte-viewer-enforced-preferences:' . count($pdfViewerPreferences['Enforce']);
                    }
                }
                if ($pdfNeedsRendering !== null) {
                    $diagnostics[] = 'pdf-byte-needs-rendering:' . ($pdfNeedsRendering ? 'true' : 'false');
                }
                if ($pdfCatalogRequirements !== []) {
                    $diagnostics[] = 'pdf-byte-catalog-requirements:' . count($pdfCatalogRequirements);
                    $requirementSubtypes = [];
                    $requirementHandlers = 0;
                    foreach ($pdfCatalogRequirements as $requirement) {
                        $subtype = is_string($requirement['subtype'] ?? null) && $requirement['subtype'] !== ''
                            ? $requirement['subtype']
                            : 'unknown';
                        $requirementSubtypes[$subtype] = ($requirementSubtypes[$subtype] ?? 0) + 1;
                        if (
                            ($requirement['handlerObject'] ?? null) !== null
                            || ($requirement['handlerType'] ?? null) !== null
                            || ($requirement['handlerName'] ?? null) !== null
                            || ($requirement['handlerCode'] ?? null) !== null
                            || ($requirement['handlerVersion'] ?? null) !== null
                        ) {
                            $requirementHandlers++;
                        }
                    }
                    ksort($requirementSubtypes);
                    foreach ($requirementSubtypes as $subtype => $count) {
                        $diagnostics[] = 'pdf-byte-catalog-requirement:' . $subtype . ':' . $count;
                    }
                    if ($requirementHandlers > 0) {
                        $diagnostics[] = 'pdf-byte-catalog-requirement-handlers:' . $requirementHandlers;
                    }
                }
                if ($pdfLegalAttestationMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-legal-attestation';
                    if (is_string($pdfLegalAttestationMetadata['status'] ?? null) && $pdfLegalAttestationMetadata['status'] !== '') {
                        $diagnostics[] = 'pdf-byte-legal-attestation-status:' . $pdfLegalAttestationMetadata['status'];
                    }
                    if (is_string($pdfLegalAttestationMetadata['jurisdiction'] ?? null) && $pdfLegalAttestationMetadata['jurisdiction'] !== '') {
                        $diagnostics[] = 'pdf-byte-legal-attestation-jurisdiction:' . $pdfLegalAttestationMetadata['jurisdiction'];
                    }
                    if (is_int($pdfLegalAttestationMetadata['attestationBytes'] ?? null)) {
                        $diagnostics[] = 'pdf-byte-legal-attestation-stream-bytes:' . $pdfLegalAttestationMetadata['attestationBytes'];
                    }
                    if (is_string($pdfLegalAttestationMetadata['attestation'] ?? null) && $pdfLegalAttestationMetadata['attestation'] !== '') {
                        $diagnostics[] = 'pdf-byte-legal-attestation-text';
                    }
                    if (is_string($pdfLegalAttestationMetadata['attestationSkipped'] ?? null) && $pdfLegalAttestationMetadata['attestationSkipped'] !== '') {
                        $diagnostics[] = 'pdf-byte-legal-attestation-skipped:' . $pdfLegalAttestationMetadata['attestationSkipped'];
                    }
                    if (isset($pdfLegalAttestationMetadata['associatedFiles']) && is_array($pdfLegalAttestationMetadata['associatedFiles']) && $pdfLegalAttestationMetadata['associatedFiles'] !== []) {
                        $diagnostics[] = 'pdf-byte-legal-attestation-associated-files:' . count($pdfLegalAttestationMetadata['associatedFiles']);
                    }
                }
                if ($pdfTaggingMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-tagging-metadata:' . count($pdfTaggingMetadata);
                    if (($pdfTaggingMetadata['marked'] ?? null) === true) {
                        $diagnostics[] = 'pdf-byte-tagged';
                    }
                    if (is_string($pdfTaggingMetadata['structTreeRoot'] ?? null) && $pdfTaggingMetadata['structTreeRoot'] !== '') {
                        $diagnostics[] = 'pdf-byte-structure-root:' . $pdfTaggingMetadata['structTreeRoot'];
                    }
                    if (isset($pdfTaggingMetadata['roleMap']) && is_array($pdfTaggingMetadata['roleMap']) && $pdfTaggingMetadata['roleMap'] !== []) {
                        $diagnostics[] = 'pdf-byte-structure-role-map:' . count($pdfTaggingMetadata['roleMap']);
                    }
                    if (isset($pdfTaggingMetadata['structureChildren']) && is_int($pdfTaggingMetadata['structureChildren'])) {
                        $diagnostics[] = 'pdf-byte-structure-children:' . $pdfTaggingMetadata['structureChildren'];
                    }
                }
                if ($pdfStructureElements !== []) {
                    $diagnostics[] = 'pdf-byte-structure-elements:' . count($pdfStructureElements);
                    $altTextCount = 0;
                    $actualTextCount = 0;
                    $languageCount = 0;
                    foreach ($pdfStructureElements as $structureElement) {
                        if (is_string($structureElement['alt'] ?? null) && $structureElement['alt'] !== '') {
                            $altTextCount++;
                        }
                        if (is_string($structureElement['actualText'] ?? null) && $structureElement['actualText'] !== '') {
                            $actualTextCount++;
                        }
                        if (is_string($structureElement['language'] ?? null) && $structureElement['language'] !== '') {
                            $languageCount++;
                        }
                    }
                    if ($altTextCount > 0) {
                        $diagnostics[] = 'pdf-byte-structure-alt-text:' . $altTextCount;
                    }
                    if ($actualTextCount > 0) {
                        $diagnostics[] = 'pdf-byte-structure-actual-text:' . $actualTextCount;
                    }
                    if ($languageCount > 0) {
                        $diagnostics[] = 'pdf-byte-structure-languages:' . $languageCount;
                    }
                }
                if ($pdfMarkedContentProperties !== []) {
                    $diagnostics[] = 'pdf-byte-marked-content-properties:' . count($pdfMarkedContentProperties);
                    $markedContentAssociatedFileCount = 0;
                    $markedContentInheritedCount = 0;
                    foreach ($pdfMarkedContentProperties as $property) {
                        if (isset($property['associatedFiles']) && is_array($property['associatedFiles'])) {
                            $markedContentAssociatedFileCount += count($property['associatedFiles']);
                        }
                        if (($property['inherited'] ?? false) === true) {
                            $markedContentInheritedCount++;
                        }
                    }
                    if ($markedContentAssociatedFileCount > 0) {
                        $diagnostics[] = 'pdf-byte-marked-content-associated-files:' . $markedContentAssociatedFileCount;
                    }
                    if ($markedContentInheritedCount > 0) {
                        $diagnostics[] = 'pdf-byte-marked-content-inherited:' . $markedContentInheritedCount;
                    }
                }
                if ($pdfMarkedContentArtifacts !== []) {
                    $diagnostics[] = 'pdf-byte-marked-content-artifacts:' . count($pdfMarkedContentArtifacts);
                    $artifactAttachedCount = 0;
                    $artifactTypes = [];
                    $artifactSubtypes = [];
                    foreach ($pdfMarkedContentArtifacts as $artifact) {
                        if (isset($artifact['attached']) && is_array($artifact['attached'])) {
                            $artifactAttachedCount += count($artifact['attached']);
                        }
                        $type = is_string($artifact['type'] ?? null) && $artifact['type'] !== ''
                            ? $artifact['type']
                            : null;
                        $subtype = is_string($artifact['subtype'] ?? null) && $artifact['subtype'] !== ''
                            ? $artifact['subtype']
                            : null;
                        if ($type !== null) {
                            $artifactTypes[$type] = ($artifactTypes[$type] ?? 0) + 1;
                        }
                        if ($subtype !== null) {
                            $artifactSubtypes[$subtype] = ($artifactSubtypes[$subtype] ?? 0) + 1;
                        }
                    }
                    if ($artifactAttachedCount > 0) {
                        $diagnostics[] = 'pdf-byte-marked-content-artifact-attached:' . $artifactAttachedCount;
                    }
                    ksort($artifactTypes);
                    foreach ($artifactTypes as $type => $typeCount) {
                        $diagnostics[] = 'pdf-byte-marked-content-artifact-type:' . $type . ':' . $typeCount;
                    }
                    ksort($artifactSubtypes);
                    foreach ($artifactSubtypes as $subtype => $subtypeCount) {
                        $diagnostics[] = 'pdf-byte-marked-content-artifact-subtype:' . $subtype . ':' . $subtypeCount;
                    }
                }
                if ($pdfOptionalContentGroups !== []) {
                    $diagnostics[] = 'pdf-byte-optional-content-groups:' . count($pdfOptionalContentGroups);
                    $intentCount = 0;
                    foreach ($pdfOptionalContentGroups as $optionalContentGroup) {
                        if (isset($optionalContentGroup['intent']) && is_array($optionalContentGroup['intent'])) {
                            $intentCount += count($optionalContentGroup['intent']);
                        }
                    }
                    if ($intentCount > 0) {
                        $diagnostics[] = 'pdf-byte-optional-content-intents:' . $intentCount;
                    }
                }
                if ($pdfOptionalContentConfig !== []) {
                    $diagnostics[] = 'pdf-byte-optional-content-config';
                    if (is_string($pdfOptionalContentConfig['baseState'] ?? null) && $pdfOptionalContentConfig['baseState'] !== '') {
                        $diagnostics[] = 'pdf-byte-optional-content-base-state:' . $pdfOptionalContentConfig['baseState'];
                    }
                    if (isset($pdfOptionalContentConfig['on']) && is_array($pdfOptionalContentConfig['on']) && $pdfOptionalContentConfig['on'] !== []) {
                        $diagnostics[] = 'pdf-byte-optional-content-on:' . count($pdfOptionalContentConfig['on']);
                    }
                    if (isset($pdfOptionalContentConfig['off']) && is_array($pdfOptionalContentConfig['off']) && $pdfOptionalContentConfig['off'] !== []) {
                        $diagnostics[] = 'pdf-byte-optional-content-off:' . count($pdfOptionalContentConfig['off']);
                    }
                    if (isset($pdfOptionalContentConfig['order']) && is_array($pdfOptionalContentConfig['order']) && $pdfOptionalContentConfig['order'] !== []) {
                        $diagnostics[] = 'pdf-byte-optional-content-order:' . count($pdfOptionalContentConfig['order']);
                    }
                }
                if ($pdfOptionalContentMemberships !== []) {
                    $diagnostics[] = 'pdf-byte-optional-content-memberships:' . count($pdfOptionalContentMemberships);
                    $membershipGroupCount = 0;
                    $membershipExpressionCount = 0;
                    $membershipInheritedCount = 0;
                    foreach ($pdfOptionalContentMemberships as $membership) {
                        if (isset($membership['groups']) && is_array($membership['groups'])) {
                            $membershipGroupCount += count($membership['groups']);
                        }
                        if (
                            (isset($membership['visibilityExpressionOperators']) && is_array($membership['visibilityExpressionOperators']) && $membership['visibilityExpressionOperators'] !== [])
                            || (isset($membership['visibilityExpressionGroups']) && is_array($membership['visibilityExpressionGroups']) && $membership['visibilityExpressionGroups'] !== [])
                        ) {
                            $membershipExpressionCount++;
                        }
                        if (($membership['inherited'] ?? false) === true) {
                            $membershipInheritedCount++;
                        }
                    }
                    if ($membershipGroupCount > 0) {
                        $diagnostics[] = 'pdf-byte-optional-content-membership-groups:' . $membershipGroupCount;
                    }
                    if ($membershipExpressionCount > 0) {
                        $diagnostics[] = 'pdf-byte-optional-content-membership-expressions:' . $membershipExpressionCount;
                    }
                    if ($membershipInheritedCount > 0) {
                        $diagnostics[] = 'pdf-byte-optional-content-membership-inherited:' . $membershipInheritedCount;
                    }
                }
                if ($pdfCollectionMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-collection';
                    if (is_string($pdfCollectionMetadata['view'] ?? null) && $pdfCollectionMetadata['view'] !== '') {
                        $diagnostics[] = 'pdf-byte-collection-view:' . $pdfCollectionMetadata['view'];
                    }
                    if (is_string($pdfCollectionMetadata['defaultDocument'] ?? null) && $pdfCollectionMetadata['defaultDocument'] !== '') {
                        $diagnostics[] = 'pdf-byte-collection-default:' . $pdfCollectionMetadata['defaultDocument'];
                    }
                    if (isset($pdfCollectionMetadata['schemaFields']) && is_array($pdfCollectionMetadata['schemaFields']) && $pdfCollectionMetadata['schemaFields'] !== []) {
                        $diagnostics[] = 'pdf-byte-collection-schema-fields:' . count($pdfCollectionMetadata['schemaFields']);
                    }
                    if (isset($pdfCollectionMetadata['sort']['fields']) && is_array($pdfCollectionMetadata['sort']['fields']) && $pdfCollectionMetadata['sort']['fields'] !== []) {
                        $diagnostics[] = 'pdf-byte-collection-sort-fields:' . count($pdfCollectionMetadata['sort']['fields']);
                    }
                }
                if ($pdfAcroFormMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-acroform';
                    if (($pdfAcroFormMetadata['fieldCount'] ?? 0) > 0) {
                        $diagnostics[] = 'pdf-byte-acroform-fields:' . $pdfAcroFormMetadata['fieldCount'];
                    }
                    if (($pdfAcroFormMetadata['needAppearances'] ?? null) === true) {
                        $diagnostics[] = 'pdf-byte-acroform-need-appearances';
                    }
                    if (($pdfAcroFormMetadata['sigFlags'] ?? null) !== null) {
                        $diagnostics[] = 'pdf-byte-acroform-sigflags:' . $pdfAcroFormMetadata['sigFlags'];
                    }
                    if (isset($pdfAcroFormMetadata['sigFlagNames']) && is_array($pdfAcroFormMetadata['sigFlagNames']) && $pdfAcroFormMetadata['sigFlagNames'] !== []) {
                        $diagnostics[] = 'pdf-byte-acroform-sigflag-names:' . count($pdfAcroFormMetadata['sigFlagNames']);
                    }
                    if (($pdfAcroFormMetadata['defaultResourcesPresent'] ?? false) === true) {
                        $diagnostics[] = 'pdf-byte-acroform-default-resources';
                    }
                    if (is_string($pdfAcroFormMetadata['defaultAppearance'] ?? null) && $pdfAcroFormMetadata['defaultAppearance'] !== '') {
                        $diagnostics[] = 'pdf-byte-acroform-default-appearance';
                    }
                    if (($pdfAcroFormMetadata['quadding'] ?? null) !== null) {
                        $diagnostics[] = 'pdf-byte-acroform-quadding:' . $pdfAcroFormMetadata['quadding'];
                    }
                    if (isset($pdfAcroFormMetadata['calculationOrder']) && is_array($pdfAcroFormMetadata['calculationOrder']) && $pdfAcroFormMetadata['calculationOrder'] !== []) {
                        $diagnostics[] = 'pdf-byte-acroform-calculation-order:' . count($pdfAcroFormMetadata['calculationOrder']);
                    }
                    if (($pdfAcroFormMetadata['xfaPresent'] ?? false) === true) {
                        $diagnostics[] = 'pdf-byte-acroform-xfa';
                    }
                    if (isset($pdfAcroFormMetadata['xfaPacketNames']) && is_array($pdfAcroFormMetadata['xfaPacketNames']) && $pdfAcroFormMetadata['xfaPacketNames'] !== []) {
                        $diagnostics[] = 'pdf-byte-acroform-xfa-packets:' . count($pdfAcroFormMetadata['xfaPacketNames']);
                    }
                }
                if ($pdfAcroFormCalculationOrder !== []) {
                    $calculationFields = 0;
                    $missingCalculationFields = 0;
                    foreach ($pdfAcroFormCalculationOrder as $calculationEntry) {
                        if (($calculationEntry['missing'] ?? false) === true) {
                            $missingCalculationFields++;
                            continue;
                        }

                        $calculationFields++;
                    }
                    if ($calculationFields > 0) {
                        $diagnostics[] = 'pdf-byte-acroform-calculation-order-fields:' . $calculationFields;
                    }
                    if ($missingCalculationFields > 0) {
                        $diagnostics[] = 'pdf-byte-acroform-calculation-order-missing:' . $missingCalculationFields;
                    }
                }
                if ($pdfThreads !== []) {
                    $diagnostics[] = 'pdf-byte-threads:' . count($pdfThreads);
                    $beadCount = 0;
                    $titleCount = 0;
                    foreach ($pdfThreads as $thread) {
                        if (isset($thread['beadCount']) && is_int($thread['beadCount'])) {
                            $beadCount += $thread['beadCount'];
                        }
                        if (is_string($thread['infoTitle'] ?? null) && $thread['infoTitle'] !== '') {
                            $titleCount++;
                        }
                    }
                    if ($beadCount > 0) {
                        $diagnostics[] = 'pdf-byte-thread-beads:' . $beadCount;
                    }
                    if ($titleCount > 0) {
                        $diagnostics[] = 'pdf-byte-thread-info-titles:' . $titleCount;
                    }
                }
                if ($pdfCatalogPermissions !== []) {
                    $diagnostics[] = 'pdf-byte-catalog-permissions:' . count($pdfCatalogPermissions);
                    $permissionByteRangeCount = 0;
                    $permissionTransformCount = 0;
                    foreach ($pdfCatalogPermissions as $permission) {
                        if (($permission['byteRange'] ?? []) !== []) {
                            $permissionByteRangeCount++;
                        }
                        if (isset($permission['referenceTransforms']) && is_array($permission['referenceTransforms'])) {
                            $permissionTransformCount += count($permission['referenceTransforms']);
                        }
                    }
                    if ($permissionByteRangeCount > 0) {
                        $diagnostics[] = 'pdf-byte-catalog-permission-byte-ranges:' . $permissionByteRangeCount;
                    }
                    if ($permissionTransformCount > 0) {
                        $diagnostics[] = 'pdf-byte-catalog-permission-reference-transforms:' . $permissionTransformCount;
                    }
                    foreach ($this->summarizePdfSignatureSubFilters($pdfCatalogPermissions) as $subFilter => $subFilterCount) {
                        $diagnostics[] = 'pdf-byte-catalog-permission-subfilter:' . $subFilter . ':' . $subFilterCount;
                    }
                }
                if ($pdfSignatures !== []) {
                    $diagnostics[] = 'pdf-byte-signatures:' . count($pdfSignatures);
                    $byteRangeCount = 0;
                    $contentsCount = 0;
                    $transformCount = 0;
                    $contentSkips = [];
                    foreach ($pdfSignatures as $signature) {
                        if (($signature['byteRange'] ?? []) !== []) {
                            $byteRangeCount++;
                        }
                        if (($signature['contentsBytes'] ?? null) !== null) {
                            $contentsCount++;
                        }
                        if (isset($signature['referenceTransforms']) && is_array($signature['referenceTransforms'])) {
                            $transformCount += count($signature['referenceTransforms']);
                        }
                        if (is_string($signature['contentsSkipped'] ?? null) && $signature['contentsSkipped'] !== '') {
                            $contentSkips[$signature['contentsSkipped']] = true;
                        }
                    }
                    if ($byteRangeCount > 0) {
                        $diagnostics[] = 'pdf-byte-signature-byte-ranges:' . $byteRangeCount;
                    }
                    if ($contentsCount > 0) {
                        $diagnostics[] = 'pdf-byte-signature-contents:' . $contentsCount;
                    }
                    if ($transformCount > 0) {
                        $diagnostics[] = 'pdf-byte-signature-reference-transforms:' . $transformCount;
                    }
                    foreach (array_keys($contentSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-signature-contents-skipped:' . $skipReason;
                    }
                }
                if ($pdfSignatureSubFilters !== []) {
                    $diagnostics[] = 'pdf-byte-signature-subfilters:' . count($pdfSignatureSubFilters);
                    foreach ($pdfSignatureSubFilters as $subFilter => $subFilterCount) {
                        $diagnostics[] = 'pdf-byte-signature-subfilter:' . $subFilter . ':' . $subFilterCount;
                    }
                }
                if ($pdfActiveActions !== []) {
                    $diagnostics[] = 'pdf-byte-active-actions:' . count($pdfActiveActions);
                }
                if ($pdfActiveActionTypes !== []) {
                    $diagnostics[] = 'pdf-byte-active-action-types:' . count($pdfActiveActionTypes);
                    foreach ($pdfActiveActionTypes as $actionType => $actionCount) {
                        $diagnostics[] = 'pdf-byte-active-action-type:' . $actionType . ':' . $actionCount;
                    }
                }
                if ($pdfRichMediaAnnotations !== []) {
                    $diagnostics[] = 'pdf-byte-rich-media-annotations:' . count($pdfRichMediaAnnotations);
                    $richMediaAssetCount = 0;
                    $richMediaConfigurationCount = 0;
                    $richMediaDeactivationModes = [];
                    foreach ($pdfRichMediaAnnotations as $annotation) {
                        if (isset($annotation['assetNames']) && is_array($annotation['assetNames'])) {
                            $richMediaAssetCount += count($annotation['assetNames']);
                        }
                        if (isset($annotation['configurations']) && is_array($annotation['configurations'])) {
                            $richMediaConfigurationCount += count($annotation['configurations']);
                        }
                        if (is_string($annotation['deactivationCondition'] ?? null) && $annotation['deactivationCondition'] !== '') {
                            $richMediaDeactivationModes[$annotation['deactivationCondition']] = ($richMediaDeactivationModes[$annotation['deactivationCondition']] ?? 0) + 1;
                        }
                    }
                    if ($richMediaAssetCount > 0) {
                        $diagnostics[] = 'pdf-byte-rich-media-assets:' . $richMediaAssetCount;
                    }
                    if ($richMediaConfigurationCount > 0) {
                        $diagnostics[] = 'pdf-byte-rich-media-configurations:' . $richMediaConfigurationCount;
                    }
                    foreach ($pdfRichMediaActivationModes as $activationMode => $activationCount) {
                        $diagnostics[] = 'pdf-byte-rich-media-activation:' . $activationMode . ':' . $activationCount;
                    }
                    ksort($richMediaDeactivationModes);
                    foreach ($richMediaDeactivationModes as $deactivationMode => $deactivationCount) {
                        $diagnostics[] = 'pdf-byte-rich-media-deactivation:' . $deactivationMode . ':' . $deactivationCount;
                    }
                }
                if ($pdfAnnotations !== []) {
                    $diagnostics[] = 'pdf-byte-annotation-metadata:' . count($pdfAnnotations);
                    $annotationContentCount = 0;
                    $annotationActionCount = 0;
                    $annotationDestinationCount = 0;
                    $annotationFlagCount = 0;
                    $annotationReplyCount = 0;
                    $annotationReviewStateCount = 0;
                    foreach ($pdfAnnotations as $annotation) {
                        if (is_string($annotation['contents'] ?? null) && $annotation['contents'] !== '') {
                            $annotationContentCount++;
                        }
                        if (is_string($annotation['actionType'] ?? null) && $annotation['actionType'] !== '') {
                            $annotationActionCount++;
                        }
                        if (
                            is_string($annotation['destPageObject'] ?? null)
                            || is_string($annotation['destFit'] ?? null)
                            || is_string($annotation['destTarget'] ?? null)
                        ) {
                            $annotationDestinationCount++;
                        }
                        if (($annotation['flags'] ?? 0) !== 0) {
                            $annotationFlagCount++;
                        }
                        if (is_string($annotation['replyTo'] ?? null) && $annotation['replyTo'] !== '') {
                            $annotationReplyCount++;
                        }
                        if (
                            (is_string($annotation['state'] ?? null) && $annotation['state'] !== '')
                            || (is_string($annotation['stateModel'] ?? null) && $annotation['stateModel'] !== '')
                        ) {
                            $annotationReviewStateCount++;
                        }
                    }
                    if ($annotationContentCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-contents:' . $annotationContentCount;
                    }
                    if ($annotationActionCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-actions:' . $annotationActionCount;
                    }
                    if ($annotationDestinationCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-destinations:' . $annotationDestinationCount;
                    }
                    if ($annotationFlagCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-flags:' . $annotationFlagCount;
                    }
                    if ($annotationReplyCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-replies:' . $annotationReplyCount;
                    }
                    if ($annotationReviewStateCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-review-states:' . $annotationReviewStateCount;
                    }
                }
                if ($pdfAnnotationAppearances !== []) {
                    $diagnostics[] = 'pdf-byte-annotation-appearances:' . count($pdfAnnotationAppearances);
                    $appearanceStreamCount = 0;
                    $appearanceStateCount = 0;
                    $appearanceGroupCount = 0;
                    $appearanceFilters = [];
                    $appearanceStreamSkips = [];
                    foreach ($pdfAnnotationAppearances as $appearance) {
                        if (($appearance['streamBytes'] ?? null) !== null) {
                            $appearanceStreamCount++;
                        }
                        if (is_string($appearance['stateName'] ?? null) && $appearance['stateName'] !== '') {
                            $appearanceStateCount++;
                        }
                        if (
                            ($appearance['groupSubtype'] ?? null) !== null
                            || ($appearance['groupColorSpace'] ?? null) !== null
                            || ($appearance['groupIsolated'] ?? null) !== null
                            || ($appearance['groupKnockout'] ?? null) !== null
                        ) {
                            $appearanceGroupCount++;
                        }
                        foreach (($appearance['filters'] ?? []) as $filter) {
                            if (is_string($filter) && $filter !== '') {
                                $appearanceFilters[$filter] = ($appearanceFilters[$filter] ?? 0) + 1;
                            }
                        }
                        if (is_string($appearance['streamSkipped'] ?? null) && $appearance['streamSkipped'] !== '') {
                            $appearanceStreamSkips[$appearance['streamSkipped']] = true;
                        }
                    }
                    if ($appearanceStreamCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-appearance-streams:' . $appearanceStreamCount;
                    }
                    if ($appearanceStateCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-appearance-states:' . $appearanceStateCount;
                    }
                    if ($appearanceGroupCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-appearance-groups:' . $appearanceGroupCount;
                    }
                    ksort($appearanceFilters);
                    foreach ($appearanceFilters as $filter => $filterCount) {
                        $diagnostics[] = 'pdf-byte-annotation-appearance-filter:' . $filter . ':' . $filterCount;
                    }
                    foreach (array_keys($appearanceStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-annotation-appearance-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfAnnotationReviewMetadata !== []) {
                    $diagnostics[] = 'pdf-byte-annotation-review-metadata:' . count($pdfAnnotationReviewMetadata);
                    $annotationBorderStyleCount = 0;
                    $annotationPopupCount = 0;
                    $annotationPopupOpenCount = 0;
                    foreach ($pdfAnnotationReviewMetadata as $metadata) {
                        if (
                            ($metadata['borderStyle'] ?? null) !== null
                            || ($metadata['borderWidth'] ?? null) !== null
                            || ($metadata['borderDashPattern'] ?? null) !== null
                        ) {
                            $annotationBorderStyleCount++;
                        }
                        if (($metadata['popupObject'] ?? null) !== null) {
                            $annotationPopupCount++;
                        }
                        if (($metadata['popupOpen'] ?? null) === true) {
                            $annotationPopupOpenCount++;
                        }
                    }
                    if ($annotationBorderStyleCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-border-styles:' . $annotationBorderStyleCount;
                    }
                    if ($annotationPopupCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-popup-links:' . $annotationPopupCount;
                    }
                    if ($annotationPopupOpenCount > 0) {
                        $diagnostics[] = 'pdf-byte-annotation-popup-open:' . $annotationPopupOpenCount;
                    }
                }
                if ($pdfAnnotationTypes !== []) {
                    $diagnostics[] = 'pdf-byte-annotations:' . array_sum($pdfAnnotationTypes);
                }
                if ($pdfLinkTargets !== []) {
                    $diagnostics[] = 'pdf-byte-link-targets:' . count($pdfLinkTargets);
                }
                if ($pdfEmbeddedFileNames !== []) {
                    $diagnostics[] = 'pdf-byte-embedded-files:' . count($pdfEmbeddedFileNames);
                }
                if ($pdfEmbeddedFiles !== []) {
                    $diagnostics[] = 'pdf-byte-embedded-file-metadata:' . count($pdfEmbeddedFiles);
                    $embeddedStreams = 0;
                    $embeddedCollectionItems = 0;
                    $embeddedStreamSkips = [];
                    foreach ($pdfEmbeddedFiles as $embeddedFile) {
                        if (($embeddedFile['streamBytes'] ?? null) !== null) {
                            $embeddedStreams++;
                        }
                        if (is_array($embeddedFile['collectionItems'] ?? null)) {
                            $embeddedCollectionItems += count($embeddedFile['collectionItems']);
                        }
                        if (is_string($embeddedFile['streamSkipped'] ?? null) && $embeddedFile['streamSkipped'] !== '') {
                            $embeddedStreamSkips[$embeddedFile['streamSkipped']] = true;
                        }
                    }
                    if ($embeddedStreams > 0) {
                        $diagnostics[] = 'pdf-byte-embedded-file-streams:' . $embeddedStreams;
                    }
                    if ($embeddedCollectionItems > 0) {
                        $diagnostics[] = 'pdf-byte-embedded-file-collection-items:' . $embeddedCollectionItems;
                    }
                    foreach (array_keys($embeddedStreamSkips) as $skipReason) {
                        $diagnostics[] = 'pdf-byte-embedded-file-stream-skipped:' . $skipReason;
                    }
                }
                if ($pdfFormFields !== []) {
                    $diagnostics[] = 'pdf-byte-form-fields:' . count($pdfFormFields);
                }
                if ($pdfFormFieldTypes !== []) {
                    $diagnostics[] = 'pdf-byte-form-field-types:' . count($pdfFormFieldTypes);
                }
                if ($pdfFormFieldActions !== []) {
                    $diagnostics[] = 'pdf-byte-form-field-actions:' . count($pdfFormFieldActions);
                    $formActionTriggers = [];
                    foreach ($pdfFormFieldActions as $formFieldAction) {
                        $trigger = is_string($formFieldAction['trigger'] ?? null) && $formFieldAction['trigger'] !== ''
                            ? $formFieldAction['trigger']
                            : 'unknown';
                        $formActionTriggers[$trigger] = ($formActionTriggers[$trigger] ?? 0) + 1;
                    }
                    ksort($formActionTriggers);
                    foreach ($formActionTriggers as $trigger => $triggerCount) {
                        $diagnostics[] = 'pdf-byte-form-field-action-trigger:' . $trigger . ':' . $triggerCount;
                    }
                }
                if ($pdfFormFieldActionTypes !== []) {
                    $diagnostics[] = 'pdf-byte-form-field-action-types:' . count($pdfFormFieldActionTypes);
                    foreach ($pdfFormFieldActionTypes as $actionType => $actionCount) {
                        $diagnostics[] = 'pdf-byte-form-field-action-type:' . $actionType . ':' . $actionCount;
                    }
                }
                if ($pdfEncrypted) {
                    $diagnostics[] = 'pdf-output-encrypted';
                    if ($pdfEncryptionFilter !== null) {
                        $diagnostics[] = 'pdf-encryption-filter:' . $pdfEncryptionFilter;
                    }
                    if ($pdfPermissionFlags !== []) {
                        $diagnostics[] = 'pdf-permission-flags:' . count($pdfPermissionFlags);
                    }
                }
            }
        }
        if (
            is_string($pdfBytes)
            && str_starts_with($pdfBytes, '%PDF-')
            && $declaredOutput['file'] !== null
            && !$this->declaredOutputFileMatches($declaredOutput['file'], $outputFile)
        ) {
            $diagnostics[] = 'engine-output-file-mismatch:' . $declaredOutput['file'] . ':' . $outputFile;
        }
        if (
            is_string($pdfBytes)
            && str_starts_with($pdfBytes, '%PDF-')
            && $declaredOutput['bytes'] !== null
            && $declaredOutput['bytes'] !== strlen($pdfBytes)
        ) {
            $diagnostics[] = 'engine-output-byte-mismatch:' . $declaredOutput['bytes'] . ':' . strlen($pdfBytes);
        }
        if (
            $declaredOutput['pages'] !== null
            && $pdfPageCount !== null
            && $declaredOutput['pages'] !== $pdfPageCount
        ) {
            $diagnostics[] = 'engine-output-page-mismatch:' . $declaredOutput['pages'] . ':' . $pdfPageCount;
        }
        if (is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') && !$pdfTrailerComplete) {
            $diagnostics[] = 'pdf-output-truncated';
        }
        if ($reason === null && $missingProgram['missing']) {
            $status = 'failed';
            $reason = 'engine-program-missing';
        }
        if ($reason === null && $engineMessages['errors'] !== []) {
            $status = 'failed';
            $reason = 'engine-log-error';
        }
        if ($reason === null && $bibliographyMessages['errors'] !== []) {
            $status = 'failed';
            $reason = 'bibliography-log-error';
        }
        if ($reason === null && $exitCode !== 0) {
            $status = 'failed';
            $reason = 'engine-exit-' . $exitCode;
        }
        if ($reason === null && $pdfBytes === null) {
            $status = 'failed';
            $reason = 'missing-pdf-output';
        }
        if ($reason === null && !str_starts_with((string) $pdfBytes, '%PDF-')) {
            $status = 'failed';
            $reason = 'non-pdf-output';
        }
        if ($reason === null && !$pdfTrailerComplete) {
            $status = 'failed';
            $reason = 'truncated-pdf-output';
        }
        if (
            $reason === null
            && $declaredOutput['file'] !== null
            && !$this->declaredOutputFileMatches($declaredOutput['file'], $outputFile)
        ) {
            $status = 'failed';
            $reason = 'pdf-output-file-mismatch';
        }
        if (
            $reason === null
            && $declaredOutput['bytes'] !== null
            && is_string($pdfBytes)
            && $declaredOutput['bytes'] !== strlen($pdfBytes)
        ) {
            $status = 'failed';
            $reason = 'pdf-output-byte-mismatch';
        }
        if (
            $reason === null
            && $declaredOutput['pages'] !== null
            && $pdfPageCount !== null
            && $declaredOutput['pages'] !== $pdfPageCount
        ) {
            $status = 'failed';
            $reason = 'pdf-output-page-mismatch';
        }
        if ($reason === null && $pdfEncrypted) {
            $status = 'failed';
            $reason = 'pdf-output-encrypted';
        }

        return [
            'ok' => $status === 'ok',
            'status' => $status,
            'reason' => $reason,
            'engine' => $engine,
            'engineMissingProgram' => $missingProgram['missing'],
            'engineMissingProgramName' => $missingProgram['program'],
            'outputFile' => $outputFile,
            'exitCode' => $exitCode,
            'bytes' => is_string($pdfBytes) ? strlen($pdfBytes) : 0,
            'sourceSha256' => $sourceSha256,
            'sourceArtifactsSha256' => $sourceArtifactsSha256,
            'resourceArtifactsSha256' => $resourceArtifactsSha256,
            'missingResourceFiles' => $missingResourceFiles,
            'producedArtifactsSha256' => $producedArtifactsSha256,
            'engineDependencyArtifactsSha256' => $engineDependencyArtifactsSha256,
            'engineInputFiles' => $engineInputFileList,
            'engineExternalInputFiles' => $engineExternalInputFileList,
            'engineOutputFiles' => $engineOutputFileList,
            'engineTranscriptInputFiles' => $engineTranscriptInputFileList,
            'engineTranscriptExternalInputFiles' => $engineTranscriptExternalInputFileList,
            'missingEngineInputFiles' => $missingEngineInputFiles,
            'missingEngineTranscriptInputFiles' => $missingEngineTranscriptInputFiles,
            'bibliographyArtifactsSha256' => $bibliographyArtifactsSha256,
            'bibliographyLogFiles' => $bibliographyLogFiles,
            'sourceMapArtifactsSha256' => $sourceMapArtifactsSha256,
            'sourceMapFiles' => $sourceMapFiles,
            'sourceMapInputs' => $sourceMapInputs,
            'sourceMapInputFiles' => $sourceMapInputFileList,
            'sourceMapExternalInputs' => $sourceMapExternalInputList,
            'sourceMapLineRanges' => $sourceMapLineRanges,
            'engineLogFiles' => $engineLogFiles,
            'engineWarnings' => $engineMessages['warnings'],
            'engineErrors' => $engineMessages['errors'],
            'bibliographyWarnings' => $bibliographyMessages['warnings'],
            'bibliographyErrors' => $bibliographyMessages['errors'],
            'bibliographyNeeded' => $bibliographyMessages['needed'],
            'rerunNeeded' => $engineMessages['rerunNeeded'] || $bibliographyMessages['needed'],
            'declaredOutputFile' => $declaredOutput['file'],
            'declaredOutputPages' => $declaredOutput['pages'],
            'declaredOutputBytes' => $declaredOutput['bytes'],
            'pdfHeaderVersion' => $pdfHeaderVersion,
            'pdfCatalogVersion' => $pdfCatalogVersion,
            'pdfEffectiveVersion' => $pdfEffectiveVersion,
            'pdfLinearization' => $pdfLinearization,
            'pdfExtensionMetadata' => $pdfExtensionMetadata,
            'pdfTrailerComplete' => $pdfTrailerComplete,
            'pdfTrailerCount' => $pdfTrailerCount,
            'pdfTrailerRevisions' => $pdfTrailerRevisions,
            'pdfStartXrefOffsets' => $pdfStartXrefOffsets,
            'pdfIncrementalUpdates' => $pdfIncrementalUpdates,
            'pdfXrefStreams' => $pdfXrefStreams,
            'pdfXrefStreamFilters' => $pdfXrefStreamFilters,
            'pdfObjectStreams' => $pdfObjectStreams,
            'pdfObjectStreamFilters' => $pdfObjectStreamFilters,
            'pdfPageCount' => $pdfPageCount,
            'pdfPageBoxes' => $pdfPageBoxes,
            'pdfPageRotations' => $pdfPageRotations,
            'pdfPageProductionMetadata' => $pdfPageProductionMetadata,
            'pdfPageDisplayMetadata' => $pdfPageDisplayMetadata,
            'pdfPageLabels' => $pdfPageLabels,
            'pdfPageTimings' => $pdfPageTimings,
            'pdfPageViewports' => $pdfPageViewports,
            'pdfPageContentStreams' => $pdfPageContentStreams,
            'pdfPageContentResourceUsage' => $pdfPageContentResourceUsage,
            'pdfPageResourceSources' => $pdfPageResourceSources,
            'pdfFonts' => $pdfFonts,
            'pdfFontSubtypes' => $pdfFontSubtypes,
            'pdfImages' => $pdfImages,
            'pdfImageColorSpaces' => $pdfImageColorSpaces,
            'pdfImageFilters' => $pdfImageFilters,
            'pdfColorSpaces' => $pdfColorSpaces,
            'pdfColorSpaceFamilies' => $pdfColorSpaceFamilies,
            'pdfFormXObjects' => $pdfFormXObjects,
            'pdfFormXObjectFilters' => $pdfFormXObjectFilters,
            'pdfGraphicsStates' => $pdfGraphicsStates,
            'pdfGraphicsStateBlendModes' => $pdfGraphicsStateBlendModes,
            'pdfOutlineTitles' => $pdfOutlineTitles,
            'pdfOutlines' => $pdfOutlines,
            'pdfOutlineDisplayMetadata' => $pdfOutlineDisplayMetadata,
            'pdfDocumentInfo' => $pdfDocumentInfo,
            'pdfDocumentInfoDateMetadata' => $pdfDocumentInfoDateMetadata,
            'pdfXmpMetadata' => $pdfXmpMetadata,
            'pdfPageMetadata' => $pdfPageMetadata,
            'pdfPieceInfo' => $pdfPieceInfo,
            'pdfWebCaptureMetadata' => $pdfWebCaptureMetadata,
            'pdfOutputIntents' => $pdfOutputIntents,
            'pdfPageOutputIntents' => $pdfPageOutputIntents,
            'pdfLanguage' => $pdfLanguage,
            'pdfPageLayout' => $pdfPageLayout,
            'pdfPageMode' => $pdfPageMode,
            'pdfOpenAction' => $pdfOpenAction,
            'pdfNamedDestinations' => $pdfNamedDestinations,
            'pdfDestinationOptions' => $pdfDestinationOptions,
            'pdfNameTrees' => $pdfNameTrees,
            'pdfUriBase' => $pdfUriBase,
            'pdfViewerPreferences' => $pdfViewerPreferences,
            'pdfNeedsRendering' => $pdfNeedsRendering,
            'pdfCatalogRequirements' => $pdfCatalogRequirements,
            'pdfLegalAttestationMetadata' => $pdfLegalAttestationMetadata,
            'pdfTaggingMetadata' => $pdfTaggingMetadata,
            'pdfStructureElements' => $pdfStructureElements,
            'pdfMarkedContentProperties' => $pdfMarkedContentProperties,
            'pdfMarkedContentArtifacts' => $pdfMarkedContentArtifacts,
            'pdfOptionalContentGroups' => $pdfOptionalContentGroups,
            'pdfOptionalContentConfig' => $pdfOptionalContentConfig,
            'pdfOptionalContentMemberships' => $pdfOptionalContentMemberships,
            'pdfCollectionMetadata' => $pdfCollectionMetadata,
            'pdfAcroFormMetadata' => $pdfAcroFormMetadata,
            'pdfAcroFormCalculationOrder' => $pdfAcroFormCalculationOrder,
            'pdfThreads' => $pdfThreads,
            'pdfCatalogPermissions' => $pdfCatalogPermissions,
            'pdfSignatures' => $pdfSignatures,
            'pdfSignatureSubFilters' => $pdfSignatureSubFilters,
            'pdfActiveActions' => $pdfActiveActions,
            'pdfActiveActionTypes' => $pdfActiveActionTypes,
            'pdfRichMediaAnnotations' => $pdfRichMediaAnnotations,
            'pdfRichMediaActivationModes' => $pdfRichMediaActivationModes,
            'pdfAnnotations' => $pdfAnnotations,
            'pdfAnnotationReviewMetadata' => $pdfAnnotationReviewMetadata,
            'pdfAnnotationAppearances' => $pdfAnnotationAppearances,
            'pdfAnnotationTypes' => $pdfAnnotationTypes,
            'pdfLinkTargets' => $pdfLinkTargets,
            'pdfEmbeddedFileNames' => $pdfEmbeddedFileNames,
            'pdfEmbeddedFiles' => $pdfEmbeddedFiles,
            'pdfFormFields' => $pdfFormFields,
            'pdfFormFieldTypes' => $pdfFormFieldTypes,
            'pdfFormFieldActions' => $pdfFormFieldActions,
            'pdfFormFieldActionTypes' => $pdfFormFieldActionTypes,
            'pdfEncrypted' => $pdfEncrypted,
            'pdfEncryptionFilter' => $pdfEncryptionFilter,
            'pdfEncryptionVersion' => $pdfEncryptionVersion,
            'pdfEncryptionRevision' => $pdfEncryptionRevision,
            'pdfEncryptionLength' => $pdfEncryptionLength,
            'pdfPermissionInteger' => $pdfPermissionInteger,
            'pdfPermissionFlags' => $pdfPermissionFlags,
            'pdfEncryptMetadata' => $pdfEncryptMetadata,
            'pdfSha256' => is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') ? hash('sha256', $pdfBytes) : null,
            'stdout' => (string) ($result['stdout'] ?? ''),
            'stderr' => (string) ($result['stderr'] ?? ''),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<array{exitCode?: int, stdout?: string, stderr?: string, files?: array<string, string>}> $runs
     * @return array{
     *     ok: bool,
     *     status: string,
     *     reason: string|null,
     *     engine: string,
     *     outputFile: string,
     *     attempts: int,
     *     successfulAttempts: int,
     *     finalRunIndex: int,
     *     finalRun: array<string, mixed>|null,
     *     runs: list<array<string, mixed>>,
     *     finalBytes: int,
     *     finalPdfSha256: string|null,
     *     finalDeclaredOutputFile: string|null,
     *     finalDeclaredOutputPages: int|null,
     *     finalDeclaredOutputBytes: int|null,
     *     finalPdfHeaderVersion: string|null,
     *     finalPdfCatalogVersion: string|null,
     *     finalPdfEffectiveVersion: string|null,
     *     finalPdfLinearization: array{object:string|null, linearizedVersion:float|null, fileLength:int|null, primaryHintOffset:int|null, primaryHintLength:int|null, firstPageObject:int|null, firstPageEndOffset:int|null, pageCount:int|null, mainXrefOffset:int|null, hintTables:list<array{offset:int, length:int}>, lengthMatches:bool|null}|array{},
     *     finalPdfExtensionMetadata: list<array{prefix:string, baseVersion:string|null, extensionLevel:int|null}>,
     *     finalPdfPageCount: int|null,
     *     finalPdfPageBoxes: list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}>,
     *     finalPdfPageRotations: array<int, int>,
     *     finalPdfPageProductionMetadata: list<array{page:int, pageObject:string|null, boxColorInfoObject:string|null, boxColorInfo:list<array{box:string, color:list<float>|null, width:float|null, style:string|null}>, separationInfoObject:string|null, separationPages:list<string>, separationDeviceColorant:string|null, separationColorSpace:string|null, presStepsObject:string|null, presStepsSubtype:string|null, presStepsNext:list<string>}>,
     *     finalPdfPageDisplayMetadata: list<array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}>,
     *     finalPdfPageLabels: list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>,
     *     finalPdfPageTimings: list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, directionLabel:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>,
     *     finalPdfPageViewports: list<array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}>,
     *     finalPdfPageContentStreams: list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}>,
     *     finalPdfPageContentResourceUsage: array<string, int>,
     *     finalPdfPageResourceSources: list<array{page:int, pageObject:string|null, resourceSourceObject:string|null, inherited:bool, categories:list<string>, fontNames:list<string>, xobjectNames:list<string>, colorSpaceNames:list<string>, graphicsStateNames:list<string>, propertyNames:list<string>}>,
     *     finalPdfFonts: list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>,
     *     finalPdfFontSubtypes: array<string, int>,
     *     finalPdfImages: list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfImageColorSpaces: array<string, int>,
     *     finalPdfImageFilters: array<string, int>,
     *     finalPdfColorSpaces: list<array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}>,
     *     finalPdfColorSpaceFamilies: array<string, int>,
     *     finalPdfFormXObjects: list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfFormXObjectFilters: array<string, int>,
     *     finalPdfGraphicsStates: list<array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}>,
     *     finalPdfGraphicsStateBlendModes: array<string, int>,
     *     finalPdfTrailerCount: int,
     *     finalPdfTrailerRevisions: list<array{revision:int, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, startxref:int|null, id:list<string>}>,
     *     finalPdfStartXrefOffsets: list<int>,
     *     finalPdfIncrementalUpdates: bool,
     *     finalPdfXrefStreams: list<array{object:string, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, index:list<int>, w:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfXrefStreamFilters: array<string, int>,
     *     finalPdfObjectStreams: list<array{object:string, objectCount:int|null, firstByteOffset:int|null, extends:string|null, objectNumbers:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfObjectStreamFilters: array<string, int>,
     *     finalPdfOutlineTitles: list<string>,
     *     finalPdfOutlines: list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}>,
     *     finalPdfOutlineDisplayMetadata: list<array{object:string, title:string, color:list<float>|null, flags:int, flagNames:list<string>}>,
     *     finalPdfDocumentInfo: array<string, string>,
     *     finalPdfDocumentInfoDateMetadata: list<array{key:string, source:string, raw:string, normalized:string|null, precision:string|null, timezone:string|null, timezoneOffsetMinutes:int|null, year:int|null, month:int|null, day:int|null, hour:int|null, minute:int|null, second:int|null, valid:bool}>,
     *     finalPdfXmpMetadata: array<string, mixed>,
     *     finalPdfPageMetadata: list<array<string, mixed>>,
     *     finalPdfPieceInfo: list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}>,
     *     finalPdfWebCaptureMetadata: list<array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}>,
     *     finalPdfOutputIntents: list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     finalPdfPageOutputIntents: list<array{page:int, pageObject:string|null, source:string, type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     finalPdfLanguage: string|null,
     *     finalPdfPageLayout: string|null,
     *     finalPdfPageMode: string|null,
     *     finalPdfOpenAction: array<string, mixed>|null,
     *     finalPdfNamedDestinations: list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>,
     *     finalPdfDestinationOptions: list<array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}>,
     *     finalPdfNameTrees: list<array{category:string, source:string, entryCount:int, names:list<string>, valueKinds:array<string, int>, valueReferences:list<string>, kidCount:int, limits:list<string>}>,
     *     finalPdfUriBase: string|null,
     *     finalPdfViewerPreferences: array<string, bool|int|string|list<int>|list<string>>,
     *     finalPdfNeedsRendering: bool|null,
     *     finalPdfCatalogRequirements: list<array{object:string|null, type:string|null, subtype:string|null, handlerObject:string|null, handlerType:string|null, handlerName:string|null, handlerCode:string|null, handlerVersion:string|null, keys:list<string>}>,
     *     finalPdfLegalAttestationMetadata: array{object:string|null, type:string|null, language:string|null, status:string|null, jurisdiction:string|null, attestation:string|null, attestationObject:string|null, attestationBytes:int|null, attestationSha256:string|null, attestationSkipped:string|null, associatedFiles:list<string>, keys:list<string>}|array{},
     *     finalPdfTaggingMetadata: array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{},
     *     finalPdfStructureElements: list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>,
     *     finalPdfMarkedContentProperties: list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}>,
     *     finalPdfMarkedContentArtifacts: list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>,
     *     finalPdfOptionalContentGroups: list<array{object:string, name:string|null, intent:list<string>, usageViewState:string|null, usagePrintState:string|null, usageExportState:string|null, usageCreator:string|null, usageCreatorSubtype:string|null, usageLanguage:string|null, usageLanguagePreferred:bool|null, usageZoomMin:float|null, usageZoomMax:float|null}>,
     *     finalPdfOptionalContentConfig: array{name:string|null, creator:string|null, baseState:string|null, listMode:string|null, on:list<string>, off:list<string>, order:list<string>, orderLabels:list<string>}|array{},
     *     finalPdfOptionalContentMemberships: list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, type:string|null, groups:list<string>, policy:string|null, visibilityExpressionOperators:list<string>, visibilityExpressionGroups:list<string>}>,
     *     finalPdfCollectionMetadata: array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{},
     *     finalPdfAcroFormMetadata: array{fieldReferences:list<string>, fieldCount:int, needAppearances:bool|null, sigFlags:int|null, sigFlagNames:list<string>, defaultResourcesPresent:bool, defaultAppearance:string|null, quadding:int|null, calculationOrder:list<string>, xfaPresent:bool, xfaPacketNames:list<string>}|array{},
     *     finalPdfAcroFormCalculationOrder: list<array{order:int, fieldObject:string, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null, alternateName:string|null, mappingName:string|null, flags:int|null, flagNames:list<string>, missing:bool}>,
     *     finalPdfThreads: list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>,
     *     finalPdfCatalogPermissions: list<array{permission:string, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     finalPdfSignatures: list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     finalPdfSignatureSubFilters: array<string, int>,
     *     finalPdfActiveActions: list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     finalPdfActiveActionTypes: array<string, int>,
     *     finalPdfRichMediaAnnotations: list<array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}>,
     *     finalPdfRichMediaActivationModes: array<string, int>,
     *     finalPdfAnnotations: list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}>,
     *     finalPdfAnnotationReviewMetadata: list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}>,
     *     finalPdfAnnotationAppearances: list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfAnnotationTypes: array<string, int>,
     *     finalPdfLinkTargets: list<string>,
     *     finalPdfEmbeddedFileNames: list<string>,
     *     finalPdfEmbeddedFiles: list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}>,
     *     finalPdfFormFields: list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}>,
     *     finalPdfFormFieldTypes: array<string, int>,
     *     finalPdfFormFieldActions: list<array{fieldName:string|null, fieldObject:string|null, fieldType:string|null, fieldTypeLabel:string|null, trigger:string, source:string, actionType:string, actionTarget:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     finalPdfFormFieldActionTypes: array<string, int>,
     *     finalPdfEncrypted: bool,
     *     finalPdfEncryptionFilter: string|null,
     *     finalPdfEncryptionVersion: int|null,
     *     finalPdfEncryptionRevision: int|null,
     *     finalPdfEncryptionLength: int|null,
     *     finalPdfPermissionInteger: int|null,
     *     finalPdfPermissionFlags: array<string, bool>,
     *     finalPdfEncryptMetadata: bool|null,
     *     sourceSha256: string|null,
     *     finalResourceArtifactsSha256: array<string, string>,
     *     finalEngineDependencyArtifactsSha256: array<string, string>,
     *     finalEngineInputFiles: list<string>,
     *     finalEngineExternalInputFiles: list<string>,
     *     finalEngineOutputFiles: list<string>,
     *     finalEngineTranscriptInputFiles: list<string>,
     *     finalEngineTranscriptExternalInputFiles: list<string>,
     *     finalBibliographyArtifactsSha256: array<string, string>,
     *     finalSourceMapArtifactsSha256: array<string, string>,
     *     finalSourceMapFiles: list<string>,
     *     finalSourceMapInputs: list<array{tag:int, path:string}>,
     *     finalSourceMapInputFiles: list<string>,
     *     finalSourceMapExternalInputs: list<string>,
     *     finalSourceMapLineRanges: list<array{tag:int, path:string, minLine:int, maxLine:int, references:int}>,
     *     missingResourceFiles: list<string>,
     *     missingEngineInputFiles: list<string>,
     *     missingEngineTranscriptInputFiles: list<string>,
     *     engineMissingProgram: bool,
     *     engineMissingProgramName: string|null,
     *     engineWarnings: list<string>,
     *     engineErrors: list<string>,
     *     bibliographyWarnings: list<string>,
     *     bibliographyErrors: list<string>,
     *     bibliographyNeeded: bool,
     *     rerunNeeded: bool,
     *     diagnostics: list<string>
     * }
     */
    public function fakeRunSequence(array $plan, array $runs): array
    {
        $engine = $this->requirePlanString($plan, 'engine');
        $outputFile = $this->requirePlanString($plan, 'outputFile');
        if ($runs === []) {
            throw new \InvalidArgumentException('PDF fake runner sequence requires at least one attempt');
        }
        if (count($runs) > 8) {
            throw new \InvalidArgumentException('PDF fake runner sequence is bounded to 8 attempts');
        }

        $attemptResults = [];
        $warnings = [];
        $errors = [];
        $bibliographyWarnings = [];
        $bibliographyErrors = [];
        $diagnostics = ['fake-runner-attempts:' . count($runs)];
        $successfulAttempts = 0;
        $status = 'ok';
        $reason = null;
        $finalRun = null;
        $finalRunIndex = 0;
        $hadRerunNeededAttempt = false;
        $hadBibliographyNeededAttempt = false;

        foreach ($runs as $index => $run) {
            if (!is_array($run)) {
                throw new \InvalidArgumentException('PDF fake runner sequence attempts must be result arrays');
            }

            $attemptIndex = $index + 1;
            $attempt = $this->fakeRun($plan, $run);
            $attemptResults[] = $attempt;
            $finalRun = $attempt;
            $finalRunIndex = $attemptIndex;

            if ($attempt['ok'] === true) {
                $successfulAttempts++;
            } elseif ($reason === null) {
                $attemptReason = is_string($attempt['reason'] ?? null) && $attempt['reason'] !== ''
                    ? $attempt['reason']
                    : 'failed';
                $status = 'failed';
                $reason = 'attempt-' . $attemptIndex . '-' . $attemptReason;
                $diagnostics[] = 'fake-runner-attempt-failed:' . $attemptIndex . ':' . $attemptReason;
            }

            if (($attempt['rerunNeeded'] ?? false) === true) {
                $hadRerunNeededAttempt = true;
                $diagnostics[] = 'fake-runner-attempt-rerun-needed:' . $attemptIndex;
            }
            if (($attempt['bibliographyNeeded'] ?? false) === true) {
                $hadBibliographyNeededAttempt = true;
                $diagnostics[] = 'fake-runner-attempt-bibliography-needed:' . $attemptIndex;
            }

            foreach ($attempt['engineWarnings'] ?? [] as $warning) {
                if (is_string($warning) && $warning !== '') {
                    $warnings[] = $warning;
                }
            }
            foreach ($attempt['engineErrors'] ?? [] as $error) {
                if (is_string($error) && $error !== '') {
                    $errors[] = $error;
                }
            }
            foreach ($attempt['bibliographyWarnings'] ?? [] as $warning) {
                if (is_string($warning) && $warning !== '') {
                    $bibliographyWarnings[] = $warning;
                }
            }
            foreach ($attempt['bibliographyErrors'] ?? [] as $error) {
                if (is_string($error) && $error !== '') {
                    $bibliographyErrors[] = $error;
                }
            }
        }

        $finalRerunNeeded = is_array($finalRun) && ($finalRun['rerunNeeded'] ?? false) === true;
        $finalBibliographyNeeded = is_array($finalRun) && ($finalRun['bibliographyNeeded'] ?? false) === true;
        if ($finalRerunNeeded) {
            $diagnostics[] = 'fake-runner-rerun-still-needed';
            if ($reason === null) {
                $status = 'failed';
                $reason = 'rerun-still-needed';
            }
        } elseif ($hadRerunNeededAttempt) {
            $diagnostics[] = 'fake-runner-final-rerun-cleared';
        }
        if ($finalBibliographyNeeded) {
            $diagnostics[] = 'fake-runner-final-bibliography-needed';
        } elseif ($hadBibliographyNeededAttempt) {
            $diagnostics[] = 'fake-runner-final-bibliography-cleared';
        }

        return [
            'ok' => $status === 'ok',
            'status' => $status,
            'reason' => $reason,
            'engine' => $engine,
            'outputFile' => $outputFile,
            'attempts' => count($runs),
            'successfulAttempts' => $successfulAttempts,
            'finalRunIndex' => $finalRunIndex,
            'finalRun' => $finalRun,
            'runs' => $attemptResults,
            'finalBytes' => is_array($finalRun) ? (int) ($finalRun['bytes'] ?? 0) : 0,
            'finalPdfSha256' => is_array($finalRun) && is_string($finalRun['pdfSha256'] ?? null) ? $finalRun['pdfSha256'] : null,
            'finalDeclaredOutputFile' => is_array($finalRun) && is_string($finalRun['declaredOutputFile'] ?? null) ? $finalRun['declaredOutputFile'] : null,
            'finalDeclaredOutputPages' => is_array($finalRun) && is_int($finalRun['declaredOutputPages'] ?? null) ? $finalRun['declaredOutputPages'] : null,
            'finalDeclaredOutputBytes' => is_array($finalRun) && is_int($finalRun['declaredOutputBytes'] ?? null) ? $finalRun['declaredOutputBytes'] : null,
            'finalPdfHeaderVersion' => is_array($finalRun) && is_string($finalRun['pdfHeaderVersion'] ?? null) ? $finalRun['pdfHeaderVersion'] : null,
            'finalPdfCatalogVersion' => is_array($finalRun) && is_string($finalRun['pdfCatalogVersion'] ?? null) ? $finalRun['pdfCatalogVersion'] : null,
            'finalPdfEffectiveVersion' => is_array($finalRun) && is_string($finalRun['pdfEffectiveVersion'] ?? null) ? $finalRun['pdfEffectiveVersion'] : null,
            'finalPdfLinearization' => is_array($finalRun) && is_array($finalRun['pdfLinearization'] ?? null) ? $finalRun['pdfLinearization'] : [],
            'finalPdfExtensionMetadata' => is_array($finalRun) && is_array($finalRun['pdfExtensionMetadata'] ?? null) ? $finalRun['pdfExtensionMetadata'] : [],
            'finalPdfPageCount' => is_array($finalRun) && is_int($finalRun['pdfPageCount'] ?? null) ? $finalRun['pdfPageCount'] : null,
            'finalPdfPageBoxes' => is_array($finalRun) && is_array($finalRun['pdfPageBoxes'] ?? null) ? $finalRun['pdfPageBoxes'] : [],
            'finalPdfPageRotations' => is_array($finalRun) && is_array($finalRun['pdfPageRotations'] ?? null) ? $finalRun['pdfPageRotations'] : [],
            'finalPdfPageProductionMetadata' => is_array($finalRun) && is_array($finalRun['pdfPageProductionMetadata'] ?? null) ? $finalRun['pdfPageProductionMetadata'] : [],
            'finalPdfPageDisplayMetadata' => is_array($finalRun) && is_array($finalRun['pdfPageDisplayMetadata'] ?? null) ? $finalRun['pdfPageDisplayMetadata'] : [],
            'finalPdfPageLabels' => is_array($finalRun) && is_array($finalRun['pdfPageLabels'] ?? null) ? $finalRun['pdfPageLabels'] : [],
            'finalPdfPageTimings' => is_array($finalRun) && is_array($finalRun['pdfPageTimings'] ?? null) ? $finalRun['pdfPageTimings'] : [],
            'finalPdfPageViewports' => is_array($finalRun) && is_array($finalRun['pdfPageViewports'] ?? null) ? $finalRun['pdfPageViewports'] : [],
            'finalPdfPageContentStreams' => is_array($finalRun) && is_array($finalRun['pdfPageContentStreams'] ?? null) ? $finalRun['pdfPageContentStreams'] : [],
            'finalPdfPageContentResourceUsage' => is_array($finalRun) && is_array($finalRun['pdfPageContentResourceUsage'] ?? null) ? $finalRun['pdfPageContentResourceUsage'] : [],
            'finalPdfPageResourceSources' => is_array($finalRun) && is_array($finalRun['pdfPageResourceSources'] ?? null) ? $finalRun['pdfPageResourceSources'] : [],
            'finalPdfFonts' => is_array($finalRun) && is_array($finalRun['pdfFonts'] ?? null) ? $finalRun['pdfFonts'] : [],
            'finalPdfFontSubtypes' => is_array($finalRun) && is_array($finalRun['pdfFontSubtypes'] ?? null) ? $finalRun['pdfFontSubtypes'] : [],
            'finalPdfImages' => is_array($finalRun) && is_array($finalRun['pdfImages'] ?? null) ? $finalRun['pdfImages'] : [],
            'finalPdfImageColorSpaces' => is_array($finalRun) && is_array($finalRun['pdfImageColorSpaces'] ?? null) ? $finalRun['pdfImageColorSpaces'] : [],
            'finalPdfImageFilters' => is_array($finalRun) && is_array($finalRun['pdfImageFilters'] ?? null) ? $finalRun['pdfImageFilters'] : [],
            'finalPdfColorSpaces' => is_array($finalRun) && is_array($finalRun['pdfColorSpaces'] ?? null) ? $finalRun['pdfColorSpaces'] : [],
            'finalPdfColorSpaceFamilies' => is_array($finalRun) && is_array($finalRun['pdfColorSpaceFamilies'] ?? null) ? $finalRun['pdfColorSpaceFamilies'] : [],
            'finalPdfFormXObjects' => is_array($finalRun) && is_array($finalRun['pdfFormXObjects'] ?? null) ? $finalRun['pdfFormXObjects'] : [],
            'finalPdfFormXObjectFilters' => is_array($finalRun) && is_array($finalRun['pdfFormXObjectFilters'] ?? null) ? $finalRun['pdfFormXObjectFilters'] : [],
            'finalPdfGraphicsStates' => is_array($finalRun) && is_array($finalRun['pdfGraphicsStates'] ?? null) ? $finalRun['pdfGraphicsStates'] : [],
            'finalPdfGraphicsStateBlendModes' => is_array($finalRun) && is_array($finalRun['pdfGraphicsStateBlendModes'] ?? null) ? $finalRun['pdfGraphicsStateBlendModes'] : [],
            'finalPdfTrailerCount' => is_array($finalRun) && is_int($finalRun['pdfTrailerCount'] ?? null) ? $finalRun['pdfTrailerCount'] : 0,
            'finalPdfTrailerRevisions' => is_array($finalRun) && is_array($finalRun['pdfTrailerRevisions'] ?? null) ? $finalRun['pdfTrailerRevisions'] : [],
            'finalPdfStartXrefOffsets' => is_array($finalRun) && is_array($finalRun['pdfStartXrefOffsets'] ?? null) ? $finalRun['pdfStartXrefOffsets'] : [],
            'finalPdfIncrementalUpdates' => is_array($finalRun) && ($finalRun['pdfIncrementalUpdates'] ?? false) === true,
            'finalPdfXrefStreams' => is_array($finalRun) && is_array($finalRun['pdfXrefStreams'] ?? null) ? $finalRun['pdfXrefStreams'] : [],
            'finalPdfXrefStreamFilters' => is_array($finalRun) && is_array($finalRun['pdfXrefStreamFilters'] ?? null) ? $finalRun['pdfXrefStreamFilters'] : [],
            'finalPdfObjectStreams' => is_array($finalRun) && is_array($finalRun['pdfObjectStreams'] ?? null) ? $finalRun['pdfObjectStreams'] : [],
            'finalPdfObjectStreamFilters' => is_array($finalRun) && is_array($finalRun['pdfObjectStreamFilters'] ?? null) ? $finalRun['pdfObjectStreamFilters'] : [],
            'finalPdfOutlineTitles' => is_array($finalRun) && is_array($finalRun['pdfOutlineTitles'] ?? null) ? $finalRun['pdfOutlineTitles'] : [],
            'finalPdfOutlines' => is_array($finalRun) && is_array($finalRun['pdfOutlines'] ?? null) ? $finalRun['pdfOutlines'] : [],
            'finalPdfOutlineDisplayMetadata' => is_array($finalRun) && is_array($finalRun['pdfOutlineDisplayMetadata'] ?? null) ? $finalRun['pdfOutlineDisplayMetadata'] : [],
            'finalPdfDocumentInfo' => is_array($finalRun) && is_array($finalRun['pdfDocumentInfo'] ?? null) ? $finalRun['pdfDocumentInfo'] : [],
            'finalPdfDocumentInfoDateMetadata' => is_array($finalRun) && is_array($finalRun['pdfDocumentInfoDateMetadata'] ?? null) ? $finalRun['pdfDocumentInfoDateMetadata'] : [],
            'finalPdfXmpMetadata' => is_array($finalRun) && is_array($finalRun['pdfXmpMetadata'] ?? null) ? $finalRun['pdfXmpMetadata'] : [],
            'finalPdfPageMetadata' => is_array($finalRun) && is_array($finalRun['pdfPageMetadata'] ?? null) ? $finalRun['pdfPageMetadata'] : [],
            'finalPdfPieceInfo' => is_array($finalRun) && is_array($finalRun['pdfPieceInfo'] ?? null) ? $finalRun['pdfPieceInfo'] : [],
            'finalPdfWebCaptureMetadata' => is_array($finalRun) && is_array($finalRun['pdfWebCaptureMetadata'] ?? null) ? $finalRun['pdfWebCaptureMetadata'] : [],
            'finalPdfOutputIntents' => is_array($finalRun) && is_array($finalRun['pdfOutputIntents'] ?? null) ? $finalRun['pdfOutputIntents'] : [],
            'finalPdfPageOutputIntents' => is_array($finalRun) && is_array($finalRun['pdfPageOutputIntents'] ?? null) ? $finalRun['pdfPageOutputIntents'] : [],
            'finalPdfLanguage' => is_array($finalRun) && is_string($finalRun['pdfLanguage'] ?? null) ? $finalRun['pdfLanguage'] : null,
            'finalPdfPageLayout' => is_array($finalRun) && is_string($finalRun['pdfPageLayout'] ?? null) ? $finalRun['pdfPageLayout'] : null,
            'finalPdfPageMode' => is_array($finalRun) && is_string($finalRun['pdfPageMode'] ?? null) ? $finalRun['pdfPageMode'] : null,
            'finalPdfOpenAction' => is_array($finalRun) && is_array($finalRun['pdfOpenAction'] ?? null) ? $finalRun['pdfOpenAction'] : null,
            'finalPdfNamedDestinations' => is_array($finalRun) && is_array($finalRun['pdfNamedDestinations'] ?? null) ? $finalRun['pdfNamedDestinations'] : [],
            'finalPdfDestinationOptions' => is_array($finalRun) && is_array($finalRun['pdfDestinationOptions'] ?? null) ? $finalRun['pdfDestinationOptions'] : [],
            'finalPdfNameTrees' => is_array($finalRun) && is_array($finalRun['pdfNameTrees'] ?? null) ? $finalRun['pdfNameTrees'] : [],
            'finalPdfUriBase' => is_array($finalRun) && is_string($finalRun['pdfUriBase'] ?? null) ? $finalRun['pdfUriBase'] : null,
            'finalPdfViewerPreferences' => is_array($finalRun) && is_array($finalRun['pdfViewerPreferences'] ?? null) ? $finalRun['pdfViewerPreferences'] : [],
            'finalPdfNeedsRendering' => is_array($finalRun) && is_bool($finalRun['pdfNeedsRendering'] ?? null) ? $finalRun['pdfNeedsRendering'] : null,
            'finalPdfCatalogRequirements' => is_array($finalRun) && is_array($finalRun['pdfCatalogRequirements'] ?? null) ? $finalRun['pdfCatalogRequirements'] : [],
            'finalPdfLegalAttestationMetadata' => is_array($finalRun) && is_array($finalRun['pdfLegalAttestationMetadata'] ?? null) ? $finalRun['pdfLegalAttestationMetadata'] : [],
            'finalPdfTaggingMetadata' => is_array($finalRun) && is_array($finalRun['pdfTaggingMetadata'] ?? null) ? $finalRun['pdfTaggingMetadata'] : [],
            'finalPdfStructureElements' => is_array($finalRun) && is_array($finalRun['pdfStructureElements'] ?? null) ? $finalRun['pdfStructureElements'] : [],
            'finalPdfMarkedContentProperties' => is_array($finalRun) && is_array($finalRun['pdfMarkedContentProperties'] ?? null) ? $finalRun['pdfMarkedContentProperties'] : [],
            'finalPdfMarkedContentArtifacts' => is_array($finalRun) && is_array($finalRun['pdfMarkedContentArtifacts'] ?? null) ? $finalRun['pdfMarkedContentArtifacts'] : [],
            'finalPdfOptionalContentGroups' => is_array($finalRun) && is_array($finalRun['pdfOptionalContentGroups'] ?? null) ? $finalRun['pdfOptionalContentGroups'] : [],
            'finalPdfOptionalContentConfig' => is_array($finalRun) && is_array($finalRun['pdfOptionalContentConfig'] ?? null) ? $finalRun['pdfOptionalContentConfig'] : [],
            'finalPdfOptionalContentMemberships' => is_array($finalRun) && is_array($finalRun['pdfOptionalContentMemberships'] ?? null) ? $finalRun['pdfOptionalContentMemberships'] : [],
            'finalPdfCollectionMetadata' => is_array($finalRun) && is_array($finalRun['pdfCollectionMetadata'] ?? null) ? $finalRun['pdfCollectionMetadata'] : [],
            'finalPdfAcroFormMetadata' => is_array($finalRun) && is_array($finalRun['pdfAcroFormMetadata'] ?? null) ? $finalRun['pdfAcroFormMetadata'] : [],
            'finalPdfAcroFormCalculationOrder' => is_array($finalRun) && is_array($finalRun['pdfAcroFormCalculationOrder'] ?? null) ? $finalRun['pdfAcroFormCalculationOrder'] : [],
            'finalPdfThreads' => is_array($finalRun) && is_array($finalRun['pdfThreads'] ?? null) ? $finalRun['pdfThreads'] : [],
            'finalPdfCatalogPermissions' => is_array($finalRun) && is_array($finalRun['pdfCatalogPermissions'] ?? null) ? $finalRun['pdfCatalogPermissions'] : [],
            'finalPdfSignatures' => is_array($finalRun) && is_array($finalRun['pdfSignatures'] ?? null) ? $finalRun['pdfSignatures'] : [],
            'finalPdfSignatureSubFilters' => is_array($finalRun) && is_array($finalRun['pdfSignatureSubFilters'] ?? null) ? $finalRun['pdfSignatureSubFilters'] : [],
            'finalPdfActiveActions' => is_array($finalRun) && is_array($finalRun['pdfActiveActions'] ?? null) ? $finalRun['pdfActiveActions'] : [],
            'finalPdfActiveActionTypes' => is_array($finalRun) && is_array($finalRun['pdfActiveActionTypes'] ?? null) ? $finalRun['pdfActiveActionTypes'] : [],
            'finalPdfRichMediaAnnotations' => is_array($finalRun) && is_array($finalRun['pdfRichMediaAnnotations'] ?? null) ? $finalRun['pdfRichMediaAnnotations'] : [],
            'finalPdfRichMediaActivationModes' => is_array($finalRun) && is_array($finalRun['pdfRichMediaActivationModes'] ?? null) ? $finalRun['pdfRichMediaActivationModes'] : [],
            'finalPdfAnnotations' => is_array($finalRun) && is_array($finalRun['pdfAnnotations'] ?? null) ? $finalRun['pdfAnnotations'] : [],
            'finalPdfAnnotationReviewMetadata' => is_array($finalRun) && is_array($finalRun['pdfAnnotationReviewMetadata'] ?? null) ? $finalRun['pdfAnnotationReviewMetadata'] : [],
            'finalPdfAnnotationAppearances' => is_array($finalRun) && is_array($finalRun['pdfAnnotationAppearances'] ?? null) ? $finalRun['pdfAnnotationAppearances'] : [],
            'finalPdfAnnotationTypes' => is_array($finalRun) && is_array($finalRun['pdfAnnotationTypes'] ?? null) ? $finalRun['pdfAnnotationTypes'] : [],
            'finalPdfLinkTargets' => is_array($finalRun) && is_array($finalRun['pdfLinkTargets'] ?? null) ? $finalRun['pdfLinkTargets'] : [],
            'finalPdfEmbeddedFileNames' => is_array($finalRun) && is_array($finalRun['pdfEmbeddedFileNames'] ?? null) ? $finalRun['pdfEmbeddedFileNames'] : [],
            'finalPdfEmbeddedFiles' => is_array($finalRun) && is_array($finalRun['pdfEmbeddedFiles'] ?? null) ? $finalRun['pdfEmbeddedFiles'] : [],
            'finalPdfFormFields' => is_array($finalRun) && is_array($finalRun['pdfFormFields'] ?? null) ? $finalRun['pdfFormFields'] : [],
            'finalPdfFormFieldTypes' => is_array($finalRun) && is_array($finalRun['pdfFormFieldTypes'] ?? null) ? $finalRun['pdfFormFieldTypes'] : [],
            'finalPdfFormFieldActions' => is_array($finalRun) && is_array($finalRun['pdfFormFieldActions'] ?? null) ? $finalRun['pdfFormFieldActions'] : [],
            'finalPdfFormFieldActionTypes' => is_array($finalRun) && is_array($finalRun['pdfFormFieldActionTypes'] ?? null) ? $finalRun['pdfFormFieldActionTypes'] : [],
            'finalPdfEncrypted' => is_array($finalRun) && ($finalRun['pdfEncrypted'] ?? false) === true,
            'finalPdfEncryptionFilter' => is_array($finalRun) && is_string($finalRun['pdfEncryptionFilter'] ?? null) ? $finalRun['pdfEncryptionFilter'] : null,
            'finalPdfEncryptionVersion' => is_array($finalRun) && is_int($finalRun['pdfEncryptionVersion'] ?? null) ? $finalRun['pdfEncryptionVersion'] : null,
            'finalPdfEncryptionRevision' => is_array($finalRun) && is_int($finalRun['pdfEncryptionRevision'] ?? null) ? $finalRun['pdfEncryptionRevision'] : null,
            'finalPdfEncryptionLength' => is_array($finalRun) && is_int($finalRun['pdfEncryptionLength'] ?? null) ? $finalRun['pdfEncryptionLength'] : null,
            'finalPdfPermissionInteger' => is_array($finalRun) && is_int($finalRun['pdfPermissionInteger'] ?? null) ? $finalRun['pdfPermissionInteger'] : null,
            'finalPdfPermissionFlags' => is_array($finalRun) && is_array($finalRun['pdfPermissionFlags'] ?? null) ? $finalRun['pdfPermissionFlags'] : [],
            'finalPdfEncryptMetadata' => is_array($finalRun) && is_bool($finalRun['pdfEncryptMetadata'] ?? null) ? $finalRun['pdfEncryptMetadata'] : null,
            'sourceSha256' => is_array($finalRun) && is_string($finalRun['sourceSha256'] ?? null) ? $finalRun['sourceSha256'] : null,
            'finalResourceArtifactsSha256' => is_array($finalRun) && is_array($finalRun['resourceArtifactsSha256'] ?? null) ? $finalRun['resourceArtifactsSha256'] : [],
            'finalEngineDependencyArtifactsSha256' => is_array($finalRun) && is_array($finalRun['engineDependencyArtifactsSha256'] ?? null) ? $finalRun['engineDependencyArtifactsSha256'] : [],
            'finalEngineInputFiles' => is_array($finalRun) && is_array($finalRun['engineInputFiles'] ?? null) ? $finalRun['engineInputFiles'] : [],
            'finalEngineExternalInputFiles' => is_array($finalRun) && is_array($finalRun['engineExternalInputFiles'] ?? null) ? $finalRun['engineExternalInputFiles'] : [],
            'finalEngineOutputFiles' => is_array($finalRun) && is_array($finalRun['engineOutputFiles'] ?? null) ? $finalRun['engineOutputFiles'] : [],
            'finalEngineTranscriptInputFiles' => is_array($finalRun) && is_array($finalRun['engineTranscriptInputFiles'] ?? null) ? $finalRun['engineTranscriptInputFiles'] : [],
            'finalEngineTranscriptExternalInputFiles' => is_array($finalRun) && is_array($finalRun['engineTranscriptExternalInputFiles'] ?? null) ? $finalRun['engineTranscriptExternalInputFiles'] : [],
            'finalBibliographyArtifactsSha256' => is_array($finalRun) && is_array($finalRun['bibliographyArtifactsSha256'] ?? null) ? $finalRun['bibliographyArtifactsSha256'] : [],
            'finalSourceMapArtifactsSha256' => is_array($finalRun) && is_array($finalRun['sourceMapArtifactsSha256'] ?? null) ? $finalRun['sourceMapArtifactsSha256'] : [],
            'finalSourceMapFiles' => is_array($finalRun) && is_array($finalRun['sourceMapFiles'] ?? null) ? $finalRun['sourceMapFiles'] : [],
            'finalSourceMapInputs' => is_array($finalRun) && is_array($finalRun['sourceMapInputs'] ?? null) ? $finalRun['sourceMapInputs'] : [],
            'finalSourceMapInputFiles' => is_array($finalRun) && is_array($finalRun['sourceMapInputFiles'] ?? null) ? $finalRun['sourceMapInputFiles'] : [],
            'finalSourceMapExternalInputs' => is_array($finalRun) && is_array($finalRun['sourceMapExternalInputs'] ?? null) ? $finalRun['sourceMapExternalInputs'] : [],
            'finalSourceMapLineRanges' => is_array($finalRun) && is_array($finalRun['sourceMapLineRanges'] ?? null) ? $finalRun['sourceMapLineRanges'] : [],
            'missingResourceFiles' => is_array($finalRun) && is_array($finalRun['missingResourceFiles'] ?? null) ? $finalRun['missingResourceFiles'] : [],
            'missingEngineInputFiles' => is_array($finalRun) && is_array($finalRun['missingEngineInputFiles'] ?? null) ? $finalRun['missingEngineInputFiles'] : [],
            'missingEngineTranscriptInputFiles' => is_array($finalRun) && is_array($finalRun['missingEngineTranscriptInputFiles'] ?? null) ? $finalRun['missingEngineTranscriptInputFiles'] : [],
            'engineMissingProgram' => is_array($finalRun) && ($finalRun['engineMissingProgram'] ?? false) === true,
            'engineMissingProgramName' => is_array($finalRun) && is_string($finalRun['engineMissingProgramName'] ?? null) ? $finalRun['engineMissingProgramName'] : null,
            'engineWarnings' => array_values(array_unique($warnings)),
            'engineErrors' => array_values(array_unique($errors)),
            'bibliographyWarnings' => array_values(array_unique($bibliographyWarnings)),
            'bibliographyErrors' => array_values(array_unique($bibliographyErrors)),
            'bibliographyNeeded' => $finalBibliographyNeeded,
            'rerunNeeded' => $finalRerunNeeded,
            'diagnostics' => $diagnostics,
        ];
    }

    private function engineName(string $engineProgram): string
    {
        $engineProgram = $this->requireString($engineProgram, 'PDF engine');
        $normalized = str_replace('\\', '/', $engineProgram);
        $basename = strtolower(basename($normalized));

        return preg_replace('/(?:\.exe|\.bat|\.cmd)\z/i', '', $basename) ?? $basename;
    }

    /**
     * @param array{family:string, intermediate:string, extension:string, defaultArgs:list<string>} $profile
     * @param list<string> $engineOptions
     * @return list<string>
     */
    private function argvFor(string $program, string $engine, array $profile, string $sourceFile, string $outputFile, array $engineOptions): array
    {
        $argv = [$program];

        if ($engine === 'typst') {
            return array_merge($argv, ['compile'], $engineOptions, [$sourceFile, $outputFile]);
        }

        if ($engine === 'pdfroff') {
            return array_merge($argv, $engineOptions, ['-o', $outputFile, $sourceFile]);
        }

        if ($engine === 'prince' || $engine === 'pagedjs-cli') {
            return array_merge($argv, $engineOptions, [$sourceFile, '-o', $outputFile]);
        }

        if ($profile['family'] === 'html') {
            return array_merge($argv, $engineOptions, [$sourceFile, $outputFile]);
        }

        return array_merge($argv, $profile['defaultArgs'], $engineOptions, [$sourceFile]);
    }

    /**
     * @param list<string> $includeInHeaderFiles
     * @param list<string> $resourcePaths
     * @param array<string, mixed> $variables
     * @return list<string>
     */
    private function writerArgumentsFor(?string $templateFile, array $includeInHeaderFiles, array $resourcePaths, array $variables): array
    {
        $arguments = [];
        if ($templateFile !== null) {
            $arguments[] = '--template=' . $templateFile;
        }
        foreach ($includeInHeaderFiles as $path) {
            $arguments[] = '--include-in-header=' . $path;
        }
        if ($resourcePaths !== []) {
            $arguments[] = '--resource-path=' . implode(':', $resourcePaths);
        }
        foreach ($this->flattenVariableArguments($variables) as $argument) {
            $arguments[] = '-V';
            $arguments[] = $argument;
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $variables
     * @return list<string>
     */
    private function flattenVariableArguments(array $variables, string $prefix = ''): array
    {
        $arguments = [];
        foreach ($variables as $name => $value) {
            $variableName = $prefix === '' ? (string) $name : $prefix . '.' . $name;
            if (is_array($value) && !array_is_list($value)) {
                array_push($arguments, ...$this->flattenVariableArguments($value, $variableName));
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $arguments[] = $variableName . '=' . $this->templateVariableScalar($item, $variableName);
                }
                continue;
            }

            $arguments[] = $variableName . '=' . $this->templateVariableScalar($value, $variableName);
        }

        return $arguments;
    }

    private function templateVariableScalar(mixed $value, string $name): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('PDF template variable ' . $name . ' must be scalar');
        }

        return $value;
    }

    private function renderIntermediateSource(AstNode $document, string $intermediateFormat): ?string
    {
        if ($intermediateFormat === 'latex') {
            return (new LatexWriter())->write($document);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataSummary(AstNode $document): array
    {
        $metadata = $document->attr('meta', $document->attr('metadata', []));
        if (!is_array($metadata)) {
            return [];
        }

        $summary = [];
        foreach (['title', 'author', 'authors', 'date', 'lang', 'papersize', 'geometry', 'documentclass'] as $key) {
            if (array_key_exists($key, $metadata)) {
                $summary[$key] = $this->normalizeMetadataValue($metadata[$key]);
            }
        }

        return $summary;
    }

    private function normalizeMetadataValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeMetadataValue($item);
            }

            return $normalized;
        }

        return (string) $value;
    }

    /**
     * @return array{
     *     files:list<string>,
     *     manifest:list<array{path:string, kind:string, sources:list<string>, title:string|null, alt:string|null}>,
     *     remote:list<string>,
     *     skipped:list<string>
     * }
     */
    private function resourceInventoryFor(AstNode $document, mixed $explicitResourceFiles): array
    {
        $entries = [];
        $remote = [];
        $skipped = [];

        foreach ($this->normalizeResourceFileList($explicitResourceFiles, 'PDF resource file') as $path) {
            $this->addResourceEntry($entries, $path, 'resource', 'explicit');
        }

        $this->collectDocumentResources($document, $entries, $remote, $skipped);
        ksort($entries);
        sort($remote);
        sort($skipped);

        return [
            'files' => array_keys($entries),
            'manifest' => array_values($entries),
            'remote' => array_values(array_unique($remote)),
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    /**
     * @param array<string, array{path:string, kind:string, sources:list<string>, title:string|null, alt:string|null}> $entries
     * @param list<string> $remote
     * @param list<string> $skipped
     */
    private function collectDocumentResources(AstNode $node, array &$entries, array &$remote, array &$skipped): void
    {
        if ($node->type === 'image') {
            $url = $node->attr('url', '');
            if (is_string($url)) {
                $classified = $this->classifyResourceReference($url);
                if ($classified['type'] === 'local') {
                    $this->addResourceEntry(
                        $entries,
                        $classified['value'],
                        'image',
                        'document-image',
                        $this->nullableString($node->attr('title', null)),
                        $this->nullableString($node->attr('alt', null))
                    );
                } elseif ($classified['type'] === 'remote') {
                    $remote[] = $classified['value'];
                } else {
                    $skipped[] = $classified['value'];
                }
            }
        }

        foreach ($node->children as $child) {
            $this->collectDocumentResources($child, $entries, $remote, $skipped);
        }
    }

    /**
     * @param array<string, array{path:string, kind:string, sources:list<string>, title:string|null, alt:string|null}> $entries
     */
    private function addResourceEntry(
        array &$entries,
        string $path,
        string $kind,
        string $source,
        ?string $title = null,
        ?string $alt = null
    ): void {
        if (!isset($entries[$path])) {
            $entries[$path] = [
                'path' => $path,
                'kind' => $kind,
                'sources' => [],
                'title' => null,
                'alt' => null,
            ];
        }

        if (!in_array($source, $entries[$path]['sources'], true)) {
            $entries[$path]['sources'][] = $source;
        }
        if ($kind === 'image') {
            $entries[$path]['kind'] = 'image';
        }
        if ($entries[$path]['title'] === null && $title !== null && $title !== '') {
            $entries[$path]['title'] = $title;
        }
        if ($entries[$path]['alt'] === null && $alt !== null && $alt !== '') {
            $entries[$path]['alt'] = $alt;
        }
    }

    /**
     * @return array{type:string, value:string}
     */
    private function classifyResourceReference(string $reference): array
    {
        $reference = str_replace('\\', '/', trim($reference));
        if ($reference === '') {
            return ['type' => 'skipped', 'value' => 'empty-resource-reference'];
        }
        if (str_contains($reference, "\0")) {
            return ['type' => 'skipped', 'value' => 'resource-reference-contains-nul'];
        }
        if ($this->isUriResourceReference($reference)) {
            return ['type' => 'remote', 'value' => $reference];
        }
        if (str_starts_with($reference, '#') || str_contains($reference, '?') || str_contains($reference, '#')) {
            return ['type' => 'skipped', 'value' => $reference];
        }

        try {
            return ['type' => 'local', 'value' => $this->normalizeRelativePath($reference, 'PDF document resource path')];
        } catch (\InvalidArgumentException) {
            return ['type' => 'skipped', 'value' => $reference];
        }
    }

    private function isUriResourceReference(string $reference): bool
    {
        return str_starts_with($reference, '//')
            || preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $reference) === 1;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function deriveSourcePath(string $outputFile, string $extension): string
    {
        $lastSlash = strrpos($outputFile, '/');
        $directory = $lastSlash === false ? '' : substr($outputFile, 0, $lastSlash + 1);
        $basename = $lastSlash === false ? $outputFile : substr($outputFile, $lastSlash + 1);
        $stem = preg_replace('/\.pdf\z/i', '', $basename) ?? 'document';
        if ($stem === '') {
            $stem = 'document';
        }

        return $directory . $stem . '.' . $extension;
    }

    /**
     * @return list<string>
     */
    private function expectedEngineArtifactsFor(string $engine, string $family, string $sourceFile): array
    {
        $stem = $this->sourceStem($sourceFile);
        $extensions = [];

        if ($family === 'latex') {
            $extensions = ['log', 'aux', 'out', 'toc'];
            if ($engine === 'latexmk') {
                $extensions[] = 'fls';
                $extensions[] = 'fdb_latexmk';
            }
        } elseif ($family === 'context') {
            $extensions = ['log', 'tuc'];
        }

        $artifacts = [];
        foreach ($extensions as $extension) {
            $artifacts[] = $stem . '.' . $extension;
        }

        return $artifacts;
    }

    /**
     * @param list<string> $engineOptions
     */
    private function sourceMapFileFor(string $family, string $sourceFile, array $engineOptions): ?string
    {
        if ($family !== 'latex') {
            return null;
        }

        foreach ($engineOptions as $option) {
            if ($this->isSyncTexEngineOption($option)) {
                return $this->sourceStem($sourceFile) . '.synctex.gz';
            }
        }

        return null;
    }

    /**
     * @param list<string> $engineOptions
     */
    private function recorderFileFor(string $engine, string $family, string $sourceFile, array $engineOptions): ?string
    {
        if ($family !== 'latex') {
            return null;
        }
        if ($engine === 'latexmk') {
            return $this->sourceStem($sourceFile) . '.fls';
        }

        foreach ($engineOptions as $option) {
            if ($this->isRecorderEngineOption($option)) {
                return $this->sourceStem($sourceFile) . '.fls';
            }
        }

        return null;
    }

    private function isSyncTexEngineOption(string $option): bool
    {
        $option = strtolower(trim($option));
        if (!str_contains($option, 'synctex')) {
            return false;
        }

        return preg_match('/synctex\s*=\s*(?:0|false|off|no)\b/i', $option) !== 1;
    }

    private function isRecorderEngineOption(string $option): bool
    {
        $option = strtolower(trim($option));
        if (!str_contains($option, 'recorder')) {
            return false;
        }

        return preg_match('/recorder\s*=\s*(?:0|false|off|no)\b/i', $option) !== 1;
    }

    /**
     * @param list<string> $artifacts
     */
    private function firstEngineLogFile(array $artifacts): ?string
    {
        foreach ($artifacts as $artifact) {
            if ($this->isEngineLogPath($artifact)) {
                return $artifact;
            }
        }

        return null;
    }

    private function sourceStem(string $sourceFile): string
    {
        $lastSlash = strrpos($sourceFile, '/');
        $lastDot = strrpos($sourceFile, '.');
        if ($lastDot !== false && ($lastSlash === false || $lastDot > $lastSlash)) {
            return substr($sourceFile, 0, $lastDot);
        }

        return $sourceFile;
    }

    private function isKnownEngineArtifact(string $path, string $sourceFile): bool
    {
        $stem = $this->sourceStem($sourceFile);
        if (!str_starts_with($path, $stem . '.')) {
            return false;
        }

        $extension = substr($path, strlen($stem) + 1);

        return in_array($extension, [
            'aux',
            'bbl',
            'bcf',
            'blg',
            'fdb_latexmk',
            'fls',
            'log',
            'out',
            'run.xml',
            'synctex',
            'synctex.gz',
            'toc',
            'tuc',
            'xdv',
        ], true);
    }

    private function isBibliographyArtifactPath(string $path): bool
    {
        return preg_match('/\.(?:bbl|bcf|blg|run\.xml)\z/i', $path) === 1;
    }

    private function isBibliographyLogPath(string $path): bool
    {
        return preg_match('/\.blg\z/i', $path) === 1;
    }

    private function isEngineDependencyArtifactPath(string $path): bool
    {
        return preg_match('/\.fls\z/i', $path) === 1;
    }

    private function isSourceMapArtifactPath(string $path): bool
    {
        return preg_match('/\.synctex(?:\.gz)?\z/i', $path) === 1;
    }

    private function isEngineLogPath(string $path): bool
    {
        return preg_match('/\.log\z/i', $path) === 1;
    }

    /**
     * @param list<string> $texts
     * @return array{inputFiles:list<string>, externalInputFiles:list<string>}
     */
    private function extractEngineTranscriptInputs(array $texts): array
    {
        $inputFiles = [];
        $externalInputFiles = [];

        foreach ($texts as $text) {
            if (strlen($text) > self::MAX_TRANSCRIPT_BYTES) {
                throw new \RuntimeException('engine transcript exceeds bounded byte limit');
            }

            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                foreach ($this->engineTranscriptPathCandidates($line) as $candidate) {
                    $classified = $this->normalizeEngineDependencyPath($candidate, 'engine transcript');
                    if ($classified['local']) {
                        $inputFiles[$classified['path']] = true;
                    } else {
                        $externalInputFiles[$classified['path']] = true;
                    }
                }
            }
        }

        $inputFileList = array_keys($inputFiles);
        sort($inputFileList);
        $externalInputFileList = array_keys($externalInputFiles);
        sort($externalInputFileList);

        return [
            'inputFiles' => $inputFileList,
            'externalInputFiles' => $externalInputFileList,
        ];
    }

    /**
     * @return list<string>
     */
    private function engineTranscriptPathCandidates(string $line): array
    {
        if (!str_contains($line, '(')) {
            return [];
        }

        if (preg_match_all('/\((?:"([^"]+)"|\'([^\']+)\'|([^\s()]+))/u', $line, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $candidates = [];
        foreach ($matches as $match) {
            $candidate = '';
            foreach ([1, 2, 3] as $index) {
                if (isset($match[$index]) && $match[$index] !== '') {
                    $candidate = $match[$index];
                    break;
                }
            }

            $candidate = trim($candidate, " \t\n\r\0\x0B'\"`()[]{}<>");
            if ($candidate === '' || !$this->isLikelyEngineTranscriptPath($candidate)) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return array_values(array_unique($candidates));
    }

    private function isLikelyEngineTranscriptPath(string $candidate): bool
    {
        return preg_match('/\.(?:tex|sty|cls|clo|def|cfg|fd|map|enc|mf|tfm|otf|ttf|bib|bst|bbx|cbx|lbx|aux|out|toc|lof|lot|bbl|bcf|run\.xml|png|jpe?g|pdf|eps|svg|xdv)\z/i', $candidate) === 1;
    }

    /**
     * @return array{inputFiles:list<string>, externalInputFiles:list<string>, outputFiles:list<string>}
     */
    private function extractEngineDependencyArtifact(string $path, string $bytes): array
    {
        if (strlen($bytes) > self::MAX_DEPENDENCY_FILE_BYTES) {
            throw new \RuntimeException('dependency file exceeds bounded byte limit');
        }

        $inputFiles = [];
        $externalInputFiles = [];
        $outputFiles = [];

        foreach (preg_split('/\R/u', $bytes) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/\APWD\s+/i', $line) === 1) {
                continue;
            }
            if (preg_match('/\A(INPUT|OUTPUT)\s+(.+)\z/i', $line, $matches) !== 1) {
                continue;
            }

            $classified = $this->normalizeEngineDependencyPath($matches[2], $path);
            if (strtoupper($matches[1]) === 'INPUT') {
                if ($classified['local']) {
                    $inputFiles[$classified['path']] = true;
                } else {
                    $externalInputFiles[$classified['path']] = true;
                }
                continue;
            }

            if ($classified['local']) {
                $outputFiles[$classified['path']] = true;
            }
        }

        $inputFileList = array_keys($inputFiles);
        sort($inputFileList);
        $externalInputFileList = array_keys($externalInputFiles);
        sort($externalInputFileList);
        $outputFileList = array_keys($outputFiles);
        sort($outputFileList);

        return [
            'inputFiles' => $inputFileList,
            'externalInputFiles' => $externalInputFileList,
            'outputFiles' => $outputFileList,
        ];
    }

    /**
     * @return array{path:string, local:bool}
     */
    private function normalizeEngineDependencyPath(string $path, string $artifactPath): array
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, " \t\n\r\0\x0B'\"`");
        if ($path === '') {
            throw new \RuntimeException('dependency path is empty in ' . $artifactPath);
        }
        if (str_contains($path, "\0")) {
            throw new \RuntimeException('dependency path contains NUL bytes in ' . $artifactPath);
        }
        if (preg_match('/\Afile:\/\//i', $path) === 1) {
            $uriPath = parse_url($path, PHP_URL_PATH);
            if (is_string($uriPath) && $uriPath !== '') {
                $path = rawurldecode($uriPath);
            }
        }
        if (
            str_starts_with($path, '/')
            || preg_match('/\A[A-Za-z]:\//', $path) === 1
            || $this->isUriResourceReference($path)
        ) {
            return ['path' => $this->externalSourceMapInputName($path), 'local' => false];
        }

        try {
            return ['path' => $this->normalizeRelativePath($path, 'PDF engine dependency path'), 'local' => true];
        } catch (\InvalidArgumentException) {
            return ['path' => $this->externalSourceMapInputName($path), 'local' => false];
        }
    }

    /**
     * @return array{
     *     inputs:list<array{tag:int, path:string}>,
     *     inputFiles:list<string>,
     *     externalInputs:list<string>,
     *     lineRanges:list<array{tag:int, path:string, minLine:int, maxLine:int, references:int}>
     * }
     */
    private function extractSourceMapArtifact(string $path, string $bytes): array
    {
        if (preg_match('/\.gz\z/i', $path) === 1) {
            $text = GzipStream::decode($bytes, self::MAX_SOURCE_MAP_BYTES);
        } else {
            if (strlen($bytes) > self::MAX_SOURCE_MAP_BYTES) {
                throw new \RuntimeException('source map exceeds bounded byte limit');
            }
            $text = $bytes;
        }

        $inputs = [];
        $inputFiles = [];
        $externalInputs = [];
        $lineStats = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/\AInput:(\d+):(.+)\z/', $line, $matches) === 1) {
                $tag = (int) $matches[1];
                $input = $this->normalizeSourceMapInputPath($matches[2]);
                $inputs[$tag] = ['tag' => $tag, 'path' => $input['path']];
                if ($input['local']) {
                    $inputFiles[$input['path']] = true;
                } else {
                    $externalInputs[$input['path']] = true;
                }
                continue;
            }

            if (preg_match_all('/[\[\(]\s*(\d+),(\d+):/', $line, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }

            foreach ($matches as $match) {
                $tag = (int) $match[1];
                $sourceLine = (int) $match[2];
                if ($sourceLine < 1) {
                    continue;
                }
                if (!isset($lineStats[$tag])) {
                    $lineStats[$tag] = [
                        'minLine' => $sourceLine,
                        'maxLine' => $sourceLine,
                        'references' => 0,
                    ];
                }
                $lineStats[$tag]['minLine'] = min($lineStats[$tag]['minLine'], $sourceLine);
                $lineStats[$tag]['maxLine'] = max($lineStats[$tag]['maxLine'], $sourceLine);
                $lineStats[$tag]['references']++;
            }
        }

        ksort($inputs);
        $ranges = [];
        foreach ($lineStats as $tag => $stats) {
            if (!isset($inputs[$tag])) {
                continue;
            }

            $ranges[] = [
                'tag' => (int) $tag,
                'path' => $inputs[$tag]['path'],
                'minLine' => $stats['minLine'],
                'maxLine' => $stats['maxLine'],
                'references' => $stats['references'],
            ];
        }
        usort($ranges, static fn (array $a, array $b): int => [$a['tag'], $a['path']] <=> [$b['tag'], $b['path']]);

        $inputFileList = array_keys($inputFiles);
        sort($inputFileList);
        $externalInputList = array_keys($externalInputs);
        sort($externalInputList);

        return [
            'inputs' => array_values($inputs),
            'inputFiles' => $inputFileList,
            'externalInputs' => $externalInputList,
            'lineRanges' => $ranges,
        ];
    }

    /**
     * @return array{path:string, local:bool}
     */
    private function normalizeSourceMapInputPath(string $path): array
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, " \t\n\r\0\x0B'\"`");
        if ($path === '') {
            throw new \RuntimeException('source map input path is empty');
        }
        if (str_contains($path, "\0")) {
            throw new \RuntimeException('source map input path contains NUL bytes');
        }

        if (preg_match('/\Afile:\/\//i', $path) === 1) {
            $uriPath = parse_url($path, PHP_URL_PATH);
            if (is_string($uriPath) && $uriPath !== '') {
                $path = rawurldecode($uriPath);
            }
        }

        if (
            str_starts_with($path, '/')
            || preg_match('/\A[A-Za-z]:\//', $path) === 1
            || $this->isUriResourceReference($path)
        ) {
            return ['path' => $this->externalSourceMapInputName($path), 'local' => false];
        }

        try {
            return ['path' => $this->normalizeRelativePath($path, 'SyncTeX input path'), 'local' => true];
        } catch (\InvalidArgumentException) {
            return ['path' => $this->externalSourceMapInputName($path), 'local' => false];
        }
    }

    private function externalSourceMapInputName(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $uriPath = parse_url($path, PHP_URL_PATH);
        if (is_string($uriPath) && $uriPath !== '') {
            $path = $uriPath;
        }

        $basename = basename($path);

        return $basename === '' || $basename === '.' ? 'external-source' : $basename;
    }

    /**
     * @param list<array{tag:int, path:string}> $inputs
     */
    private function sourceMapReferencesSource(string $sourceFile, array $inputs): bool
    {
        $sourceFile = str_replace('\\', '/', $sourceFile);
        $sourceBasename = basename($sourceFile);
        foreach ($inputs as $input) {
            $inputPath = str_replace('\\', '/', $input['path']);
            if (
                $inputPath === $sourceFile
                || str_ends_with($inputPath, '/' . $sourceFile)
                || basename($inputPath) === $sourceBasename
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $texts
     * @return array{missing:bool, program:string|null}
     */
    private function extractMissingEngineProgram(string $engineProgram, int $exitCode, array $texts, mixed $explicit): array
    {
        $program = $this->engineName($engineProgram);
        if ($explicit !== null) {
            if (is_bool($explicit)) {
                return ['missing' => $explicit, 'program' => $explicit ? $program : null];
            }
            if (is_string($explicit)) {
                return ['missing' => true, 'program' => $this->engineName($explicit)];
            }

            throw new \InvalidArgumentException('PDF fake runner missingProgram must be a boolean or string');
        }

        $engineProgramLower = strtolower(str_replace('\\', '/', $engineProgram));
        $programLower = strtolower($program);
        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $lineLower = strtolower(str_replace('\\', '/', $line));
                $looksMissing = preg_match('/command not found|executable file not found|not recognized as an internal or external command|no such file or directory|\benoent\b|could not find executable|\bnot found\b/i', $line) === 1;
                if (!$looksMissing) {
                    continue;
                }

                $mentionsProgram = str_contains($lineLower, $programLower)
                    || str_contains($lineLower, $engineProgramLower);
                if ($mentionsProgram || $exitCode === 126 || $exitCode === 127) {
                    return ['missing' => true, 'program' => $program];
                }
            }
        }

        return ['missing' => false, 'program' => null];
    }

    /**
     * @param list<string> $texts
     * @return array{warnings:list<string>, errors:list<string>, rerunNeeded:bool}
     */
    private function extractEngineMessages(array $texts): array
    {
        $warnings = [];
        $errors = [];
        $rerunNeeded = false;

        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if ($this->isEngineErrorLine($line)) {
                    $errors[] = $line;
                    continue;
                }
                if ($this->isEngineWarningLine($line)) {
                    $warnings[] = $line;
                }
                if ($this->isEngineRerunLine($line)) {
                    $rerunNeeded = true;
                }
            }
        }

        return [
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'rerunNeeded' => $rerunNeeded,
        ];
    }

    /**
     * @param list<string> $texts
     * @return array{warnings:list<string>, errors:list<string>, needed:bool}
     */
    private function extractBibliographyMessages(array $texts): array
    {
        $warnings = [];
        $errors = [];
        $needed = false;

        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if ($this->isBibliographyErrorLine($line)) {
                    $errors[] = $line;
                    continue;
                }
                if ($this->isBibliographyWarningLine($line)) {
                    $warnings[] = $line;
                }
                if ($this->isBibliographyRunNeededLine($line)) {
                    $needed = true;
                }
            }
        }

        return [
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'needed' => $needed,
        ];
    }

    private function isBibliographyWarningLine(string $line): bool
    {
        if (preg_match('/\b0\s+warnings?\b/i', $line) === 1) {
            return false;
        }

        return preg_match('/\bwarning\b|^Warning--/i', $line) === 1
            && preg_match('/\b(?:biber|bibtex|biblatex|natbib|citation|citations|bibliograph|bibliography|entry|database)\b|^Warning--/i', $line) === 1;
    }

    private function isBibliographyErrorLine(string $line): bool
    {
        if (preg_match('/\b0\s+errors?\b/i', $line) === 1) {
            return false;
        }

        return preg_match('/\b(?:biber|bibtex|biblatex|natbib)\b.*\berror\b|\berror\b.*\b(?:biber|bibtex|biblatex|natbib|citation|bibliograph|bibliography)\b|\AI (?:couldn\'t open database file|found no \\\\bibdata command|found no \\\\bibstyle command)\b|\b[1-9]\d*\s+error messages?\b/i', $line) === 1;
    }

    private function isBibliographyRunNeededLine(string $line): bool
    {
        return preg_match('/please\s+\(re\)run\s+(?:biber|bibtex)|please\s+rerun\s+(?:biber|bibtex)|\brerun\s+(?:biber|bibtex)\b|\b(?:run|re-run)\s+(?:biber|bibtex)\b|undefined citations?|\bCitation\b.*\bundefined\b|No file .*\.bbl\b/i', $line) === 1;
    }

    private function isEngineWarningLine(string $line): bool
    {
        return preg_match('/\bwarning\b|\bwarning:/i', $line) === 1
            && preg_match('/\b0\s+warnings?\b/i', $line) !== 1;
    }

    private function isEngineErrorLine(string $line): bool
    {
        return preg_match('/\A! .*\bError:|\A! .*Error\b|\bFatal error\b|\bError:/i', $line) === 1
            && preg_match('/\b0\s+errors?\b/i', $line) !== 1;
    }

    private function isEngineRerunLine(string $line): bool
    {
        return preg_match('/\brerun\b/i', $line) === 1
            && preg_match('/cross-references?|bibliograph|citation|label|file .* changed|rerunfilecheck/i', $line) === 1;
    }

    /**
     * @param list<string> $texts
     * @return array{file:string|null, pages:int|null, bytes:int|null}
     */
    private function extractDeclaredOutput(array $texts): array
    {
        $output = ['file' => null, 'pages' => null, 'bytes' => null];

        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/\bOutput written on\s+(.+?)\s+\((\d+)\s+pages?,\s+(\d+)\s+bytes?\)\.?/i', $line, $matches) !== 1) {
                    continue;
                }

                $output = [
                    'file' => $this->normalizeDeclaredOutputFile($matches[1]),
                    'pages' => (int) $matches[2],
                    'bytes' => (int) $matches[3],
                ];
            }
        }

        return $output;
    }

    private function normalizeDeclaredOutputFile(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, " \t\n\r\0\x0B'\"`");
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path;
    }

    private function declaredOutputFileMatches(string $declaredOutputFile, string $outputFile): bool
    {
        return $declaredOutputFile === $outputFile
            || basename($declaredOutputFile) === basename($outputFile);
    }

    private function hasCompletePdfTrailer(string $pdfBytes): bool
    {
        return preg_match('/%%EOF\s*\z/s', $pdfBytes) === 1;
    }

    /**
     * @return array{
     *     trailerCount:int,
     *     trailerRevisions:list<array{revision:int, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, startxref:int|null, id:list<string>}>,
     *     startXrefOffsets:list<int>,
     *     incrementalUpdates:bool,
     *     xrefStreams:list<array{object:string, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, index:list<int>, w:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     xrefStreamFilters:array<string, int>,
     *     objectStreams:list<array{object:string, objectCount:int|null, firstByteOffset:int|null, extends:string|null, objectNumbers:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     objectStreamFilters:array<string, int>,
     *     pageCount:int|null,
     *     pageBoxes:list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}>,
     *     pageRotations:array<int, int>,
     *     pageDisplayMetadata:list<array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}>,
     *     pageLabels:list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>,
     *     pageTimings:list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>,
     *     pageViewports:list<array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}>,
     *     pageContentStreams:list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}>,
     *     pageContentResourceUsage:array<string, int>,
     *     pageResourceSources:list<array{page:int, pageObject:string|null, resourceSourceObject:string|null, inherited:bool, categories:list<string>, fontNames:list<string>, xobjectNames:list<string>, colorSpaceNames:list<string>, graphicsStateNames:list<string>, propertyNames:list<string>}>,
     *     fonts:list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>,
     *     fontSubtypes:array<string, int>,
     *     images:list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     imageColorSpaces:array<string, int>,
     *     imageFilters:array<string, int>,
     *     colorSpaces:list<array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}>,
     *     colorSpaceFamilies:array<string, int>,
     *     formXObjects:list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     formXObjectFilters:array<string, int>,
     *     outlineTitles:list<string>,
     *     outlines:list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}>,
     *     outlineDisplayMetadata:list<array{object:string, title:string, color:list<float>|null, flags:int, flagNames:list<string>}>,
     *     documentInfo:array<string, string>,
     *     documentInfoDateMetadata:list<array{key:string, source:string, raw:string, normalized:string|null, precision:string|null, timezone:string|null, timezoneOffsetMinutes:int|null, year:int|null, month:int|null, day:int|null, hour:int|null, minute:int|null, second:int|null, valid:bool}>,
     *     xmpMetadata:array<string, mixed>,
     *     pageMetadata:list<array<string, mixed>>,
     *     pieceInfo:list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}>,
     *     webCaptureMetadata:list<array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}>,
     *     outputIntents:list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     pageOutputIntents:list<array{page:int, pageObject:string|null, source:string, type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     language:string|null,
     *     pageLayout:string|null,
     *     pageMode:string|null,
     *     openAction:array<string, mixed>|null,
     *     namedDestinations:list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>,
     *     destinationOptions:list<array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}>,
     *     viewerPreferences:array<string, bool|int|string>,
     *     taggingMetadata:array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{},
     *     structureElements:list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>,
     *     markedContentProperties:list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}>,
     *     markedContentArtifacts:list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>,
     *     collectionMetadata:array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{},
     *     threads:list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>,
     *     catalogPermissions:list<array{permission:string, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     signatures:list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     signatureSubFilters:array<string, int>,
     *     activeActions:list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     activeActionTypes:array<string, int>,
     *     annotations:list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}>,
     *     annotationReviewMetadata:list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}>,
     *     annotationTypes:array<string, int>,
     *     linkTargets:list<string>,
     *     embeddedFileNames:list<string>,
     *     embeddedFiles:list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}>,
     *     acroFormCalculationOrder:list<array{order:int, fieldObject:string, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null, alternateName:string|null, mappingName:string|null, flags:int|null, flagNames:list<string>, missing:bool}>,
     *     formFields:list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}>,
     *     formFieldTypes:array<string, int>,
     *     encryption:array{
     *         encrypted:bool,
     *         filter:string|null,
     *         version:int|null,
     *         revision:int|null,
     *         length:int|null,
     *         permissions:int|null,
     *         permissionFlags:array<string, bool>,
     *         encryptMetadata:bool|null
     *     },
     *     headerVersion:string|null,
     *     catalogVersion:string|null,
     *     effectiveVersion:string|null,
     *     linearization:array{object:string|null, linearizedVersion:float|null, fileLength:int|null, primaryHintOffset:int|null, primaryHintLength:int|null, firstPageObject:int|null, firstPageEndOffset:int|null, pageCount:int|null, mainXrefOffset:int|null, hintTables:list<array{offset:int, length:int}>, lengthMatches:bool|null}|array{},
     *     extensionMetadata:list<array{prefix:string, baseVersion:string|null, extensionLevel:int|null}>
     * }
     */
    private function inspectPdfOutput(string $pdfBytes): array
    {
        $catalog = $this->extractPdfCatalogDictionary($pdfBytes);
        $headerVersion = $this->extractPdfHeaderVersion($pdfBytes);
        $catalogVersion = $this->extractPdfCatalogName($catalog, 'Version');
        $formFields = $this->extractPdfFormFields($pdfBytes, $catalog);
        $formFieldActions = $this->extractPdfFormFieldActions($pdfBytes, $catalog);
        $trailerRevisions = $this->extractPdfTrailerRevisions($pdfBytes);
        $xrefStreams = $this->extractPdfXrefStreams($pdfBytes);
        $objectStreams = $this->extractPdfObjectStreams($pdfBytes);
        $pageBoxes = $this->extractPdfPageBoxes($pdfBytes, $catalog);
        $pageProductionMetadata = $this->extractPdfPageProductionMetadata($pdfBytes, $catalog);
        $pageDisplayMetadata = $this->extractPdfPageDisplayMetadata($pdfBytes, $catalog);
        $pageTimings = $this->extractPdfPageTimings($pdfBytes, $catalog);
        $pageViewports = $this->extractPdfPageViewports($pdfBytes, $catalog);
        $pageContentStreams = $this->extractPdfPageContentStreams($pdfBytes, $catalog);
        $pageResourceSources = $this->extractPdfPageResourceSources($pdfBytes, $catalog);
        $fonts = $this->extractPdfFonts($pdfBytes, $catalog);
        $images = $this->extractPdfImages($pdfBytes, $catalog);
        $colorSpaces = $this->extractPdfColorSpaces($pdfBytes, $catalog);
        $formXObjects = $this->extractPdfFormXObjects($pdfBytes, $catalog);
        $graphicsStates = $this->extractPdfGraphicsStates($pdfBytes, $catalog);
        $optionalContent = $this->extractPdfOptionalContent($pdfBytes, $catalog);
        $signatures = $this->extractPdfSignatures($pdfBytes, $catalog);
        $activeActions = $this->extractPdfActiveActions($pdfBytes, $catalog);
        $richMediaAnnotations = $this->extractPdfRichMediaAnnotations($pdfBytes, $catalog);
        $annotationAppearances = $this->extractPdfAnnotationAppearances($pdfBytes, $catalog);
        $embeddedFiles = $this->extractPdfEmbeddedFiles($pdfBytes, $catalog);
        $documentInfo = $this->extractPdfDocumentInfo($pdfBytes);
        $embeddedFileNames = $this->extractPdfEmbeddedFileNames($pdfBytes);
        foreach ($embeddedFiles as $embeddedFile) {
            if (($embeddedFile['name'] ?? '') !== '') {
                $embeddedFileNames[] = $embeddedFile['name'];
            }
            if (is_string($embeddedFile['unicodeName'] ?? null) && $embeddedFile['unicodeName'] !== '') {
                $embeddedFileNames[] = $embeddedFile['unicodeName'];
            }
        }
        $embeddedFileNames = array_values(array_unique($embeddedFileNames));
        sort($embeddedFileNames);

        return [
            'trailerCount' => count($trailerRevisions),
            'trailerRevisions' => $trailerRevisions,
            'startXrefOffsets' => $this->pdfStartXrefOffsets($trailerRevisions),
            'incrementalUpdates' => $this->pdfHasIncrementalUpdates($trailerRevisions),
            'xrefStreams' => $xrefStreams,
            'xrefStreamFilters' => $this->summarizePdfStructuralStreamFilters($xrefStreams),
            'objectStreams' => $objectStreams,
            'objectStreamFilters' => $this->summarizePdfStructuralStreamFilters($objectStreams),
            'pageCount' => $this->extractPdfPageCount($pdfBytes),
            'pageBoxes' => $pageBoxes,
            'pageRotations' => $this->summarizePdfPageRotations($pageBoxes),
            'pageProductionMetadata' => $pageProductionMetadata,
            'pageDisplayMetadata' => $pageDisplayMetadata,
            'pageLabels' => $this->extractPdfPageLabels($pdfBytes, $catalog),
            'pageTimings' => $pageTimings,
            'pageViewports' => $pageViewports,
            'pageContentStreams' => $pageContentStreams,
            'pageContentResourceUsage' => $this->summarizePdfPageContentResourceUsage($pageContentStreams),
            'pageResourceSources' => $pageResourceSources,
            'fonts' => $fonts,
            'fontSubtypes' => $this->summarizePdfFontSubtypes($fonts),
            'images' => $images,
            'imageColorSpaces' => $this->summarizePdfImageColorSpaces($images),
            'imageFilters' => $this->summarizePdfImageFilters($images),
            'colorSpaces' => $colorSpaces,
            'colorSpaceFamilies' => $this->summarizePdfColorSpaceFamilies($colorSpaces),
            'formXObjects' => $formXObjects,
            'formXObjectFilters' => $this->summarizePdfFormXObjectFilters($formXObjects),
            'graphicsStates' => $graphicsStates,
            'graphicsStateBlendModes' => $this->summarizePdfGraphicsStateBlendModes($graphicsStates),
            'outlineTitles' => $this->extractPdfOutlineTitles($pdfBytes),
            'outlines' => $this->extractPdfOutlines($pdfBytes, $catalog),
            'outlineDisplayMetadata' => $this->extractPdfOutlineDisplayMetadata($pdfBytes, $catalog),
            'documentInfo' => $documentInfo,
            'documentInfoDateMetadata' => $this->extractPdfDocumentInfoDateMetadata($documentInfo),
            'xmpMetadata' => $this->extractPdfXmpMetadata($pdfBytes, $catalog),
            'pageMetadata' => $this->extractPdfPageMetadata($pdfBytes, $catalog),
            'pieceInfo' => $this->extractPdfPieceInfo($pdfBytes, $catalog),
            'webCaptureMetadata' => $this->extractPdfWebCaptureMetadata($pdfBytes, $catalog),
            'outputIntents' => $this->extractPdfOutputIntents($pdfBytes, $catalog),
            'pageOutputIntents' => $this->extractPdfPageOutputIntents($pdfBytes, $catalog),
            'language' => $this->extractPdfCatalogLanguage($pdfBytes, $catalog),
            'pageLayout' => $this->extractPdfCatalogName($catalog, 'PageLayout'),
            'pageMode' => $this->extractPdfCatalogName($catalog, 'PageMode'),
            'openAction' => $this->extractPdfOpenAction($pdfBytes, $catalog),
            'namedDestinations' => $this->extractPdfNamedDestinations($pdfBytes, $catalog),
            'destinationOptions' => $this->extractPdfDestinationOptions($pdfBytes, $catalog),
            'nameTrees' => $this->extractPdfNameTrees($pdfBytes, $catalog),
            'uriBase' => $this->extractPdfUriBase($pdfBytes, $catalog),
            'viewerPreferences' => $this->extractPdfViewerPreferences($pdfBytes, $catalog),
            'needsRendering' => $this->extractPdfNeedsRendering($catalog),
            'catalogRequirements' => $this->extractPdfCatalogRequirements($pdfBytes, $catalog),
            'legalAttestationMetadata' => $this->extractPdfLegalAttestationMetadata($pdfBytes, $catalog),
            'taggingMetadata' => $this->extractPdfTaggingMetadata($pdfBytes, $catalog),
            'structureElements' => $this->extractPdfStructureElements($pdfBytes),
            'markedContentProperties' => $this->extractPdfMarkedContentProperties($pdfBytes, $catalog),
            'markedContentArtifacts' => $this->extractPdfMarkedContentArtifacts($pdfBytes, $catalog),
            'optionalContentGroups' => $optionalContent['groups'],
            'optionalContentConfig' => $optionalContent['config'],
            'optionalContentMemberships' => $this->extractPdfOptionalContentMemberships($pdfBytes, $catalog),
            'collectionMetadata' => $this->extractPdfCollectionMetadata($pdfBytes, $catalog),
            'acroFormMetadata' => $this->extractPdfAcroFormMetadata($pdfBytes, $catalog),
            'acroFormCalculationOrder' => $this->extractPdfAcroFormCalculationOrder($pdfBytes, $catalog),
            'threads' => $this->extractPdfThreads($pdfBytes, $catalog),
            'catalogPermissions' => $this->extractPdfCatalogPermissions($pdfBytes, $catalog),
            'signatures' => $signatures,
            'signatureSubFilters' => $this->summarizePdfSignatureSubFilters($signatures),
            'activeActions' => $activeActions,
            'activeActionTypes' => $this->summarizePdfActiveActionTypes($activeActions),
            'richMediaAnnotations' => $richMediaAnnotations,
            'richMediaActivationModes' => $this->summarizePdfRichMediaActivationModes($richMediaAnnotations),
            'annotations' => $this->extractPdfAnnotations($pdfBytes, $catalog),
            'annotationReviewMetadata' => $this->extractPdfAnnotationReviewMetadata($pdfBytes, $catalog),
            'annotationAppearances' => $annotationAppearances,
            'annotationTypes' => $this->extractPdfAnnotationTypes($pdfBytes),
            'linkTargets' => $this->extractPdfLinkTargets($pdfBytes),
            'embeddedFileNames' => $embeddedFileNames,
            'embeddedFiles' => $embeddedFiles,
            'formFields' => $formFields,
            'formFieldTypes' => $this->summarizePdfFormFieldTypes($formFields),
            'formFieldActions' => $formFieldActions,
            'formFieldActionTypes' => $this->summarizePdfFormFieldActionTypes($formFieldActions),
            'encryption' => $this->extractPdfEncryptionInfo($pdfBytes),
            'headerVersion' => $headerVersion,
            'catalogVersion' => $catalogVersion,
            'effectiveVersion' => $this->effectivePdfVersion($headerVersion, $catalogVersion),
            'linearization' => $this->extractPdfLinearizationMetadata($pdfBytes),
            'extensionMetadata' => $this->extractPdfExtensionMetadata($pdfBytes, $catalog),
        ];
    }

    /**
     * @return array{object:string|null, linearizedVersion:float|null, fileLength:int|null, primaryHintOffset:int|null, primaryHintLength:int|null, firstPageObject:int|null, firstPageEndOffset:int|null, pageCount:int|null, mainXrefOffset:int|null, hintTables:list<array{offset:int, length:int}>, lengthMatches:bool|null}|array{}
     */
    private function extractPdfLinearizationMetadata(string $pdfBytes): array
    {
        if (preg_match('/\A%PDF-[^\r\n]*(?:\r\n|\r|\n)(?:%[^\r\n]*(?:\r\n|\r|\n))*\s*(\d+)\s+(\d+)\s+obj\s*(<<.*?>>)\s*endobj/s', $pdfBytes, $matches) !== 1) {
            return [];
        }

        $dictionary = $matches[3];
        if (!str_contains($dictionary, '/Linearized')) {
            return [];
        }

        $version = $this->extractPdfNumberToken($dictionary, 'Linearized');
        if ($version === null) {
            return [];
        }

        $hintTables = [];
        $hints = $this->extractPdfIntegerArrayToken($dictionary, 'H');
        for ($i = 0; $i + 1 < count($hints) && count($hintTables) < 8; $i += 2) {
            $hintTables[] = [
                'offset' => $hints[$i],
                'length' => $hints[$i + 1],
            ];
        }

        $fileLength = $this->extractPdfIntegerToken($dictionary, 'L');

        return [
            'object' => $matches[1] . ' ' . $matches[2] . ' R',
            'linearizedVersion' => $version,
            'fileLength' => $fileLength,
            'primaryHintOffset' => $hintTables[0]['offset'] ?? null,
            'primaryHintLength' => $hintTables[0]['length'] ?? null,
            'firstPageObject' => $this->extractPdfIntegerToken($dictionary, 'O'),
            'firstPageEndOffset' => $this->extractPdfIntegerToken($dictionary, 'E'),
            'pageCount' => $this->extractPdfIntegerToken($dictionary, 'N'),
            'mainXrefOffset' => $this->extractPdfIntegerToken($dictionary, 'T'),
            'hintTables' => $hintTables,
            'lengthMatches' => $fileLength === null ? null : $fileLength === strlen($pdfBytes),
        ];
    }

    private function extractPdfHeaderVersion(string $pdfBytes): ?string
    {
        if (preg_match('/\A%PDF-(\d+\.\d+)\b/', $pdfBytes, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function effectivePdfVersion(?string $headerVersion, ?string $catalogVersion): ?string
    {
        if ($headerVersion === null) {
            return $catalogVersion;
        }
        if ($catalogVersion === null) {
            return $headerVersion;
        }

        return version_compare($catalogVersion, $headerVersion, '>') ? $catalogVersion : $headerVersion;
    }

    /**
     * @return list<array{prefix:string, baseVersion:string|null, extensionLevel:int|null}>
     */
    private function extractPdfExtensionMetadata(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/Extensions')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $extensions = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Extensions', $objects);
        if ($extensions === null) {
            return [];
        }

        $metadata = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($extensions) as $entry) {
            $dictionary = null;
            if ($entry['value']['kind'] === 'dictionary') {
                $dictionary = $entry['value']['value'];
            } elseif ($entry['value']['kind'] === 'reference') {
                $dictionary = $objects[$this->pdfReferenceKey($entry['value']['value'])] ?? null;
            }

            if ($dictionary === null) {
                continue;
            }

            $metadata[] = [
                'prefix' => $entry['key'],
                'baseVersion' => $this->extractPdfNameToken($dictionary, 'BaseVersion'),
                'extensionLevel' => $this->extractPdfIntegerToken($dictionary, 'ExtensionLevel'),
            ];
        }

        usort($metadata, static fn (array $a, array $b): int => $a['prefix'] <=> $b['prefix']);

        return $metadata;
    }

    /**
     * @return list<array{revision:int, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, startxref:int|null, id:list<string>}>
     */
    private function extractPdfTrailerRevisions(string $pdfBytes): array
    {
        $revisions = [];
        $offset = 0;
        $length = strlen($pdfBytes);

        while ($offset < $length && ($position = strpos($pdfBytes, 'trailer', $offset)) !== false) {
            $before = $position > 0 ? $pdfBytes[$position - 1] : '';
            $afterPosition = $position + strlen('trailer');
            $after = $afterPosition < $length ? $pdfBytes[$afterPosition] : '';
            if (
                ($before !== '' && preg_match('/[A-Za-z0-9_.-]/', $before) === 1)
                || ($after !== '' && preg_match('/[A-Za-z0-9_.-]/', $after) === 1)
            ) {
                $offset = $afterPosition;
                continue;
            }

            $cursor = $afterPosition;
            while ($cursor < $length && ctype_space($pdfBytes[$cursor])) {
                $cursor++;
            }
            if (substr($pdfBytes, $cursor, 2) !== '<<') {
                $offset = min($length, $cursor + 1);
                continue;
            }

            $parsed = $this->parsePdfDictionary($pdfBytes, $cursor);
            if ($parsed === null) {
                $offset = $cursor + 2;
                continue;
            }

            $dictionary = $parsed['value'];
            $revisions[] = [
                'revision' => count($revisions) + 1,
                'size' => $this->extractPdfIntegerToken($dictionary, 'Size'),
                'root' => $this->extractPdfReferenceToken($dictionary, 'Root'),
                'info' => $this->extractPdfReferenceToken($dictionary, 'Info'),
                'encrypt' => $this->extractPdfReferenceToken($dictionary, 'Encrypt'),
                'prev' => $this->extractPdfIntegerToken($dictionary, 'Prev'),
                'startxref' => $this->extractPdfStartXrefAfter($pdfBytes, $parsed['next']),
                'id' => $this->extractPdfTrailerIdValues($dictionary),
            ];
            $offset = $parsed['next'];
        }

        return $revisions;
    }

    /**
     * @param list<array{revision:int, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, startxref:int|null, id:list<string>}> $revisions
     * @return list<int>
     */
    private function pdfStartXrefOffsets(array $revisions): array
    {
        $offsets = [];
        foreach ($revisions as $revision) {
            if ($revision['startxref'] !== null) {
                $offsets[] = $revision['startxref'];
            }
        }

        return $offsets;
    }

    /**
     * @param list<array{revision:int, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, startxref:int|null, id:list<string>}> $revisions
     */
    private function pdfHasIncrementalUpdates(array $revisions): bool
    {
        if (count($revisions) > 1) {
            return true;
        }

        foreach ($revisions as $revision) {
            if ($revision['prev'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{object:string, size:int|null, root:string|null, info:string|null, encrypt:string|null, prev:int|null, index:list<int>, w:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function extractPdfXrefStreams(string $pdfBytes): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $streams = [];
        foreach ($objects as $reference => $body) {
            if ($this->extractPdfNameToken($body, 'Type') !== 'XRef') {
                continue;
            }

            $stream = $this->summarizePdfStructuralStream($body, self::MAX_XREF_STREAM_BYTES);
            $streams[] = [
                'object' => $reference . ' R',
                'size' => $this->extractPdfIntegerToken($body, 'Size'),
                'root' => $this->extractPdfReferenceToken($body, 'Root'),
                'info' => $this->extractPdfReferenceToken($body, 'Info'),
                'encrypt' => $this->extractPdfReferenceToken($body, 'Encrypt'),
                'prev' => $this->extractPdfIntegerToken($body, 'Prev'),
                'index' => $this->extractPdfIntegerArrayToken($body, 'Index'),
                'w' => $this->extractPdfIntegerArrayToken($body, 'W'),
                'filters' => $this->extractPdfFilterNames($body, $objects),
                'streamBytes' => $stream['bytes'],
                'streamSha256' => $stream['sha256'],
                'streamSkipped' => $stream['skipped'],
            ];
        }

        usort($streams, fn (array $a, array $b): int => $this->pdfReferenceSortKey($a['object']) <=> $this->pdfReferenceSortKey($b['object']));

        return $streams;
    }

    /**
     * @return list<array{object:string, objectCount:int|null, firstByteOffset:int|null, extends:string|null, objectNumbers:list<int>, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function extractPdfObjectStreams(string $pdfBytes): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $streams = [];
        foreach ($objects as $reference => $body) {
            if ($this->extractPdfNameToken($body, 'Type') !== 'ObjStm') {
                continue;
            }

            $stream = $this->summarizePdfStructuralStream($body, self::MAX_OBJECT_STREAM_BYTES);
            $streams[] = [
                'object' => $reference . ' R',
                'objectCount' => $this->extractPdfIntegerToken($body, 'N'),
                'firstByteOffset' => $this->extractPdfIntegerToken($body, 'First'),
                'extends' => $this->extractPdfReferenceToken($body, 'Extends'),
                'objectNumbers' => $this->extractPdfObjectStreamObjectNumbers($body),
                'filters' => $this->extractPdfFilterNames($body, $objects),
                'streamBytes' => $stream['bytes'],
                'streamSha256' => $stream['sha256'],
                'streamSkipped' => $stream['skipped'],
            ];
        }

        usort($streams, fn (array $a, array $b): int => $this->pdfReferenceSortKey($a['object']) <=> $this->pdfReferenceSortKey($b['object']));

        return $streams;
    }

    /**
     * @param list<array{filters:list<string>}> $streams
     * @return array<string, int>
     */
    private function summarizePdfStructuralStreamFilters(array $streams): array
    {
        $filters = [];
        foreach ($streams as $stream) {
            foreach ($stream['filters'] as $filter) {
                if ($filter === '') {
                    continue;
                }

                $filters[$filter] = ($filters[$filter] ?? 0) + 1;
            }
        }

        ksort($filters);

        return $filters;
    }

    /**
     * @return array{bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfStructuralStream(string $dictionary, int $maxBytes): array
    {
        $summary = [
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];
        $bytes = $this->extractPdfStreamBytes($dictionary);
        if ($bytes === null) {
            return $summary;
        }

        $summary['bytes'] = strlen($bytes);
        if (preg_match('/\/Filter\b/s', $dictionary) === 1) {
            $summary['skipped'] = 'filtered';

            return $summary;
        }
        if (strlen($bytes) > $maxBytes) {
            $summary['skipped'] = 'too-large';

            return $summary;
        }

        $summary['sha256'] = hash('sha256', $bytes);

        return $summary;
    }

    /**
     * @return list<int>
     */
    private function extractPdfObjectStreamObjectNumbers(string $dictionary): array
    {
        if (preg_match('/\/Filter\b/s', $dictionary) === 1) {
            return [];
        }

        $bytes = $this->extractPdfStreamBytes($dictionary);
        if ($bytes === null || strlen($bytes) > self::MAX_OBJECT_STREAM_BYTES) {
            return [];
        }

        $first = $this->extractPdfIntegerToken($dictionary, 'First');
        $objectCount = $this->extractPdfIntegerToken($dictionary, 'N');
        $headerLength = $first === null ? strlen($bytes) : max(0, min(strlen($bytes), $first));
        $header = substr($bytes, 0, $headerLength);
        if (preg_match_all('/-?\d+/', $header, $matches) < 1) {
            return [];
        }

        $numbers = array_map('intval', $matches[0]);
        $objects = [];
        $limit = $objectCount === null ? count($numbers) : max(0, $objectCount);
        for ($i = 0; $i + 1 < count($numbers) && count($objects) < $limit; $i += 2) {
            if ($numbers[$i] < 0) {
                continue;
            }

            $objects[] = $numbers[$i];
        }

        return $objects;
    }

    /**
     * @return list<int>
     */
    private function extractPdfIntegerArrayToken(string $dictionary, string $name): array
    {
        $array = $this->extractPdfArrayValue($dictionary, $name);
        if ($array === null || preg_match_all('/-?\d+/', $array, $matches) < 1) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    /**
     * @return list<float>|null
     */
    private function extractPdfNumberArrayToken(string $dictionary, string $name, int $required): ?array
    {
        $array = $this->extractPdfArrayValue($dictionary, $name);
        if ($array === null || preg_match_all('/[-+]?(?:\d+\.\d*|\.\d+|\d+)(?:[Ee][-+]?\d+)?/', $array, $matches) < $required) {
            return null;
        }

        $numbers = [];
        foreach (array_slice($matches[0], 0, $required) as $number) {
            $numbers[] = (float) $number;
        }

        return $numbers;
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function pdfReferenceSortKey(string $reference): array
    {
        if (preg_match('/\A(\d+)\s+(\d+)\s+R\z/', trim($reference), $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [PHP_INT_MAX, PHP_INT_MAX];
    }

    private function extractPdfStartXrefAfter(string $pdfBytes, int $offset): ?int
    {
        $end = strpos($pdfBytes, '%%EOF', $offset);
        if ($end === false) {
            $end = min(strlen($pdfBytes), $offset + 4096);
        }

        $chunk = substr($pdfBytes, $offset, max(0, min($end, $offset + 4096) - $offset));
        if (preg_match('/\bstartxref\s+(\d+)\b/s', $chunk, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function extractPdfReferenceToken(string $dictionary, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+(\d+)\s+R\b/s', $dictionary, $matches) !== 1) {
            return null;
        }

        return $matches[1] . ' ' . $matches[2] . ' R';
    }

    /**
     * @return list<string>
     */
    private function extractPdfTrailerIdValues(string $dictionary): array
    {
        $array = $this->extractPdfArrayValue($dictionary, 'ID');
        if ($array === null) {
            return [];
        }

        $ids = [];
        if (preg_match_all('/<([0-9A-Fa-f\s]+)>/', $array, $matches) >= 1) {
            foreach ($matches[1] as $rawHex) {
                $id = strtoupper(preg_replace('/\s+/', '', $rawHex) ?? '');
                if ($id !== '') {
                    $ids[] = $id;
                }
                if (count($ids) >= 2) {
                    return $ids;
                }
            }
        }

        $cursor = 0;
        $length = strlen($array);
        while ($cursor < $length && count($ids) < 2) {
            if ($array[$cursor] !== '(') {
                $cursor++;
                continue;
            }

            $parsed = $this->parsePdfLiteralString($array, $cursor);
            if ($parsed === null) {
                $cursor++;
                continue;
            }

            $id = trim($parsed['value']);
            if ($id !== '') {
                $ids[] = $id;
            }
            $cursor = $parsed['next'];
        }

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private function extractPdfDocumentInfo(string $pdfBytes): array
    {
        $dictionary = $this->extractPdfInfoDictionary($pdfBytes);
        if ($dictionary === null) {
            return [];
        }

        $info = [];
        foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'] as $key) {
            $values = $this->extractPdfNamedStrings($dictionary, $key);
            if ($values === []) {
                continue;
            }

            $value = trim($values[0]);
            if ($value !== '') {
                $info[$key] = $value;
            }
        }

        $trapped = $this->extractPdfNameToken($dictionary, 'Trapped');
        if ($trapped !== null && $trapped !== '') {
            $info['Trapped'] = $trapped;
        }

        return $info;
    }

    /**
     * @param array<string, string> $documentInfo
     * @return list<array{key:string, source:string, raw:string, normalized:string|null, precision:string|null, timezone:string|null, timezoneOffsetMinutes:int|null, year:int|null, month:int|null, day:int|null, hour:int|null, minute:int|null, second:int|null, valid:bool}>
     */
    private function extractPdfDocumentInfoDateMetadata(array $documentInfo): array
    {
        $dates = [];
        foreach (['CreationDate', 'ModDate'] as $key) {
            $raw = trim($documentInfo[$key] ?? '');
            if ($raw === '') {
                continue;
            }

            $dates[] = $this->parsePdfDocumentInfoDate($key, $raw);
        }

        return $dates;
    }

    /**
     * @return array{key:string, source:string, raw:string, normalized:string|null, precision:string|null, timezone:string|null, timezoneOffsetMinutes:int|null, year:int|null, month:int|null, day:int|null, hour:int|null, minute:int|null, second:int|null, valid:bool}
     */
    private function parsePdfDocumentInfoDate(string $key, string $raw): array
    {
        $metadata = [
            'key' => $key,
            'source' => 'Info.' . $key,
            'raw' => $raw,
            'normalized' => null,
            'precision' => null,
            'timezone' => null,
            'timezoneOffsetMinutes' => null,
            'year' => null,
            'month' => null,
            'day' => null,
            'hour' => null,
            'minute' => null,
            'second' => null,
            'valid' => false,
        ];

        if (preg_match('/\A(?:D:)?(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?(?:(Z|z)|([+\-])(\d{2})\'?(\d{2})?\'?)?\z/', $raw, $matches) !== 1) {
            return $metadata;
        }

        $year = (int) $matches[1];
        $month = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null;
        $day = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : null;
        $hour = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : null;
        $minute = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : null;
        $second = isset($matches[6]) && $matches[6] !== '' ? (int) $matches[6] : null;
        $precision = 'year';
        if ($month !== null) {
            $precision = 'month';
        }
        if ($day !== null) {
            $precision = 'day';
        }
        if ($hour !== null) {
            $precision = 'hour';
        }
        if ($minute !== null) {
            $precision = 'minute';
        }
        if ($second !== null) {
            $precision = 'second';
        }

        $metadata['year'] = $year;
        $metadata['month'] = $month;
        $metadata['day'] = $day;
        $metadata['hour'] = $hour;
        $metadata['minute'] = $minute;
        $metadata['second'] = $second;
        $metadata['precision'] = $precision;

        $valid = true;
        if ($month !== null && ($month < 1 || $month > 12)) {
            $valid = false;
        }
        if ($day !== null && !checkdate($month ?? 1, $day, $year)) {
            $valid = false;
        }
        if ($hour !== null && ($hour < 0 || $hour > 23)) {
            $valid = false;
        }
        if ($minute !== null && ($minute < 0 || $minute > 59)) {
            $valid = false;
        }
        if ($second !== null && ($second < 0 || $second > 59)) {
            $valid = false;
        }

        if (isset($matches[7]) && $matches[7] !== '') {
            $metadata['timezone'] = 'Z';
            $metadata['timezoneOffsetMinutes'] = 0;
        } elseif (isset($matches[8]) && $matches[8] !== '') {
            $tzHour = isset($matches[9]) && $matches[9] !== '' ? (int) $matches[9] : 0;
            $tzMinute = isset($matches[10]) && $matches[10] !== '' ? (int) $matches[10] : 0;
            if ($tzHour > 23 || $tzMinute > 59) {
                $valid = false;
            }

            $metadata['timezone'] = sprintf('%s%02d:%02d', $matches[8], $tzHour, $tzMinute);
            $metadata['timezoneOffsetMinutes'] = ($matches[8] === '-' ? -1 : 1) * (($tzHour * 60) + $tzMinute);
        }

        $metadata['valid'] = $valid;
        if (!$valid) {
            return $metadata;
        }

        $normalized = sprintf('%04d', $year);
        if ($month !== null) {
            $normalized .= sprintf('-%02d', $month);
        }
        if ($day !== null) {
            $normalized .= sprintf('-%02d', $day);
        }
        if ($hour !== null) {
            $normalized .= sprintf('T%02d', $hour);
            if ($minute !== null) {
                $normalized .= sprintf(':%02d', $minute);
                if ($second !== null) {
                    $normalized .= sprintf(':%02d', $second);
                }
            }
            if (is_string($metadata['timezone'])) {
                $normalized .= $metadata['timezone'];
            }
        }
        $metadata['normalized'] = $normalized;

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPdfXmpMetadata(string $pdfBytes, ?string $catalog): array
    {
        $stream = $this->extractPdfMetadataStream($pdfBytes, $catalog);
        if ($stream === null) {
            return [];
        }

        return $this->summarizePdfXmpMetadataStream($stream);
    }

    /**
     * @param array{bytes:string, filtered:bool} $stream
     * @return array<string, mixed>
     */
    private function summarizePdfXmpMetadataStream(array $stream): array
    {
        if ($stream['filtered']) {
            return [
                'packetBytes' => strlen($stream['bytes']),
                'skipped' => 'filtered',
            ];
        }
        if (strlen($stream['bytes']) > self::MAX_XMP_METADATA_BYTES) {
            return [
                'packetBytes' => strlen($stream['bytes']),
                'skipped' => 'too-large',
            ];
        }

        $xml = $stream['bytes'];
        if (
            !str_contains($xml, '<rdf:RDF')
            && !str_contains($xml, '<x:xmpmeta')
            && !str_contains($xml, '<?xpacket')
        ) {
            return [];
        }

        $metadata = [
            'packetBytes' => strlen($xml),
            'packetSha256' => hash('sha256', $xml),
        ];

        foreach ([
            'title' => 'title',
            'description' => 'description',
            'format' => 'format',
            'creatorTool' => 'CreatorTool',
            'createDate' => 'CreateDate',
            'modifyDate' => 'ModifyDate',
            'metadataDate' => 'MetadataDate',
            'documentId' => 'DocumentID',
            'instanceId' => 'InstanceID',
        ] as $target => $name) {
            $value = in_array($name, ['title', 'description'], true)
                ? $this->xmpLocalizedText($xml, $name)
                : $this->xmpScalarText($xml, $name);
            if ($value !== null && $value !== '') {
                $metadata[$target] = $value;
            }
        }

        $creators = $this->xmpListText($xml, 'creator');
        if ($creators !== []) {
            $metadata['creators'] = $creators;
        }

        $pdfaPart = $this->xmpNamespaceScalarText($xml, self::XMP_PDF_A_ID_NAMESPACE, 'part', ['pdfaid']);
        $pdfaConformance = $this->xmpNamespaceScalarText($xml, self::XMP_PDF_A_ID_NAMESPACE, 'conformance', ['pdfaid']);
        if ($pdfaPart !== null || $pdfaConformance !== null) {
            $metadata['pdfaIdentification'] = [
                'part' => $pdfaPart,
                'conformance' => $pdfaConformance,
            ];
        }

        $pdfuaPart = $this->xmpNamespaceScalarText($xml, self::XMP_PDF_UA_ID_NAMESPACE, 'part', ['pdfuaid']);
        $pdfuaAmendment = $this->xmpNamespaceScalarText($xml, self::XMP_PDF_UA_ID_NAMESPACE, 'amd', ['pdfuaid']);
        $pdfuaCorrigendum = $this->xmpNamespaceScalarText($xml, self::XMP_PDF_UA_ID_NAMESPACE, 'corr', ['pdfuaid']);
        if ($pdfuaPart !== null || $pdfuaAmendment !== null || $pdfuaCorrigendum !== null) {
            $metadata['pdfuaIdentification'] = [
                'part' => $pdfuaPart,
                'amendment' => $pdfuaAmendment,
                'corrigendum' => $pdfuaCorrigendum,
            ];
        }

        $extensionSchemas = $this->xmpPdfaExtensionSchemas($xml);
        if ($extensionSchemas !== []) {
            $metadata['pdfaExtensionSchemas'] = $extensionSchemas;
        }

        return $metadata;
    }

    /**
     * @return list<array{schema:string|null, namespaceUri:string|null, prefix:string|null, properties:list<array{name:string, valueType:string|null, category:string|null, description:string|null}>}>
     */
    private function xmpPdfaExtensionSchemas(string $xml): array
    {
        if (
            !str_contains($xml, self::XMP_PDF_A_EXTENSION_NAMESPACE)
            && !str_contains($xml, self::XMP_PDF_A_SCHEMA_NAMESPACE)
            && !str_contains($xml, 'pdfaExtension:schemas')
            && !str_contains($xml, 'pdfaSchema:schema')
        ) {
            return [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('pdfaSchema', self::XMP_PDF_A_SCHEMA_NAMESPACE);
        $schemaNodes = $xpath->query('//pdfaSchema:schema');
        if (!$schemaNodes instanceof \DOMNodeList || $schemaNodes->length === 0) {
            return [];
        }

        $schemas = [];
        $seen = [];
        foreach ($schemaNodes as $schemaNode) {
            if (!$schemaNode instanceof \DOMElement || !$schemaNode->parentNode instanceof \DOMElement) {
                continue;
            }

            $schemaContainer = $schemaNode->parentNode;
            $schema = $this->normalizeXmpText($schemaNode->textContent);
            $namespaceUri = $this->xmpDirectChildText($schemaContainer, self::XMP_PDF_A_SCHEMA_NAMESPACE, 'namespaceURI');
            $prefix = $this->xmpDirectChildText($schemaContainer, self::XMP_PDF_A_SCHEMA_NAMESPACE, 'prefix');
            $key = implode("\0", [$schema ?? '', $namespaceUri ?? '', $prefix ?? '']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $schemas[] = [
                'schema' => $schema,
                'namespaceUri' => $namespaceUri,
                'prefix' => $prefix,
                'properties' => $this->xmpPdfaExtensionSchemaProperties($schemaContainer),
            ];
            if (count($schemas) >= 64) {
                break;
            }
        }

        usort(
            $schemas,
            static fn (array $a, array $b): int => [
                $a['prefix'] ?? '',
                $a['schema'] ?? '',
                $a['namespaceUri'] ?? '',
            ] <=> [
                $b['prefix'] ?? '',
                $b['schema'] ?? '',
                $b['namespaceUri'] ?? '',
            ]
        );

        return $schemas;
    }

    /**
     * @return list<array{name:string, valueType:string|null, category:string|null, description:string|null}>
     */
    private function xmpPdfaExtensionSchemaProperties(\DOMElement $schemaContainer): array
    {
        $properties = [];
        $seen = [];
        foreach ($this->xmpDescendantElements($schemaContainer, self::XMP_PDF_A_PROPERTY_NAMESPACE, 'name') as $nameNode) {
            if (!$nameNode->parentNode instanceof \DOMElement) {
                continue;
            }

            $propertyContainer = $nameNode->parentNode;
            $name = $this->normalizeXmpText($nameNode->textContent);
            if ($name === null || $name === '') {
                continue;
            }

            $valueType = $this->xmpDirectChildText($propertyContainer, self::XMP_PDF_A_PROPERTY_NAMESPACE, 'valueType');
            $category = $this->xmpDirectChildText($propertyContainer, self::XMP_PDF_A_PROPERTY_NAMESPACE, 'category');
            $description = $this->xmpDirectChildText($propertyContainer, self::XMP_PDF_A_PROPERTY_NAMESPACE, 'description');
            $key = implode("\0", [$name, $valueType ?? '', $category ?? '', $description ?? '']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $properties[] = [
                'name' => $name,
                'valueType' => $valueType,
                'category' => $category,
                'description' => $description,
            ];
            if (count($properties) >= 256) {
                break;
            }
        }

        usort(
            $properties,
            static fn (array $a, array $b): int => [
                $a['name'],
                $a['valueType'] ?? '',
            ] <=> [
                $b['name'],
                $b['valueType'] ?? '',
            ]
        );

        return $properties;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractPdfPageMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $pages = [];
        $visited = [];
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageMetadataFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $pages,
                0
            );
        }

        if ($pages === []) {
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $summary = $this->summarizePdfPageMetadata($body, $reference, $objects);
                if ($summary !== null) {
                    $pages[] = $summary;
                }
            }
        }

        foreach ($pages as $index => &$page) {
            $page['page'] = $index + 1;
        }
        unset($page);

        return $pages;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array<string, mixed>> $pages
     */
    private function collectPdfPageMetadataFromTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$pages,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $summary = $this->summarizePdfPageMetadata($body, $reference, $objects);
            if ($summary !== null) {
                $pages[] = $summary;
            }

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageMetadataFromTree(
                $objects,
                $this->pdfReferenceKey($kidReference),
                $visited,
                $pages,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, mixed>|null
     */
    private function summarizePdfPageMetadata(string $pageDictionary, string $pageReference, array $objects): ?array
    {
        $metadataReference = $this->extractPdfReferenceToken($pageDictionary, 'Metadata');
        if ($metadataReference === null) {
            return null;
        }

        $metadataObject = $objects[$this->pdfReferenceKey($metadataReference)] ?? null;
        if ($metadataObject === null) {
            return null;
        }

        $streamBytes = $this->extractPdfStreamBytes($metadataObject);
        if ($streamBytes === null) {
            return null;
        }

        $metadata = $this->summarizePdfXmpMetadataStream([
            'bytes' => $streamBytes,
            'filtered' => preg_match('/\/Filter\b/s', $metadataObject) === 1,
        ]);
        if ($metadata === []) {
            return null;
        }

        return array_merge([
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'metadataObject' => $metadataReference,
        ], $metadata);
    }

    /**
     * @return list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}>
     */
    private function extractPdfPieceInfo(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $entries = [];
        $pageEntries = [];

        if ($catalog !== null) {
            $pieceInfo = $this->extractPdfDictionaryOrReferenceValue($catalog, 'PieceInfo', $objects);
            if ($pieceInfo !== null) {
                array_push(
                    $entries,
                    ...$this->summarizePdfPieceInfoDictionary(
                        $pieceInfo,
                        'catalog.PieceInfo',
                        null,
                        null,
                        $objects
                    )
                );
            }
        }

        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');
        if ($pagesReference !== null) {
            $this->collectPdfPieceInfoFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $pageEntries,
                $pageNumber,
                0
            );
        }

        if ($pageEntries === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                array_push(
                    $pageEntries,
                    ...$this->summarizePdfPagePieceInfo(
                        $body,
                        $reference,
                        $objects,
                        $pageNumber
                    )
                );
            }
        }

        array_push($entries, ...$pageEntries);

        return $entries;
    }

    /**
     * @return list<array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}>
     */
    private function extractPdfWebCaptureMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $entries = [];
        $pageEntries = [];

        if ($catalog !== null) {
            $summary = $this->summarizePdfSpiderInfoValue(
                $this->extractPdfValueForName($catalog, 'SpiderInfo'),
                'catalog.SpiderInfo',
                null,
                null,
                $objects
            );
            if ($summary !== null) {
                $entries[] = $summary;
            }
        }

        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');
        if ($pagesReference !== null) {
            $this->collectPdfWebCaptureFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $pageEntries,
                $pageNumber,
                0
            );
        }

        if ($pageEntries === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $summary = $this->summarizePdfPageSpiderInfo($body, $reference, $objects, $pageNumber);
                if ($summary !== null) {
                    $pageEntries[] = $summary;
                }
            }
        }

        array_push($entries, ...$pageEntries);

        return $entries;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}> $entries
     */
    private function collectPdfWebCaptureFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$entries,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $summary = $this->summarizePdfPageSpiderInfo($body, $reference, $objects, $pageNumber);
            if ($summary !== null) {
                $entries[] = $summary;
            }

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfWebCaptureFromPageTree(
                $objects,
                $kidReference,
                $visited,
                $entries,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}|null
     */
    private function summarizePdfPageSpiderInfo(string $pageDictionary, string $pageReference, array $objects, int $pageNumber): ?array
    {
        return $this->summarizePdfSpiderInfoValue(
            $this->extractPdfValueForName($pageDictionary, 'SpiderInfo'),
            'page:' . $pageReference . ' R.SpiderInfo',
            $pageNumber,
            $pageReference . ' R',
            $objects
        );
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return array{source:string, page:int|null, pageObject:string|null, spiderInfoObject:string|null, version:float|null, commandCount:int, sourceUrls:list<string>, captures:list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>}|null
     */
    private function summarizePdfSpiderInfoValue(
        ?array $value,
        string $source,
        ?int $page,
        ?string $pageObject,
        array $objects
    ): ?array {
        $resolved = $this->resolvePdfDictionaryValue($value, $objects);
        if ($resolved['dictionary'] === null) {
            return null;
        }

        $commands = $this->extractPdfWebCaptureCommands(
            $this->extractPdfValueForName($resolved['dictionary'], 'C'),
            $objects
        );
        $sourceUrls = [];
        foreach ($commands as $command) {
            if (($command['sourceUrl'] ?? null) !== null) {
                $sourceUrls[] = $command['sourceUrl'];
            }
        }
        $sourceUrls = $this->uniqueStrings($sourceUrls);
        sort($sourceUrls);

        return [
            'source' => $source,
            'page' => $page,
            'pageObject' => $pageObject,
            'spiderInfoObject' => $resolved['object'],
            'version' => $this->extractPdfNumberToken($resolved['dictionary'], 'V'),
            'commandCount' => count($commands),
            'sourceUrls' => $sourceUrls,
            'captures' => $commands,
        ];
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return list<array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}>
     */
    private function extractPdfWebCaptureCommands(?array $value, array $objects, int $depth = 0): array
    {
        if ($value === null || $depth > 8) {
            return [];
        }

        if ($value['kind'] === 'array') {
            $commands = [];
            foreach ($this->pdfTopLevelArrayValues($value['value']) as $entry) {
                $summary = $this->summarizePdfWebCaptureCommandValue($entry, $objects, $depth + 1);
                if ($summary !== null) {
                    $commands[] = $summary;
                }
                if (count($commands) >= 64) {
                    break;
                }
            }

            return $commands;
        }

        if ($value['kind'] === 'reference') {
            $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
            if ($body === '') {
                return [];
            }
            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved === null) {
                return [];
            }
            if ($resolved['kind'] === 'array') {
                return $this->extractPdfWebCaptureCommands($resolved, $objects, $depth + 1);
            }
        }

        $summary = $this->summarizePdfWebCaptureCommandValue($value, $objects, $depth + 1);

        return $summary === null ? [] : [$summary];
    }

    /**
     * @param array{kind:string, value:string, next?:int} $value
     * @param array<string, string> $objects
     * @return array{commandObject:string|null, sourceUrl:string|null, sourceTitle:string|null, commandName:string|null, commandType:string|null, identifier:string|null, timestamp:string|null, flags:int|null, depth:int|null, pageReferences:list<string>, parentCommand:string|null, nextCommand:string|null}|null
     */
    private function summarizePdfWebCaptureCommandValue(array $value, array $objects, int $depth = 0): ?array
    {
        $resolved = $this->resolvePdfDictionaryValue($value, $objects);
        if ($resolved['dictionary'] === null) {
            return null;
        }

        $pageReferences = [];
        foreach (['Pages', 'Page'] as $name) {
            foreach ($this->collectPdfReferencesFromValue($this->extractPdfValueForName($resolved['dictionary'], $name), $objects) as $reference) {
                $pageReferences[] = $reference;
            }
        }
        $pageReferences = $this->uniqueStrings($pageReferences);

        return [
            'commandObject' => $resolved['object'],
            'sourceUrl' => $this->extractPdfStringOrNameValue($resolved['dictionary'], 'URL'),
            'sourceTitle' => $this->extractPdfStringOrNameValue($resolved['dictionary'], 'Title')
                ?? $this->extractPdfStringOrNameValue($resolved['dictionary'], 'T'),
            'commandName' => $this->extractPdfStringOrNameValue($resolved['dictionary'], 'N'),
            'commandType' => $this->extractPdfStringOrNameValue($resolved['dictionary'], 'CT')
                ?? $this->extractPdfStringOrNameValue($resolved['dictionary'], 'S'),
            'identifier' => $this->extractPdfStringOrNameValue($resolved['dictionary'], 'ID'),
            'timestamp' => $this->extractPdfStringOrNameValue($resolved['dictionary'], 'TS'),
            'flags' => $this->extractPdfIntegerToken($resolved['dictionary'], 'F'),
            'depth' => $this->extractPdfIntegerToken($resolved['dictionary'], 'L'),
            'pageReferences' => $pageReferences,
            'parentCommand' => $this->extractPdfReferenceValue($resolved['dictionary'], 'P'),
            'nextCommand' => $this->extractPdfReferenceValue($resolved['dictionary'], 'Next'),
        ];
    }

    private function extractPdfReferenceValue(string $dictionary, string $name): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, $name);

        return $value !== null && $value['kind'] === 'reference' ? $value['value'] : null;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}> $entries
     */
    private function collectPdfPieceInfoFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$entries,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            array_push(
                $entries,
                ...$this->summarizePdfPagePieceInfo(
                    $body,
                    $reference,
                    $objects,
                    $pageNumber
                )
            );

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPieceInfoFromPageTree(
                $objects,
                $kidReference,
                $visited,
                $entries,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}>
     */
    private function summarizePdfPagePieceInfo(string $pageDictionary, string $pageReference, array $objects, int $pageNumber): array
    {
        $pieceInfo = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'PieceInfo', $objects);
        if ($pieceInfo === null) {
            return [];
        }

        return $this->summarizePdfPieceInfoDictionary(
            $pieceInfo,
            'page:' . $pageReference . ' R.PieceInfo',
            $pageNumber,
            $pageReference . ' R',
            $objects
        );
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{source:string, page:int|null, pageObject:string|null, application:string, pieceObject:string|null, lastModified:string|null, privateObject:string|null, privateKeys:list<string>, privateValues:array<string, bool|float|int|string|null>, privateStreamBytes:int|null, privateStreamSha256:string|null, privateStreamSkipped:string|null}>
     */
    private function summarizePdfPieceInfoDictionary(
        string $pieceInfo,
        string $source,
        ?int $page,
        ?string $pageObject,
        array $objects
    ): array {
        $entries = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($pieceInfo) as $entry) {
            if ($entry['key'] === 'Type') {
                continue;
            }

            $pieceObject = null;
            $pieceDictionary = null;
            if ($entry['value']['kind'] === 'reference') {
                $pieceObject = $entry['value']['value'];
                $pieceDictionary = $objects[$this->pdfReferenceKey($entry['value']['value'])] ?? null;
            } elseif ($entry['value']['kind'] === 'dictionary') {
                $pieceObject = 'inline';
                $pieceDictionary = $entry['value']['value'];
            }

            if ($pieceDictionary === null) {
                continue;
            }

            $private = $this->summarizePdfPieceInfoPrivateObject($pieceDictionary, $objects);
            $entries[] = [
                'source' => $source,
                'page' => $page,
                'pageObject' => $pageObject,
                'application' => $entry['key'],
                'pieceObject' => $pieceObject,
                'lastModified' => $this->extractPdfStringOrNameValue($pieceDictionary, 'LastModified'),
                'privateObject' => $private['object'],
                'privateKeys' => $private['keys'],
                'privateValues' => $private['values'],
                'privateStreamBytes' => $private['streamBytes'],
                'privateStreamSha256' => $private['streamSha256'],
                'privateStreamSkipped' => $private['streamSkipped'],
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, string> $objects
     * @return array{object:string|null, keys:list<string>, values:array<string, bool|float|int|string|null>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}
     */
    private function summarizePdfPieceInfoPrivateObject(string $pieceDictionary, array $objects): array
    {
        $summary = [
            'object' => null,
            'keys' => [],
            'values' => [],
            'streamBytes' => null,
            'streamSha256' => null,
            'streamSkipped' => null,
        ];

        $value = $this->extractPdfValueForName($pieceDictionary, 'Private');
        if ($value === null) {
            return $summary;
        }

        if ($value['kind'] === 'reference') {
            $summary['object'] = $value['value'];
            $privateObject = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($privateObject === null) {
                return $summary;
            }
        } elseif ($value['kind'] === 'dictionary') {
            $summary['object'] = 'inline';
            $privateObject = $value['value'];
        } else {
            $scalar = $this->pdfPieceInfoScalarValue($value);
            if ($scalar['ok']) {
                $summary['keys'] = ['value'];
                $summary['values'] = ['value' => $scalar['value']];
            }

            return $summary;
        }

        foreach ($this->extractPdfTopLevelDictionaryEntries($privateObject) as $entry) {
            if ($this->isPdfPieceInfoPrivateTechnicalKey($entry['key'])) {
                continue;
            }

            $summary['keys'][] = $entry['key'];
            $scalar = $this->pdfPieceInfoScalarValue($entry['value']);
            if ($scalar['ok']) {
                $summary['values'][$entry['key']] = $scalar['value'];
            }
        }
        $summary['keys'] = array_values(array_unique($summary['keys']));
        sort($summary['keys']);
        ksort($summary['values']);

        $streamBytes = $this->extractPdfStreamBytes($privateObject);
        if ($streamBytes !== null) {
            $summary['streamBytes'] = strlen($streamBytes);
            if (preg_match('/\/Filter\b/s', $privateObject) === 1) {
                $summary['streamSkipped'] = 'filtered';
            } elseif (strlen($streamBytes) > self::MAX_PIECE_INFO_PRIVATE_STREAM_BYTES) {
                $summary['streamSkipped'] = 'too-large';
            } else {
                $summary['streamSha256'] = hash('sha256', $streamBytes);
            }
        }

        return $summary;
    }

    private function isPdfPieceInfoPrivateTechnicalKey(string $key): bool
    {
        return in_array($key, ['DecodeParms', 'DL', 'Filter', 'Length', 'Subtype', 'Type'], true);
    }

    /**
     * @param array{kind:string, value:string} $value
     * @return array{ok:bool, value:bool|float|int|string|null}
     */
    private function pdfPieceInfoScalarValue(array $value): array
    {
        if (in_array($value['kind'], ['literal', 'hex', 'name', 'reference'], true)) {
            return ['ok' => true, 'value' => $value['value']];
        }

        if ($value['kind'] === 'number') {
            $number = $value['value'];
            if (preg_match('/[.Ee]/', $number) === 1) {
                return ['ok' => true, 'value' => (float) $number];
            }

            return ['ok' => true, 'value' => (int) $number];
        }

        if ($value['kind'] === 'keyword') {
            if ($value['value'] === 'true' || $value['value'] === 'false') {
                return ['ok' => true, 'value' => $value['value'] === 'true'];
            }
            if ($value['value'] === 'null') {
                return ['ok' => true, 'value' => null];
            }
        }

        return ['ok' => false, 'value' => null];
    }

    /**
     * @return array{bytes:string, filtered:bool}|null
     */
    private function extractPdfMetadataStream(string $pdfBytes, ?string $catalog): ?array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $candidates = [];
        $metadataReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Metadata');
        if ($metadataReference !== null) {
            $reference = $this->pdfReferenceKey($metadataReference);
            if (isset($objects[$reference])) {
                $candidates[] = $objects[$reference];
            }
        }

        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (
                !str_contains($body, '/Metadata')
                && !str_contains($body, '/Subtype /XML')
                && !str_contains($body, '/Subtype/XML')
            ) {
                continue;
            }
            $candidates[] = $body;
        }

        foreach ($candidates as $body) {
            $bytes = $this->extractPdfStreamBytes($body);
            if ($bytes === null) {
                continue;
            }

            return [
                'bytes' => $bytes,
                'filtered' => preg_match('/\/Filter\b/s', $body) === 1,
            ];
        }

        return null;
    }

    /**
     * @return list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>
     */
    private function extractPdfOutputIntents(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/OutputIntents')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $array = $this->extractPdfOutputIntentArrayValue($catalog, $objects);
        if ($array === null) {
            return [];
        }

        return $this->summarizePdfOutputIntentArray($array, $objects);
    }

    /**
     * @return list<array{page:int, pageObject:string|null, source:string, type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>
     */
    private function extractPdfPageOutputIntents(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $intents = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageOutputIntentsFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $intents,
                $pageNumber,
                0
            );
        }

        if ($intents === []) {
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                array_push(
                    $intents,
                    ...$this->summarizePdfPageOutputIntents(
                        $body,
                        $reference,
                        $objects,
                        $pageNumber
                    )
                );
            }
        }

        return $intents;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, source:string, type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}> $intents
     */
    private function collectPdfPageOutputIntentsFromTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$intents,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            array_push(
                $intents,
                ...$this->summarizePdfPageOutputIntents($body, $reference, $objects, $pageNumber)
            );

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageOutputIntentsFromTree(
                $objects,
                $this->pdfReferenceKey($kidReference),
                $visited,
                $intents,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, source:string, type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>
     */
    private function summarizePdfPageOutputIntents(string $pageDictionary, string $pageReference, array $objects, int $pageNumber): array
    {
        if (!str_contains($pageDictionary, '/OutputIntents')) {
            return [];
        }

        $array = $this->extractPdfOutputIntentArrayValue($pageDictionary, $objects);
        if ($array === null) {
            return [];
        }

        $intents = [];
        $source = 'page:' . $pageReference . ' R.OutputIntents';
        foreach ($this->summarizePdfOutputIntentArray($array, $objects) as $intent) {
            $intents[] = array_merge([
                'page' => $pageNumber,
                'pageObject' => $pageReference . ' R',
                'source' => $source,
            ], $intent);
        }

        return $intents;
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>
     */
    private function summarizePdfOutputIntentArray(string $array, array $objects): array
    {
        $intents = [];
        $cursor = 0;
        $length = strlen($array);
        while ($cursor < $length && count($intents) < 16) {
            while ($cursor < $length && ctype_space($array[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $length) {
                break;
            }

            if (substr($array, $cursor, 2) === '<<') {
                $parsed = $this->parsePdfDictionary($array, $cursor);
                if ($parsed !== null) {
                    $intent = $this->summarizePdfOutputIntent($parsed['value'], $objects);
                    if ($intent !== null) {
                        $intents[] = $intent;
                    }
                    $cursor = $parsed['next'];
                    continue;
                }
            }

            if (preg_match('/\A(\d+)\s+(\d+)\s+R\b/s', substr($array, $cursor), $matches) === 1) {
                $reference = $matches[1] . ' ' . $matches[2];
                if (isset($objects[$reference])) {
                    $intent = $this->summarizePdfOutputIntent($objects[$reference], $objects);
                    if ($intent !== null) {
                        $intents[] = $intent;
                    }
                }
                $cursor += strlen($matches[0]);
                continue;
            }

            $cursor++;
        }

        return $intents;
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfOutputIntentArrayValue(string $catalog, array $objects): ?string
    {
        $array = $this->extractPdfArrayValue($catalog, 'OutputIntents');
        if ($array !== null) {
            return $array;
        }

        $reference = $this->extractPdfReferenceToken($catalog, 'OutputIntents');
        if ($reference === null) {
            return null;
        }

        $body = $objects[$this->pdfReferenceKey($reference)] ?? null;
        if ($body === null) {
            return null;
        }

        $offset = strpos($body, '[');
        if ($offset === false) {
            return null;
        }

        $parsed = $this->parsePdfArray($body, $offset);

        return $parsed === null ? null : $parsed['value'];
    }

    /**
     * @param array<string, string> $objects
     * @return array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}|null
     */
    private function summarizePdfOutputIntent(string $dictionary, array $objects): ?array
    {
        if (
            !str_contains($dictionary, '/OutputIntent')
            && !str_contains($dictionary, '/OutputConditionIdentifier')
            && !str_contains($dictionary, '/DestOutputProfile')
        ) {
            return null;
        }

        $profileReference = $this->extractPdfReferenceToken($dictionary, 'DestOutputProfile');
        $profile = $profileReference === null
            ? [
                'components' => null,
                'alternate' => null,
                'bytes' => null,
                'sha256' => null,
                'skipped' => null,
            ]
            : $this->summarizePdfOutputProfile($objects[$this->pdfReferenceKey($profileReference)] ?? null);

        return [
            'type' => $this->extractPdfNameToken($dictionary, 'Type'),
            'subtype' => $this->extractPdfNameToken($dictionary, 'S'),
            'outputConditionIdentifier' => $this->extractPdfStringOrNameValue($dictionary, 'OutputConditionIdentifier'),
            'outputCondition' => $this->extractPdfStringOrNameValue($dictionary, 'OutputCondition'),
            'registryName' => $this->extractPdfStringOrNameValue($dictionary, 'RegistryName'),
            'info' => $this->extractPdfStringOrNameValue($dictionary, 'Info'),
            'destOutputProfile' => $profileReference,
            'profileComponents' => $profile['components'],
            'profileAlternate' => $profile['alternate'],
            'profileBytes' => $profile['bytes'],
            'profileSha256' => $profile['sha256'],
            'profileSkipped' => $profile['skipped'],
        ];
    }

    /**
     * @return array{components:int|null, alternate:string|null, bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfOutputProfile(?string $profileObject): array
    {
        $profile = [
            'components' => null,
            'alternate' => null,
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];

        if ($profileObject === null) {
            return $profile;
        }

        $profile['components'] = $this->extractPdfIntegerToken($profileObject, 'N');
        $profile['alternate'] = $this->extractPdfNameToken($profileObject, 'Alternate');
        $bytes = $this->extractPdfStreamBytes($profileObject);
        if ($bytes === null) {
            return $profile;
        }

        $profile['bytes'] = strlen($bytes);
        if (preg_match('/\/Filter\b/s', $profileObject) === 1) {
            $profile['skipped'] = 'filtered';

            return $profile;
        }
        if (strlen($bytes) > self::MAX_OUTPUT_INTENT_PROFILE_BYTES) {
            $profile['skipped'] = 'too-large';

            return $profile;
        }

        $profile['sha256'] = hash('sha256', $bytes);

        return $profile;
    }

    private function extractPdfStreamBytes(string $objectBody): ?string
    {
        $streamPosition = strpos($objectBody, 'stream');
        if ($streamPosition === false) {
            return null;
        }

        $start = $streamPosition + strlen('stream');
        if (substr($objectBody, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (isset($objectBody[$start]) && ($objectBody[$start] === "\n" || $objectBody[$start] === "\r")) {
            $start++;
        }

        $end = strpos($objectBody, 'endstream', $start);
        if ($end === false || $end < $start) {
            return null;
        }

        $bytes = substr($objectBody, $start, $end - $start);
        if (str_ends_with($bytes, "\r\n")) {
            return substr($bytes, 0, -2);
        }
        if (str_ends_with($bytes, "\n") || str_ends_with($bytes, "\r")) {
            return substr($bytes, 0, -1);
        }

        return $bytes;
    }

    private function xmpLocalizedText(string $xml, string $name): ?string
    {
        $block = null;
        foreach ($this->xmpNamespacePrefixes($xml, 'http://purl.org/dc/elements/1.1/', ['dc']) as $prefix) {
            $block = $this->xmpQualifiedElementBlock($xml, $prefix, $name);
            if ($block !== null) {
                break;
            }
        }
        $block ??= $this->xmpUnqualifiedElementBlock($xml, $name);
        if ($block === null) {
            return $this->xmpUnqualifiedScalarText($xml, $name);
        }

        if (preg_match('/<[^:>]*(?::)?li\b[^>]*\bxml:lang\s*=\s*["\']x-default["\'][^>]*>(.*?)<\/[^:>]*(?::)?li>/s', $block, $matches) === 1) {
            return $this->normalizeXmpText($matches[1]);
        }

        foreach ($this->xmpElementTextsFromBlock($block, 'li') as $value) {
            return $value;
        }

        return $this->normalizeXmpText($block);
    }

    private function xmpScalarText(string $xml, string $name): ?string
    {
        $block = $this->xmpElementBlock($xml, $name);
        if ($block !== null) {
            return $this->normalizeXmpText($block);
        }

        if (preg_match('/\b(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/s', $xml, $matches) === 1) {
            return $this->normalizeXmpText($matches[2]);
        }

        return null;
    }

    private function xmpQualifiedScalarText(string $xml, string $prefix, string $name): ?string
    {
        $block = $this->xmpQualifiedElementBlock($xml, $prefix, $name);
        if ($block !== null) {
            return $this->normalizeXmpText($block);
        }

        if (preg_match('/\b' . preg_quote($prefix, '/') . ':' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/s', $xml, $matches) === 1) {
            return $this->normalizeXmpText($matches[2]);
        }

        return null;
    }

    /**
     * @param list<string> $fallbackPrefixes
     */
    private function xmpNamespaceScalarText(string $xml, string $namespaceUri, string $name, array $fallbackPrefixes): ?string
    {
        foreach ($this->xmpNamespacePrefixes($xml, $namespaceUri, $fallbackPrefixes) as $prefix) {
            $value = $this->xmpQualifiedScalarText($xml, $prefix, $name);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<string> $fallbackPrefixes
     * @return list<string>
     */
    private function xmpNamespacePrefixes(string $xml, string $namespaceUri, array $fallbackPrefixes): array
    {
        $prefixes = [];
        if (preg_match_all('/\bxmlns:([A-Za-z_][A-Za-z0-9_.-]*)\s*=\s*(["\'])(.*?)\2/s', $xml, $matches, PREG_SET_ORDER) >= 1) {
            foreach ($matches as $match) {
                $uri = html_entity_decode($match[3], ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($uri === $namespaceUri) {
                    $prefixes[] = $match[1];
                }
            }
        }

        foreach ($fallbackPrefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '') {
                $prefixes[] = $prefix;
            }
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * @return list<string>
     */
    private function xmpListText(string $xml, string $name): array
    {
        $block = $this->xmpElementBlock($xml, $name);
        if ($block === null) {
            $scalar = $this->xmpScalarText($xml, $name);

            return $scalar === null || $scalar === '' ? [] : [$scalar];
        }

        $values = $this->xmpElementTextsFromBlock($block, 'li');
        if ($values === []) {
            $scalar = $this->normalizeXmpText($block);
            if ($scalar !== null && $scalar !== '') {
                $values[] = $scalar;
            }
        }

        return array_values(array_unique($values));
    }

    private function xmpElementBlock(string $xml, string $name): ?string
    {
        if (preg_match('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($name, '/') . '\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($name, '/') . '>/s', $xml, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function xmpQualifiedElementBlock(string $xml, string $prefix, string $name): ?string
    {
        if (preg_match('/<' . preg_quote($prefix, '/') . ':' . preg_quote($name, '/') . '\b[^>]*>(.*?)<\/' . preg_quote($prefix, '/') . ':' . preg_quote($name, '/') . '>/s', $xml, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function xmpUnqualifiedElementBlock(string $xml, string $name): ?string
    {
        if (preg_match('/<' . preg_quote($name, '/') . '\b[^>]*>(.*?)<\/' . preg_quote($name, '/') . '>/s', $xml, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function xmpUnqualifiedScalarText(string $xml, string $name): ?string
    {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/s', $xml, $matches) !== 1) {
            return null;
        }

        return $this->normalizeXmpText($matches[2]);
    }

    private function xmpDirectChildText(\DOMElement $element, string $namespaceUri, string $localName): ?string
    {
        foreach ($element->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->namespaceURI === $namespaceUri
                && $child->localName === $localName
            ) {
                return $this->normalizeXmpText($child->textContent);
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function xmpDescendantElements(\DOMNode $node, string $namespaceUri, string $localName): array
    {
        $elements = [];
        foreach ($node->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->namespaceURI === $namespaceUri
                && $child->localName === $localName
            ) {
                $elements[] = $child;
            }

            foreach ($this->xmpDescendantElements($child, $namespaceUri, $localName) as $descendant) {
                $elements[] = $descendant;
            }
        }

        return $elements;
    }

    /**
     * @return list<string>
     */
    private function xmpElementTextsFromBlock(string $block, string $name): array
    {
        if (preg_match_all('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($name, '/') . '\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($name, '/') . '>/s', $block, $matches) < 1) {
            return [];
        }

        $values = [];
        foreach ($matches[1] as $rawValue) {
            $value = $this->normalizeXmpText($rawValue);
            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function normalizeXmpText(string $text): ?string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function extractPdfInfoDictionary(string $pdfBytes): ?string
    {
        if (preg_match('/\/Info\s+(\d+)\s+(\d+)\s+R\b/s', $pdfBytes, $matches) === 1) {
            $objects = $this->pdfObjectBodiesByReference($pdfBytes);
            $key = $matches[1] . ' ' . $matches[2];

            return $objects[$key] ?? null;
        }

        $offset = 0;
        while (($position = strpos($pdfBytes, '/Info', $offset)) !== false) {
            $cursor = $position + strlen('/Info');
            if ($cursor < strlen($pdfBytes) && preg_match('/[A-Za-z0-9_.-]/', $pdfBytes[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < strlen($pdfBytes) && ctype_space($pdfBytes[$cursor])) {
                $cursor++;
            }
            if (substr($pdfBytes, $cursor, 2) !== '<<') {
                $offset = $cursor + 1;
                continue;
            }

            $parsed = $this->parsePdfDictionary($pdfBytes, $cursor);
            if ($parsed !== null) {
                return $parsed['value'];
            }

            $offset = $cursor + 2;
        }

        return null;
    }

    private function extractPdfCatalogLanguage(string $pdfBytes, ?string $catalog = null): ?string
    {
        if ($catalog !== null) {
            foreach ($this->extractPdfNamedStrings($catalog, 'Lang') as $language) {
                $language = trim($language);
                if ($language !== '') {
                    return $language;
                }
            }

            $language = $this->extractPdfNameToken($catalog, 'Lang');
            if ($language !== null && $language !== '') {
                return $language;
            }
        }

        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/s', $body) !== 1 || !str_contains($body, '/Lang')) {
                continue;
            }

            foreach ($this->extractPdfNamedStrings($body, 'Lang') as $language) {
                $language = trim($language);
                if ($language !== '') {
                    return $language;
                }
            }

            $language = $this->extractPdfNameToken($body, 'Lang');
            if ($language !== null && $language !== '') {
                return $language;
            }
        }

        return null;
    }

    private function extractPdfCatalogDictionary(string $pdfBytes): ?string
    {
        if (preg_match('/\/Root\s+(\d+)\s+(\d+)\s+R\b/s', $pdfBytes, $matches) === 1) {
            $objects = $this->pdfObjectBodiesByReference($pdfBytes);
            $key = $matches[1] . ' ' . $matches[2];
            if (isset($objects[$key])) {
                return $objects[$key];
            }
        }

        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/s', $body) === 1) {
                return $body;
            }
        }

        $offset = 0;
        while (($position = strpos($pdfBytes, '/Root', $offset)) !== false) {
            $cursor = $position + strlen('/Root');
            if ($cursor < strlen($pdfBytes) && preg_match('/[A-Za-z0-9_.-]/', $pdfBytes[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < strlen($pdfBytes) && ctype_space($pdfBytes[$cursor])) {
                $cursor++;
            }
            if (substr($pdfBytes, $cursor, 2) !== '<<') {
                $offset = $cursor + 1;
                continue;
            }

            $parsed = $this->parsePdfDictionary($pdfBytes, $cursor);
            if ($parsed !== null) {
                return $parsed['value'];
            }

            $offset = $cursor + 2;
        }

        return null;
    }

    private function extractPdfCatalogName(?string $catalog, string $name): ?string
    {
        if ($catalog === null) {
            return null;
        }

        $value = $this->extractPdfNameToken($catalog, $name);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPdfOpenAction(string $pdfBytes, ?string $catalog): ?array
    {
        if ($catalog === null || !str_contains($catalog, '/OpenAction')) {
            return null;
        }

        if (preg_match('/\/OpenAction\s+(\d+)\s+(\d+)\s+R\b/s', $catalog, $matches) === 1) {
            $objects = $this->pdfObjectBodiesByReference($pdfBytes);
            $body = $objects[$matches[1] . ' ' . $matches[2]] ?? null;
            if ($body !== null) {
                return $this->summarizePdfOpenActionValue($body);
            }
        }

        return $this->extractPdfOpenActionValue($catalog);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPdfOpenActionValue(string $dictionary): ?array
    {
        $needle = '/OpenAction';
        $offset = 0;
        $length = strlen($dictionary);
        while (($position = strpos($dictionary, $needle, $offset)) !== false) {
            $cursor = $position + strlen($needle);
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $dictionary[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $length) {
                return null;
            }

            if ($dictionary[$cursor] === '[') {
                $parsed = $this->parsePdfArray($dictionary, $cursor);
                return $parsed === null ? null : $this->summarizePdfDestinationArray($parsed['value']);
            }

            if (substr($dictionary, $cursor, 2) === '<<') {
                $parsed = $this->parsePdfDictionary($dictionary, $cursor);
                return $parsed === null ? null : $this->summarizePdfActionDictionary($parsed['value']);
            }

            if ($dictionary[$cursor] === '(') {
                $parsed = $this->parsePdfLiteralString($dictionary, $cursor);
                if ($parsed !== null && trim($parsed['value']) !== '') {
                    return ['type' => 'named-destination', 'target' => trim($parsed['value'])];
                }
            }

            if ($dictionary[$cursor] === '<' && ($cursor + 1 >= $length || $dictionary[$cursor + 1] !== '<')) {
                $parsed = $this->parsePdfHexString($dictionary, $cursor);
                if ($parsed !== null && trim($parsed['value']) !== '') {
                    return ['type' => 'named-destination', 'target' => trim($parsed['value'])];
                }
            }

            if (preg_match('/\/([A-Za-z0-9_.#+-]+)/A', substr($dictionary, $cursor), $matches) === 1) {
                return ['type' => 'named-action', 'target' => $this->decodePdfNameToken($matches[1])];
            }

            $offset = $cursor + 1;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summarizePdfOpenActionValue(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '<<')) {
            return $this->summarizePdfActionDictionary($value);
        }
        if (str_starts_with($value, '[')) {
            return $this->summarizePdfDestinationArray($value);
        }

        return $this->extractPdfOpenActionValue('/OpenAction ' . $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summarizePdfActionDictionary(string $dictionary): ?array
    {
        $type = $this->extractPdfNameToken($dictionary, 'S');
        if ($type === null || $type === '') {
            return ['type' => 'action'];
        }

        if ($type === 'URI') {
            $targets = $this->extractPdfNamedStrings($dictionary, 'URI');
            $target = $targets === [] ? null : trim($targets[0]);

            return $target === null || $target === ''
                ? ['type' => 'URI']
                : ['type' => 'URI', 'target' => $target];
        }

        if ($type === 'GoTo') {
            $destination = $this->extractPdfDestinationValue($dictionary, 'D');

            return $destination === null
                ? ['type' => 'GoTo']
                : array_merge(['type' => 'GoTo'], $destination);
        }

        if ($type === 'Named') {
            $target = $this->extractPdfNameToken($dictionary, 'N');
            if ($target === null) {
                $values = $this->extractPdfNamedStrings($dictionary, 'N');
                $target = $values === [] ? null : trim($values[0]);
            }

            return $target === null || $target === ''
                ? ['type' => 'Named']
                : ['type' => 'Named', 'target' => $target];
        }

        if ($type === 'Launch') {
            $target = $this->pdfLaunchActionTarget($dictionary);

            return $target === null || $target === ''
                ? ['type' => 'Launch']
                : ['type' => 'Launch', 'target' => $target];
        }

        return ['type' => $type];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPdfDestinationValue(string $dictionary, string $name): ?array
    {
        $needle = '/' . $name;
        $offset = 0;
        $length = strlen($dictionary);
        while (($position = strpos($dictionary, $needle, $offset)) !== false) {
            $cursor = $position + strlen($needle);
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $dictionary[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $length) {
                return null;
            }
            if ($dictionary[$cursor] === '[') {
                $parsed = $this->parsePdfArray($dictionary, $cursor);
                return $parsed === null ? null : $this->summarizePdfDestinationArray($parsed['value']);
            }
            if ($dictionary[$cursor] === '(') {
                $parsed = $this->parsePdfLiteralString($dictionary, $cursor);
                return $parsed === null || trim($parsed['value']) === ''
                    ? null
                    : ['target' => trim($parsed['value'])];
            }
            if ($dictionary[$cursor] === '<' && ($cursor + 1 >= $length || $dictionary[$cursor + 1] !== '<')) {
                $parsed = $this->parsePdfHexString($dictionary, $cursor);
                return $parsed === null || trim($parsed['value']) === ''
                    ? null
                    : ['target' => trim($parsed['value'])];
            }
            if (preg_match('/\/([A-Za-z0-9_.#+-]+)/A', substr($dictionary, $cursor), $matches) === 1) {
                return ['target' => $this->decodePdfNameToken($matches[1])];
            }

            $offset = $cursor + 1;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizePdfDestinationArray(string $array): array
    {
        $summary = ['type' => 'destination'];
        if (preg_match('/\b(\d+)\s+(\d+)\s+R\b/s', $array, $matches) === 1) {
            $summary['pageObject'] = $matches[1] . ' ' . $matches[2] . ' R';
        }
        if (preg_match('/\/(XYZ|Fit|FitH|FitV|FitR|FitB|FitBH|FitBV)\b/s', $array, $matches) === 1) {
            $summary['fit'] = $matches[1];
        }
        if (!isset($summary['pageObject'])) {
            if (preg_match('/\[\s*\/([A-Za-z0-9_.#+-]+)/s', $array, $matches) === 1) {
                $summary['target'] = $this->decodePdfNameToken($matches[1]);
            } else {
                $strings = [];
                if (preg_match('/\[\s*\(/s', $array) === 1) {
                    $parsed = $this->parsePdfLiteralString($array, strpos($array, '(') ?: 0);
                    if ($parsed !== null) {
                        $strings[] = trim($parsed['value']);
                    }
                }
                if ($strings !== [] && $strings[0] !== '') {
                    $summary['target'] = $strings[0];
                }
            }
        }

        return $summary;
    }

    /**
     * @return list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>
     */
    private function extractPdfNamedDestinations(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || (!str_contains($catalog, '/Names') && !str_contains($catalog, '/Dests'))) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $destinations = [];
        $visited = [];

        $names = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Names', $objects);
        if ($names !== null) {
            $dests = $this->extractPdfDictionaryOrReferenceValue($names, 'Dests', $objects);
            if ($dests !== null) {
                $this->collectPdfNameTreeDestinations(
                    $destinations,
                    'catalog.Names.Dests',
                    $dests,
                    $objects,
                    $visited,
                    0
                );
            }
        }

        $legacyDests = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Dests', $objects);
        if ($legacyDests !== null) {
            $this->collectPdfDestinationDictionaryEntries(
                $destinations,
                'catalog.Dests',
                $legacyDests,
                $objects,
                $visited
            );
        }

        $destinations = array_values($destinations);
        usort(
            $destinations,
            static fn (array $a, array $b): int => [
                $a['name'],
                $a['source'],
                $a['target'] ?? '',
                $a['pageObject'] ?? '',
                $a['fit'] ?? '',
            ] <=> [
                $b['name'],
                $b['source'],
                $b['target'] ?? '',
                $b['pageObject'] ?? '',
                $b['fit'] ?? '',
            ]
        );

        return $destinations;
    }

    /**
     * @return list<array{category:string, source:string, entryCount:int, names:list<string>, valueKinds:array<string, int>, valueReferences:list<string>, kidCount:int, limits:list<string>}>
     */
    private function extractPdfNameTrees(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/Names')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $names = $this->resolvePdfDictionaryValue($this->extractPdfValueForName($catalog, 'Names'), $objects);
        if ($names['dictionary'] === null) {
            return [];
        }

        $trees = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($names['dictionary']) as $entry) {
            if (in_array($entry['key'], ['Kids', 'Limits', 'Names', 'Type'], true)) {
                continue;
            }

            $root = $this->resolvePdfDictionaryValue($entry['value'], $objects);
            if ($root['dictionary'] === null) {
                continue;
            }

            $state = [
                'entryCount' => 0,
                'names' => [],
                'valueKinds' => [],
                'valueReferences' => [],
                'kidCount' => 0,
                'limits' => [],
            ];
            $visited = [];
            if (is_string($root['object']) && $root['object'] !== 'inline') {
                $visited[$this->pdfReferenceKey($root['object'])] = true;
            }

            $this->collectPdfNameTreeInventory(
                $state,
                $root['dictionary'],
                $objects,
                $visited,
                0
            );

            $state['names'] = $this->uniqueStrings($state['names']);
            sort($state['names']);
            ksort($state['valueKinds']);
            $state['valueReferences'] = $this->uniqueStrings($state['valueReferences']);
            sort($state['valueReferences']);
            $state['limits'] = $this->uniqueStrings($state['limits']);

            $trees[] = [
                'category' => $entry['key'],
                'source' => 'catalog.Names.' . $entry['key'],
                'entryCount' => $state['entryCount'],
                'names' => $state['names'],
                'valueKinds' => $state['valueKinds'],
                'valueReferences' => $state['valueReferences'],
                'kidCount' => $state['kidCount'],
                'limits' => $state['limits'],
            ];
        }

        usort($trees, static fn (array $a, array $b): int => $a['category'] <=> $b['category']);

        return $trees;
    }

    /**
     * @param array{entryCount:int, names:list<string>, valueKinds:array<string, int>, valueReferences:list<string>, kidCount:int, limits:list<string>} $state
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfNameTreeInventory(
        array &$state,
        string $dictionary,
        array $objects,
        array &$visited,
        int $depth
    ): void {
        if ($depth > 16) {
            return;
        }

        $limits = $this->extractPdfArrayValue($dictionary, 'Limits');
        if ($limits !== null) {
            foreach ($this->collectPdfNamesFromArray($limits) as $limit) {
                $state['limits'][] = $limit;
            }
        }

        $array = $this->extractPdfArrayValue($dictionary, 'Names');
        if ($array !== null) {
            $values = $this->pdfTopLevelArrayValues($array);
            for ($index = 0; $index + 1 < count($values); $index += 2) {
                $name = $values[$index];
                $value = $values[$index + 1];
                if (!in_array($name['kind'], ['literal', 'hex', 'name'], true)) {
                    continue;
                }

                $nameValue = trim($name['value']);
                if ($nameValue === '') {
                    continue;
                }

                $state['entryCount']++;
                if (count($state['names']) < 128) {
                    $state['names'][] = $nameValue;
                }
                $state['valueKinds'][$value['kind']] = ($state['valueKinds'][$value['kind']] ?? 0) + 1;
                if ($value['kind'] === 'reference') {
                    $state['valueReferences'][] = $value['value'];
                }
            }
        }

        foreach ($this->extractPdfReferenceArray($dictionary, 'Kids') as $kidReference) {
            if (isset($visited[$kidReference]) || !isset($objects[$kidReference])) {
                continue;
            }

            $visited[$kidReference] = true;
            $state['kidCount']++;
            $this->collectPdfNameTreeInventory(
                $state,
                $objects[$kidReference],
                $objects,
                $visited,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}> $destinations
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfNameTreeDestinations(
        array &$destinations,
        string $source,
        string $dictionary,
        array $objects,
        array &$visited,
        int $depth
    ): void {
        if ($depth > 16) {
            return;
        }

        $array = $this->extractPdfArrayValue($dictionary, 'Names');
        if ($array !== null) {
            $cursor = str_starts_with($array, '[') ? 1 : 0;
            $length = strlen($array);
            if (str_ends_with($array, ']')) {
                $length--;
            }

            while ($cursor < $length) {
                $name = $this->parsePdfValueAt($array, $cursor);
                if ($name === null) {
                    $cursor++;
                    continue;
                }
                $cursor = $name['next'];
                $value = $this->parsePdfValueAt($array, $cursor);
                if ($value === null) {
                    break;
                }
                $cursor = $value['next'];
                if (!in_array($name['kind'], ['literal', 'hex', 'name'], true)) {
                    continue;
                }

                $nameValue = trim($name['value']);
                if ($nameValue === '') {
                    continue;
                }

                $summary = $this->summarizePdfNamedDestinationValue($value, $objects);
                if ($summary !== null) {
                    $this->addPdfNamedDestination($destinations, $nameValue, $source, $summary);
                }
            }
        }

        foreach ($this->extractPdfReferenceArray($dictionary, 'Kids') as $kidReference) {
            if (isset($visited[$kidReference]) || !isset($objects[$kidReference])) {
                continue;
            }

            $visited[$kidReference] = true;
            $this->collectPdfNameTreeDestinations(
                $destinations,
                $source . '.Kids.' . $kidReference . ' R',
                $objects[$kidReference],
                $objects,
                $visited,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}> $destinations
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfDestinationDictionaryEntries(
        array &$destinations,
        string $source,
        string $dictionary,
        array $objects,
        array &$visited
    ): void {
        if (str_contains($dictionary, '/Names') || str_contains($dictionary, '/Kids')) {
            $this->collectPdfNameTreeDestinations($destinations, $source, $dictionary, $objects, $visited, 0);
        }

        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            $name = $entry['key'];
            if (in_array($name, ['Kids', 'Limits', 'Names', 'Type'], true)) {
                continue;
            }

            $summary = $this->summarizePdfNamedDestinationValue($entry['value'], $objects);
            if ($summary !== null) {
                $this->addPdfNamedDestination($destinations, $name, $source, $summary);
            }
        }
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array<string, mixed>|null
     */
    private function summarizePdfNamedDestinationValue(array $value, array $objects): ?array
    {
        if ($value['kind'] === 'array') {
            return $this->summarizePdfDestinationArray($value['value']);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfDestinationDictionary($value['value'], $objects);
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return null;
            }

            return $this->summarizePdfDestinationObjectBody($body, $objects);
        }

        if (in_array($value['kind'], ['literal', 'hex', 'name'], true)) {
            $target = trim($value['value']);

            return $target === '' ? null : ['type' => 'named-destination', 'target' => $target];
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, mixed>|null
     */
    private function summarizePdfDestinationObjectBody(string $body, array $objects): ?array
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        if (str_starts_with($body, '[')) {
            $parsed = $this->parsePdfArray($body, 0);

            return $parsed === null ? null : $this->summarizePdfDestinationArray($parsed['value']);
        }

        if (str_starts_with($body, '<<')) {
            $parsed = $this->parsePdfDictionary($body, 0);

            return $parsed === null ? null : $this->summarizePdfDestinationDictionary($parsed['value'], $objects);
        }

        $value = $this->parsePdfValueAt($body, 0);

        return $value === null ? null : $this->summarizePdfNamedDestinationValue($value, $objects);
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, mixed>|null
     */
    private function summarizePdfDestinationDictionary(string $dictionary, array $objects): ?array
    {
        $destination = $this->extractPdfDestinationValue($dictionary, 'D')
            ?? $this->extractPdfDestinationValue($dictionary, 'Dest');
        if ($destination !== null) {
            return $destination;
        }

        $value = $this->extractPdfValueForName($dictionary, 'D');
        if ($value !== null) {
            return $this->summarizePdfNamedDestinationValue($value, $objects);
        }

        return null;
    }

    /**
     * @param array<string, array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}> $destinations
     * @param array<string, mixed> $summary
     */
    private function addPdfNamedDestination(array &$destinations, string $name, string $source, array $summary): void
    {
        $entry = [
            'name' => $name,
            'source' => $source,
            'target' => is_string($summary['target'] ?? null) && $summary['target'] !== '' ? $summary['target'] : null,
            'pageObject' => is_string($summary['pageObject'] ?? null) && $summary['pageObject'] !== '' ? $summary['pageObject'] : null,
            'fit' => is_string($summary['fit'] ?? null) && $summary['fit'] !== '' ? $summary['fit'] : null,
        ];
        $key = implode("\0", [
            $entry['name'],
            $entry['source'],
            $entry['target'] ?? '',
            $entry['pageObject'] ?? '',
            $entry['fit'] ?? '',
        ]);
        $destinations[$key] = $entry;
    }

    /**
     * @return list<array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}>
     */
    private function extractPdfDestinationOptions(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $options = [];

        $openAction = $this->extractPdfValueForName($catalog, 'OpenAction');
        if ($openAction !== null) {
            $this->addPdfDestinationOptionFromValue($options, 'catalog.OpenAction', null, $openAction, $objects);
        }

        $names = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Names', $objects);
        if ($names !== null) {
            $dests = $this->extractPdfDictionaryOrReferenceValue($names, 'Dests', $objects);
            if ($dests !== null) {
                $visited = [];
                $this->collectPdfNameTreeDestinationOptions(
                    $options,
                    'catalog.Names.Dests',
                    $dests,
                    $objects,
                    $visited,
                    0
                );
            }
        }

        $legacyDests = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Dests', $objects);
        if ($legacyDests !== null) {
            $visited = [];
            $this->collectPdfDestinationDictionaryOptions(
                $options,
                'catalog.Dests',
                $legacyDests,
                $objects,
                $visited
            );
        }

        foreach ($objects as $reference => $body) {
            if (str_contains($body, '/Title') && preg_match('/\/(?:Parent|Dest|A|Next|Prev|First|Last)\b/s', $body) === 1) {
                $destination = $this->extractPdfValueForName($body, 'Dest');
                if ($destination !== null) {
                    $this->addPdfDestinationOptionFromValue($options, 'outline:' . $reference . ' R.Dest', null, $destination, $objects);
                }

                $action = $this->extractPdfDictionaryOrReferenceValue($body, 'A', $objects);
                if ($action !== null && $this->extractPdfNameToken($action, 'S') === 'GoTo') {
                    $actionDestination = $this->extractPdfValueForName($action, 'D');
                    if ($actionDestination !== null) {
                        $this->addPdfDestinationOptionFromValue($options, 'outline:' . $reference . ' R.A.D', null, $actionDestination, $objects);
                    }
                }
            }

            $subtype = $this->extractPdfNameToken($body, 'Subtype');
            if ($subtype === null || !$this->isPdfAnnotationSubtype($subtype)) {
                continue;
            }

            $destination = $this->extractPdfValueForName($body, 'Dest');
            if ($destination !== null) {
                $this->addPdfDestinationOptionFromValue($options, 'annotation:' . $reference . ' R.Dest', null, $destination, $objects);
            }

            $action = $this->extractPdfDictionaryOrReferenceValue($body, 'A', $objects);
            if ($action !== null && $this->extractPdfNameToken($action, 'S') === 'GoTo') {
                $actionDestination = $this->extractPdfValueForName($action, 'D');
                if ($actionDestination !== null) {
                    $this->addPdfDestinationOptionFromValue($options, 'annotation:' . $reference . ' R.A.D', null, $actionDestination, $objects);
                }
            }
        }

        $options = array_values($options);
        usort(
            $options,
            static fn (array $a, array $b): int => [
                $a['source'],
                $a['name'] ?? '',
                $a['target'] ?? '',
                $a['pageObject'] ?? '',
                $a['fit'] ?? '',
                json_encode($a['arguments']),
            ] <=> [
                $b['source'],
                $b['name'] ?? '',
                $b['target'] ?? '',
                $b['pageObject'] ?? '',
                $b['fit'] ?? '',
                json_encode($b['arguments']),
            ]
        );

        return $options;
    }

    /**
     * @param array<string, array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}> $options
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfNameTreeDestinationOptions(
        array &$options,
        string $source,
        string $dictionary,
        array $objects,
        array &$visited,
        int $depth
    ): void {
        if ($depth > 16) {
            return;
        }

        $array = $this->extractPdfArrayValue($dictionary, 'Names');
        if ($array !== null) {
            $values = $this->pdfTopLevelArrayValues($array);
            for ($index = 0; $index + 1 < count($values); $index += 2) {
                $name = $values[$index];
                $value = $values[$index + 1];
                if (!in_array($name['kind'], ['literal', 'hex', 'name'], true)) {
                    continue;
                }

                $nameValue = trim($name['value']);
                if ($nameValue === '') {
                    continue;
                }

                $this->addPdfDestinationOptionFromValue($options, $source, $nameValue, $value, $objects);
            }
        }

        foreach ($this->extractPdfReferenceArray($dictionary, 'Kids') as $kidReference) {
            if (isset($visited[$kidReference]) || !isset($objects[$kidReference])) {
                continue;
            }

            $visited[$kidReference] = true;
            $this->collectPdfNameTreeDestinationOptions(
                $options,
                $source . '.Kids.' . $kidReference . ' R',
                $objects[$kidReference],
                $objects,
                $visited,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}> $options
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfDestinationDictionaryOptions(
        array &$options,
        string $source,
        string $dictionary,
        array $objects,
        array &$visited
    ): void {
        if (str_contains($dictionary, '/Names') || str_contains($dictionary, '/Kids')) {
            $this->collectPdfNameTreeDestinationOptions($options, $source, $dictionary, $objects, $visited, 0);
        }

        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            $name = $entry['key'];
            if (in_array($name, ['Kids', 'Limits', 'Names', 'Type'], true)) {
                continue;
            }

            $this->addPdfDestinationOptionFromValue($options, $source, $name, $entry['value'], $objects);
        }
    }

    /**
     * @param array<string, array{source:string, name:string|null, pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}> $options
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     */
    private function addPdfDestinationOptionFromValue(
        array &$options,
        string $source,
        ?string $name,
        array $value,
        array $objects
    ): void {
        $summary = $this->summarizePdfDestinationOptionValue($value, $objects, 0);
        if ($summary === null) {
            return;
        }

        $entry = array_merge([
            'source' => $source,
            'name' => $name,
        ], $summary);
        $key = implode("\0", [
            $entry['source'],
            $entry['name'] ?? '',
            $entry['target'] ?? '',
            $entry['pageObject'] ?? '',
            $entry['fit'] ?? '',
            json_encode($entry['arguments']),
        ]);
        $options[$key] = $entry;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}|null
     */
    private function summarizePdfDestinationOptionValue(array $value, array $objects, int $depth): ?array
    {
        if ($depth > 8) {
            return null;
        }

        if ($value['kind'] === 'array') {
            return $this->summarizePdfDestinationOptionArray($value['value']);
        }

        if ($value['kind'] === 'dictionary') {
            $actionType = $this->extractPdfNameToken($value['value'], 'S');
            if ($actionType !== null && $actionType !== 'GoTo') {
                return null;
            }

            $destination = $this->extractPdfValueForName($value['value'], 'D')
                ?? $this->extractPdfValueForName($value['value'], 'Dest');

            return $destination === null
                ? null
                : $this->summarizePdfDestinationOptionValue($destination, $objects, $depth + 1);
        }

        if ($value['kind'] === 'reference') {
            $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
            if ($body === '') {
                return null;
            }

            $resolved = $this->parsePdfValueAt($body, 0);

            return $resolved === null
                ? null
                : $this->summarizePdfDestinationOptionValue($resolved, $objects, $depth + 1);
        }

        if (in_array($value['kind'], ['literal', 'hex', 'name'], true)) {
            $target = trim($value['value']);

            return $target === ''
                ? null
                : $this->pdfDestinationOptionSummary(null, $target, null, []);
        }

        return null;
    }

    /**
     * @return array{pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}|null
     */
    private function summarizePdfDestinationOptionArray(string $array): ?array
    {
        $values = $this->pdfTopLevelArrayValues($array);
        if ($values === []) {
            return null;
        }

        $fitNames = ['XYZ' => true, 'Fit' => true, 'FitH' => true, 'FitV' => true, 'FitR' => true, 'FitB' => true, 'FitBH' => true, 'FitBV' => true];
        $pageObject = null;
        $target = null;
        $fit = null;
        $fitIndex = null;

        foreach ($values as $index => $value) {
            if ($pageObject === null && $value['kind'] === 'reference') {
                $pageObject = $value['value'];
                continue;
            }

            if ($value['kind'] === 'name' && isset($fitNames[$value['value']])) {
                $fit = $value['value'];
                $fitIndex = $index;
                break;
            }

            if ($pageObject === null && $target === null && in_array($value['kind'], ['literal', 'hex', 'name'], true)) {
                $targetValue = trim($value['value']);
                if ($targetValue !== '') {
                    $target = $targetValue;
                }
            }
        }

        if ($pageObject === null && $target === null && $fit === null) {
            return null;
        }

        $arguments = [];
        if ($fitIndex !== null) {
            for ($index = $fitIndex + 1; $index < count($values) && count($arguments) < 8; $index++) {
                $argument = $this->pdfDestinationArgumentValue($values[$index]);
                if ($argument['accepted']) {
                    $arguments[] = $argument['value'];
                }
            }
        }

        return $this->pdfDestinationOptionSummary($pageObject, $target, $fit, $arguments);
    }

    /**
     * @param list<float|null> $arguments
     * @return array{pageObject:string|null, target:string|null, fit:string|null, arguments:list<float|null>, left:float|null, top:float|null, right:float|null, bottom:float|null, zoom:float|null}
     */
    private function pdfDestinationOptionSummary(?string $pageObject, ?string $target, ?string $fit, array $arguments): array
    {
        $left = null;
        $top = null;
        $right = null;
        $bottom = null;
        $zoom = null;

        if ($fit === 'XYZ') {
            $left = $arguments[0] ?? null;
            $top = $arguments[1] ?? null;
            $zoom = $arguments[2] ?? null;
        } elseif ($fit === 'FitH' || $fit === 'FitBH') {
            $top = $arguments[0] ?? null;
        } elseif ($fit === 'FitV' || $fit === 'FitBV') {
            $left = $arguments[0] ?? null;
        } elseif ($fit === 'FitR') {
            $left = $arguments[0] ?? null;
            $bottom = $arguments[1] ?? null;
            $right = $arguments[2] ?? null;
            $top = $arguments[3] ?? null;
        }

        return [
            'pageObject' => $pageObject,
            'target' => $target,
            'fit' => $fit,
            'arguments' => $arguments,
            'left' => $left,
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'zoom' => $zoom,
        ];
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @return array{accepted:bool, value:float|null}
     */
    private function pdfDestinationArgumentValue(array $value): array
    {
        if ($value['kind'] === 'number') {
            return ['accepted' => true, 'value' => (float) $value['value']];
        }

        if ($value['kind'] === 'keyword' && $value['value'] === 'null') {
            return ['accepted' => true, 'value' => null];
        }

        return ['accepted' => false, 'value' => null];
    }

    /**
     * @param list<array{fit:string|null}> $destinationOptions
     * @return array<string, int>
     */
    private function summarizePdfDestinationFits(array $destinationOptions): array
    {
        $fits = [];
        foreach ($destinationOptions as $destinationOption) {
            $fit = is_string($destinationOption['fit'] ?? null) && $destinationOption['fit'] !== ''
                ? $destinationOption['fit']
                : 'named';
            $fits[$fit] = ($fits[$fit] ?? 0) + 1;
        }

        ksort($fits);

        return $fits;
    }

    /**
     * @return list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>
     */
    private function extractPdfActiveActions(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $actions = [];

        if ($catalog !== null) {
            $this->addPdfActiveActionFromNamedValue($actions, 'catalog.OpenAction', $catalog, 'OpenAction', $objects);
            $this->collectPdfAdditionalActions($actions, 'catalog', $catalog, $objects);
            $this->collectPdfNamedJavaScriptActions($actions, $catalog, $objects);
        }

        foreach ($objects as $reference => $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/s', $body) === 1) {
                continue;
            }

            $source = $this->pdfActionContainerSource($reference, $body);
            if ($source === null) {
                continue;
            }

            $this->addPdfActiveActionFromNamedValue($actions, $source . '.A', $body, 'A', $objects);
            $this->collectPdfAdditionalActions($actions, $source, $body, $objects);
        }

        $actions = array_values($actions);
        usort($actions, static fn (array $a, array $b): int => [$a['source'], $a['type'], $a['target'] ?? ''] <=> [$b['source'], $b['type'], $b['target'] ?? '']);

        return $actions;
    }

    private function pdfActionContainerSource(string $reference, string $body): ?string
    {
        $pdfReference = $reference . ' R';
        $type = $this->extractPdfNameToken($body, 'Type');
        $subtype = $this->extractPdfNameToken($body, 'Subtype');
        if ($type === 'Page') {
            return 'page:' . $pdfReference;
        }
        if ($type === 'Annot' || $subtype !== null) {
            return 'annotation:' . $pdfReference;
        }
        if (str_contains($body, '/A') || str_contains($body, '/AA')) {
            return 'object:' . $pdfReference;
        }

        return null;
    }

    /**
     * @param array<string, array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array<string, string> $objects
     */
    private function addPdfActiveActionFromNamedValue(array &$actions, string $source, string $dictionary, string $name, array $objects, ?string $nameHint = null): void
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return;
        }

        $this->addPdfActiveActionFromValue($actions, $source, $value, $objects, $nameHint);
    }

    /**
     * @param array<string, array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array<string, string> $objects
     * @param array{kind:string, value:string} $value
     */
    private function addPdfActiveActionFromValue(
        array &$actions,
        string $source,
        array $value,
        array $objects,
        ?string $nameHint = null,
        int $depth = 0,
        array $visited = []
    ): void
    {
        if ($depth > 16) {
            return;
        }

        $actionDictionary = null;
        if ($value['kind'] === 'reference') {
            $referenceKey = $this->pdfReferenceKey($value['value']);
            if (isset($visited[$referenceKey])) {
                return;
            }

            $visited[$referenceKey] = true;
            $body = $objects[$referenceKey] ?? null;
            if ($body === null) {
                return;
            }

            $actionDictionary = $body;
            $summary = $this->summarizePdfActiveActionDictionary($body, $source, $objects, $nameHint);
        } elseif ($value['kind'] === 'dictionary') {
            $actionDictionary = $value['value'];
            $summary = $this->summarizePdfActiveActionDictionary($actionDictionary, $source, $objects, $nameHint);
        } elseif ($value['kind'] === 'name' || $value['kind'] === 'literal' || $value['kind'] === 'hex') {
            $target = trim($value['value']);
            $summary = $target === ''
                ? null
                : [
                    'source' => $source,
                    'type' => 'Named',
                    'target' => $target,
                    'scriptBytes' => null,
                    'scriptSha256' => null,
                ];
        } else {
            $summary = null;
        }

        if ($summary === null) {
            return;
        }

        $key = implode("\0", [
            $summary['source'],
            $summary['type'],
            $summary['target'] ?? '',
            (string) ($summary['scriptBytes'] ?? ''),
            $summary['scriptSha256'] ?? '',
        ]);
        $actions[$key] = $summary;

        if ($actionDictionary !== null) {
            $this->collectPdfNextActions($actions, $source, $actionDictionary, $objects, $depth + 1, $visited);
        }
    }

    /**
     * @param array<string, array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array<string, string> $objects
     */
    private function collectPdfNextActions(array &$actions, string $source, string $dictionary, array $objects, int $depth, array $visited): void
    {
        if ($depth > 16) {
            return;
        }

        $next = $this->extractPdfValueForName($dictionary, 'Next');
        if ($next === null) {
            return;
        }

        $this->addPdfActiveNextActionValue($actions, $source . '.Next', $next, $objects, $depth, $visited);
    }

    /**
     * @param array<string, array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array<string, string> $objects
     * @param array{kind:string, value:string, next?:int} $value
     */
    private function addPdfActiveNextActionValue(array &$actions, string $source, array $value, array $objects, int $depth, array $visited): void
    {
        if ($depth > 16) {
            return;
        }

        if ($value['kind'] === 'array') {
            $cursor = str_starts_with($value['value'], '[') ? 1 : 0;
            $length = strlen($value['value']);
            if (str_ends_with($value['value'], ']')) {
                $length--;
            }
            $index = 0;
            while ($cursor < $length) {
                $item = $this->parsePdfValueAt($value['value'], $cursor);
                if ($item === null) {
                    $cursor++;
                    continue;
                }

                $this->addPdfActiveNextActionValue($actions, $source . '[' . $index . ']', $item, $objects, $depth + 1, $visited);
                $index++;
                $cursor = max($cursor + 1, min($length, $item['next']));
            }

            return;
        }

        if ($value['kind'] === 'reference') {
            $referenceKey = $this->pdfReferenceKey($value['value']);
            if (isset($visited[$referenceKey])) {
                return;
            }

            $body = $objects[$referenceKey] ?? null;
            if ($body === null) {
                return;
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved !== null && $resolved['kind'] === 'array') {
                $visited[$referenceKey] = true;
                $this->addPdfActiveNextActionValue($actions, $source, $resolved, $objects, $depth + 1, $visited);
                return;
            }
        }

        $this->addPdfActiveActionFromValue($actions, $source, $value, $objects, null, $depth + 1, $visited);
    }

    /**
     * @param array<string, array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array<string, string> $objects
     */
    private function collectPdfAdditionalActions(array &$actions, string $source, string $dictionary, array $objects): void
    {
        $additionalActions = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'AA', $objects);
        if ($additionalActions === null) {
            return;
        }

        foreach (['E', 'X', 'D', 'U', 'Fo', 'Bl', 'PO', 'PC', 'PV', 'PI', 'O', 'C', 'K', 'F', 'V', 'WC', 'WS', 'DS', 'WP', 'DP'] as $trigger) {
            $this->addPdfActiveActionFromNamedValue($actions, $source . '.AA.' . $trigger, $additionalActions, $trigger, $objects);
        }
    }

    /**
     * @param array<string, array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array<string, string> $objects
     */
    private function collectPdfNamedJavaScriptActions(array &$actions, string $catalog, array $objects): void
    {
        $names = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Names', $objects);
        if ($names === null) {
            return;
        }

        $javaScript = $this->extractPdfDictionaryOrReferenceValue($names, 'JavaScript', $objects);
        if ($javaScript === null) {
            return;
        }

        $array = $this->extractPdfArrayValue($javaScript, 'Names');
        if ($array === null) {
            return;
        }

        $cursor = str_starts_with($array, '[') ? 1 : 0;
        $length = strlen($array);
        if (str_ends_with($array, ']')) {
            $length--;
        }
        while ($cursor < $length) {
            $name = $this->parsePdfValueAt($array, $cursor);
            if ($name === null) {
                $cursor++;
                continue;
            }
            $cursor = $name['next'];
            if (!in_array($name['kind'], ['literal', 'hex', 'name'], true)) {
                continue;
            }

            $action = $this->parsePdfValueAt($array, $cursor);
            if ($action === null) {
                break;
            }

            $nameValue = trim($name['value']);
            $source = 'catalog.Names.JavaScript' . ($nameValue === '' ? '' : '.' . $this->pdfActionSourceToken($nameValue));
            $this->addPdfActiveActionFromValue($actions, $source, $action, $objects, $nameValue === '' ? null : $nameValue);
            $cursor = $action['next'];
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}|null
     */
    private function summarizePdfActiveActionDictionary(string $dictionary, string $source, array $objects, ?string $nameHint = null): ?array
    {
        $type = $this->extractPdfNameToken($dictionary, 'S');
        if ($type === null || !$this->isPdfActiveActionType($type)) {
            return null;
        }

        $script = $type === 'JavaScript' ? $this->extractPdfJavaScriptActionBytes($dictionary, $objects) : null;

        return [
            'source' => $source,
            'type' => $type,
            'target' => $this->pdfActiveActionTarget($dictionary, $type, $objects, $nameHint),
            'scriptBytes' => $script === null ? null : strlen($script),
            'scriptSha256' => $script === null ? null : hash('sha256', $script),
        ];
    }

    private function isPdfActiveActionType(string $type): bool
    {
        return in_array($type, [
            'GoToR',
            'Hide',
            'ImportData',
            'JavaScript',
            'Launch',
            'Movie',
            'Named',
            'Rendition',
            'ResetForm',
            'Sound',
            'SubmitForm',
        ], true);
    }

    /**
     * @param list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @return array<string, int>
     */
    private function summarizePdfActiveActionTypes(array $actions): array
    {
        $types = [];
        foreach ($actions as $action) {
            $types[$action['type']] = ($types[$action['type']] ?? 0) + 1;
        }

        ksort($types);

        return $types;
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfJavaScriptActionBytes(string $dictionary, array $objects): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, 'JS');
        if ($value === null) {
            return null;
        }

        if ($value['kind'] === 'literal' || $value['kind'] === 'hex' || $value['kind'] === 'name') {
            return $value['value'];
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return null;
            }

            return $this->extractPdfStreamBytes($body) ?? $this->extractPdfStringOrNameValue($body, 'JS');
        }

        if ($value['kind'] === 'dictionary') {
            return $this->extractPdfStreamBytes($value['value']) ?? $this->extractPdfStringOrNameValue($value['value'], 'JS');
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     */
    private function pdfActiveActionTarget(string $dictionary, string $type, array $objects, ?string $nameHint): ?string
    {
        if ($type === 'JavaScript') {
            return $nameHint;
        }
        if ($type === 'Named') {
            return $this->extractPdfStringOrNameValue($dictionary, 'N');
        }
        if ($type === 'Launch') {
            return $this->pdfLaunchActionTarget($dictionary, $objects);
        }
        if (in_array($type, ['SubmitForm', 'GoToR', 'ImportData'], true)) {
            return $this->extractPdfStringOrNameValue($dictionary, 'F');
        }
        if ($type === 'Hide') {
            return $this->extractPdfStringOrNameValue($dictionary, 'T');
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     */
    private function pdfLaunchActionTarget(string $dictionary, array $objects = []): ?string
    {
        $platformTargets = [];
        foreach (['Win', 'Unix', 'Mac'] as $platform) {
            $platformDictionary = $this->pdfDictionaryValueForName($dictionary, $platform, $objects);
            if ($platformDictionary === null) {
                continue;
            }

            $target = $this->pdfLaunchPlatformTarget($platform, $platformDictionary, $objects);
            if ($target !== null && $target !== '') {
                $platformTargets[] = $target;
            }
        }

        if ($platformTargets !== []) {
            return implode('|', $platformTargets);
        }

        return $this->pdfLaunchFileTargetFromValue($this->extractPdfValueForName($dictionary, 'F'), $objects);
    }

    /**
     * @param array<string, string> $objects
     */
    private function pdfDictionaryValueForName(string $dictionary, string $name, array $objects): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return null;
        }

        return $this->pdfDictionaryFromValue($value, $objects);
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     */
    private function pdfDictionaryFromValue(array $value, array $objects, int $depth = 0): ?string
    {
        if ($depth > 8) {
            return null;
        }

        if ($value['kind'] === 'dictionary') {
            return $value['value'];
        }

        if ($value['kind'] !== 'reference') {
            return null;
        }

        $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        if ($body === null) {
            return null;
        }

        $resolved = $this->parsePdfValueAt($body, 0);
        if ($resolved === null) {
            return str_starts_with(ltrim($body), '<<') ? $body : null;
        }

        return $this->pdfDictionaryFromValue($resolved, $objects, $depth + 1);
    }

    /**
     * @param array<string, string> $objects
     */
    private function pdfLaunchPlatformTarget(string $platform, string $dictionary, array $objects): ?string
    {
        $components = [];
        foreach (['F', 'D', 'O', 'P'] as $key) {
            $value = $key === 'F'
                ? $this->pdfLaunchFileTargetFromValue($this->extractPdfValueForName($dictionary, $key), $objects)
                : $this->pdfScalarTargetFromValue($this->extractPdfValueForName($dictionary, $key), $objects);
            if ($value === null || trim($value) === '') {
                continue;
            }

            $components[] = $key . '=' . $this->normalizePdfActionTargetComponent($value);
        }

        return $components === [] ? null : $platform . ':' . implode(';', $components);
    }

    /**
     * @param array{kind:string, value:string, next:int}|null $value
     * @param array<string, string> $objects
     */
    private function pdfLaunchFileTargetFromValue(?array $value, array $objects, int $depth = 0): ?string
    {
        if ($value === null || $depth > 8) {
            return null;
        }

        if (in_array($value['kind'], ['literal', 'hex', 'name', 'number'], true)) {
            $target = trim($value['value']);

            return $target === '' ? null : $target;
        }

        if ($value['kind'] === 'dictionary') {
            $target = $this->extractPdfStringOrNameValue($value['value'], 'UF')
                ?? $this->extractPdfStringOrNameValue($value['value'], 'F');
            $target = $target === null ? null : trim($target);

            return $target === '' ? null : $target;
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return null;
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved === null) {
                return null;
            }

            return $this->pdfLaunchFileTargetFromValue($resolved, $objects, $depth + 1);
        }

        return null;
    }

    /**
     * @param array{kind:string, value:string, next:int}|null $value
     * @param array<string, string> $objects
     */
    private function pdfScalarTargetFromValue(?array $value, array $objects, int $depth = 0): ?string
    {
        if ($value === null || $depth > 8) {
            return null;
        }

        if (in_array($value['kind'], ['literal', 'hex', 'name', 'number', 'keyword'], true)) {
            $target = trim($value['value']);

            return $target === '' ? null : $target;
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return null;
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved === null) {
                return null;
            }

            return $this->pdfScalarTargetFromValue($resolved, $objects, $depth + 1);
        }

        return null;
    }

    private function normalizePdfActionTargetComponent(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return str_replace(['|', ';'], ['/', ','], $value);
    }

    private function pdfActionSourceToken(string $value): string
    {
        $token = preg_replace('/[^A-Za-z0-9_.:-]+/', '-', trim($value)) ?? '';
        $token = trim($token, '-');

        return $token === '' ? 'unnamed' : $token;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}>
     */
    private function extractPdfRichMediaAnnotations(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $annotations = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfRichMediaAnnotationsFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $annotations,
                $pageNumber,
                0
            );
        }

        if ($annotations === []) {
            foreach ($objects as $reference => $body) {
                $summary = $this->summarizePdfRichMediaAnnotation($body, $reference . ' R', null, null, $objects);
                if ($summary !== null) {
                    $annotations[] = $summary;
                }
            }
        }

        usort(
            $annotations,
            fn (array $left, array $right): int => [$left['page'], $this->pdfReferenceSortKey($left['annotationObject'] ?? '')]
                <=> [$right['page'], $this->pdfReferenceSortKey($right['annotationObject'] ?? '')]
        );

        return $annotations;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}> $annotations
     */
    private function collectPdfRichMediaAnnotationsFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$annotations,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $this->collectPdfRichMediaAnnotationsFromPage($body, $reference, $pageNumber, $objects, $annotations);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfRichMediaAnnotationsFromPageTree(
                $objects,
                $this->pdfReferenceKey($kidReference),
                $visited,
                $annotations,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}> $annotations
     */
    private function collectPdfRichMediaAnnotationsFromPage(
        string $pageDictionary,
        string $pageReference,
        int $pageNumber,
        array $objects,
        array &$annotations
    ): void {
        $array = $this->extractPdfArrayOrReferenceValue($pageDictionary, 'Annots', $objects);
        if ($array === null) {
            return;
        }

        foreach ($this->pdfTopLevelArrayValues($array) as $value) {
            if (!in_array($value['kind'], ['reference', 'dictionary'], true)) {
                continue;
            }

            $summary = $this->summarizePdfRichMediaAnnotationValue(
                $value,
                $objects,
                $pageNumber,
                $pageReference . ' R'
            );
            if ($summary !== null) {
                $annotations[] = $summary;
            }
        }
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}|null
     */
    private function summarizePdfRichMediaAnnotationValue(array $value, array $objects, int $pageNumber, string $pageReference): ?array
    {
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $dictionary = $objects[$reference] ?? null;

            return $dictionary === null
                ? null
                : $this->summarizePdfRichMediaAnnotation($dictionary, $reference . ' R', $pageNumber, $pageReference, $objects);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfRichMediaAnnotation($value['value'], 'inline', $pageNumber, $pageReference, $objects);
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, rect:list<float>|null, contents:string|null, contentObject:string|null, settingsObject:string|null, assetNames:list<string>, activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null, configurations:list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>}|null
     */
    private function summarizePdfRichMediaAnnotation(
        string $dictionary,
        ?string $annotationObject,
        ?int $pageNumber,
        ?string $pageReference,
        array $objects
    ): ?array {
        if ($this->extractPdfNameToken($dictionary, 'Subtype') !== 'RichMedia') {
            return null;
        }

        $contentValue = $this->extractPdfValueForName($dictionary, 'RichMediaContent');
        $settingsValue = $this->extractPdfValueForName($dictionary, 'RichMediaSettings');
        $contentDictionary = $this->pdfDictionaryForValue($contentValue, $objects);
        $settingsDictionary = $this->pdfDictionaryForValue($settingsValue, $objects);
        $assets = $contentDictionary === null
            ? ['names' => [], 'namesByReference' => []]
            : $this->extractPdfRichMediaAssets($contentDictionary, $objects);
        $settings = $settingsDictionary === null
            ? $this->emptyPdfRichMediaSettings()
            : $this->summarizePdfRichMediaSettings($settingsDictionary, $objects);

        return [
            'page' => $pageNumber ?? 0,
            'pageObject' => $pageReference,
            'annotationObject' => $annotationObject,
            'rect' => $this->extractPdfNumberArrayToken($dictionary, 'Rect', 4),
            'contents' => $this->extractPdfStringOrNameValue($dictionary, 'Contents'),
            'contentObject' => $this->pdfReferenceObjectForValue($contentValue),
            'settingsObject' => $this->pdfReferenceObjectForValue($settingsValue),
            'assetNames' => $assets['names'],
            'activationCondition' => $settings['activationCondition'],
            'deactivationCondition' => $settings['deactivationCondition'],
            'presentationStyle' => $settings['presentationStyle'],
            'presentationTransparent' => $settings['presentationTransparent'],
            'presentationToolbar' => $settings['presentationToolbar'],
            'presentationNavigationPane' => $settings['presentationNavigationPane'],
            'presentationPassContextClick' => $settings['presentationPassContextClick'],
            'configurations' => $contentDictionary === null
                ? []
                : $this->extractPdfRichMediaConfigurations($contentDictionary, $objects, $assets['namesByReference']),
        ];
    }

    /**
     * @param array{kind:string, value:string, next:int}|null $value
     * @param array<string, string> $objects
     */
    private function pdfDictionaryForValue(?array $value, array $objects): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value['kind'] === 'dictionary') {
            return $value['value'];
        }
        if ($value['kind'] === 'reference') {
            return $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        }

        return null;
    }

    /**
     * @param array{kind:string, value:string, next:int}|null $value
     */
    private function pdfReferenceObjectForValue(?array $value): ?string
    {
        return $value !== null && $value['kind'] === 'reference'
            ? $this->pdfReferenceKey($value['value']) . ' R'
            : null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{names:list<string>, namesByReference:array<string, string>}
     */
    private function extractPdfRichMediaAssets(string $contentDictionary, array $objects): array
    {
        $assets = $this->extractPdfDictionaryOrReferenceValue($contentDictionary, 'Assets', $objects);
        if ($assets === null) {
            return ['names' => [], 'namesByReference' => []];
        }

        $names = [];
        $namesByReference = [];
        $this->collectPdfRichMediaAssetNameTree($assets, $objects, $names, $namesByReference, 0);
        $names = array_values(array_unique($names));
        sort($names);
        ksort($namesByReference);

        return [
            'names' => $names,
            'namesByReference' => $namesByReference,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @param list<string> $names
     * @param array<string, string> $namesByReference
     */
    private function collectPdfRichMediaAssetNameTree(
        string $dictionary,
        array $objects,
        array &$names,
        array &$namesByReference,
        int $depth
    ): void {
        if ($depth > 8) {
            return;
        }

        $array = $this->extractPdfArrayValue($dictionary, 'Names');
        if ($array !== null) {
            $values = $this->pdfTopLevelArrayValues($array);
            $count = count($values);
            for ($index = 0; $index + 1 < $count; $index += 2) {
                $name = $values[$index];
                $asset = $values[$index + 1];
                if (!in_array($name['kind'], ['literal', 'hex', 'name'], true)) {
                    continue;
                }

                $nameValue = trim($name['value']);
                if ($nameValue === '') {
                    continue;
                }

                $names[] = $nameValue;
                if ($asset['kind'] === 'reference') {
                    $namesByReference[$this->pdfReferenceKey($asset['value']) . ' R'] = $nameValue;
                }
            }
        }

        foreach ($this->extractPdfReferenceArray($dictionary, 'Kids') as $kidReference) {
            $kidDictionary = $objects[$this->pdfReferenceKey($kidReference)] ?? null;
            if ($kidDictionary !== null) {
                $this->collectPdfRichMediaAssetNameTree($kidDictionary, $objects, $names, $namesByReference, $depth + 1);
            }
        }
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, string> $assetNamesByReference
     * @return list<array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}>
     */
    private function extractPdfRichMediaConfigurations(string $contentDictionary, array $objects, array $assetNamesByReference): array
    {
        $array = $this->extractPdfArrayOrReferenceValue($contentDictionary, 'Configurations', $objects);
        if ($array === null) {
            return [];
        }

        $configurations = [];
        foreach ($this->pdfTopLevelArrayValues($array) as $value) {
            if (!in_array($value['kind'], ['reference', 'dictionary'], true)) {
                continue;
            }

            $summary = $this->summarizePdfRichMediaConfiguration($value, $objects, $assetNamesByReference);
            if ($summary !== null) {
                $configurations[] = $summary;
            }
        }

        usort($configurations, static fn (array $a, array $b): int => [$a['object'] ?? '', $a['name'] ?? ''] <=> [$b['object'] ?? '', $b['name'] ?? '']);

        return $configurations;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @param array<string, string> $assetNamesByReference
     * @return array{object:string|null, subtype:string|null, name:string|null, instanceCount:int, assetReferences:list<string>, assetNames:list<string>}|null
     */
    private function summarizePdfRichMediaConfiguration(array $value, array $objects, array $assetNamesByReference): ?array
    {
        $dictionary = $this->pdfDictionaryForValue($value, $objects);
        if ($dictionary === null) {
            return null;
        }

        $instances = $this->extractPdfArrayOrReferenceValue($dictionary, 'Instances', $objects);
        $instanceCount = 0;
        $assetReferences = [];
        if ($instances !== null) {
            foreach ($this->pdfTopLevelArrayValues($instances) as $instanceValue) {
                if (!in_array($instanceValue['kind'], ['reference', 'dictionary'], true)) {
                    continue;
                }

                $instanceDictionary = $this->pdfDictionaryForValue($instanceValue, $objects);
                if ($instanceDictionary === null) {
                    continue;
                }

                $instanceCount++;
                $assetValue = $this->extractPdfValueForName($instanceDictionary, 'Asset');
                $assetReference = $this->pdfReferenceObjectForValue($assetValue);
                if ($assetReference !== null) {
                    $assetReferences[] = $assetReference;
                }
            }
        }

        $assetReferences = array_values(array_unique($assetReferences));
        sort($assetReferences);
        $assetNames = [];
        foreach ($assetReferences as $assetReference) {
            if (isset($assetNamesByReference[$assetReference])) {
                $assetNames[] = $assetNamesByReference[$assetReference];
            }
        }
        $assetNames = array_values(array_unique($assetNames));
        sort($assetNames);

        return [
            'object' => $this->pdfReferenceObjectForValue($value),
            'subtype' => $this->extractPdfNameToken($dictionary, 'Subtype'),
            'name' => $this->extractPdfStringOrNameValue($dictionary, 'Name'),
            'instanceCount' => $instanceCount,
            'assetReferences' => $assetReferences,
            'assetNames' => $assetNames,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array{activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null}
     */
    private function summarizePdfRichMediaSettings(string $settingsDictionary, array $objects): array
    {
        $activation = $this->extractPdfDictionaryOrReferenceValue($settingsDictionary, 'Activation', $objects);
        $deactivation = $this->extractPdfDictionaryOrReferenceValue($settingsDictionary, 'Deactivation', $objects);
        $presentation = $activation === null
            ? null
            : $this->extractPdfDictionaryOrReferenceValue($activation, 'Presentation', $objects);

        return [
            'activationCondition' => $activation === null ? null : $this->extractPdfNameToken($activation, 'Condition'),
            'deactivationCondition' => $deactivation === null ? null : $this->extractPdfNameToken($deactivation, 'Condition'),
            'presentationStyle' => $presentation === null ? null : $this->extractPdfNameToken($presentation, 'Style'),
            'presentationTransparent' => $presentation === null ? null : $this->extractPdfBooleanToken($presentation, 'Transparent'),
            'presentationToolbar' => $presentation === null ? null : $this->extractPdfBooleanToken($presentation, 'Toolbar'),
            'presentationNavigationPane' => $presentation === null ? null : $this->extractPdfBooleanToken($presentation, 'NavigationPane'),
            'presentationPassContextClick' => $presentation === null ? null : $this->extractPdfBooleanToken($presentation, 'PassContextClick'),
        ];
    }

    /**
     * @return array{activationCondition:string|null, deactivationCondition:string|null, presentationStyle:string|null, presentationTransparent:bool|null, presentationToolbar:bool|null, presentationNavigationPane:bool|null, presentationPassContextClick:bool|null}
     */
    private function emptyPdfRichMediaSettings(): array
    {
        return [
            'activationCondition' => null,
            'deactivationCondition' => null,
            'presentationStyle' => null,
            'presentationTransparent' => null,
            'presentationToolbar' => null,
            'presentationNavigationPane' => null,
            'presentationPassContextClick' => null,
        ];
    }

    /**
     * @param list<array{activationCondition:string|null}> $annotations
     * @return array<string, int>
     */
    private function summarizePdfRichMediaActivationModes(array $annotations): array
    {
        $modes = [];
        foreach ($annotations as $annotation) {
            if (!is_string($annotation['activationCondition'] ?? null) || $annotation['activationCondition'] === '') {
                continue;
            }

            $modes[$annotation['activationCondition']] = ($modes[$annotation['activationCondition']] ?? 0) + 1;
        }
        ksort($modes);

        return $modes;
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function extractPdfViewerPreferences(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/ViewerPreferences')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $dictionary = null;
        if (preg_match('/\/ViewerPreferences\s+(\d+)\s+(\d+)\s+R\b/s', $catalog, $matches) === 1) {
            $dictionary = $objects[$matches[1] . ' ' . $matches[2]] ?? null;
        }

        if ($dictionary === null) {
            $dictionary = $this->extractPdfNestedDictionary($catalog, 'ViewerPreferences');
        }

        if ($dictionary === null) {
            return [];
        }

        $preferences = [];
        foreach (['HideToolbar', 'HideMenubar', 'HideWindowUI', 'FitWindow', 'CenterWindow', 'DisplayDocTitle', 'PickTrayByPDFSize'] as $key) {
            $value = $this->extractPdfBooleanToken($dictionary, $key);
            if ($value !== null) {
                $preferences[$key] = $value;
            }
        }

        foreach (['NonFullScreenPageMode', 'Direction', 'ViewArea', 'ViewClip', 'PrintArea', 'PrintClip', 'PrintScaling', 'Duplex'] as $key) {
            $value = $this->extractPdfNameToken($dictionary, $key);
            if ($value !== null && $value !== '') {
                $preferences[$key] = $value;
            }
        }

        foreach (['NumCopies'] as $key) {
            $value = $this->extractPdfIntegerToken($dictionary, $key);
            if ($value !== null) {
                $preferences[$key] = $value;
            }
        }

        $printPageRange = $this->extractPdfArrayOrReferenceValue($dictionary, 'PrintPageRange', $objects);
        if ($printPageRange !== null && preg_match_all('/-?\d+/', $printPageRange, $matches) > 0) {
            $preferences['PrintPageRange'] = array_map('intval', $matches[0]);
        }

        $enforceValue = $this->extractPdfValueForName($dictionary, 'Enforce');
        if ($enforceValue !== null) {
            $enforcedPreferences = $this->collectPdfNamesFromValue($enforceValue, $objects);
            if ($enforcedPreferences !== []) {
                $preferences['Enforce'] = $enforcedPreferences;
            }
        }

        return $preferences;
    }

    private function extractPdfNeedsRendering(?string $catalog): ?bool
    {
        if ($catalog === null || !str_contains($catalog, '/NeedsRendering')) {
            return null;
        }

        return $this->extractPdfBooleanToken($catalog, 'NeedsRendering');
    }

    /**
     * @return list<array{object:string|null, type:string|null, subtype:string|null, handlerObject:string|null, handlerType:string|null, handlerName:string|null, handlerCode:string|null, handlerVersion:string|null, keys:list<string>}>
     */
    private function extractPdfCatalogRequirements(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/Requirements')) {
            return [];
        }

        $value = $this->extractPdfValueForName($catalog, 'Requirements');
        if ($value === null) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $values = $value['kind'] === 'array' ? $this->pdfTopLevelArrayValues($value['value']) : [$value];
        $requirements = [];
        foreach ($values as $item) {
            $summary = $this->summarizePdfCatalogRequirementValue($item, $objects, 0);
            if ($summary !== null) {
                $requirements[] = $summary;
            }
            if (count($requirements) >= 64) {
                break;
            }
        }

        return $requirements;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{object:string|null, type:string|null, subtype:string|null, handlerObject:string|null, handlerType:string|null, handlerName:string|null, handlerCode:string|null, handlerVersion:string|null, keys:list<string>}|null
     */
    private function summarizePdfCatalogRequirementValue(array $value, array $objects, int $depth): ?array
    {
        if ($depth > 8) {
            return null;
        }

        $object = null;
        $dictionary = null;
        if ($value['kind'] === 'dictionary') {
            $dictionary = $value['value'];
        } elseif ($value['kind'] === 'reference') {
            $objectKey = $this->pdfReferenceKey($value['value']);
            $object = $objectKey . ' R';
            $body = $objects[$objectKey] ?? null;
            if ($body !== null) {
                $resolved = $this->parsePdfValueAt($body, 0);
                if ($resolved !== null && $resolved['kind'] === 'dictionary') {
                    $dictionary = $resolved['value'];
                } elseif (str_starts_with(ltrim($body), '<<')) {
                    $dictionary = $body;
                }
            }
        }

        if ($dictionary === null) {
            return null;
        }

        return $this->summarizePdfCatalogRequirement($dictionary, $object, $objects, $depth + 1);
    }

    /**
     * @param array<string, string> $objects
     * @return array{object:string|null, type:string|null, subtype:string|null, handlerObject:string|null, handlerType:string|null, handlerName:string|null, handlerCode:string|null, handlerVersion:string|null, keys:list<string>}|null
     */
    private function summarizePdfCatalogRequirement(string $dictionary, ?string $object, array $objects, int $depth): ?array
    {
        $keys = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            if ($entry['key'] !== '') {
                $keys[] = $entry['key'];
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        $handler = $this->resolvePdfRequirementHandlerDictionary(
            $this->extractPdfValueForName($dictionary, 'RH') ?? $this->extractPdfValueForName($dictionary, 'Handler'),
            $objects,
            $depth
        );
        $type = $this->extractPdfNameToken($dictionary, 'Type');
        $subtype = $this->extractPdfNameToken($dictionary, 'S') ?? $this->extractPdfNameToken($dictionary, 'Subtype');
        $handlerDictionary = $handler['dictionary'] ?? null;
        $summary = [
            'object' => $object,
            'type' => $type,
            'subtype' => $subtype,
            'handlerObject' => $handler['object'] ?? null,
            'handlerType' => $handlerDictionary === null ? null : $this->extractPdfNameToken($handlerDictionary, 'Type'),
            'handlerName' => $handlerDictionary === null ? null : $this->extractPdfStringOrNameValue($handlerDictionary, 'Name'),
            'handlerCode' => $handlerDictionary === null ? null : $this->extractPdfStringOrNameValue($handlerDictionary, 'C'),
            'handlerVersion' => $handlerDictionary === null
                ? null
                : ($this->extractPdfStringOrNameValue($handlerDictionary, 'V') ?? $this->extractPdfStringOrNameValue($handlerDictionary, 'Version')),
            'keys' => $keys,
        ];

        if (
            $summary['type'] === null
            && $summary['subtype'] === null
            && $summary['handlerObject'] === null
            && $summary['handlerType'] === null
            && $summary['handlerName'] === null
            && $summary['handlerCode'] === null
            && $summary['handlerVersion'] === null
            && $summary['keys'] === []
        ) {
            return null;
        }

        return $summary;
    }

    /**
     * @param array{kind:string, value:string, next:int}|null $value
     * @param array<string, string> $objects
     * @return array{object:string|null, dictionary:string}|null
     */
    private function resolvePdfRequirementHandlerDictionary(?array $value, array $objects, int $depth): ?array
    {
        if ($value === null || $depth > 8) {
            return null;
        }

        if ($value['kind'] === 'dictionary') {
            return [
                'object' => 'inline',
                'dictionary' => $value['value'],
            ];
        }

        if ($value['kind'] !== 'reference') {
            return null;
        }

        $objectKey = $this->pdfReferenceKey($value['value']);
        $body = $objects[$objectKey] ?? null;
        if ($body === null) {
            return null;
        }

        $resolved = $this->parsePdfValueAt($body, 0);
        if ($resolved === null || $resolved['kind'] !== 'dictionary') {
            return null;
        }

        return [
            'object' => $objectKey . ' R',
            'dictionary' => $resolved['value'],
        ];
    }

    /**
     * @return array{object:string|null, type:string|null, language:string|null, status:string|null, jurisdiction:string|null, attestation:string|null, attestationObject:string|null, attestationBytes:int|null, attestationSha256:string|null, attestationSkipped:string|null, associatedFiles:list<string>, keys:list<string>}|array{}
     */
    private function extractPdfLegalAttestationMetadata(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/LegalAttestation')) {
            return [];
        }

        $value = $this->extractPdfValueForName($catalog, 'LegalAttestation');
        if ($value === null) {
            return [];
        }

        return $this->summarizePdfLegalAttestationValue(
            $value,
            $this->pdfObjectBodiesByReference($pdfBytes),
            0
        );
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{object:string|null, type:string|null, language:string|null, status:string|null, jurisdiction:string|null, attestation:string|null, attestationObject:string|null, attestationBytes:int|null, attestationSha256:string|null, attestationSkipped:string|null, associatedFiles:list<string>, keys:list<string>}|array{}
     */
    private function summarizePdfLegalAttestationValue(array $value, array $objects, int $depth): array
    {
        if ($depth > 8) {
            return [];
        }

        $object = null;
        $dictionary = null;
        if ($value['kind'] === 'dictionary') {
            $object = 'inline';
            $dictionary = $value['value'];
        } elseif ($value['kind'] === 'reference') {
            $objectKey = $this->pdfReferenceKey($value['value']);
            $object = $objectKey . ' R';
            $body = $objects[$objectKey] ?? null;
            if ($body !== null) {
                $resolved = $this->parsePdfValueAt($body, 0);
                if ($resolved !== null && $resolved['kind'] === 'dictionary') {
                    $dictionary = $resolved['value'];
                } elseif (str_starts_with(ltrim($body), '<<')) {
                    $dictionary = $body;
                }
            }
        }

        if ($dictionary === null) {
            return [];
        }

        $keys = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            if ($entry['key'] !== '') {
                $keys[] = $entry['key'];
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        $attestation = $this->summarizePdfLegalAttestationPayload(
            $this->extractPdfValueForName($dictionary, 'Attestation')
                ?? $this->extractPdfValueForName($dictionary, 'Statement')
                ?? $this->extractPdfValueForName($dictionary, 'Contents')
                ?? $this->extractPdfValueForName($dictionary, 'Text'),
            $objects,
            $depth + 1
        );
        $associatedFiles = array_map(
            static fn (string $reference): string => $reference . ' R',
            $this->extractPdfReferenceArray($dictionary, 'AF')
        );
        $associatedFiles = array_values(array_unique($associatedFiles));
        sort($associatedFiles, SORT_STRING);

        return [
            'object' => $object,
            'type' => $this->extractPdfNameToken($dictionary, 'Type'),
            'language' => $this->extractPdfStringOrNameValue($dictionary, 'Lang'),
            'status' => $this->extractPdfStringOrNameValue($dictionary, 'Status'),
            'jurisdiction' => $this->extractPdfStringOrNameValue($dictionary, 'Jurisdiction'),
            'attestation' => $attestation['text'],
            'attestationObject' => $attestation['object'],
            'attestationBytes' => $attestation['bytes'],
            'attestationSha256' => $attestation['sha256'],
            'attestationSkipped' => $attestation['skipped'],
            'associatedFiles' => $associatedFiles,
            'keys' => $keys,
        ];
    }

    /**
     * @param array{kind:string, value:string, next:int}|null $value
     * @param array<string, string> $objects
     * @return array{text:string|null, object:string|null, bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfLegalAttestationPayload(?array $value, array $objects, int $depth): array
    {
        $empty = [
            'text' => null,
            'object' => null,
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];
        if ($value === null || $depth > 8) {
            return $empty;
        }

        if (in_array($value['kind'], ['literal', 'hex', 'name', 'number'], true)) {
            $text = trim($value['value']);
            $empty['text'] = $text === '' ? null : $text;

            return $empty;
        }

        if ($value['kind'] === 'dictionary') {
            $text = $this->extractPdfStringOrNameValue($value['value'], 'Statement')
                ?? $this->extractPdfStringOrNameValue($value['value'], 'Contents')
                ?? $this->extractPdfStringOrNameValue($value['value'], 'Text');
            $empty['text'] = $text === null || trim($text) === '' ? null : trim($text);

            return $empty;
        }

        if ($value['kind'] !== 'reference') {
            return $empty;
        }

        $objectKey = $this->pdfReferenceKey($value['value']);
        $summary = $empty;
        $summary['object'] = $objectKey . ' R';
        $body = $objects[$objectKey] ?? null;
        if ($body === null) {
            return $summary;
        }

        $streamBytes = $this->extractPdfStreamBytes($body);
        if ($streamBytes !== null) {
            $summary['bytes'] = strlen($streamBytes);
            if (preg_match('/\/Filter\b/s', $body) === 1) {
                $summary['skipped'] = 'filtered';

                return $summary;
            }
            if (strlen($streamBytes) > self::MAX_LEGAL_ATTESTATION_STREAM_BYTES) {
                $summary['skipped'] = 'too-large';

                return $summary;
            }

            $summary['sha256'] = hash('sha256', $streamBytes);

            return $summary;
        }

        $resolved = $this->parsePdfValueAt($body, 0);
        if ($resolved === null) {
            return $summary;
        }

        $resolvedSummary = $this->summarizePdfLegalAttestationPayload($resolved, $objects, $depth + 1);
        $summary['text'] = $resolvedSummary['text'];
        $summary['bytes'] = $resolvedSummary['bytes'];
        $summary['sha256'] = $resolvedSummary['sha256'];
        $summary['skipped'] = $resolvedSummary['skipped'];

        return $summary;
    }

    private function extractPdfUriBase(string $pdfBytes, ?string $catalog): ?string
    {
        if ($catalog === null || !str_contains($catalog, '/URI')) {
            return null;
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $uriDictionary = $this->extractPdfDictionaryOrReferenceValue($catalog, 'URI', $objects);
        if ($uriDictionary === null) {
            return null;
        }

        $base = $this->extractPdfStringOrNameValue($uriDictionary, 'Base');
        if ($base === null) {
            return null;
        }

        $base = trim($base);

        return $base === '' ? null : $base;
    }

    /**
     * @return array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{}
     */
    private function extractPdfTaggingMetadata(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || (!str_contains($catalog, '/MarkInfo') && !str_contains($catalog, '/StructTreeRoot'))) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $markInfo = $this->extractPdfDictionaryOrReferenceValue($catalog, 'MarkInfo', $objects);
        $structTreeRootReference = $this->extractPdfReferenceToken($catalog, 'StructTreeRoot');
        $structTreeRoot = $this->extractPdfDictionaryOrReferenceValue($catalog, 'StructTreeRoot', $objects);

        if ($markInfo === null && $structTreeRootReference === null && $structTreeRoot === null) {
            return [];
        }

        $metadata = [
            'marked' => null,
            'userProperties' => null,
            'suspects' => null,
            'structTreeRoot' => $structTreeRootReference,
            'roleMap' => [],
            'structureChildren' => null,
            'parentTree' => null,
            'parentTreeNextKey' => null,
            'idTree' => null,
        ];

        if ($markInfo !== null) {
            $metadata['marked'] = $this->extractPdfBooleanToken($markInfo, 'Marked');
            $metadata['userProperties'] = $this->extractPdfBooleanToken($markInfo, 'UserProperties');
            $metadata['suspects'] = $this->extractPdfBooleanToken($markInfo, 'Suspects');
        }

        if ($structTreeRoot !== null) {
            if ($metadata['structTreeRoot'] === null) {
                $metadata['structTreeRoot'] = 'inline';
            }
            $metadata['roleMap'] = $this->extractPdfStructureRoleMap($structTreeRoot, $objects);
            $metadata['structureChildren'] = $this->countPdfStructureChildren($structTreeRoot);
            $metadata['parentTree'] = $this->extractPdfReferenceToken($structTreeRoot, 'ParentTree');
            $metadata['parentTreeNextKey'] = $this->extractPdfIntegerToken($structTreeRoot, 'ParentTreeNextKey');
            $metadata['idTree'] = $this->extractPdfReferenceToken($structTreeRoot, 'IDTree');
        }

        return $metadata;
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, string>
     */
    private function extractPdfStructureRoleMap(string $structTreeRoot, array $objects): array
    {
        $roleMap = $this->extractPdfDictionaryOrReferenceValue($structTreeRoot, 'RoleMap', $objects);
        if ($roleMap === null) {
            return [];
        }

        $mapped = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($roleMap) as $entry) {
            $value = $entry['value'];
            if (!in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
                continue;
            }

            $role = trim($entry['key']);
            $mapsTo = trim($value['value']);
            if ($role === '' || $mapsTo === '') {
                continue;
            }

            $mapped[$role] = $mapsTo;
        }

        ksort($mapped);

        return $mapped;
    }

    private function countPdfStructureChildren(string $structTreeRoot): ?int
    {
        $value = $this->extractPdfValueForName($structTreeRoot, 'K');
        if ($value === null) {
            return null;
        }

        if ($value['kind'] !== 'array') {
            return 1;
        }

        return $this->countPdfTopLevelArrayValues($value['value']);
    }

    /**
     * @return list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>
     */
    private function extractPdfStructureElements(string $pdfBytes): array
    {
        $elements = [];
        foreach ($this->pdfObjectBodiesByReference($pdfBytes) as $reference => $body) {
            if ($this->extractPdfNameToken($body, 'Type') !== 'StructElem') {
                continue;
            }

            $elements[] = $this->summarizePdfStructureElement($reference . ' R', $body);
        }

        usort($elements, fn (array $a, array $b): int => $this->pdfReferenceSortKey($a['object']) <=> $this->pdfReferenceSortKey($b['object']));

        return $elements;
    }

    /**
     * @return array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}
     */
    private function summarizePdfStructureElement(string $reference, string $dictionary): array
    {
        $children = null;
        $value = $this->extractPdfValueForName($dictionary, 'K');
        if ($value !== null) {
            $children = $value['kind'] === 'array'
                ? $this->countPdfTopLevelArrayValues($value['value'])
                : 1;
        }

        return [
            'object' => $reference,
            'type' => $this->extractPdfNameToken($dictionary, 'S'),
            'parent' => $this->extractPdfReferenceToken($dictionary, 'P'),
            'pageObject' => $this->extractPdfReferenceToken($dictionary, 'Pg'),
            'alt' => $this->extractPdfStringOrNameValue($dictionary, 'Alt'),
            'actualText' => $this->extractPdfStringOrNameValue($dictionary, 'ActualText'),
            'language' => $this->extractPdfStringOrNameValue($dictionary, 'Lang'),
            'title' => $this->extractPdfStringOrNameValue($dictionary, 'T'),
            'childCount' => $children,
        ];
    }

    /**
     * @return list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}>
     */
    private function extractPdfMarkedContentProperties(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $properties = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfMarkedContentPropertiesFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $properties,
                $pageNumber,
                0
            );
        }

        if ($properties === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageProperties = $this->summarizePdfPageMarkedContentProperties($body, $reference, null, $objects);
                foreach ($pageProperties as &$property) {
                    $property['page'] = $pageNumber;
                }
                unset($property);
                array_push($properties, ...$pageProperties);
            }
        }

        $properties = array_values($properties);
        usort(
            $properties,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['propertyName'],
                $a['propertyObject'] ?? '',
            ] <=> [
                $b['page'],
                $b['propertyName'],
                $b['propertyObject'] ?? '',
            ]
        );

        return $properties;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}> $properties
     */
    private function collectPdfMarkedContentPropertiesFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$properties,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageProperties = $this->summarizePdfPageMarkedContentProperties($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageProperties as &$property) {
                $property['page'] = $pageNumber;
            }
            unset($property);
            array_push($properties, ...$pageProperties);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfMarkedContentPropertiesFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $properties,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}>
     */
    private function summarizePdfPageMarkedContentProperties(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $propertyDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'Properties', $objects);
        if ($propertyDictionary === null) {
            return [];
        }

        $properties = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($propertyDictionary) as $entry) {
            $property = $this->summarizePdfMarkedContentProperty(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null
            );
            if ($property !== null) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, mcid:int|null, language:string|null, alt:string|null, actualText:string|null, expanded:string|null, associatedFiles:list<string>}|null
     */
    private function summarizePdfMarkedContentProperty(string $propertyName, array $value, array $objects, string $pageReference, bool $inherited): ?array
    {
        $dictionary = null;
        $propertyObject = null;
        if ($value['kind'] === 'reference') {
            $propertyObject = $value['value'];
            $dictionary = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        } elseif ($value['kind'] === 'dictionary') {
            $propertyObject = 'inline';
            $dictionary = $value['value'];
        }

        if ($dictionary === null) {
            return null;
        }
        if ($this->extractPdfNameToken($dictionary, 'Type') === 'OCMD') {
            return null;
        }

        $associatedFiles = [];
        foreach ($this->extractPdfReferenceArray($dictionary, 'AF') as $reference) {
            $associatedFiles[] = $reference . ' R';
        }

        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'propertyName' => $propertyName,
            'propertyObject' => $propertyObject,
            'inherited' => $inherited,
            'mcid' => $this->extractPdfIntegerToken($dictionary, 'MCID'),
            'language' => $this->extractPdfStringOrNameValue($dictionary, 'Lang'),
            'alt' => $this->extractPdfStringOrNameValue($dictionary, 'Alt'),
            'actualText' => $this->extractPdfStringOrNameValue($dictionary, 'ActualText'),
            'expanded' => $this->extractPdfStringOrNameValue($dictionary, 'E'),
            'associatedFiles' => $associatedFiles,
        ];
    }

    /**
     * @return list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, type:string|null, groups:list<string>, policy:string|null, visibilityExpressionOperators:list<string>, visibilityExpressionGroups:list<string>}>
     */
    private function extractPdfOptionalContentMemberships(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $memberships = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfOptionalContentMembershipsFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $memberships,
                $pageNumber,
                0
            );
        }

        if ($memberships === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageMemberships = $this->summarizePdfPageOptionalContentMemberships($body, $reference, null, $objects);
                foreach ($pageMemberships as &$membership) {
                    $membership['page'] = $pageNumber;
                }
                unset($membership);
                array_push($memberships, ...$pageMemberships);
            }
        }

        $memberships = array_values($memberships);
        usort(
            $memberships,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['propertyName'],
                $a['propertyObject'] ?? '',
            ] <=> [
                $b['page'],
                $b['propertyName'],
                $b['propertyObject'] ?? '',
            ]
        );

        return $memberships;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, type:string|null, groups:list<string>, policy:string|null, visibilityExpressionOperators:list<string>, visibilityExpressionGroups:list<string>}> $memberships
     */
    private function collectPdfOptionalContentMembershipsFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$memberships,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageMemberships = $this->summarizePdfPageOptionalContentMemberships($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageMemberships as &$membership) {
                $membership['page'] = $pageNumber;
            }
            unset($membership);
            array_push($memberships, ...$pageMemberships);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfOptionalContentMembershipsFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $memberships,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, type:string|null, groups:list<string>, policy:string|null, visibilityExpressionOperators:list<string>, visibilityExpressionGroups:list<string>}>
     */
    private function summarizePdfPageOptionalContentMemberships(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $propertyDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'Properties', $objects);
        if ($propertyDictionary === null) {
            return [];
        }

        $memberships = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($propertyDictionary) as $entry) {
            $membership = $this->summarizePdfOptionalContentMembership(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null
            );
            if ($membership !== null) {
                $memberships[] = $membership;
            }
        }

        return $memberships;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, propertyName:string, propertyObject:string|null, inherited:bool, type:string|null, groups:list<string>, policy:string|null, visibilityExpressionOperators:list<string>, visibilityExpressionGroups:list<string>}|null
     */
    private function summarizePdfOptionalContentMembership(string $propertyName, array $value, array $objects, string $pageReference, bool $inherited): ?array
    {
        $dictionary = null;
        $propertyObject = null;
        if ($value['kind'] === 'reference') {
            $propertyObject = $value['value'];
            $dictionary = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        } elseif ($value['kind'] === 'dictionary') {
            $propertyObject = 'inline';
            $dictionary = $value['value'];
        }

        if ($dictionary === null || $this->extractPdfNameToken($dictionary, 'Type') !== 'OCMD') {
            return null;
        }

        $visibilityExpression = $this->extractPdfValueForName($dictionary, 'VE');

        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'propertyName' => $propertyName,
            'propertyObject' => $propertyObject,
            'inherited' => $inherited,
            'type' => $this->extractPdfNameToken($dictionary, 'Type'),
            'groups' => $this->collectPdfReferencesFromValue($this->extractPdfValueForName($dictionary, 'OCGs'), $objects),
            'policy' => $this->extractPdfNameToken($dictionary, 'P'),
            'visibilityExpressionOperators' => $this->collectPdfNamesFromValue($visibilityExpression, $objects),
            'visibilityExpressionGroups' => $this->collectPdfReferencesFromValue($visibilityExpression, $objects),
        ];
    }

    /**
     * @return array{
     *     groups:list<array{object:string, name:string|null, intent:list<string>, usageViewState:string|null, usagePrintState:string|null, usageExportState:string|null, usageCreator:string|null, usageCreatorSubtype:string|null, usageLanguage:string|null, usageLanguagePreferred:bool|null, usageZoomMin:float|null, usageZoomMax:float|null}>,
     *     config:array{name:string|null, creator:string|null, baseState:string|null, listMode:string|null, on:list<string>, off:list<string>, order:list<string>, orderLabels:list<string>}|array{}
     * }
     */
    private function extractPdfOptionalContent(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $groupReferences = [];
        $config = [];

        if ($catalog !== null && str_contains($catalog, '/OCProperties')) {
            $properties = $this->extractPdfDictionaryOrReferenceValue($catalog, 'OCProperties', $objects);
            if ($properties !== null) {
                foreach ($this->collectPdfReferencesFromArray($this->extractPdfArrayOrReferenceValue($properties, 'OCGs', $objects)) as $reference) {
                    $groupReferences[$this->pdfReferenceKey($reference)] = $reference;
                }
                $config = $this->summarizePdfOptionalContentConfig($properties, $objects);
            }
        }

        foreach ($objects as $reference => $body) {
            if ($this->extractPdfNameToken($body, 'Type') === 'OCG') {
                $groupReferences[$reference] = $reference . ' R';
            }
        }

        $references = array_values($groupReferences);
        usort($references, fn (string $a, string $b): int => $this->pdfReferenceSortKey($a) <=> $this->pdfReferenceSortKey($b));

        $groups = [];
        foreach ($references as $reference) {
            $body = $objects[$this->pdfReferenceKey($reference)] ?? null;
            if ($body === null) {
                continue;
            }

            $groups[] = $this->summarizePdfOptionalContentGroup($reference, $body, $objects);
        }

        return [
            'groups' => $groups,
            'config' => $config,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array{object:string, name:string|null, intent:list<string>, usageViewState:string|null, usagePrintState:string|null, usageExportState:string|null, usageCreator:string|null, usageCreatorSubtype:string|null, usageLanguage:string|null, usageLanguagePreferred:bool|null, usageZoomMin:float|null, usageZoomMax:float|null}
     */
    private function summarizePdfOptionalContentGroup(string $reference, string $dictionary, array $objects): array
    {
        $usage = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'Usage', $objects);
        $creatorInfo = $usage === null ? null : $this->extractPdfDictionaryOrReferenceValue($usage, 'CreatorInfo', $objects);
        $language = $usage === null ? null : $this->extractPdfDictionaryOrReferenceValue($usage, 'Language', $objects);
        $zoom = $usage === null ? null : $this->extractPdfDictionaryOrReferenceValue($usage, 'Zoom', $objects);

        return [
            'object' => $reference,
            'name' => $this->extractPdfStringOrNameValue($dictionary, 'Name'),
            'intent' => $this->extractPdfOptionalContentIntent($dictionary, $objects),
            'usageViewState' => $this->extractPdfOptionalContentUsageState($usage, 'View', 'ViewState', $objects),
            'usagePrintState' => $this->extractPdfOptionalContentUsageState($usage, 'Print', 'PrintState', $objects),
            'usageExportState' => $this->extractPdfOptionalContentUsageState($usage, 'Export', 'ExportState', $objects),
            'usageCreator' => $creatorInfo === null ? null : $this->extractPdfStringOrNameValue($creatorInfo, 'Creator'),
            'usageCreatorSubtype' => $creatorInfo === null ? null : $this->extractPdfNameToken($creatorInfo, 'Subtype'),
            'usageLanguage' => $language === null ? null : $this->extractPdfStringOrNameValue($language, 'Lang'),
            'usageLanguagePreferred' => $language === null ? null : $this->extractPdfBooleanToken($language, 'Preferred'),
            'usageZoomMin' => $zoom === null ? null : $this->extractPdfNumberToken($zoom, 'min'),
            'usageZoomMax' => $zoom === null ? null : $this->extractPdfNumberToken($zoom, 'max'),
        ];
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfOptionalContentUsageState(?string $usage, string $section, string $stateName, array $objects): ?string
    {
        if ($usage === null) {
            return null;
        }

        $dictionary = $this->extractPdfDictionaryOrReferenceValue($usage, $section, $objects);
        if ($dictionary === null) {
            return null;
        }

        return $this->extractPdfNameToken($dictionary, $stateName);
    }

    /**
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function extractPdfOptionalContentIntent(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'Intent');
        if ($value === null) {
            return [];
        }

        return $this->pdfValueToNameList($value, $objects);
    }

    /**
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function pdfValueToNameList(array $value, array $objects, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $name = trim($value['value']);

            return $name === '' ? [] : [$name];
        }

        if ($value['kind'] === 'array') {
            return $this->collectPdfNamesFromArray($value['value']);
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return [];
            }

            $resolved = $this->parsePdfValueAt($body, 0);

            return $resolved === null ? [] : $this->pdfValueToNameList($resolved, $objects, $depth + 1);
        }

        return [];
    }

    /**
     * @param array<string, string> $objects
     * @return array{name:string|null, creator:string|null, baseState:string|null, listMode:string|null, on:list<string>, off:list<string>, order:list<string>, orderLabels:list<string>}|array{}
     */
    private function summarizePdfOptionalContentConfig(string $properties, array $objects): array
    {
        $configuration = $this->extractPdfDictionaryOrReferenceValue($properties, 'D', $objects);
        if ($configuration === null) {
            return [];
        }

        return [
            'name' => $this->extractPdfStringOrNameValue($configuration, 'Name'),
            'creator' => $this->extractPdfStringOrNameValue($configuration, 'Creator'),
            'baseState' => $this->extractPdfNameToken($configuration, 'BaseState'),
            'listMode' => $this->extractPdfNameToken($configuration, 'ListMode'),
            'on' => $this->collectPdfReferencesFromArray($this->extractPdfArrayOrReferenceValue($configuration, 'ON', $objects)),
            'off' => $this->collectPdfReferencesFromArray($this->extractPdfArrayOrReferenceValue($configuration, 'OFF', $objects)),
            'order' => $this->collectPdfReferencesFromArray($this->extractPdfArrayOrReferenceValue($configuration, 'Order', $objects)),
            'orderLabels' => $this->collectPdfStringsFromArray($this->extractPdfArrayOrReferenceValue($configuration, 'Order', $objects)),
        ];
    }

    /**
     * @return list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>
     */
    private function extractPdfThreads(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/Threads')) {
            return [];
        }

        $value = $this->extractPdfValueForName($catalog, 'Threads');
        if ($value === null) {
            return [];
        }

        $threads = [];
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $this->addPdfThreadsFromValue($threads, $value, $objects);
        usort(
            $threads,
            fn (array $left, array $right): int => $this->pdfReferenceSortKey($left['object'])
                <=> $this->pdfReferenceSortKey($right['object'])
                ?: strcmp($left['object'], $right['object'])
        );

        return array_values($threads);
    }

    /**
     * @param list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}> $threads
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     */
    private function addPdfThreadsFromValue(array &$threads, array $value, array $objects, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }

        if ($value['kind'] === 'array') {
            $cursor = str_starts_with($value['value'], '[') ? 1 : 0;
            $length = strlen($value['value']);
            if (str_ends_with($value['value'], ']')) {
                $length--;
            }
            while ($cursor < $length) {
                $item = $this->parsePdfValueAt($value['value'], $cursor);
                if ($item === null) {
                    $cursor++;
                    continue;
                }
                if (in_array($item['kind'], ['array', 'dictionary', 'reference'], true)) {
                    $this->addPdfThreadsFromValue($threads, $item, $objects, $depth + 1);
                }
                $cursor = max($cursor + 1, min($length, $item['next']));
            }

            return;
        }

        if ($value['kind'] === 'reference') {
            $reference = $value['value'];
            $body = $objects[$this->pdfReferenceKey($reference)] ?? null;
            if ($body === null) {
                return;
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved !== null && $resolved['kind'] === 'array') {
                $this->addPdfThreadsFromValue($threads, $resolved, $objects, $depth + 1);
                return;
            }

            $this->addPdfThreadSummary($threads, $this->summarizePdfThread($reference, $body, $objects));
            return;
        }

        if ($value['kind'] === 'dictionary') {
            $this->addPdfThreadSummary($threads, $this->summarizePdfThread('inline', $value['value'], $objects));
        }
    }

    /**
     * @param list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}> $threads
     * @param array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>} $summary
     */
    private function addPdfThreadSummary(array &$threads, array $summary): void
    {
        if ($summary['object'] !== 'inline') {
            foreach ($threads as $thread) {
                if ($thread['object'] === $summary['object']) {
                    return;
                }
            }
        }

        $threads[] = $summary;
    }

    /**
     * @param array<string, string> $objects
     * @return array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}
     */
    private function summarizePdfThread(string $reference, string $dictionary, array $objects): array
    {
        $info = $this->extractPdfThreadInfoMetadata($dictionary, $objects);
        $firstBead = $this->extractPdfReferenceToken($dictionary, 'F');
        $beads = $firstBead === null ? [] : $this->extractPdfThreadBeads($firstBead, $objects);

        return [
            'object' => $reference,
            'infoTitle' => $info['title'],
            'infoAuthor' => $info['author'],
            'infoSubject' => $info['subject'],
            'firstBead' => $firstBead,
            'beadCount' => count($beads),
            'beads' => $beads,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array{title:string|null, author:string|null, subject:string|null}
     */
    private function extractPdfThreadInfoMetadata(string $thread, array $objects): array
    {
        $info = $this->extractPdfDictionaryOrReferenceValue($thread, 'I', $objects);
        if ($info === null) {
            $info = $thread;
        }

        return [
            'title' => $this->extractPdfStringOrNameValue($info, 'Title'),
            'author' => $this->extractPdfStringOrNameValue($info, 'Author'),
            'subject' => $this->extractPdfStringOrNameValue($info, 'Subject'),
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>
     */
    private function extractPdfThreadBeads(string $firstBead, array $objects): array
    {
        $beads = [];
        $visited = [];
        $firstKey = $this->pdfReferenceKey($firstBead);
        $cursor = $firstKey;
        for ($depth = 0; $depth < 64; $depth++) {
            if (isset($visited[$cursor])) {
                break;
            }
            $body = $objects[$cursor] ?? null;
            if ($body === null) {
                break;
            }

            $visited[$cursor] = true;
            $next = $this->extractPdfReferenceToken($body, 'N');
            $beads[] = [
                'object' => $cursor . ' R',
                'pageObject' => $this->extractPdfReferenceToken($body, 'P'),
                'rect' => $this->extractPdfNumberArrayToken($body, 'R', 4),
                'next' => $next,
                'prev' => $this->extractPdfReferenceToken($body, 'V'),
            ];

            if ($next === null) {
                break;
            }

            $nextKey = $this->pdfReferenceKey($next);
            if ($nextKey === $firstKey || isset($visited[$nextKey])) {
                break;
            }

            $cursor = $nextKey;
        }

        return $beads;
    }

    /**
     * @return array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{}
     */
    private function extractPdfCollectionMetadata(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/Collection')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $collection = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Collection', $objects);
        if ($collection === null) {
            return [];
        }

        return [
            'type' => $this->extractPdfNameToken($collection, 'Type'),
            'view' => $this->extractPdfNameToken($collection, 'View'),
            'defaultDocument' => $this->extractPdfStringOrNameValue($collection, 'D'),
            'schemaFields' => $this->extractPdfCollectionSchemaFields($collection, $objects),
            'sort' => $this->extractPdfCollectionSort($collection, $objects),
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>
     */
    private function extractPdfCollectionSchemaFields(string $collection, array $objects): array
    {
        $schema = $this->extractPdfDictionaryOrReferenceValue($collection, 'Schema', $objects);
        if ($schema === null) {
            return [];
        }

        $fields = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($schema) as $entry) {
            if ($entry['key'] === 'Type') {
                continue;
            }

            $dictionary = null;
            if ($entry['value']['kind'] === 'dictionary') {
                $dictionary = $entry['value']['value'];
            } elseif ($entry['value']['kind'] === 'reference') {
                $dictionary = $objects[$this->pdfReferenceKey($entry['value']['value'])] ?? null;
            }
            if ($dictionary === null) {
                continue;
            }

            $summary = $this->summarizePdfCollectionField($entry['key'], $dictionary);
            if ($summary !== null) {
                $fields[] = $summary;
            }
        }

        usort(
            $fields,
            static function (array $left, array $right): int {
                $leftOrder = $left['order'] ?? PHP_INT_MAX;
                $rightOrder = $right['order'] ?? PHP_INT_MAX;
                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                return strcmp($left['name'], $right['name']);
            }
        );

        return $fields;
    }

    /**
     * @return array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}|null
     */
    private function summarizePdfCollectionField(string $name, string $dictionary): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'subtype' => $this->extractPdfNameToken($dictionary, 'Subtype'),
            'title' => $this->extractPdfStringOrNameValue($dictionary, 'N'),
            'order' => $this->extractPdfIntegerToken($dictionary, 'O'),
            'visible' => $this->extractPdfBooleanToken($dictionary, 'V'),
            'editable' => $this->extractPdfBooleanToken($dictionary, 'E'),
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array{fields:list<string>, ascending:list<bool>}|array{}
     */
    private function extractPdfCollectionSort(string $collection, array $objects): array
    {
        $sort = $this->extractPdfDictionaryOrReferenceValue($collection, 'Sort', $objects);
        if ($sort === null) {
            return [];
        }

        $fieldsValue = $this->extractPdfValueForName($sort, 'S');
        $fields = $fieldsValue === null ? [] : $this->pdfValueToNameList($fieldsValue, $objects);
        $ascendingValue = $this->extractPdfValueForName($sort, 'A');
        $ascending = $ascendingValue === null ? [] : $this->pdfValueToBooleanList($ascendingValue, $objects);
        if ($fields === [] && $ascending === []) {
            return [];
        }

        return [
            'fields' => $fields,
            'ascending' => $ascending,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return list<bool>
     */
    private function pdfValueToBooleanList(array $value, array $objects, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        if ($value['kind'] === 'keyword' && in_array($value['value'], ['true', 'false'], true)) {
            return [$value['value'] === 'true'];
        }

        if ($value['kind'] === 'array') {
            $booleans = [];
            $this->walkPdfArrayValues($value['value'], function (array $item) use (&$booleans, $objects, $depth): void {
                if ($item['kind'] === 'keyword' && in_array($item['value'], ['true', 'false'], true)) {
                    $booleans[] = $item['value'] === 'true';
                    return;
                }

                if ($item['kind'] === 'reference') {
                    foreach ($this->pdfValueToBooleanList($item, $objects, $depth + 1) as $boolean) {
                        $booleans[] = $boolean;
                    }
                }
            });

            return $booleans;
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return [];
            }

            $resolved = $this->parsePdfValueAt($body, 0);

            return $resolved === null ? [] : $this->pdfValueToBooleanList($resolved, $objects, $depth + 1);
        }

        return [];
    }

    /**
     * @return list<array{permission:string, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>
     */
    private function extractPdfCatalogPermissions(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/Perms')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $permissionsDictionary = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Perms', $objects);
        if ($permissionsDictionary === null) {
            return [];
        }

        $permissions = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($permissionsDictionary) as $entry) {
            $permission = $entry['key'];
            if ($permission === 'Type' || $permission === '') {
                continue;
            }

            $summary = null;
            $value = $entry['value'];
            if ($value['kind'] === 'reference') {
                $reference = $this->pdfReferenceKey($value['value']);
                $body = $objects[$reference] ?? null;
                if ($body !== null) {
                    $summary = $this->summarizePdfSignatureDictionary($body, null, null, $reference . ' R', $objects);
                }
            } elseif ($value['kind'] === 'dictionary') {
                $summary = $this->summarizePdfSignatureDictionary($value['value'], null, null, 'inline', $objects);
            }

            if ($summary === null || !$this->isPdfSignatureLikeSummary($summary)) {
                continue;
            }

            $permissions[] = [
                'permission' => $permission,
                'signatureObject' => $summary['signatureObject'],
                'filter' => $summary['filter'],
                'subFilter' => $summary['subFilter'],
                'name' => $summary['name'],
                'reason' => $summary['reason'],
                'location' => $summary['location'],
                'contactInfo' => $summary['contactInfo'],
                'signingTime' => $summary['signingTime'],
                'byteRange' => $summary['byteRange'],
                'byteRangeSegmentCount' => $summary['byteRangeSegmentCount'],
                'coveredBytes' => $summary['coveredBytes'],
                'contentsBytes' => $summary['contentsBytes'],
                'contentsSha256' => $summary['contentsSha256'],
                'contentsSkipped' => $summary['contentsSkipped'],
                'referenceTransforms' => $summary['referenceTransforms'],
            ];
        }

        usort(
            $permissions,
            static fn (array $a, array $b): int => [
                $a['permission'],
                $a['signatureObject'] ?? '',
            ] <=> [
                $b['permission'],
                $b['signatureObject'] ?? '',
            ]
        );

        return $permissions;
    }

    /**
     * @param array{filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, contentsBytes:int|null, referenceTransforms:list<array<string, mixed>>} $summary
     */
    private function isPdfSignatureLikeSummary(array $summary): bool
    {
        if (($summary['byteRange'] ?? []) !== [] || ($summary['contentsBytes'] ?? null) !== null) {
            return true;
        }
        if (($summary['referenceTransforms'] ?? []) !== []) {
            return true;
        }

        foreach (['filter', 'subFilter', 'name', 'reason', 'location', 'contactInfo', 'signingTime'] as $key) {
            if (is_string($summary[$key] ?? null) && $summary[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>
     */
    private function extractPdfSignatures(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $signatures = [];
        $visitedFields = [];
        $acroForm = $this->extractPdfAcroFormDictionary($pdfBytes, $catalog);

        if ($acroForm !== null) {
            foreach ($this->extractPdfReferenceArray($acroForm, 'Fields') as $reference) {
                $this->collectPdfSignatureFields($signatures, $objects, $reference, $visitedFields, 0);
            }
        }

        foreach ($objects as $reference => $body) {
            if ($this->extractPdfNameToken($body, 'FT') === 'Sig') {
                $this->addPdfSignatureFromField($signatures, $reference . ' R', $body, $objects);
                continue;
            }

            if (
                $this->extractPdfNameToken($body, 'Type') === 'Sig'
                || (str_contains($body, '/ByteRange') && str_contains($body, '/Contents'))
            ) {
                $this->addPdfSignatureEntry(
                    $signatures,
                    $this->summarizePdfSignatureDictionary($body, null, null, $reference . ' R', $objects)
                );
            }
        }

        $signatures = array_values($signatures);
        usort(
            $signatures,
            static fn (array $a, array $b): int => [
                $a['fieldName'] ?? '',
                $a['fieldObject'] ?? '',
                $a['signatureObject'] ?? '',
            ] <=> [
                $b['fieldName'] ?? '',
                $b['fieldObject'] ?? '',
                $b['signatureObject'] ?? '',
            ]
        );

        return $signatures;
    }

    /**
     * @param array<string, array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}> $signatures
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfSignatureFields(array &$signatures, array $objects, string $reference, array &$visited, int $depth): void
    {
        if ($depth > 16 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }

        $visited[$reference] = true;
        $body = $objects[$reference];
        $this->addPdfSignatureFromField($signatures, $reference . ' R', $body, $objects);

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfSignatureFields($signatures, $objects, $kidReference, $visited, $depth + 1);
        }
    }

    /**
     * @param array<string, array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}> $signatures
     * @param array<string, string> $objects
     */
    private function addPdfSignatureFromField(array &$signatures, string $fieldReference, string $fieldDictionary, array $objects): void
    {
        if ($this->extractPdfNameToken($fieldDictionary, 'FT') !== 'Sig') {
            return;
        }

        $fieldName = $this->extractPdfStringOrNameValue($fieldDictionary, 'T')
            ?? $this->extractPdfStringOrNameValue($fieldDictionary, 'TU')
            ?? $this->extractPdfStringOrNameValue($fieldDictionary, 'TM');
        $value = $this->extractPdfValueForName($fieldDictionary, 'V');
        if ($value === null) {
            return;
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body !== null) {
                $this->addPdfSignatureEntry(
                    $signatures,
                    $this->summarizePdfSignatureDictionary($body, $fieldName, $fieldReference, $value['value'], $objects)
                );
            }

            return;
        }

        if ($value['kind'] === 'dictionary') {
            $this->addPdfSignatureEntry(
                $signatures,
                $this->summarizePdfSignatureDictionary($value['value'], $fieldName, $fieldReference, 'inline', $objects)
            );
        }
    }

    /**
     * @param array<string, array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}> $signatures
     * @param array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>} $entry
     */
    private function addPdfSignatureEntry(array &$signatures, array $entry): void
    {
        $key = $entry['signatureObject'] ?? $entry['fieldObject'] ?? hash('sha256', json_encode($entry) ?: serialize($entry));
        if (
            !isset($signatures[$key])
            || (($signatures[$key]['fieldName'] ?? null) === null && ($entry['fieldName'] ?? null) !== null)
        ) {
            $signatures[$key] = $entry;
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}
     */
    private function summarizePdfSignatureDictionary(
        string $dictionary,
        ?string $fieldName,
        ?string $fieldObject,
        ?string $signatureObject,
        array $objects
    ): array {
        $byteRange = $this->extractPdfIntegerArrayToken($dictionary, 'ByteRange');
        $contents = $this->summarizePdfSignatureContents($dictionary);

        return [
            'fieldName' => $fieldName === '' ? null : $fieldName,
            'fieldObject' => $fieldObject,
            'signatureObject' => $signatureObject,
            'filter' => $this->extractPdfStringOrNameValue($dictionary, 'Filter'),
            'subFilter' => $this->extractPdfStringOrNameValue($dictionary, 'SubFilter'),
            'name' => $this->extractPdfStringOrNameValue($dictionary, 'Name'),
            'reason' => $this->extractPdfStringOrNameValue($dictionary, 'Reason'),
            'location' => $this->extractPdfStringOrNameValue($dictionary, 'Location'),
            'contactInfo' => $this->extractPdfStringOrNameValue($dictionary, 'ContactInfo'),
            'signingTime' => $this->extractPdfStringOrNameValue($dictionary, 'M'),
            'byteRange' => $byteRange,
            'byteRangeSegmentCount' => intdiv(count($byteRange), 2),
            'coveredBytes' => $this->coveredBytesForPdfSignatureRange($byteRange),
            'contentsBytes' => $contents['bytes'],
            'contentsSha256' => $contents['sha256'],
            'contentsSkipped' => $contents['skipped'],
            'referenceTransforms' => $this->extractPdfSignatureReferenceTransforms($dictionary, $objects),
        ];
    }

    /**
     * @return array{bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfSignatureContents(string $dictionary): array
    {
        $hex = $this->extractPdfByteStringHexValue($dictionary, 'Contents');
        if ($hex === null) {
            return ['bytes' => null, 'sha256' => null, 'skipped' => null];
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return ['bytes' => null, 'sha256' => null, 'skipped' => null];
        }

        if (strlen($bytes) > self::MAX_SIGNATURE_CONTENTS_BYTES) {
            return ['bytes' => strlen($bytes), 'sha256' => null, 'skipped' => 'too-large'];
        }

        return ['bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'skipped' => null];
    }

    /**
     * @param list<int> $byteRange
     */
    private function coveredBytesForPdfSignatureRange(array $byteRange): ?int
    {
        if (count($byteRange) < 2 || count($byteRange) % 2 !== 0) {
            return null;
        }

        $coveredBytes = 0;
        for ($index = 1; $index < count($byteRange); $index += 2) {
            $coveredBytes += $byteRange[$index];
        }

        return $coveredBytes;
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>
     */
    private function extractPdfSignatureReferenceTransforms(string $dictionary, array $objects): array
    {
        $array = $this->extractPdfArrayOrReferenceValue($dictionary, 'Reference', $objects);
        if ($array === null) {
            return [];
        }

        $transforms = [];
        $cursor = str_starts_with($array, '[') ? 1 : 0;
        $length = strlen($array);
        if (str_ends_with($array, ']')) {
            $length--;
        }

        while ($cursor < $length && count($transforms) < 16) {
            $value = $this->parsePdfValueAt($array, $cursor);
            if ($value === null) {
                $cursor++;
                continue;
            }

            $summary = null;
            if ($value['kind'] === 'dictionary') {
                $summary = $this->summarizePdfSignatureReferenceTransform($value['value'], $objects);
            } elseif ($value['kind'] === 'reference') {
                $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
                if ($body !== null) {
                    $summary = $this->summarizePdfSignatureReferenceTransform($body, $objects);
                }
            }
            if ($summary !== null) {
                $transforms[] = $summary;
            }

            $cursor = max($cursor + 1, min($length, $value['next']));
        }

        return $transforms;
    }

    /**
     * @param array<string, string> $objects
     * @return array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}|null
     */
    private function summarizePdfSignatureReferenceTransform(string $dictionary, array $objects): ?array
    {
        $params = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'TransformParams', $objects);
        $transformMethod = $this->extractPdfNameToken($dictionary, 'TransformMethod');
        if ($transformMethod === null && $params === null) {
            return null;
        }

        return [
            'transformMethod' => $transformMethod,
            'transformParamsType' => $params === null ? null : $this->extractPdfNameToken($params, 'Type'),
            'permissions' => $params === null ? null : $this->extractPdfIntegerToken($params, 'P'),
            'action' => $params === null ? null : $this->extractPdfNameToken($params, 'Action'),
            'fields' => $params === null ? [] : $this->extractPdfStringArrayValue($params, 'Fields'),
        ];
    }

    /**
     * @param list<array{subFilter:string|null}> $signatures
     * @return array<string, int>
     */
    private function summarizePdfSignatureSubFilters(array $signatures): array
    {
        $subFilters = [];
        foreach ($signatures as $signature) {
            $subFilter = $signature['subFilter'] ?? null;
            if (!is_string($subFilter) || $subFilter === '') {
                continue;
            }

            $subFilters[$subFilter] = ($subFilters[$subFilter] ?? 0) + 1;
        }

        ksort($subFilters);

        return $subFilters;
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfArrayOrReferenceValue(string $dictionary, string $name, array $objects): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return null;
        }

        if ($value['kind'] === 'array') {
            return $value['value'];
        }

        if ($value['kind'] !== 'reference') {
            return null;
        }

        $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        if ($body === null) {
            return null;
        }

        $resolved = $this->parsePdfValueAt($body, 0);

        return $resolved !== null && $resolved['kind'] === 'array' ? $resolved['value'] : null;
    }

    /**
     * @return list<string>
     */
    private function collectPdfReferencesFromArray(?string $array): array
    {
        if ($array === null) {
            return [];
        }

        $references = [];
        $this->walkPdfArrayValues($array, static function (array $value) use (&$references): void {
            if ($value['kind'] === 'reference') {
                $references[] = $value['value'];
            }
        });

        return $this->uniqueStrings($references);
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function collectPdfReferencesFromValue(?array $value, array $objects, int $depth = 0): array
    {
        if ($value === null || $depth > 8) {
            return [];
        }

        if ($value['kind'] === 'array') {
            return $this->collectPdfReferencesFromArray($value['value']);
        }

        if ($value['kind'] !== 'reference') {
            return [];
        }

        $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
        if ($body !== '') {
            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved !== null && $resolved['kind'] === 'array') {
                return $this->collectPdfReferencesFromValue($resolved, $objects, $depth + 1);
            }
        }

        return [$value['value']];
    }

    /**
     * @return list<string>
     */
    private function collectPdfNamesFromArray(string $array): array
    {
        $names = [];
        $this->walkPdfArrayValues($array, static function (array $value) use (&$names): void {
            if (!in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
                return;
            }

            $name = trim($value['value']);
            if ($name !== '') {
                $names[] = $name;
            }
        });

        return $this->uniqueStrings($names);
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function collectPdfNamesFromValue(?array $value, array $objects, int $depth = 0): array
    {
        if ($value === null || $depth > 8) {
            return [];
        }

        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $name = trim($value['value']);

            return $name === '' ? [] : [$name];
        }

        if ($value['kind'] === 'array') {
            return $this->collectPdfNamesFromArray($value['value']);
        }

        if ($value['kind'] === 'reference') {
            $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
            if ($body === '') {
                return [];
            }

            $resolved = $this->parsePdfValueAt($body, 0);

            return $resolved === null ? [] : $this->collectPdfNamesFromValue($resolved, $objects, $depth + 1);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function collectPdfStringsFromArray(?string $array): array
    {
        if ($array === null) {
            return [];
        }

        $strings = [];
        $this->walkPdfArrayValues($array, static function (array $value) use (&$strings): void {
            if (!in_array($value['kind'], ['literal', 'hex'], true)) {
                return;
            }

            $string = trim($value['value']);
            if ($string !== '') {
                $strings[] = $string;
            }
        });

        return $this->uniqueStrings($strings);
    }

    private function walkPdfArrayValues(string $array, callable $visitor, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }

        $cursor = str_starts_with($array, '[') ? 1 : 0;
        $length = strlen($array);
        if (str_ends_with($array, ']')) {
            $length--;
        }

        while ($cursor < $length) {
            $value = $this->parsePdfValueAt($array, $cursor);
            if ($value === null) {
                $cursor++;
                continue;
            }

            $visitor($value);
            if ($value['kind'] === 'array') {
                $this->walkPdfArrayValues($value['value'], $visitor, $depth + 1);
            }
            $cursor = max($cursor + 1, min($length, $value['next']));
        }
    }

    /**
     * @return list<array{kind:string, value:string, next:int}>
     */
    private function pdfTopLevelArrayValues(string $array): array
    {
        $values = [];
        $cursor = str_starts_with($array, '[') ? 1 : 0;
        $length = strlen($array);
        if (str_ends_with($array, ']')) {
            $length--;
        }

        while ($cursor < $length && count($values) < 1024) {
            $value = $this->parsePdfValueAt($array, $cursor);
            if ($value === null) {
                $cursor++;
                continue;
            }

            $values[] = $value;
            $cursor = max($cursor + 1, min($length, $value['next']));
        }

        return $values;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $unique = [];
        $seen = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $unique[] = $value;
        }

        return $unique;
    }

    private function countPdfTopLevelArrayValues(string $array): int
    {
        $cursor = str_starts_with($array, '[') ? 1 : 0;
        $length = strlen($array);
        if (str_ends_with($array, ']')) {
            $length--;
        }

        $count = 0;
        while ($cursor < $length && $count < 1024) {
            $value = $this->parsePdfValueAt($array, $cursor);
            if ($value === null) {
                $cursor++;
                continue;
            }

            $count++;
            $cursor = max($cursor + 1, min($length, $value['next']));
        }

        return $count;
    }

    private function extractPdfNestedDictionary(string $dictionary, string $name): ?string
    {
        $needle = '/' . $name;
        $offset = 0;
        $length = strlen($dictionary);
        while (($position = strpos($dictionary, $needle, $offset)) !== false) {
            $cursor = $position + strlen($needle);
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $dictionary[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }
            if (substr($dictionary, $cursor, 2) !== '<<') {
                $offset = $cursor + 1;
                continue;
            }

            $parsed = $this->parsePdfDictionary($dictionary, $cursor);
            if ($parsed !== null) {
                return $parsed['value'];
            }

            $offset = $cursor + 2;
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfDictionaryOrReferenceValue(string $dictionary, string $name, array $objects): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return null;
        }

        if ($value['kind'] === 'dictionary') {
            return $value['value'];
        }
        if ($value['kind'] === 'reference') {
            return $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        }

        return null;
    }

    /**
     * @return list<array{key:string, value:array{kind:string, value:string, next:int}}>
     */
    private function extractPdfTopLevelDictionaryEntries(string $dictionary): array
    {
        $length = strlen($dictionary);
        $start = 0;
        $end = $length;

        while ($start < $length && ctype_space($dictionary[$start])) {
            $start++;
        }
        if (substr($dictionary, $start, 2) === '<<') {
            $parsed = $this->parsePdfDictionary($dictionary, $start);
            if ($parsed !== null) {
                $start += 2;
                $end = max($start, $parsed['next'] - 2);
            }
        }

        $entries = [];
        $cursor = $start;
        while ($cursor < $end) {
            while ($cursor < $end && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $end) {
                break;
            }

            if ($dictionary[$cursor] !== '/') {
                $value = $this->parsePdfValueAt($dictionary, $cursor);
                $cursor = $value === null ? $cursor + 1 : min($end, $value['next']);
                continue;
            }

            if (preg_match('/\A\/([A-Za-z0-9_.#+-]+)/s', substr($dictionary, $cursor), $matches) !== 1) {
                $cursor++;
                continue;
            }

            $key = $this->decodePdfNameToken($matches[1]);
            $cursor += strlen($matches[0]);
            $value = $this->parsePdfValueAt($dictionary, $cursor);
            if ($value !== null) {
                $entries[] = [
                    'key' => $key,
                    'value' => $value,
                ];
                $cursor = min($end, $value['next']);
                continue;
            }

            $cursor++;
        }

        return $entries;
    }

    /**
     * @return array{kind:string, value:string, next:int}|null
     */
    private function extractPdfValueForName(string $dictionary, string $name): ?array
    {
        $length = strlen($dictionary);
        $start = 0;
        $end = $length;

        while ($start < $length && ctype_space($dictionary[$start])) {
            $start++;
        }
        if (substr($dictionary, $start, 2) === '<<') {
            $parsed = $this->parsePdfDictionary($dictionary, $start);
            if ($parsed !== null) {
                $start += 2;
                $end = max($start, $parsed['next'] - 2);
            }
        }

        $cursor = $start;
        while ($cursor < $end) {
            while ($cursor < $end && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $end) {
                break;
            }

            if ($dictionary[$cursor] !== '/') {
                $value = $this->parsePdfValueAt($dictionary, $cursor);
                $cursor = $value === null ? $cursor + 1 : min($end, $value['next']);
                continue;
            }

            if (preg_match('/\A\/([A-Za-z0-9_.#+-]+)/s', substr($dictionary, $cursor), $matches) !== 1) {
                $cursor++;
                continue;
            }

            $key = $this->decodePdfNameToken($matches[1]);
            $cursor += strlen($matches[0]);
            $value = $this->parsePdfValueAt($dictionary, $cursor);
            if ($key === $name && $value !== null) {
                return $value;
            }

            $cursor = $value === null ? $cursor + 1 : min($end, $value['next']);
        }

        return null;
    }

    /**
     * @return array{kind:string, value:string, next:int}|null
     */
    private function parsePdfValueAt(string $bytes, int $offset): ?array
    {
        $length = strlen($bytes);
        while ($offset < $length && ctype_space($bytes[$offset])) {
            $offset++;
        }
        if ($offset >= $length) {
            return null;
        }

        if (substr($bytes, $offset, 2) === '<<') {
            $parsed = $this->parsePdfDictionary($bytes, $offset);

            return $parsed === null ? null : ['kind' => 'dictionary', 'value' => $parsed['value'], 'next' => $parsed['next']];
        }

        if ($bytes[$offset] === '[') {
            $parsed = $this->parsePdfArray($bytes, $offset);

            return $parsed === null ? null : ['kind' => 'array', 'value' => $parsed['value'], 'next' => $parsed['next']];
        }

        if ($bytes[$offset] === '(') {
            $parsed = $this->parsePdfLiteralString($bytes, $offset);

            return $parsed === null ? null : ['kind' => 'literal', 'value' => $parsed['value'], 'next' => $parsed['next']];
        }

        if ($bytes[$offset] === '<' && ($offset + 1 >= $length || $bytes[$offset + 1] !== '<')) {
            $parsed = $this->parsePdfHexString($bytes, $offset);

            return $parsed === null ? null : ['kind' => 'hex', 'value' => $parsed['value'], 'next' => $parsed['next']];
        }

        $chunk = substr($bytes, $offset);
        if (preg_match('/\A(\d+)\s+(\d+)\s+R\b/s', $chunk, $matches) === 1) {
            return [
                'kind' => 'reference',
                'value' => $matches[1] . ' ' . $matches[2] . ' R',
                'next' => $offset + strlen($matches[0]),
            ];
        }

        if (preg_match('/\A\/([A-Za-z0-9_.#+-]+)/s', $chunk, $matches) === 1) {
            return [
                'kind' => 'name',
                'value' => $this->decodePdfNameToken($matches[1]),
                'next' => $offset + strlen($matches[0]),
            ];
        }

        if (preg_match('/\A(true|false|null)\b/si', $chunk, $matches) === 1) {
            return [
                'kind' => 'keyword',
                'value' => strtolower($matches[1]),
                'next' => $offset + strlen($matches[0]),
            ];
        }

        if (preg_match('/\A[-+]?(?:\d+\.\d*|\.\d+|\d+)(?:[Ee][-+]?\d+)?\b/s', $chunk, $matches) === 1) {
            return [
                'kind' => 'number',
                'value' => $matches[0],
                'next' => $offset + strlen($matches[0]),
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     encrypted:bool,
     *     filter:string|null,
     *     version:int|null,
     *     revision:int|null,
     *     length:int|null,
     *     permissions:int|null,
     *     permissionFlags:array<string, bool>,
     *     encryptMetadata:bool|null
     * }
     */
    private function extractPdfEncryptionInfo(string $pdfBytes): array
    {
        $info = [
            'encrypted' => false,
            'filter' => null,
            'version' => null,
            'revision' => null,
            'length' => null,
            'permissions' => null,
            'permissionFlags' => [],
            'encryptMetadata' => null,
        ];

        if (preg_match('/\/Encrypt\b/s', $pdfBytes) !== 1) {
            return $info;
        }

        $info['encrypted'] = true;
        $dictionary = $this->extractPdfEncryptDictionary($pdfBytes);
        if ($dictionary === null) {
            return $info;
        }

        $info['filter'] = $this->extractPdfNameToken($dictionary, 'Filter');
        $info['version'] = $this->extractPdfIntegerToken($dictionary, 'V');
        $info['revision'] = $this->extractPdfIntegerToken($dictionary, 'R');
        $info['length'] = $this->extractPdfIntegerToken($dictionary, 'Length');
        $info['permissions'] = $this->extractPdfIntegerToken($dictionary, 'P');
        $info['encryptMetadata'] = $this->extractPdfBooleanToken($dictionary, 'EncryptMetadata');
        if ($info['permissions'] !== null) {
            $info['permissionFlags'] = $this->decodePdfPermissionFlags($info['permissions']);
        }

        return $info;
    }

    private function extractPdfEncryptDictionary(string $pdfBytes): ?string
    {
        if (preg_match('/\/Encrypt\s+(\d+)\s+(\d+)\s+R\b/s', $pdfBytes, $matches) === 1) {
            $objects = $this->pdfObjectBodiesByReference($pdfBytes);
            $key = $matches[1] . ' ' . $matches[2];

            return $objects[$key] ?? null;
        }

        $offset = 0;
        while (($position = strpos($pdfBytes, '/Encrypt', $offset)) !== false) {
            $cursor = $position + strlen('/Encrypt');
            if ($cursor < strlen($pdfBytes) && preg_match('/[A-Za-z0-9_.-]/', $pdfBytes[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < strlen($pdfBytes) && ctype_space($pdfBytes[$cursor])) {
                $cursor++;
            }
            if (substr($pdfBytes, $cursor, 2) !== '<<') {
                $offset = $cursor + 1;
                continue;
            }

            $parsed = $this->parsePdfDictionary($pdfBytes, $cursor);
            if ($parsed !== null) {
                return $parsed['value'];
            }

            $offset = $cursor + 2;
        }

        return null;
    }

    private function extractPdfNameToken(string $dictionary, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\/([A-Za-z0-9_.#+-]+)/s', $dictionary, $matches) !== 1) {
            return null;
        }

        return $this->decodePdfNameToken($matches[1]);
    }

    private function decodePdfNameToken(string $name): string
    {
        return preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            static fn (array $matches): string => chr(hexdec($matches[1])),
            $name
        ) ?? $name;
    }

    private function extractPdfIntegerToken(string $dictionary, string $name): ?int
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(-?\d+)\b/s', $dictionary, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function extractPdfNumberToken(string $dictionary, string $name): ?float
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([-+]?(?:\d+\.\d*|\.\d+|\d+)(?:[Ee][-+]?\d+)?)\b/s', $dictionary, $matches) !== 1) {
            return null;
        }

        return (float) $matches[1];
    }

    private function extractPdfBooleanToken(string $dictionary, string $name): ?bool
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(true|false)\b/si', $dictionary, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]) === 'true';
    }

    /**
     * @return array<string, bool>
     */
    private function decodePdfPermissionFlags(int $permissions): array
    {
        $bits = $permissions < 0 ? $permissions + 4294967296 : $permissions;

        return [
            'printLowQuality' => ($bits & 0x0004) !== 0,
            'modify' => ($bits & 0x0008) !== 0,
            'copy' => ($bits & 0x0010) !== 0,
            'annotate' => ($bits & 0x0020) !== 0,
            'fillForms' => ($bits & 0x0100) !== 0,
            'extractAccessibility' => ($bits & 0x0200) !== 0,
            'assemble' => ($bits & 0x0400) !== 0,
            'printHighQuality' => ($bits & 0x0800) !== 0,
        ];
    }

    private function extractPdfPageCount(string $pdfBytes): ?int
    {
        $objects = $this->pdfObjectBodies($pdfBytes);
        $pageTreeCounts = [];
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Pages\b/s', $body) !== 1) {
                continue;
            }
            if (preg_match('/\/Count\s+(\d+)\b/s', $body, $matches) !== 1) {
                continue;
            }

            $count = (int) $matches[1];
            if ($count > 0) {
                $pageTreeCounts[] = $count;
            }
        }
        if ($pageTreeCounts !== []) {
            return max($pageTreeCounts);
        }

        $pageObjects = 0;
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Page\b/s', $body) === 1) {
                $pageObjects++;
            }
        }

        return $pageObjects > 0 ? $pageObjects : null;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}>
     */
    private function extractPdfPageBoxes(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $pages = [];
        $visited = [];
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageBoxesFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                [],
                $visited,
                $pages,
                0
            );
        }

        if ($pages === []) {
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pages[] = $this->summarizePdfPageBox($body, $reference, []);
            }
        }

        foreach ($pages as $index => &$page) {
            $page['page'] = $index + 1;
        }
        unset($page);

        return $pages;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, mixed> $inherited
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}> $pages
     */
    private function collectPdfPageBoxesFromTree(
        array $objects,
        string $reference,
        array $inherited,
        array &$visited,
        array &$pages,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pages[] = $this->summarizePdfPageBox($body, $reference, $inherited);
            return;
        }

        $childInherited = $this->pdfPageTreeInheritedValues($body, $inherited);
        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageBoxesFromTree(
                $objects,
                $kidReference,
                $childInherited,
                $visited,
                $pages,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, mixed> $inherited
     * @return array<string, mixed>
     */
    private function pdfPageTreeInheritedValues(string $dictionary, array $inherited): array
    {
        foreach (['MediaBox', 'CropBox'] as $boxName) {
            $box = $this->extractPdfBoxValue($dictionary, $boxName);
            if ($box !== null) {
                $inherited[$boxName] = $box;
            }
        }

        $rotation = $this->extractPdfRotationValue($dictionary);
        if ($rotation !== null) {
            $inherited['Rotate'] = $rotation;
        }

        return $inherited;
    }

    /**
     * @param array<string, mixed> $inherited
     * @return array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}
     */
    private function summarizePdfPageBox(string $dictionary, ?string $reference, array $inherited): array
    {
        $inheritedNames = [];
        $boxValues = [];
        foreach (['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox'] as $boxName) {
            $box = $this->extractPdfBoxValue($dictionary, $boxName);
            if ($box === null && isset($inherited[$boxName]) && is_array($inherited[$boxName])) {
                $box = $inherited[$boxName];
                $inheritedNames[] = $this->pdfPageGeometryKey($boxName);
            }

            $boxValues[$boxName] = $box;
        }

        $rotation = $this->extractPdfRotationValue($dictionary);
        if ($rotation === null && isset($inherited['Rotate']) && is_int($inherited['Rotate'])) {
            $rotation = $inherited['Rotate'];
            $inheritedNames[] = 'rotation';
        }

        $inheritedNames = array_values(array_unique($inheritedNames));
        sort($inheritedNames);

        return [
            'page' => 0,
            'pageObject' => $reference === null ? null : $reference . ' R',
            'mediaBox' => $boxValues['MediaBox'],
            'cropBox' => $boxValues['CropBox'],
            'bleedBox' => $boxValues['BleedBox'],
            'trimBox' => $boxValues['TrimBox'],
            'artBox' => $boxValues['ArtBox'],
            'rotation' => $rotation,
            'inherited' => $inheritedNames,
        ];
    }

    /**
     * @return list<float>|null
     */
    private function extractPdfBoxValue(string $dictionary, string $name): ?array
    {
        $array = $this->extractPdfArrayValue($dictionary, $name);
        if ($array === null) {
            return null;
        }
        if (preg_match_all('/[-+]?(?:\d+\.\d*|\.\d+|\d+)(?:[Ee][-+]?\d+)?/', $array, $matches) < 4) {
            return null;
        }

        $box = [];
        foreach (array_slice($matches[0], 0, 4) as $number) {
            $box[] = (float) $number;
        }

        return $box;
    }

    /**
     * @return list<float>|null
     */
    private function extractPdfNumberArrayValues(string $dictionary, string $name): ?array
    {
        $array = $this->extractPdfArrayValue($dictionary, $name);
        if ($array === null) {
            return null;
        }
        if (preg_match_all('/[-+]?(?:\d+\.\d*|\.\d+|\d+)(?:[Ee][-+]?\d+)?/', $array, $matches) < 1) {
            return null;
        }

        $numbers = [];
        foreach (array_slice($matches[0], 0, 128) as $number) {
            $numbers[] = (float) $number;
        }

        return $numbers;
    }

    private function extractPdfRotationValue(string $dictionary): ?int
    {
        $rotation = $this->extractPdfIntegerToken($dictionary, 'Rotate');
        if ($rotation === null) {
            return null;
        }

        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return $rotation;
    }

    private function pdfPageGeometryKey(string $pdfName): string
    {
        return match ($pdfName) {
            'MediaBox' => 'mediaBox',
            'CropBox' => 'cropBox',
            'BleedBox' => 'bleedBox',
            'TrimBox' => 'trimBox',
            'ArtBox' => 'artBox',
            default => $pdfName,
        };
    }

    /**
     * @param list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}> $pageBoxes
     * @return array<int, int>
     */
    private function summarizePdfPageRotations(array $pageBoxes): array
    {
        $rotations = [];
        foreach ($pageBoxes as $pageBox) {
            if ($pageBox['rotation'] === null) {
                continue;
            }

            $rotations[$pageBox['page']] = $pageBox['rotation'];
        }

        return $rotations;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, boxColorInfoObject:string|null, boxColorInfo:list<array{box:string, color:list<float>|null, width:float|null, style:string|null}>, separationInfoObject:string|null, separationPages:list<string>, separationDeviceColorant:string|null, separationColorSpace:string|null, presStepsObject:string|null, presStepsSubtype:string|null, presStepsNext:list<string>}>
     */
    private function extractPdfPageProductionMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $metadata = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageProductionMetadataFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $metadata,
                $pageNumber,
                0
            );
        }

        if ($metadata === []) {
            uksort($objects, fn (string $a, string $b): int => $this->pdfReferenceSortKey($a . ' R') <=> $this->pdfReferenceSortKey($b . ' R'));
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $summary = $this->summarizePdfPageProductionMetadata($body, $reference, $objects, $pageNumber);
                if ($this->pdfPageProductionMetadataHasValues($summary)) {
                    $metadata[] = $summary;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, boxColorInfoObject:string|null, boxColorInfo:list<array{box:string, color:list<float>|null, width:float|null, style:string|null}>, separationInfoObject:string|null, separationPages:list<string>, separationDeviceColorant:string|null, separationColorSpace:string|null, presStepsObject:string|null, presStepsSubtype:string|null, presStepsNext:list<string>}> $metadata
     */
    private function collectPdfPageProductionMetadataFromTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$metadata,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $summary = $this->summarizePdfPageProductionMetadata($body, $reference, $objects, $pageNumber);
            if ($this->pdfPageProductionMetadataHasValues($summary)) {
                $metadata[] = $summary;
            }

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageProductionMetadataFromTree(
                $objects,
                $kidReference,
                $visited,
                $metadata,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, boxColorInfoObject:string|null, boxColorInfo:list<array{box:string, color:list<float>|null, width:float|null, style:string|null}>, separationInfoObject:string|null, separationPages:list<string>, separationDeviceColorant:string|null, separationColorSpace:string|null, presStepsObject:string|null, presStepsSubtype:string|null, presStepsNext:list<string>}
     */
    private function summarizePdfPageProductionMetadata(string $dictionary, string $reference, array $objects, int $pageNumber): array
    {
        $boxColorInfo = $this->resolvePdfDictionaryValue($this->extractPdfValueForName($dictionary, 'BoxColorInfo'), $objects);
        $separationInfo = $this->resolvePdfDictionaryValue($this->extractPdfValueForName($dictionary, 'SeparationInfo'), $objects);
        $presSteps = $this->resolvePdfDictionaryValue($this->extractPdfValueForName($dictionary, 'PresSteps'), $objects);
        $separationDictionary = $separationInfo['dictionary'];
        $presStepsDictionary = $presSteps['dictionary'];

        return [
            'page' => $pageNumber,
            'pageObject' => $reference . ' R',
            'boxColorInfoObject' => $boxColorInfo['object'],
            'boxColorInfo' => $boxColorInfo['dictionary'] === null
                ? []
                : $this->extractPdfBoxColorInfoEntries($boxColorInfo['dictionary'], $objects),
            'separationInfoObject' => $separationInfo['object'],
            'separationPages' => $separationDictionary === null
                ? []
                : $this->collectPdfReferencesFromValue($this->extractPdfValueForName($separationDictionary, 'Pages'), $objects),
            'separationDeviceColorant' => $separationDictionary === null
                ? null
                : $this->extractPdfStringOrNameValue($separationDictionary, 'DeviceColorant'),
            'separationColorSpace' => $separationDictionary === null
                ? null
                : $this->extractPdfColorSpaceNameValue($separationDictionary, 'ColorSpace', $objects),
            'presStepsObject' => $presSteps['object'],
            'presStepsSubtype' => $presStepsDictionary === null ? null : $this->extractPdfNameToken($presStepsDictionary, 'S'),
            'presStepsNext' => $presStepsDictionary === null
                ? []
                : $this->collectPdfReferencesFromValue($this->extractPdfValueForName($presStepsDictionary, 'Next'), $objects),
        ];
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return array{dictionary:string|null, object:string|null}
     */
    private function resolvePdfDictionaryValue(?array $value, array $objects): array
    {
        if ($value === null) {
            return ['dictionary' => null, 'object' => null];
        }

        if ($value['kind'] === 'dictionary') {
            return ['dictionary' => $value['value'], 'object' => 'inline'];
        }

        if ($value['kind'] === 'reference') {
            return [
                'dictionary' => $objects[$this->pdfReferenceKey($value['value'])] ?? null,
                'object' => $value['value'],
            ];
        }

        return ['dictionary' => null, 'object' => null];
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{box:string, color:list<float>|null, width:float|null, style:string|null}>
     */
    private function extractPdfBoxColorInfoEntries(string $dictionary, array $objects): array
    {
        $entries = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            if ($entry['key'] === 'Type') {
                continue;
            }

            $boxInfo = $this->resolvePdfDictionaryValue($entry['value'], $objects);
            if ($boxInfo['dictionary'] === null) {
                continue;
            }

            $entries[] = [
                'box' => $entry['key'],
                'color' => $this->extractPdfNumberArrayValues($boxInfo['dictionary'], 'C'),
                'width' => $this->extractPdfNumberToken($boxInfo['dictionary'], 'W'),
                'style' => $this->extractPdfNameToken($boxInfo['dictionary'], 'S'),
            ];
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($a['box'], $b['box']));

        return $entries;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function pdfPageProductionMetadataHasValues(array $metadata): bool
    {
        return ($metadata['boxColorInfo'] ?? []) !== []
            || ($metadata['separationInfoObject'] ?? null) !== null
            || ($metadata['presStepsObject'] ?? null) !== null;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}>
     */
    private function extractPdfPageDisplayMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $metadata = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageDisplayMetadataFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $metadata,
                $pageNumber,
                0
            );
        }

        if ($metadata === []) {
            uksort($objects, fn (string $a, string $b): int => $this->pdfReferenceSortKey($a . ' R') <=> $this->pdfReferenceSortKey($b . ' R'));
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $summary = $this->summarizePdfPageDisplayMetadata($body, $reference, $objects, $pageNumber);
                if ($this->pdfPageDisplayMetadataHasValues($summary)) {
                    $metadata[] = $summary;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}> $metadata
     */
    private function collectPdfPageDisplayMetadataFromTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$metadata,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $summary = $this->summarizePdfPageDisplayMetadata($body, $reference, $objects, $pageNumber);
            if ($this->pdfPageDisplayMetadataHasValues($summary)) {
                $metadata[] = $summary;
            }

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageDisplayMetadataFromTree(
                $objects,
                $kidReference,
                $visited,
                $metadata,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}
     */
    private function summarizePdfPageDisplayMetadata(string $dictionary, ?string $reference, array $objects, int $pageNumber): array
    {
        $group = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'Group', $objects);

        return [
            'page' => $pageNumber,
            'pageObject' => $reference === null ? null : $reference . ' R',
            'userUnit' => $this->extractPdfNumberToken($dictionary, 'UserUnit'),
            'tabOrder' => $this->extractPdfNameToken($dictionary, 'Tabs'),
            'groupSubtype' => $group === null ? null : $this->extractPdfNameToken($group, 'S'),
            'groupColorSpace' => $group === null ? null : $this->extractPdfColorSpaceNameValue($group, 'CS', $objects),
            'groupIsolated' => $group === null ? null : $this->extractPdfBooleanToken($group, 'I'),
            'groupKnockout' => $group === null ? null : $this->extractPdfBooleanToken($group, 'K'),
            'thumbnailObject' => $this->extractPdfReferenceToken($dictionary, 'Thumb'),
            'lastModified' => $this->extractPdfStringOrNameValue($dictionary, 'LastModified'),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function pdfPageDisplayMetadataHasValues(array $metadata): bool
    {
        foreach (['userUnit', 'tabOrder', 'groupSubtype', 'groupColorSpace', 'groupIsolated', 'groupKnockout', 'thumbnailObject', 'lastModified'] as $key) {
            if (($metadata[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, userUnit:float|null, tabOrder:string|null, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, thumbnailObject:string|null, lastModified:string|null}> $metadata
     * @return array<string, int>
     */
    private function summarizePdfPageTabOrders(array $metadata): array
    {
        $orders = [];
        foreach ($metadata as $pageDisplay) {
            $tabOrder = $pageDisplay['tabOrder'] ?? null;
            if (!is_string($tabOrder) || $tabOrder === '') {
                continue;
            }

            $orders[$tabOrder] = ($orders[$tabOrder] ?? 0) + 1;
        }
        ksort($orders);

        return $orders;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}>
     */
    private function extractPdfPageViewports(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $viewports = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageViewportsFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $viewports,
                $pageNumber,
                0
            );
        }

        if ($viewports === []) {
            uksort($objects, fn (string $a, string $b): int => $this->pdfReferenceSortKey($a . ' R') <=> $this->pdfReferenceSortKey($b . ' R'));
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                foreach ($this->summarizePdfPageViewports($body, $reference, $objects) as $viewport) {
                    $viewport['page'] = $pageNumber;
                    $viewports[] = $viewport;
                }
            }
        }

        return $viewports;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}> $viewports
     */
    private function collectPdfPageViewportsFromTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$viewports,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            foreach ($this->summarizePdfPageViewports($body, $reference, $objects) as $viewport) {
                $viewport['page'] = $pageNumber;
                $viewports[] = $viewport;
            }

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageViewportsFromTree(
                $objects,
                $kidReference,
                $visited,
                $viewports,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}>
     */
    private function summarizePdfPageViewports(string $dictionary, ?string $pageReference, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'VP');
        if ($value === null) {
            return [];
        }

        $values = $value['kind'] === 'array' ? $this->pdfTopLevelArrayValues($value['value']) : [$value];
        $viewports = [];
        foreach ($values as $index => $viewportValue) {
            $sourceSuffix = $value['kind'] === 'array' ? 'VP[' . $index . ']' : 'VP';
            $viewport = $this->summarizePdfViewportValue(
                $viewportValue,
                $objects,
                $pageReference,
                ($pageReference === null ? 'page' : 'page:' . $pageReference . ' R') . '.' . $sourceSuffix
            );
            if ($viewport !== null) {
                $viewports[] = $viewport;
            }
        }

        return $viewports;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, viewportObject:string|null, source:string, name:string|null, bbox:list<float>|null, measureSubtype:string|null, scaleRatio:string|null, xUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, yUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, distanceUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, areaUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>, angleUnits:list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>}|null
     */
    private function summarizePdfViewportValue(array $value, array $objects, ?string $pageReference, string $source): ?array
    {
        $dictionary = null;
        $viewportObject = null;
        if ($value['kind'] === 'dictionary') {
            $dictionary = $value['value'];
        } elseif ($value['kind'] === 'reference') {
            $viewportObject = $value['value'];
            $dictionary = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        }

        if ($dictionary === null) {
            return null;
        }

        $measure = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'Measure', $objects);

        return [
            'page' => 0,
            'pageObject' => $pageReference === null ? null : $pageReference . ' R',
            'viewportObject' => $viewportObject,
            'source' => $source,
            'name' => $this->extractPdfStringOrNameValue($dictionary, 'Name'),
            'bbox' => $this->extractPdfNumberArrayToken($dictionary, 'BBox', 4),
            'measureSubtype' => $measure === null ? null : $this->extractPdfNameToken($measure, 'Subtype'),
            'scaleRatio' => $measure === null ? null : $this->extractPdfStringOrNameValue($measure, 'R'),
            'xUnits' => $measure === null ? [] : $this->extractPdfMeasureUnitFormats($measure, 'X', $objects),
            'yUnits' => $measure === null ? [] : $this->extractPdfMeasureUnitFormats($measure, 'Y', $objects),
            'distanceUnits' => $measure === null ? [] : $this->extractPdfMeasureUnitFormats($measure, 'D', $objects),
            'areaUnits' => $measure === null ? [] : $this->extractPdfMeasureUnitFormats($measure, 'A', $objects),
            'angleUnits' => $measure === null ? [] : $this->extractPdfMeasureUnitFormats($measure, 'T', $objects),
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}>
     */
    private function extractPdfMeasureUnitFormats(string $dictionary, string $name, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return [];
        }

        $values = $value['kind'] === 'array' ? $this->pdfTopLevelArrayValues($value['value']) : [$value];
        $units = [];
        foreach ($values as $unitValue) {
            $unit = $this->summarizePdfMeasureUnitFormatValue($unitValue, $objects);
            if ($unit !== null) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{unit:string|null, conversionFactor:float|null, fractionalDisplay:string|null}|null
     */
    private function summarizePdfMeasureUnitFormatValue(array $value, array $objects): ?array
    {
        $dictionary = null;
        if ($value['kind'] === 'dictionary') {
            $dictionary = $value['value'];
        } elseif ($value['kind'] === 'reference') {
            $dictionary = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        }

        if ($dictionary === null) {
            return null;
        }

        $unit = $this->extractPdfStringOrNameValue($dictionary, 'U');
        $conversionFactor = $this->extractPdfNumberToken($dictionary, 'C');
        $fractionalDisplay = $this->extractPdfStringOrNameValue($dictionary, 'F');
        if ($unit === null && $conversionFactor === null && $fractionalDisplay === null) {
            return null;
        }

        return [
            'unit' => $unit,
            'conversionFactor' => $conversionFactor,
            'fractionalDisplay' => $fractionalDisplay,
        ];
    }

    /**
     * @return list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>
     */
    private function extractPdfPageTimings(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $timings = [];
        $visited = [];
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageTimingsFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $timings,
                0
            );
        }

        if ($timings === []) {
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $timings[] = $this->summarizePdfPageTiming($body, $reference, $objects);
            }
        }

        foreach ($timings as $index => &$timing) {
            $timing['page'] = $index + 1;
        }
        unset($timing);

        return array_values(array_filter(
            $timings,
            fn (array $timing): bool => ($timing['duration'] ?? null) !== null || $this->pdfPageTimingHasTransition($timing)
        ));
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}> $timings
     */
    private function collectPdfPageTimingsFromTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$timings,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $timings[] = $this->summarizePdfPageTiming($body, $reference, $objects);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageTimingsFromTree(
                $objects,
                $kidReference,
                $visited,
                $timings,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, directionLabel:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}
     */
    private function summarizePdfPageTiming(string $dictionary, ?string $reference, array $objects): array
    {
        $transition = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'Trans', $objects);
        $direction = $transition === null ? null : $this->extractPdfTransitionDirection($transition);

        return [
            'page' => 0,
            'pageObject' => $reference === null ? null : $reference . ' R',
            'duration' => $this->extractPdfNumberToken($dictionary, 'Dur'),
            'transitionType' => $transition === null ? null : $this->extractPdfNameToken($transition, 'S'),
            'transitionDuration' => $transition === null ? null : $this->extractPdfNumberToken($transition, 'D'),
            'direction' => $direction,
            'directionLabel' => $direction === null ? null : $this->pdfTransitionDirectionLabel($direction),
            'dimension' => $transition === null ? null : $this->extractPdfNameToken($transition, 'Dm'),
            'motion' => $transition === null ? null : $this->extractPdfNameToken($transition, 'M'),
            'scale' => $transition === null ? null : $this->extractPdfNumberToken($transition, 'SS'),
            'background' => $transition === null ? null : $this->extractPdfBooleanToken($transition, 'B'),
        ];
    }

    private function extractPdfTransitionDirection(string $dictionary): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, 'Di');
        if ($value === null) {
            return null;
        }
        if ($value['kind'] === 'number') {
            return $this->normalizePdfNumberString($value['value']);
        }
        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $direction = trim($value['value']);

            return $direction === '' ? null : $direction;
        }

        return null;
    }

    private function pdfTransitionDirectionLabel(string $direction): ?string
    {
        $direction = trim($direction);
        if ($direction === '') {
            return null;
        }

        if (preg_match('/\A-?(?:\d+(?:\.\d*)?|\.\d+)\z/', $direction) === 1) {
            $degrees = $this->normalizePdfNumberString($direction);

            return [
                '0' => 'left-to-right',
                '90' => 'bottom-to-top',
                '180' => 'right-to-left',
                '270' => 'top-to-bottom',
                '315' => 'top-left-to-bottom-right',
            ][$degrees] ?? 'degrees:' . $degrees;
        }

        $label = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $direction));
        $label = preg_replace('/[^a-z0-9]+/', '-', $label);
        if (!is_string($label)) {
            return null;
        }
        $label = trim($label, '-');

        return $label === '' ? null : $label;
    }

    private function normalizePdfNumberString(string $number): string
    {
        $number = trim($number);
        if ($number === '') {
            return '';
        }
        $float = (float) $number;
        if (fmod($float, 1.0) === 0.0) {
            return (string) (int) $float;
        }

        return rtrim(rtrim(sprintf('%.12F', $float), '0'), '.');
    }

    /**
     * @param array<string, mixed> $timing
     */
    private function pdfPageTimingHasTransition(array $timing): bool
    {
        foreach (['transitionType', 'transitionDuration', 'direction', 'dimension', 'motion', 'scale', 'background'] as $key) {
            if (($timing[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, directionLabel:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}> $pageTimings
     * @return array<string, int>
     */
    private function summarizePdfPageTransitionTypes(array $pageTimings): array
    {
        $types = [];
        foreach ($pageTimings as $pageTiming) {
            $type = $pageTiming['transitionType'] ?? null;
            if (!is_string($type) || $type === '') {
                continue;
            }

            $types[$type] = ($types[$type] ?? 0) + 1;
        }
        ksort($types);

        return $types;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, directionLabel:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}> $pageTimings
     * @return array<string, int>
     */
    private function summarizePdfPageTransitionDirectionLabels(array $pageTimings): array
    {
        $directions = [];
        foreach ($pageTimings as $pageTiming) {
            $direction = $pageTiming['directionLabel'] ?? null;
            if (!is_string($direction) || $direction === '') {
                continue;
            }

            $directions[$direction] = ($directions[$direction] ?? 0) + 1;
        }
        ksort($directions);

        return $directions;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}>
     */
    private function extractPdfPageContentStreams(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $streams = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageContentStreamsFromTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $streams,
                $pageNumber,
                0
            );
        }

        if ($streams === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageStreams = $this->summarizePdfPageContentStreams($body, $reference, null, $objects);
                foreach ($pageStreams as &$stream) {
                    $stream['page'] = $pageNumber;
                }
                unset($stream);
                array_push($streams, ...$pageStreams);
            }
        }

        return $streams;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}> $streams
     */
    private function collectPdfPageContentStreamsFromTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$streams,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageStreams = $this->summarizePdfPageContentStreams($body, $reference, $resources, $objects);
            foreach ($pageStreams as &$stream) {
                $stream['page'] = $pageNumber;
            }
            unset($stream);
            array_push($streams, ...$pageStreams);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageContentStreamsFromTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $streams,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}>
     */
    private function summarizePdfPageContentStreams(string $pageDictionary, string $pageReference, ?string $resources, array $objects): array
    {
        $value = $this->extractPdfValueForName($pageDictionary, 'Contents');
        if ($value === null) {
            return [];
        }

        $xobjectTypes = $resources === null ? [] : $this->pdfPageXObjectResourceTypes($resources, $objects);
        $contentValues = [];
        if ($value['kind'] === 'array') {
            foreach ($this->pdfTopLevelArrayValues($value['value']) as $index => $arrayValue) {
                $contentValues[] = [$arrayValue, 'Contents[' . $index . ']'];
            }
        } else {
            $contentValues[] = [$value, 'Contents'];
        }

        $streams = [];
        foreach ($contentValues as [$contentValue, $sourceSuffix]) {
            $stream = $this->summarizePdfPageContentStreamValue(
                $contentValue,
                $objects,
                $pageReference,
                'page:' . $pageReference . ' R.' . $sourceSuffix,
                $xobjectTypes
            );
            if ($stream !== null) {
                $streams[] = $stream;
            }
        }

        return $streams;
    }

    /**
     * @param array{kind:string, value:string, next?:int} $value
     * @param array<string, string> $objects
     * @param array<string, string> $xobjectTypes
     * @return array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}|null
     */
    private function summarizePdfPageContentStreamValue(array $value, array $objects, string $pageReference, string $source, array $xobjectTypes): ?array
    {
        $contentObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $contentDictionary = $objects[$reference] ?? null;
            $contentObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $contentDictionary = $value['value'];
        } else {
            return null;
        }

        if ($contentDictionary === null) {
            return null;
        }

        $filters = $this->extractPdfFilterNames($contentDictionary, $objects);
        $bytes = $this->extractPdfStreamBytes($contentDictionary);
        $summary = [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'contentObject' => $contentObject,
            'source' => $source,
            'filters' => $filters,
            'streamBytes' => $bytes === null ? null : strlen($bytes),
            'streamSha256' => null,
            'streamSkipped' => null,
            'textObjectCount' => 0,
            'imagePaintCount' => 0,
            'formPaintCount' => 0,
            'markedContentBeginCount' => 0,
            'markedContentEndCount' => 0,
            'mcidValues' => [],
            'propertyNames' => [],
            'resourceNames' => [],
        ];

        if ($bytes === null) {
            return $summary;
        }
        if ($filters !== []) {
            $summary['streamSkipped'] = 'filtered';

            return $summary;
        }
        if (strlen($bytes) > self::MAX_PAGE_CONTENT_STREAM_BYTES) {
            $summary['streamSkipped'] = 'too-large';

            return $summary;
        }

        $summary['streamSha256'] = hash('sha256', $bytes);
        $operators = $this->summarizePdfPageContentOperators($bytes, $xobjectTypes);
        $summary['textObjectCount'] = $operators['textObjectCount'];
        $summary['imagePaintCount'] = $operators['imagePaintCount'];
        $summary['formPaintCount'] = $operators['formPaintCount'];
        $summary['markedContentBeginCount'] = $operators['markedContentBeginCount'];
        $summary['markedContentEndCount'] = $operators['markedContentEndCount'];
        $summary['mcidValues'] = $operators['mcidValues'];
        $summary['propertyNames'] = $operators['propertyNames'];
        $summary['resourceNames'] = $operators['resourceNames'];

        return $summary;
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, string>
     */
    private function pdfPageXObjectResourceTypes(string $resources, array $objects): array
    {
        $xobjectDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'XObject', $objects);
        if ($xobjectDictionary === null) {
            return [];
        }

        $types = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($xobjectDictionary) as $entry) {
            $objectDictionary = null;
            if ($entry['value']['kind'] === 'reference') {
                $objectDictionary = $objects[$this->pdfReferenceKey($entry['value']['value'])] ?? null;
            } elseif ($entry['value']['kind'] === 'dictionary') {
                $objectDictionary = $entry['value']['value'];
            }

            if ($objectDictionary === null) {
                continue;
            }
            $subtype = $this->extractPdfNameToken($objectDictionary, 'Subtype');
            if ($subtype !== null && $subtype !== '') {
                $types[$entry['key']] = $subtype;
            }
        }

        return $types;
    }

    /**
     * @param array<string, string> $xobjectTypes
     * @return array{textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}
     */
    private function summarizePdfPageContentOperators(string $bytes, array $xobjectTypes): array
    {
        $summary = [
            'textObjectCount' => (int) preg_match_all('/(?<![A-Za-z0-9])BT(?![A-Za-z0-9])/', $bytes),
            'imagePaintCount' => 0,
            'formPaintCount' => 0,
            'markedContentBeginCount' => (int) preg_match_all('/(?<![A-Za-z0-9])(?:BMC|BDC)(?![A-Za-z0-9])/', $bytes),
            'markedContentEndCount' => (int) preg_match_all('/(?<![A-Za-z0-9])EMC(?![A-Za-z0-9])/', $bytes),
            'mcidValues' => [],
            'propertyNames' => [],
            'resourceNames' => [],
        ];

        if (preg_match_all('/\/([A-Za-z0-9_.#+-]+)\s+Do\b/', $bytes, $matches) >= 1) {
            foreach ($matches[1] as $name) {
                $resourceName = $this->decodePdfNameToken($name);
                if ($resourceName === '') {
                    continue;
                }
                $summary['resourceNames'][] = $resourceName;
                $subtype = $xobjectTypes[$resourceName] ?? null;
                if ($subtype === 'Image') {
                    $summary['imagePaintCount']++;
                } elseif ($subtype === 'Form') {
                    $summary['formPaintCount']++;
                }
            }
        }
        if (preg_match_all('/\/MCID\s+(-?\d+)/', $bytes, $matches) >= 1) {
            foreach ($matches[1] as $mcid) {
                $summary['mcidValues'][] = (int) $mcid;
            }
        }
        if (preg_match_all('/\/([A-Za-z0-9_.#+-]+)(?:\s+(?:<<.*?>>|\/[A-Za-z0-9_.#+-]+))?\s+(?:BMC|BDC)\b/sU', $bytes, $matches) >= 1) {
            foreach ($matches[1] as $name) {
                $propertyName = $this->decodePdfNameToken($name);
                if ($propertyName !== '') {
                    $summary['propertyNames'][] = $propertyName;
                }
            }
        }

        $summary['mcidValues'] = array_values(array_unique($summary['mcidValues']));
        sort($summary['mcidValues']);
        $summary['propertyNames'] = $this->uniqueStrings($summary['propertyNames']);
        $summary['resourceNames'] = $this->uniqueStrings($summary['resourceNames']);

        return $summary;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, textObjectCount:int, imagePaintCount:int, formPaintCount:int, markedContentBeginCount:int, markedContentEndCount:int, mcidValues:list<int>, propertyNames:list<string>, resourceNames:list<string>}> $streams
     * @return array<string, int>
     */
    private function summarizePdfPageContentResourceUsage(array $streams): array
    {
        $usage = [];
        foreach ($streams as $stream) {
            foreach ($stream['resourceNames'] as $resourceName) {
                if (!is_string($resourceName) || $resourceName === '') {
                    continue;
                }

                $usage[$resourceName] = ($usage[$resourceName] ?? 0) + 1;
            }
        }

        ksort($usage);

        return $usage;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>
     */
    private function extractPdfMarkedContentArtifacts(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $artifacts = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfMarkedContentArtifactsFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $artifacts,
                $pageNumber,
                0
            );
        }

        if ($artifacts === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageArtifacts = $this->summarizePdfPageMarkedContentArtifacts($body, $reference, $objects);
                foreach ($pageArtifacts as &$artifact) {
                    $artifact['page'] = $pageNumber;
                }
                unset($artifact);
                array_push($artifacts, ...$pageArtifacts);
            }
        }

        $artifacts = array_values($artifacts);
        usort(
            $artifacts,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['source'],
                $a['type'] ?? '',
                $a['subtype'] ?? '',
            ] <=> [
                $b['page'],
                $b['source'],
                $b['type'] ?? '',
                $b['subtype'] ?? '',
            ]
        );

        return $artifacts;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}> $artifacts
     */
    private function collectPdfMarkedContentArtifactsFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$artifacts,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        if ($this->extractPdfNameToken($body, 'Type') === 'Page') {
            $pageNumber++;
            $pageArtifacts = $this->summarizePdfPageMarkedContentArtifacts($body, $reference, $objects);
            foreach ($pageArtifacts as &$artifact) {
                $artifact['page'] = $pageNumber;
            }
            unset($artifact);
            array_push($artifacts, ...$pageArtifacts);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfMarkedContentArtifactsFromPageTree(
                $objects,
                $kidReference,
                $visited,
                $artifacts,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>
     */
    private function summarizePdfPageMarkedContentArtifacts(string $pageDictionary, string $pageReference, array $objects): array
    {
        $value = $this->extractPdfValueForName($pageDictionary, 'Contents');
        if ($value === null) {
            return [];
        }

        $contentValues = [];
        if ($value['kind'] === 'array') {
            foreach ($this->pdfTopLevelArrayValues($value['value']) as $index => $arrayValue) {
                $contentValues[] = [$arrayValue, 'Contents[' . $index . ']'];
            }
        } else {
            $contentValues[] = [$value, 'Contents'];
        }

        $artifacts = [];
        foreach ($contentValues as [$contentValue, $sourceSuffix]) {
            array_push(
                $artifacts,
                ...$this->summarizePdfMarkedContentArtifactsFromStreamValue(
                    $contentValue,
                    $objects,
                    $pageReference,
                    'page:' . $pageReference . ' R.' . $sourceSuffix
                )
            );
        }

        return $artifacts;
    }

    /**
     * @param array{kind:string, value:string, next?:int} $value
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>
     */
    private function summarizePdfMarkedContentArtifactsFromStreamValue(array $value, array $objects, string $pageReference, string $source): array
    {
        $contentObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $contentDictionary = $objects[$reference] ?? null;
            $contentObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $contentDictionary = $value['value'];
        } else {
            return [];
        }

        if ($contentDictionary === null || $this->extractPdfFilterNames($contentDictionary, $objects) !== []) {
            return [];
        }

        $bytes = $this->extractPdfStreamBytes($contentDictionary);
        if ($bytes === null || strlen($bytes) > self::MAX_PAGE_CONTENT_STREAM_BYTES) {
            return [];
        }

        return $this->extractPdfMarkedContentArtifactsFromStream(
            $bytes,
            $source,
            $pageReference . ' R',
            $contentObject
        );
    }

    /**
     * @return list<array{page:int, pageObject:string|null, contentObject:string|null, source:string, operator:string, type:string|null, subtype:string|null, bbox:list<float>|null, attached:list<string>, mcid:int|null, propertyName:string|null}>
     */
    private function extractPdfMarkedContentArtifactsFromStream(string $bytes, string $source, string $pageObject, ?string $contentObject): array
    {
        if (preg_match_all('/\/Artifact\b/s', $bytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $artifacts = [];
        $artifactIndex = 0;
        $length = strlen($bytes);
        foreach ($matches[0] as $match) {
            if (count($artifacts) >= 256) {
                break;
            }

            $cursor = $match[1] + strlen($match[0]);
            while ($cursor < $length && ctype_space($bytes[$cursor])) {
                $cursor++;
            }

            $property = null;
            $operator = null;
            if (preg_match('/\A(BMC|BDC)(?![A-Za-z0-9])/', substr($bytes, $cursor), $operatorMatch) === 1) {
                $operator = $operatorMatch[1];
            } else {
                $property = $this->parsePdfValueAt($bytes, $cursor);
                if ($property === null) {
                    continue;
                }

                $cursor = $property['next'];
                while ($cursor < $length && ctype_space($bytes[$cursor])) {
                    $cursor++;
                }
                if (preg_match('/\A(BMC|BDC)(?![A-Za-z0-9])/', substr($bytes, $cursor), $operatorMatch) !== 1) {
                    continue;
                }
                $operator = $operatorMatch[1];
            }

            $artifact = [
                'page' => 0,
                'pageObject' => $pageObject,
                'contentObject' => $contentObject,
                'source' => $source . '.Artifact[' . $artifactIndex . ']',
                'operator' => $operator,
                'type' => null,
                'subtype' => null,
                'bbox' => null,
                'attached' => [],
                'mcid' => null,
                'propertyName' => null,
            ];

            if ($property !== null && $property['kind'] === 'dictionary') {
                $artifact['type'] = $this->extractPdfNameToken($property['value'], 'Type');
                $artifact['subtype'] = $this->extractPdfNameToken($property['value'], 'Subtype');
                $artifact['bbox'] = $this->extractPdfNumberArrayValues($property['value'], 'BBox');
                $artifact['attached'] = $this->extractPdfArtifactAttachedValues($property['value']);
                $artifact['mcid'] = $this->extractPdfIntegerToken($property['value'], 'MCID');
            } elseif ($property !== null && $property['kind'] === 'name') {
                $artifact['propertyName'] = $property['value'];
            }

            $artifacts[] = $artifact;
            $artifactIndex++;
        }

        return $artifacts;
    }

    /**
     * @return list<string>
     */
    private function extractPdfArtifactAttachedValues(string $dictionary): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'Attached');
        if ($value === null) {
            return [];
        }

        if ($value['kind'] === 'name') {
            return $value['value'] === '' ? [] : [$value['value']];
        }

        if ($value['kind'] !== 'array') {
            return [];
        }

        $attached = [];
        foreach ($this->pdfTopLevelArrayValues($value['value']) as $arrayValue) {
            if ($arrayValue['kind'] === 'name' && $arrayValue['value'] !== '') {
                $attached[] = $arrayValue['value'];
            }
        }

        return $this->uniqueStrings($attached);
    }

    /**
     * @return list<array{page:int, pageObject:string|null, resourceSourceObject:string|null, inherited:bool, categories:list<string>, fontNames:list<string>, xobjectNames:list<string>, colorSpaceNames:list<string>, graphicsStateNames:list<string>, propertyNames:list<string>}>
     */
    private function extractPdfPageResourceSources(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $sources = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfPageResourceSourcesFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                null,
                $visited,
                $sources,
                $pageNumber,
                0
            );
        }

        if ($sources === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $summary = $this->summarizePdfPageResourceSource(
                    $body,
                    $reference,
                    null,
                    null,
                    $objects,
                    $pageNumber
                );
                if ($summary !== null) {
                    $sources[] = $summary;
                }
            }
        }

        usort(
            $sources,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['resourceSourceObject'] ?? '',
                $a['pageObject'] ?? '',
            ] <=> [
                $b['page'],
                $b['resourceSourceObject'] ?? '',
                $b['pageObject'] ?? '',
            ]
        );

        return $sources;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, resourceSourceObject:string|null, inherited:bool, categories:list<string>, fontNames:list<string>, xobjectNames:list<string>, colorSpaceNames:list<string>, graphicsStateNames:list<string>, propertyNames:list<string>}> $sources
     */
    private function collectPdfPageResourceSourcesFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        ?string $inheritedResourceSourceObject,
        array &$visited,
        array &$sources,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resourceSourceObject = $ownResources === null ? $inheritedResourceSourceObject : $reference . ' R';
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $summary = $this->summarizePdfPageResourceSource(
                $body,
                $reference,
                $ownResources === null ? $resources : null,
                $ownResources === null ? $resourceSourceObject : null,
                $objects,
                $pageNumber
            );
            if ($summary !== null) {
                $sources[] = $summary;
            }

            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfPageResourceSourcesFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $resourceSourceObject,
                $visited,
                $sources,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceSourceObject:string|null, inherited:bool, categories:list<string>, fontNames:list<string>, xobjectNames:list<string>, colorSpaceNames:list<string>, graphicsStateNames:list<string>, propertyNames:list<string>}|null
     */
    private function summarizePdfPageResourceSource(
        string $pageDictionary,
        string $pageReference,
        ?string $inheritedResources,
        ?string $inheritedResourceSourceObject,
        array $objects,
        int $pageNumber
    ): ?array {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return null;
        }

        $fontNames = $this->pdfResourceDictionaryNames($resources, 'Font', $objects);
        $xobjectNames = $this->pdfResourceDictionaryNames($resources, 'XObject', $objects);
        $colorSpaceNames = $this->pdfResourceDictionaryNames($resources, 'ColorSpace', $objects);
        $graphicsStateNames = $this->pdfResourceDictionaryNames($resources, 'ExtGState', $objects);
        $propertyNames = $this->pdfResourceDictionaryNames($resources, 'Properties', $objects);

        $categories = [];
        if ($fontNames !== []) {
            $categories[] = 'Font';
        }
        if ($xobjectNames !== []) {
            $categories[] = 'XObject';
        }
        if ($colorSpaceNames !== []) {
            $categories[] = 'ColorSpace';
        }
        if ($graphicsStateNames !== []) {
            $categories[] = 'ExtGState';
        }
        if ($propertyNames !== []) {
            $categories[] = 'Properties';
        }
        sort($categories);

        if ($categories === []) {
            return null;
        }

        $inherited = $ownResources === null;

        return [
            'page' => $pageNumber,
            'pageObject' => $pageReference . ' R',
            'resourceSourceObject' => $inherited ? $inheritedResourceSourceObject : $pageReference . ' R',
            'inherited' => $inherited,
            'categories' => $categories,
            'fontNames' => $fontNames,
            'xobjectNames' => $xobjectNames,
            'colorSpaceNames' => $colorSpaceNames,
            'graphicsStateNames' => $graphicsStateNames,
            'propertyNames' => $propertyNames,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function pdfResourceDictionaryNames(string $resources, string $name, array $objects): array
    {
        $dictionary = $this->extractPdfDictionaryOrReferenceValue($resources, $name, $objects);
        if ($dictionary === null) {
            return [];
        }

        $names = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            if ($entry['key'] !== '') {
                $names[] = $entry['key'];
            }
        }
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>
     */
    private function extractPdfFonts(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $fonts = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfFontsFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $fonts,
                $pageNumber,
                0
            );
        }

        if ($fonts === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageFonts = $this->summarizePdfPageFonts($body, $reference, null, $objects);
                foreach ($pageFonts as &$font) {
                    $font['page'] = $pageNumber;
                }
                unset($font);
                array_push($fonts, ...$pageFonts);
            }
        }

        $fonts = array_values($fonts);
        usort(
            $fonts,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['resourceName'],
                $a['fontObject'] ?? '',
                $a['baseFont'] ?? '',
            ] <=> [
                $b['page'],
                $b['resourceName'],
                $b['fontObject'] ?? '',
                $b['baseFont'] ?? '',
            ]
        );

        return $fonts;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}> $fonts
     */
    private function collectPdfFontsFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$fonts,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageFonts = $this->summarizePdfPageFonts($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageFonts as &$font) {
                $font['page'] = $pageNumber;
            }
            unset($font);
            array_push($fonts, ...$pageFonts);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfFontsFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $fonts,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>
     */
    private function summarizePdfPageFonts(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $fontDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'Font', $objects);
        if ($fontDictionary === null) {
            return [];
        }

        $fonts = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($fontDictionary) as $entry) {
            $font = $this->summarizePdfFontResource(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null
            );
            if ($font !== null) {
                $fonts[] = $font;
            }
        }

        return $fonts;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}|null
     */
    private function summarizePdfFontResource(string $resourceName, array $value, array $objects, string $pageReference, bool $inherited): ?array
    {
        $fontObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $fontDictionary = $objects[$reference] ?? null;
            $fontObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $fontDictionary = $value['value'];
        } else {
            return null;
        }

        if ($fontDictionary === null) {
            return null;
        }

        $descendantFonts = [];
        $descriptorDictionary = null;
        $descriptorReference = $this->extractPdfReferenceToken($fontDictionary, 'FontDescriptor');
        if ($descriptorReference !== null) {
            $descriptorDictionary = $objects[$this->pdfReferenceKey($descriptorReference)] ?? null;
        } else {
            $descriptorDictionary = $this->extractPdfDictionaryOrReferenceValue($fontDictionary, 'FontDescriptor', $objects);
            if ($descriptorDictionary !== null) {
                $descriptorReference = 'inline';
            }
        }

        foreach ($this->extractPdfReferenceArray($fontDictionary, 'DescendantFonts') as $descendantReference) {
            $descendantFonts[] = $descendantReference . ' R';
            if ($descriptorDictionary !== null || !isset($objects[$descendantReference])) {
                continue;
            }

            $descriptorReference = $this->extractPdfReferenceToken($objects[$descendantReference], 'FontDescriptor');
            if ($descriptorReference !== null) {
                $descriptorDictionary = $objects[$this->pdfReferenceKey($descriptorReference)] ?? null;
                continue;
            }

            $descriptorDictionary = $this->extractPdfDictionaryOrReferenceValue($objects[$descendantReference], 'FontDescriptor', $objects);
            if ($descriptorDictionary !== null) {
                $descriptorReference = 'inline';
            }
        }

        sort($descendantFonts);
        $embedded = $descriptorDictionary === null
            ? $this->emptyPdfFontFileSummary()
            : $this->extractPdfFontFileSummary($descriptorDictionary, $objects);

        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'resourceName' => $resourceName,
            'fontObject' => $fontObject,
            'inherited' => $inherited,
            'subtype' => $this->extractPdfNameToken($fontDictionary, 'Subtype'),
            'baseFont' => $this->extractPdfNameToken($fontDictionary, 'BaseFont'),
            'encoding' => $this->extractPdfNameToken($fontDictionary, 'Encoding'),
            'toUnicode' => $this->extractPdfReferenceToken($fontDictionary, 'ToUnicode'),
            'descendantFonts' => $descendantFonts,
            'descriptor' => $descriptorReference,
            'descriptorFontName' => $descriptorDictionary === null ? null : $this->extractPdfNameToken($descriptorDictionary, 'FontName'),
            'descriptorFontFamily' => $descriptorDictionary === null ? null : $this->extractPdfStringOrNameValue($descriptorDictionary, 'FontFamily'),
            'descriptorFlags' => $descriptorDictionary === null ? null : $this->extractPdfIntegerToken($descriptorDictionary, 'Flags'),
            'descriptorItalicAngle' => $descriptorDictionary === null ? null : $this->extractPdfNumberToken($descriptorDictionary, 'ItalicAngle'),
            'descriptorFontWeight' => $descriptorDictionary === null ? null : $this->extractPdfIntegerToken($descriptorDictionary, 'FontWeight'),
            'embedded' => $embedded['reference'] !== null,
            'embeddedFile' => $embedded['reference'],
            'embeddedFileKind' => $embedded['kind'],
            'embeddedFileSubtype' => $embedded['subtype'],
            'embeddedFileBytes' => $embedded['bytes'],
            'embeddedFileSha256' => $embedded['sha256'],
            'embeddedFileSkipped' => $embedded['skipped'],
        ];
    }

    /**
     * @return array{reference:string|null, kind:string|null, subtype:string|null, bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function emptyPdfFontFileSummary(): array
    {
        return [
            'reference' => null,
            'kind' => null,
            'subtype' => null,
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array{reference:string|null, kind:string|null, subtype:string|null, bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function extractPdfFontFileSummary(string $descriptorDictionary, array $objects): array
    {
        foreach (['FontFile', 'FontFile2', 'FontFile3'] as $kind) {
            $value = $this->extractPdfValueForName($descriptorDictionary, $kind);
            if ($value === null) {
                continue;
            }

            $streamObject = null;
            $reference = null;
            if ($value['kind'] === 'reference') {
                $referenceKey = $this->pdfReferenceKey($value['value']);
                $streamObject = $objects[$referenceKey] ?? null;
                $reference = $streamObject === null ? null : $referenceKey . ' R';
            } elseif ($value['kind'] === 'dictionary') {
                $streamObject = $value['value'];
                $reference = 'inline';
            }

            if ($streamObject === null) {
                continue;
            }

            $summary = [
                'reference' => $reference,
                'kind' => $kind,
                'subtype' => $this->extractPdfNameToken($streamObject, 'Subtype'),
                'bytes' => null,
                'sha256' => null,
                'skipped' => null,
            ];
            $streamBytes = $this->extractPdfStreamBytes($streamObject);
            if ($streamBytes === null) {
                return $summary;
            }

            $summary['bytes'] = strlen($streamBytes);
            if (preg_match('/\/Filter\b/s', $streamObject) === 1) {
                $summary['skipped'] = 'filtered';

                return $summary;
            }
            if (strlen($streamBytes) > self::MAX_EMBEDDED_FONT_STREAM_BYTES) {
                $summary['skipped'] = 'too-large';

                return $summary;
            }

            $summary['sha256'] = hash('sha256', $streamBytes);

            return $summary;
        }

        return $this->emptyPdfFontFileSummary();
    }

    /**
     * @param list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}> $fonts
     * @return array<string, int>
     */
    private function summarizePdfFontSubtypes(array $fonts): array
    {
        $subtypes = [];
        foreach ($fonts as $font) {
            $subtype = $font['subtype'] ?? null;
            if (!is_string($subtype) || $subtype === '') {
                continue;
            }

            $subtypes[$subtype] = ($subtypes[$subtype] ?? 0) + 1;
        }

        ksort($subtypes);

        return $subtypes;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function extractPdfImages(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $images = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfImagesFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $images,
                $pageNumber,
                0
            );
        }

        if ($images === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageImages = $this->summarizePdfPageImages($body, $reference, null, $objects);
                foreach ($pageImages as &$image) {
                    $image['page'] = $pageNumber;
                }
                unset($image);
                array_push($images, ...$pageImages);
            }
        }

        $images = array_values($images);
        usort(
            $images,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['resourceName'],
                $a['imageObject'] ?? '',
            ] <=> [
                $b['page'],
                $b['resourceName'],
                $b['imageObject'] ?? '',
            ]
        );

        return $images;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $images
     */
    private function collectPdfImagesFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$images,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageImages = $this->summarizePdfPageImages($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageImages as &$image) {
                $image['page'] = $pageNumber;
            }
            unset($image);
            array_push($images, ...$pageImages);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfImagesFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $images,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function summarizePdfPageImages(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $xobjectDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'XObject', $objects);
        if ($xobjectDictionary === null) {
            return [];
        }

        $images = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($xobjectDictionary) as $entry) {
            $image = $this->summarizePdfImageXObject(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null
            );
            if ($image !== null) {
                $images[] = $image;
            }
        }

        return $images;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}|null
     */
    private function summarizePdfImageXObject(string $resourceName, array $value, array $objects, string $pageReference, bool $inherited): ?array
    {
        $imageObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $imageDictionary = $objects[$reference] ?? null;
            $imageObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $imageDictionary = $value['value'];
        } else {
            return null;
        }

        if ($imageDictionary === null || $this->extractPdfNameToken($imageDictionary, 'Subtype') !== 'Image') {
            return null;
        }

        $stream = $this->summarizePdfImageStream($imageDictionary);

        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'resourceName' => $resourceName,
            'imageObject' => $imageObject,
            'inherited' => $inherited,
            'width' => $this->extractPdfIntegerToken($imageDictionary, 'Width'),
            'height' => $this->extractPdfIntegerToken($imageDictionary, 'Height'),
            'bitsPerComponent' => $this->extractPdfIntegerToken($imageDictionary, 'BitsPerComponent'),
            'colorSpace' => $this->extractPdfColorSpaceValue($imageDictionary, $objects),
            'filters' => $this->extractPdfFilterNames($imageDictionary, $objects),
            'interpolate' => $this->extractPdfBooleanToken($imageDictionary, 'Interpolate'),
            'imageMask' => $this->extractPdfBooleanToken($imageDictionary, 'ImageMask'),
            'softMask' => $this->extractPdfReferenceToken($imageDictionary, 'SMask') ?? $this->extractPdfNameToken($imageDictionary, 'SMask'),
            'streamBytes' => $stream['bytes'],
            'streamSha256' => $stream['sha256'],
            'streamSkipped' => $stream['skipped'],
        ];
    }

    /**
     * @return array{bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfImageStream(string $imageDictionary): array
    {
        $summary = [
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];
        $bytes = $this->extractPdfStreamBytes($imageDictionary);
        if ($bytes === null) {
            return $summary;
        }

        $summary['bytes'] = strlen($bytes);
        if (strlen($bytes) > self::MAX_IMAGE_STREAM_BYTES) {
            $summary['skipped'] = 'too-large';

            return $summary;
        }

        $summary['sha256'] = hash('sha256', $bytes);

        return $summary;
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfColorSpaceValue(string $dictionary, array $objects): ?string
    {
        return $this->extractPdfColorSpaceNameValue($dictionary, 'ColorSpace', $objects);
    }

    /**
     * @param array<string, string> $objects
     */
    private function extractPdfColorSpaceNameValue(string $dictionary, string $name, array $objects): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return null;
        }

        return $this->summarizePdfColorSpaceValue($value, $objects, 0);
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     */
    private function summarizePdfColorSpaceValue(array $value, array $objects, int $depth): ?string
    {
        if ($value['kind'] === 'name' || $value['kind'] === 'literal' || $value['kind'] === 'hex') {
            $colorSpace = trim($value['value']);

            return $colorSpace === '' ? null : $colorSpace;
        }

        if ($value['kind'] === 'array') {
            if (preg_match('/\[\s*\/([A-Za-z0-9_.#+-]+)/s', $value['value'], $matches) === 1) {
                return $this->decodePdfNameToken($matches[1]);
            }

            return null;
        }

        if ($value['kind'] === 'reference') {
            if ($depth >= 2) {
                return $value['value'];
            }
            $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
            if ($body === '') {
                return $value['value'];
            }
            $resolved = $this->parsePdfValueAt($body, 0);

            return $resolved === null ? $value['value'] : $this->summarizePdfColorSpaceValue($resolved, $objects, $depth + 1);
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function extractPdfFilterNames(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'Filter');
        if ($value === null) {
            return [];
        }

        $filters = $this->summarizePdfFilterValue($value, $objects, 0);
        $filters = array_values(array_unique(array_filter($filters, static fn (string $filter): bool => $filter !== '')));
        sort($filters);

        return $filters;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function summarizePdfFilterValue(array $value, array $objects, int $depth): array
    {
        if ($value['kind'] === 'name' || $value['kind'] === 'literal' || $value['kind'] === 'hex') {
            $filter = trim($value['value']);

            return $filter === '' ? [] : [$filter];
        }

        if ($value['kind'] === 'array') {
            $filters = [];
            if (preg_match_all('/\/([A-Za-z0-9_.#+-]+)/s', $value['value'], $matches) >= 1) {
                foreach ($matches[1] as $filter) {
                    $filters[] = $this->decodePdfNameToken($filter);
                }
            }

            return $filters;
        }

        if ($value['kind'] === 'reference') {
            if ($depth >= 2) {
                return [$value['value']];
            }
            $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
            if ($body === '') {
                return [$value['value']];
            }
            $resolved = $this->parsePdfValueAt($body, 0);

            return $resolved === null ? [$value['value']] : $this->summarizePdfFilterValue($resolved, $objects, $depth + 1);
        }

        return [];
    }

    /**
     * @param list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $images
     * @return array<string, int>
     */
    private function summarizePdfImageColorSpaces(array $images): array
    {
        $colorSpaces = [];
        foreach ($images as $image) {
            $colorSpace = $image['colorSpace'] ?? null;
            if (!is_string($colorSpace) || $colorSpace === '') {
                continue;
            }

            $colorSpaces[$colorSpace] = ($colorSpaces[$colorSpace] ?? 0) + 1;
        }

        ksort($colorSpaces);

        return $colorSpaces;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $images
     * @return array<string, int>
     */
    private function summarizePdfImageFilters(array $images): array
    {
        $filters = [];
        foreach ($images as $image) {
            foreach ($image['filters'] as $filter) {
                if (!is_string($filter) || $filter === '') {
                    continue;
                }

                $filters[$filter] = ($filters[$filter] ?? 0) + 1;
            }
        }

        ksort($filters);

        return $filters;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}>
     */
    private function extractPdfColorSpaces(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $colorSpaces = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfColorSpacesFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $colorSpaces,
                $pageNumber,
                0
            );
        }

        if ($colorSpaces === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageColorSpaces = $this->summarizePdfPageColorSpaces($body, $reference, null, $objects);
                foreach ($pageColorSpaces as &$colorSpace) {
                    $colorSpace['page'] = $pageNumber;
                }
                unset($colorSpace);
                array_push($colorSpaces, ...$pageColorSpaces);
            }
        }

        $colorSpaces = array_values($colorSpaces);
        usort(
            $colorSpaces,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['resourceName'],
                $a['colorSpaceObject'] ?? '',
                $a['family'] ?? '',
            ] <=> [
                $b['page'],
                $b['resourceName'],
                $b['colorSpaceObject'] ?? '',
                $b['family'] ?? '',
            ]
        );

        return $colorSpaces;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}> $colorSpaces
     */
    private function collectPdfColorSpacesFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$colorSpaces,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageColorSpaces = $this->summarizePdfPageColorSpaces($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageColorSpaces as &$colorSpace) {
                $colorSpace['page'] = $pageNumber;
            }
            unset($colorSpace);
            array_push($colorSpaces, ...$pageColorSpaces);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfColorSpacesFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $colorSpaces,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}>
     */
    private function summarizePdfPageColorSpaces(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $colorSpaceDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'ColorSpace', $objects);
        if ($colorSpaceDictionary === null) {
            return [];
        }

        $colorSpaces = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($colorSpaceDictionary) as $entry) {
            $colorSpace = $this->summarizePdfColorSpaceResource(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null,
                null,
                0
            );
            if ($colorSpace !== null) {
                $colorSpaces[] = $colorSpace;
            }
        }

        return $colorSpaces;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}|null
     */
    private function summarizePdfColorSpaceResource(
        string $resourceName,
        array $value,
        array $objects,
        string $pageReference,
        bool $inherited,
        ?string $colorSpaceObject,
        int $depth
    ): ?array {
        if ($depth > 8) {
            return null;
        }

        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $body = trim($objects[$reference] ?? '');
            if ($body === '') {
                return null;
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved === null) {
                return null;
            }

            return $this->summarizePdfColorSpaceResource(
                $resourceName,
                $resolved,
                $objects,
                $pageReference,
                $inherited,
                $reference . ' R',
                $depth + 1
            );
        }

        $summary = $this->emptyPdfColorSpaceSummary($resourceName, $pageReference, $inherited, $colorSpaceObject);
        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $family = trim($value['value']);
            if ($family === '') {
                return null;
            }

            $summary['family'] = $family;

            return $summary;
        }

        if ($value['kind'] !== 'array') {
            return null;
        }

        return $this->summarizePdfColorSpaceArray($summary, $value['value'], $objects);
    }

    /**
     * @return array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}
     */
    private function emptyPdfColorSpaceSummary(string $resourceName, string $pageReference, bool $inherited, ?string $colorSpaceObject): array
    {
        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'resourceName' => $resourceName,
            'colorSpaceObject' => $colorSpaceObject,
            'inherited' => $inherited,
            'family' => null,
            'colorantNames' => [],
            'alternateColorSpace' => null,
            'profileComponents' => null,
            'profileAlternate' => null,
            'profileBytes' => null,
            'profileSha256' => null,
            'profileSkipped' => null,
            'tintTransform' => null,
        ];
    }

    /**
     * @param array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null} $summary
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceName:string, colorSpaceObject:string|null, inherited:bool, family:string|null, colorantNames:list<string>, alternateColorSpace:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null, tintTransform:string|null}|null
     */
    private function summarizePdfColorSpaceArray(array $summary, string $array, array $objects): ?array
    {
        $values = $this->pdfTopLevelArrayValues($array);
        if ($values === [] || !in_array($values[0]['kind'], ['name', 'literal', 'hex'], true)) {
            return null;
        }

        $family = trim($values[0]['value']);
        if ($family === '') {
            return null;
        }

        $summary['family'] = $family;
        if ($family === 'ICCBased') {
            $profile = $this->pdfProfileSummaryForColorSpaceValue($values[1] ?? null, $objects);
            $summary['profileComponents'] = $profile['components'];
            $summary['profileAlternate'] = $profile['alternate'];
            $summary['profileBytes'] = $profile['bytes'];
            $summary['profileSha256'] = $profile['sha256'];
            $summary['profileSkipped'] = $profile['skipped'];
            $summary['alternateColorSpace'] = $profile['alternate'];
        } elseif ($family === 'Separation') {
            $summary['colorantNames'] = $this->pdfColorantNamesFromValue($values[1] ?? null, $objects);
            $summary['alternateColorSpace'] = isset($values[2]) ? $this->summarizePdfColorSpaceValue($values[2], $objects, 0) : null;
            $summary['tintTransform'] = $this->pdfColorSpaceTintTransformName($values[3] ?? null);
        } elseif ($family === 'DeviceN') {
            $summary['colorantNames'] = $this->pdfColorantNamesFromValue($values[1] ?? null, $objects);
            $summary['alternateColorSpace'] = isset($values[2]) ? $this->summarizePdfColorSpaceValue($values[2], $objects, 0) : null;
            $summary['tintTransform'] = $this->pdfColorSpaceTintTransformName($values[3] ?? null);
        } elseif (isset($values[1])) {
            $summary['alternateColorSpace'] = $this->summarizePdfColorSpaceValue($values[1], $objects, 0);
        }

        return $summary;
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function pdfColorantNamesFromValue(?array $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $names = $this->collectPdfNamesFromValue($value, $objects);
        $names = array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
        sort($names);

        return $names;
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     * @param array<string, string> $objects
     * @return array{components:int|null, alternate:string|null, bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function pdfProfileSummaryForColorSpaceValue(?array $value, array $objects): array
    {
        if ($value === null) {
            return $this->summarizePdfOutputProfile(null);
        }

        if ($value['kind'] === 'reference') {
            return $this->summarizePdfOutputProfile($objects[$this->pdfReferenceKey($value['value'])] ?? null);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfOutputProfile($value['value']);
        }

        return $this->summarizePdfOutputProfile(null);
    }

    /**
     * @param array{kind:string, value:string, next?:int}|null $value
     */
    private function pdfColorSpaceTintTransformName(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value['kind'] === 'reference') {
            return $value['value'];
        }
        if ($value['kind'] === 'dictionary') {
            return 'inline';
        }
        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $name = trim($value['value']);

            return $name === '' ? null : $name;
        }

        return null;
    }

    /**
     * @param list<array{family:string|null}> $colorSpaces
     * @return array<string, int>
     */
    private function summarizePdfColorSpaceFamilies(array $colorSpaces): array
    {
        $families = [];
        foreach ($colorSpaces as $colorSpace) {
            $family = $colorSpace['family'] ?? null;
            if (!is_string($family) || $family === '') {
                continue;
            }

            $families[$family] = ($families[$family] ?? 0) + 1;
        }

        ksort($families);

        return $families;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function extractPdfFormXObjects(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $forms = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfFormXObjectsFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $forms,
                $pageNumber,
                0
            );
        }

        if ($forms === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageForms = $this->summarizePdfPageFormXObjects($body, $reference, null, $objects);
                foreach ($pageForms as &$form) {
                    $form['page'] = $pageNumber;
                }
                unset($form);
                array_push($forms, ...$pageForms);
            }
        }

        $forms = array_values($forms);
        usort(
            $forms,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['resourceName'],
                $a['formObject'] ?? '',
            ] <=> [
                $b['page'],
                $b['resourceName'],
                $b['formObject'] ?? '',
            ]
        );

        return $forms;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $forms
     */
    private function collectPdfFormXObjectsFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$forms,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageForms = $this->summarizePdfPageFormXObjects($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageForms as &$form) {
                $form['page'] = $pageNumber;
            }
            unset($form);
            array_push($forms, ...$pageForms);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfFormXObjectsFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $forms,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function summarizePdfPageFormXObjects(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $xobjectDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'XObject', $objects);
        if ($xobjectDictionary === null) {
            return [];
        }

        $forms = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($xobjectDictionary) as $entry) {
            $form = $this->summarizePdfFormXObject(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null
            );
            if ($form !== null) {
                $forms[] = $form;
            }
        }

        return $forms;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}|null
     */
    private function summarizePdfFormXObject(string $resourceName, array $value, array $objects, string $pageReference, bool $inherited): ?array
    {
        $formObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $formDictionary = $objects[$reference] ?? null;
            $formObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $formDictionary = $value['value'];
        } else {
            return null;
        }

        if ($formDictionary === null || $this->extractPdfNameToken($formDictionary, 'Subtype') !== 'Form') {
            return null;
        }

        $group = $this->extractPdfDictionaryOrReferenceValue($formDictionary, 'Group', $objects);
        $stream = $this->summarizePdfFormXObjectStream($formDictionary);

        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'resourceName' => $resourceName,
            'formObject' => $formObject,
            'inherited' => $inherited,
            'bbox' => $this->extractPdfNumberArrayToken($formDictionary, 'BBox', 4),
            'matrix' => $this->extractPdfNumberArrayToken($formDictionary, 'Matrix', 6),
            'resourcesPresent' => $this->extractPdfValueForName($formDictionary, 'Resources') !== null,
            'groupSubtype' => $group === null ? null : $this->extractPdfNameToken($group, 'S'),
            'groupColorSpace' => $group === null ? null : $this->extractPdfColorSpaceNameValue($group, 'CS', $objects),
            'groupIsolated' => $group === null ? null : $this->extractPdfBooleanToken($group, 'I'),
            'groupKnockout' => $group === null ? null : $this->extractPdfBooleanToken($group, 'K'),
            'filters' => $this->extractPdfFilterNames($formDictionary, $objects),
            'streamBytes' => $stream['bytes'],
            'streamSha256' => $stream['sha256'],
            'streamSkipped' => $stream['skipped'],
        ];
    }

    /**
     * @return array{bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfFormXObjectStream(string $formDictionary): array
    {
        $summary = [
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];
        $bytes = $this->extractPdfStreamBytes($formDictionary);
        if ($bytes === null) {
            return $summary;
        }

        $summary['bytes'] = strlen($bytes);
        if (strlen($bytes) > self::MAX_FORM_XOBJECT_STREAM_BYTES) {
            $summary['skipped'] = 'too-large';

            return $summary;
        }

        $summary['sha256'] = hash('sha256', $bytes);

        return $summary;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $forms
     * @return array<string, int>
     */
    private function summarizePdfFormXObjectFilters(array $forms): array
    {
        $filters = [];
        foreach ($forms as $form) {
            foreach ($form['filters'] as $filter) {
                if (!is_string($filter) || $filter === '') {
                    continue;
                }

                $filters[$filter] = ($filters[$filter] ?? 0) + 1;
            }
        }

        ksort($filters);

        return $filters;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}>
     */
    private function extractPdfGraphicsStates(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $states = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfGraphicsStatesFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                null,
                $visited,
                $states,
                $pageNumber,
                0
            );
        }

        if ($states === []) {
            $pageNumber = 0;
            foreach ($objects as $reference => $body) {
                if (preg_match('/\/Type\s*\/Page\b/s', $body) !== 1) {
                    continue;
                }

                $pageNumber++;
                $pageStates = $this->summarizePdfPageGraphicsStates($body, $reference, null, $objects);
                foreach ($pageStates as &$state) {
                    $state['page'] = $pageNumber;
                }
                unset($state);
                array_push($states, ...$pageStates);
            }
        }

        $states = array_values($states);
        usort(
            $states,
            static fn (array $a, array $b): int => [
                $a['page'],
                $a['resourceName'],
                $a['graphicsStateObject'] ?? '',
            ] <=> [
                $b['page'],
                $b['resourceName'],
                $b['graphicsStateObject'] ?? '',
            ]
        );

        return $states;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}> $states
     */
    private function collectPdfGraphicsStatesFromPageTree(
        array $objects,
        string $reference,
        ?string $inheritedResources,
        array &$visited,
        array &$states,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($body, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $pageStates = $this->summarizePdfPageGraphicsStates($body, $reference, $ownResources === null ? $inheritedResources : null, $objects);
            foreach ($pageStates as &$state) {
                $state['page'] = $pageNumber;
            }
            unset($state);
            array_push($states, ...$pageStates);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfGraphicsStatesFromPageTree(
                $objects,
                $kidReference,
                $resources,
                $visited,
                $states,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}>
     */
    private function summarizePdfPageGraphicsStates(string $pageDictionary, string $pageReference, ?string $inheritedResources, array $objects): array
    {
        $ownResources = $this->extractPdfDictionaryOrReferenceValue($pageDictionary, 'Resources', $objects);
        $resources = $ownResources ?? $inheritedResources;
        if ($resources === null) {
            return [];
        }

        $stateDictionary = $this->extractPdfDictionaryOrReferenceValue($resources, 'ExtGState', $objects);
        if ($stateDictionary === null) {
            return [];
        }

        $states = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($stateDictionary) as $entry) {
            $state = $this->summarizePdfGraphicsStateResource(
                $entry['key'],
                $entry['value'],
                $objects,
                $pageReference,
                $ownResources === null
            );
            if ($state !== null) {
                $states[] = $state;
            }
        }

        return $states;
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}|null
     */
    private function summarizePdfGraphicsStateResource(string $resourceName, array $value, array $objects, string $pageReference, bool $inherited): ?array
    {
        $graphicsStateObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $graphicsStateDictionary = $objects[$reference] ?? null;
            $graphicsStateObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $graphicsStateDictionary = $value['value'];
        } else {
            return null;
        }

        if ($graphicsStateDictionary === null) {
            return null;
        }

        return [
            'page' => 0,
            'pageObject' => $pageReference . ' R',
            'resourceName' => $resourceName,
            'graphicsStateObject' => $graphicsStateObject,
            'inherited' => $inherited,
            'strokingAlpha' => $this->extractPdfNumberToken($graphicsStateDictionary, 'CA'),
            'nonstrokingAlpha' => $this->extractPdfNumberToken($graphicsStateDictionary, 'ca'),
            'blendModes' => $this->extractPdfGraphicsStateBlendModes($graphicsStateDictionary, $objects),
            'overprintStroking' => $this->extractPdfExactBooleanValue($graphicsStateDictionary, 'OP'),
            'overprintNonstroking' => $this->extractPdfExactBooleanValue($graphicsStateDictionary, 'op'),
            'overprintMode' => $this->extractPdfIntegerToken($graphicsStateDictionary, 'OPM'),
            'alphaSource' => $this->extractPdfExactBooleanValue($graphicsStateDictionary, 'AIS'),
            'textKnockout' => $this->extractPdfExactBooleanValue($graphicsStateDictionary, 'TK'),
            'softMask' => $this->extractPdfGraphicsStateSoftMask($graphicsStateDictionary),
        ];
    }

    private function extractPdfExactBooleanValue(string $dictionary, string $name): ?bool
    {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null || $value['kind'] !== 'keyword') {
            return null;
        }
        if ($value['value'] === 'true') {
            return true;
        }
        if ($value['value'] === 'false') {
            return false;
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function extractPdfGraphicsStateBlendModes(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'BM');
        if ($value === null) {
            return [];
        }

        return $this->pdfValueToNameList($value, $objects);
    }

    private function extractPdfGraphicsStateSoftMask(string $dictionary): ?string
    {
        $value = $this->extractPdfValueForName($dictionary, 'SMask');
        if ($value === null) {
            return null;
        }

        if ($value['kind'] === 'reference') {
            return $value['value'];
        }
        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $softMask = trim($value['value']);

            return $softMask === '' ? null : $softMask;
        }
        if ($value['kind'] === 'dictionary') {
            return 'inline';
        }

        return null;
    }

    /**
     * @param list<array{page:int, pageObject:string|null, resourceName:string, graphicsStateObject:string|null, inherited:bool, strokingAlpha:float|null, nonstrokingAlpha:float|null, blendModes:list<string>, overprintStroking:bool|null, overprintNonstroking:bool|null, overprintMode:int|null, alphaSource:bool|null, textKnockout:bool|null, softMask:string|null}> $states
     * @return array<string, int>
     */
    private function summarizePdfGraphicsStateBlendModes(array $states): array
    {
        $blendModes = [];
        foreach ($states as $state) {
            foreach ($state['blendModes'] as $blendMode) {
                if (!is_string($blendMode) || $blendMode === '') {
                    continue;
                }

                $blendModes[$blendMode] = ($blendModes[$blendMode] ?? 0) + 1;
            }
        }

        ksort($blendModes);

        return $blendModes;
    }

    /**
     * @return list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>
     */
    private function extractPdfPageLabels(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/PageLabels')) {
            return [];
        }

        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $pageLabels = $this->extractPdfDictionaryOrReferenceValue($catalog, 'PageLabels', $objects);
        if ($pageLabels === null) {
            return [];
        }

        $labels = [];
        $visited = [];
        $this->collectPdfPageLabels($labels, 'catalog.PageLabels', $pageLabels, $objects, $visited, 0);

        $labels = array_values($labels);
        usort($labels, static fn (array $a, array $b): int => [$a['pageIndex'], $a['source']] <=> [$b['pageIndex'], $b['source']]);

        return $labels;
    }

    /**
     * @param array<string, array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}> $labels
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfPageLabels(
        array &$labels,
        string $source,
        string $dictionary,
        array $objects,
        array &$visited,
        int $depth
    ): void {
        if ($depth > 16) {
            return;
        }

        $array = $this->extractPdfArrayValue($dictionary, 'Nums');
        if ($array !== null) {
            $cursor = str_starts_with($array, '[') ? 1 : 0;
            $length = strlen($array);
            if (str_ends_with($array, ']')) {
                $length--;
            }

            while ($cursor < $length) {
                $pageIndex = $this->parsePdfValueAt($array, $cursor);
                if ($pageIndex === null) {
                    $cursor++;
                    continue;
                }
                $cursor = $pageIndex['next'];

                $labelValue = $this->parsePdfValueAt($array, $cursor);
                if ($labelValue === null) {
                    break;
                }
                $cursor = $labelValue['next'];

                if ($pageIndex['kind'] !== 'number') {
                    continue;
                }

                $index = (int) $pageIndex['value'];
                if ($index < 0) {
                    continue;
                }

                $labelDictionary = $this->pdfPageLabelDictionaryForValue($labelValue, $objects);
                if ($labelDictionary === null) {
                    continue;
                }

                $label = $this->summarizePdfPageLabel($index, $labelDictionary, $source);
                $labels[$label['pageIndex'] . "\0" . $label['source']] = $label;
            }
        }

        foreach ($this->extractPdfReferenceArray($dictionary, 'Kids') as $kidReference) {
            if (isset($visited[$kidReference]) || !isset($objects[$kidReference])) {
                continue;
            }

            $visited[$kidReference] = true;
            $this->collectPdfPageLabels(
                $labels,
                $source . '.Kids.' . $kidReference . ' R',
                $objects[$kidReference],
                $objects,
                $visited,
                $depth + 1
            );
        }
    }

    /**
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     */
    private function pdfPageLabelDictionaryForValue(array $value, array $objects): ?string
    {
        if ($value['kind'] === 'dictionary') {
            return $value['value'];
        }

        if ($value['kind'] !== 'reference') {
            return null;
        }

        $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        if ($body === null) {
            return null;
        }

        $body = trim($body);
        if (str_starts_with($body, '<<')) {
            return $body;
        }

        $parsed = $this->parsePdfValueAt($body, 0);

        return $parsed !== null && $parsed['kind'] === 'dictionary' ? $parsed['value'] : null;
    }

    /**
     * @return array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}
     */
    private function summarizePdfPageLabel(int $pageIndex, string $dictionary, string $source): array
    {
        $style = $this->extractPdfNameToken($dictionary, 'S');
        $prefixValues = $this->extractPdfNamedStrings($dictionary, 'P');
        $prefix = $prefixValues === [] ? ($this->extractPdfNameToken($dictionary, 'P') ?? '') : $prefixValues[0];
        $start = $this->extractPdfIntegerToken($dictionary, 'St') ?? 1;
        if ($start < 1) {
            $start = 1;
        }

        return [
            'pageIndex' => $pageIndex,
            'pageNumber' => $pageIndex + 1,
            'style' => $style,
            'styleLabel' => $this->pdfPageLabelStyleName($style),
            'prefix' => $prefix,
            'start' => $start,
            'firstLabel' => $prefix . $this->formatPdfPageLabelNumber($start, $style),
            'source' => $source,
        ];
    }

    private function pdfPageLabelStyleName(?string $style): ?string
    {
        return match ($style) {
            'D' => 'decimal',
            'R' => 'upper-roman',
            'r' => 'lower-roman',
            'A' => 'upper-alpha',
            'a' => 'lower-alpha',
            null => null,
            default => 'unknown',
        };
    }

    private function formatPdfPageLabelNumber(int $number, ?string $style): string
    {
        if ($style === null) {
            return '';
        }

        return match ($style) {
            'D' => (string) $number,
            'R' => $this->formatPdfRomanPageLabel($number),
            'r' => strtolower($this->formatPdfRomanPageLabel($number)),
            'A' => $this->formatPdfAlphabeticPageLabel($number),
            'a' => strtolower($this->formatPdfAlphabeticPageLabel($number)),
            default => (string) $number,
        };
    }

    private function formatPdfRomanPageLabel(int $number): string
    {
        if ($number < 1) {
            return '';
        }

        $roman = '';
        foreach ([1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $value => $token) {
            while ($number >= $value) {
                $roman .= $token;
                $number -= $value;
            }
        }

        return $roman;
    }

    private function formatPdfAlphabeticPageLabel(int $number): string
    {
        if ($number < 1) {
            return '';
        }

        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(65 + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    private function pdfReferenceKey(string $reference): string
    {
        if (preg_match('/\A(\d+)\s+(\d+)\s+R\z/', trim($reference), $matches) !== 1) {
            return $reference;
        }

        return $matches[1] . ' ' . $matches[2];
    }

    /**
     * @return list<string>
     */
    private function extractPdfOutlineTitles(string $pdfBytes): array
    {
        $titles = [];
        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (!str_contains($body, '/Title')) {
                continue;
            }
            if (preg_match('/\/(?:Parent|Dest|A|Next|Prev)\b/s', $body) !== 1) {
                continue;
            }

            foreach ($this->extractPdfTitleStrings($body) as $title) {
                $titles[] = $title;
            }
        }

        return $titles;
    }

    /**
     * @return list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}>
     */
    private function extractPdfOutlines(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $outlines = [];
        $visited = [];
        $outlinesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Outlines');
        if ($outlinesReference !== null) {
            $root = $objects[$this->pdfReferenceKey($outlinesReference)] ?? null;
            if ($root !== null) {
                $first = $this->extractPdfReferenceToken($root, 'First');
                if ($first !== null) {
                    $this->collectPdfOutlinesFromSiblingChain(
                        $objects,
                        $this->pdfReferenceKey($first),
                        $outlines,
                        $visited,
                        0
                    );
                }
            }
        }

        if ($outlines === []) {
            foreach ($objects as $reference => $body) {
                if (!str_contains($body, '/Title')) {
                    continue;
                }
                if (preg_match('/\/(?:Parent|Dest|A|Next|Prev|First|Last)\b/s', $body) !== 1) {
                    continue;
                }

                $summary = $this->summarizePdfOutlineItem($reference, $body, $objects);
                if ($summary !== null) {
                    $outlines[] = $summary;
                }
            }
            usort($outlines, fn (array $a, array $b): int => $this->pdfReferenceSortKey($a['object']) <=> $this->pdfReferenceSortKey($b['object']));
        }

        return $outlines;
    }

    /**
     * @return list<array{object:string, title:string, color:list<float>|null, flags:int, flagNames:list<string>}>
     */
    private function extractPdfOutlineDisplayMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $metadata = [];
        $visited = [];
        $outlinesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Outlines');
        if ($outlinesReference !== null) {
            $root = $objects[$this->pdfReferenceKey($outlinesReference)] ?? null;
            if ($root !== null) {
                $first = $this->extractPdfReferenceToken($root, 'First');
                if ($first !== null) {
                    $this->collectPdfOutlineDisplayMetadataFromSiblingChain(
                        $objects,
                        $this->pdfReferenceKey($first),
                        $metadata,
                        $visited,
                        0
                    );
                }
            }
        }

        if ($metadata === []) {
            foreach ($objects as $reference => $body) {
                if (!str_contains($body, '/Title')) {
                    continue;
                }
                if (preg_match('/\/(?:Parent|Dest|A|Next|Prev|First|Last)\b/s', $body) !== 1) {
                    continue;
                }

                $summary = $this->summarizePdfOutlineDisplayMetadata($reference, $body);
                if ($summary !== null) {
                    $metadata[] = $summary;
                }
            }
            usort($metadata, fn (array $a, array $b): int => $this->pdfReferenceSortKey($a['object']) <=> $this->pdfReferenceSortKey($b['object']));
        }

        return $metadata;
    }

    /**
     * @param array<string, string> $objects
     * @param list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}> $outlines
     * @param array<string, bool> $visited
     */
    private function collectPdfOutlinesFromSiblingChain(
        array $objects,
        string $reference,
        array &$outlines,
        array &$visited,
        int $depth
    ): void {
        $cursor = $reference;
        $siblingCount = 0;
        while ($depth <= 32 && $siblingCount < 256 && isset($objects[$cursor]) && !isset($visited[$cursor])) {
            $visited[$cursor] = true;
            $summary = $this->summarizePdfOutlineItem($cursor, $objects[$cursor], $objects);
            if ($summary !== null) {
                $outlines[] = $summary;
            }

            $first = $this->extractPdfReferenceToken($objects[$cursor], 'First');
            if ($first !== null) {
                $this->collectPdfOutlinesFromSiblingChain(
                    $objects,
                    $this->pdfReferenceKey($first),
                    $outlines,
                    $visited,
                    $depth + 1
                );
            }

            $next = $this->extractPdfReferenceToken($objects[$cursor], 'Next');
            if ($next === null) {
                return;
            }
            $cursor = $this->pdfReferenceKey($next);
            $siblingCount++;
        }
    }

    /**
     * @param array<string, string> $objects
     * @param list<array{object:string, title:string, color:list<float>|null, flags:int, flagNames:list<string>}> $metadata
     * @param array<string, bool> $visited
     */
    private function collectPdfOutlineDisplayMetadataFromSiblingChain(
        array $objects,
        string $reference,
        array &$metadata,
        array &$visited,
        int $depth
    ): void {
        $cursor = $reference;
        $siblingCount = 0;
        while ($depth <= 32 && $siblingCount < 256 && isset($objects[$cursor]) && !isset($visited[$cursor])) {
            $visited[$cursor] = true;
            $summary = $this->summarizePdfOutlineDisplayMetadata($cursor, $objects[$cursor]);
            if ($summary !== null) {
                $metadata[] = $summary;
            }

            $first = $this->extractPdfReferenceToken($objects[$cursor], 'First');
            if ($first !== null) {
                $this->collectPdfOutlineDisplayMetadataFromSiblingChain(
                    $objects,
                    $this->pdfReferenceKey($first),
                    $metadata,
                    $visited,
                    $depth + 1
                );
            }

            $next = $this->extractPdfReferenceToken($objects[$cursor], 'Next');
            if ($next === null) {
                return;
            }
            $cursor = $this->pdfReferenceKey($next);
            $siblingCount++;
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}|null
     */
    private function summarizePdfOutlineItem(string $reference, string $dictionary, array $objects): ?array
    {
        $title = null;
        foreach ($this->extractPdfNamedStrings($dictionary, 'Title') as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $title = $trimmed;
                break;
            }
        }
        if ($title === null) {
            return null;
        }

        $count = $this->extractPdfIntegerToken($dictionary, 'Count');
        $destination = $this->extractPdfOutlineDestination($dictionary, $objects);
        $action = $this->extractPdfOutlineAction($dictionary, $objects);

        return [
            'object' => $reference . ' R',
            'title' => $title,
            'parent' => $this->extractPdfReferenceToken($dictionary, 'Parent'),
            'prev' => $this->extractPdfReferenceToken($dictionary, 'Prev'),
            'next' => $this->extractPdfReferenceToken($dictionary, 'Next'),
            'first' => $this->extractPdfReferenceToken($dictionary, 'First'),
            'last' => $this->extractPdfReferenceToken($dictionary, 'Last'),
            'count' => $count,
            'open' => $count === null ? null : $count >= 0,
            'destPageObject' => $destination['pageObject'],
            'destFit' => $destination['fit'],
            'actionType' => $action['type'],
            'actionTarget' => $action['target'],
        ];
    }

    /**
     * @return array{object:string, title:string, color:list<float>|null, flags:int, flagNames:list<string>}|null
     */
    private function summarizePdfOutlineDisplayMetadata(string $reference, string $dictionary): ?array
    {
        $title = null;
        foreach ($this->extractPdfNamedStrings($dictionary, 'Title') as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $title = $trimmed;
                break;
            }
        }
        if ($title === null) {
            return null;
        }

        $color = $this->extractPdfNumberArrayToken($dictionary, 'C', 3);
        $flags = max(0, $this->extractPdfIntegerToken($dictionary, 'F') ?? 0);
        if ($color === null && $flags === 0) {
            return null;
        }

        return [
            'object' => $reference . ' R',
            'title' => $title,
            'color' => $color,
            'flags' => $flags,
            'flagNames' => $this->pdfOutlineDisplayFlagNames($flags),
        ];
    }

    /**
     * @return list<string>
     */
    private function pdfOutlineDisplayFlagNames(int $flags): array
    {
        $names = [];
        if (($flags & 1) !== 0) {
            $names[] = 'italic';
        }
        if (($flags & 2) !== 0) {
            $names[] = 'bold';
        }

        return $names;
    }

    /**
     * @param array<string, string> $objects
     * @return array{pageObject:string|null, fit:string|null}
     */
    private function extractPdfOutlineDestination(string $dictionary, array $objects): array
    {
        $destination = ['pageObject' => null, 'fit' => null];
        $value = $this->extractPdfValueForName($dictionary, 'Dest');
        if ($value === null) {
            return $destination;
        }

        if ($value['kind'] === 'array') {
            return $this->summarizePdfOutlineDestinationArray($value['value']);
        }
        if ($value['kind'] !== 'reference') {
            return $destination;
        }

        $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        if ($body === null) {
            return $destination;
        }

        $resolved = $this->parsePdfValueAt($body, 0);
        if ($resolved !== null && $resolved['kind'] === 'array') {
            return $this->summarizePdfOutlineDestinationArray($resolved['value']);
        }

        $nested = $this->extractPdfArrayOrReferenceValue($body, 'D', $objects);
        if ($nested !== null) {
            return $this->summarizePdfOutlineDestinationArray($nested);
        }

        return $destination;
    }

    /**
     * @return array{pageObject:string|null, fit:string|null}
     */
    private function summarizePdfOutlineDestinationArray(string $array): array
    {
        $pageObject = null;
        $fit = null;
        $cursor = str_starts_with($array, '[') ? 1 : 0;
        $length = strlen($array);
        if (str_ends_with($array, ']')) {
            $length--;
        }

        while ($cursor < $length) {
            $value = $this->parsePdfValueAt($array, $cursor);
            if ($value === null) {
                $cursor++;
                continue;
            }
            if ($pageObject === null && $value['kind'] === 'reference') {
                $pageObject = $value['value'];
            } elseif ($fit === null && $value['kind'] === 'name') {
                $fit = $value['value'];
            }
            if ($pageObject !== null && $fit !== null) {
                break;
            }

            $cursor = max($cursor + 1, min($length, $value['next']));
        }

        return [
            'pageObject' => $pageObject,
            'fit' => $fit,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array{type:string|null, target:string|null}
     */
    private function extractPdfOutlineAction(string $dictionary, array $objects): array
    {
        $action = ['type' => null, 'target' => null];
        $actionDictionary = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'A', $objects);
        if ($actionDictionary === null) {
            return $action;
        }

        $action['type'] = $this->extractPdfNameToken($actionDictionary, 'S');
        if ($action['type'] === 'URI') {
            $action['target'] = $this->extractPdfStringOrNameValue($actionDictionary, 'URI');
        } elseif ($action['type'] === 'Launch') {
            $action['target'] = $this->extractPdfStringOrNameValue($actionDictionary, 'F');
        } elseif ($action['type'] === 'Named') {
            $action['target'] = $this->extractPdfStringOrNameValue($actionDictionary, 'N');
        } elseif ($action['type'] === 'GoTo') {
            $destination = $this->extractPdfArrayOrReferenceValue($actionDictionary, 'D', $objects);
            if ($destination !== null) {
                $summary = $this->summarizePdfOutlineDestinationArray($destination);
                $action['target'] = $summary['pageObject'];
            } else {
                $action['target'] = $this->extractPdfStringOrNameValue($actionDictionary, 'D');
            }
        }

        return $action;
    }

    /**
     * @return array<string, int>
     */
    private function extractPdfAnnotationTypes(string $pdfBytes): array
    {
        $counts = [];
        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (preg_match_all('/\/Subtype\s*\/([A-Za-z0-9_.-]+)/s', $body, $matches) < 1) {
                continue;
            }

            foreach ($matches[1] as $type) {
                if (!$this->isPdfAnnotationSubtype($type)) {
                    continue;
                }

                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    private function isPdfAnnotationSubtype(string $type): bool
    {
        return in_array($type, [
            '3D',
            'Caret',
            'Circle',
            'FileAttachment',
            'FreeText',
            'Highlight',
            'Ink',
            'Line',
            'Link',
            'Movie',
            'Polygon',
            'PolyLine',
            'Popup',
            'PrinterMark',
            'Redact',
            'RichMedia',
            'Screen',
            'Sound',
            'Square',
            'Squiggly',
            'Stamp',
            'StrikeOut',
            'Text',
            'TrapNet',
            'Underline',
            'Watermark',
            'Widget',
        ], true);
    }

    /**
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function extractPdfAnnotationAppearances(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $appearances = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfAnnotationAppearancesFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $appearances,
                $pageNumber,
                0
            );
        }

        if ($appearances === []) {
            foreach ($objects as $reference => $body) {
                $subtype = $this->extractPdfNameToken($body, 'Subtype');
                if ($subtype === null || !$this->isPdfAnnotationSubtype($subtype)) {
                    continue;
                }

                array_push(
                    $appearances,
                    ...$this->summarizePdfAnnotationAppearances($body, $reference . ' R', null, null, $objects)
                );
            }
        }

        usort(
            $appearances,
            fn (array $left, array $right): int => [
                $left['page'],
                $this->pdfReferenceSortKey($left['annotationObject'] ?? ''),
                $this->pdfAnnotationAppearanceSortOrder((string) ($left['appearance'] ?? '')),
                (string) ($left['stateName'] ?? ''),
                $this->pdfReferenceSortKey($left['appearanceObject'] ?? ''),
            ] <=> [
                $right['page'],
                $this->pdfReferenceSortKey($right['annotationObject'] ?? ''),
                $this->pdfAnnotationAppearanceSortOrder((string) ($right['appearance'] ?? '')),
                (string) ($right['stateName'] ?? ''),
                $this->pdfReferenceSortKey($right['appearanceObject'] ?? ''),
            ]
        );

        return $appearances;
    }

    private function pdfAnnotationAppearanceSortOrder(string $appearance): int
    {
        return match ($appearance) {
            'N' => 0,
            'R' => 1,
            'D' => 2,
            default => 9,
        };
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $appearances
     */
    private function collectPdfAnnotationAppearancesFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$appearances,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $this->collectPdfAnnotationAppearancesFromPage($body, $reference, $pageNumber, $objects, $appearances);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfAnnotationAppearancesFromPageTree(
                $objects,
                $this->pdfReferenceKey($kidReference),
                $visited,
                $appearances,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}> $appearances
     */
    private function collectPdfAnnotationAppearancesFromPage(
        string $pageDictionary,
        string $pageReference,
        int $pageNumber,
        array $objects,
        array &$appearances
    ): void {
        $array = $this->extractPdfArrayOrReferenceValue($pageDictionary, 'Annots', $objects);
        if ($array === null) {
            return;
        }

        $this->walkPdfArrayValues($array, function (array $value) use (&$appearances, $objects, $pageNumber, $pageReference): void {
            if (!in_array($value['kind'], ['reference', 'dictionary'], true)) {
                return;
            }

            array_push(
                $appearances,
                ...$this->summarizePdfAnnotationAppearancesFromValue(
                    $value,
                    $objects,
                    $pageNumber,
                    $pageReference . ' R'
                )
            );
        });
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function summarizePdfAnnotationAppearancesFromValue(array $value, array $objects, int $pageNumber, string $pageReference): array
    {
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $dictionary = $objects[$reference] ?? null;

            return $dictionary === null
                ? []
                : $this->summarizePdfAnnotationAppearances($dictionary, $reference . ' R', $pageNumber, $pageReference, $objects);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfAnnotationAppearances($value['value'], 'inline', $pageNumber, $pageReference, $objects);
        }

        return [];
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function summarizePdfAnnotationAppearances(
        string $dictionary,
        ?string $annotationObject,
        ?int $pageNumber,
        ?string $pageReference,
        array $objects
    ): array {
        $subtype = $this->extractPdfNameToken($dictionary, 'Subtype');
        if ($subtype === null || !$this->isPdfAnnotationSubtype($subtype)) {
            return [];
        }

        $appearanceDictionary = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'AP', $objects);
        if ($appearanceDictionary === null) {
            return [];
        }

        $appearances = [];
        $base = [
            'page' => $pageNumber ?? 0,
            'pageObject' => $pageReference,
            'annotationObject' => $annotationObject,
            'subtype' => $subtype,
            'fieldName' => $this->extractPdfStringOrNameValue($dictionary, 'T'),
            'selectedState' => $this->extractPdfNameToken($dictionary, 'AS'),
            'source' => $annotationObject === null || $annotationObject === '' ? 'annotation:inline' : 'annotation:' . $annotationObject,
        ];

        foreach ($this->extractPdfTopLevelDictionaryEntries($appearanceDictionary) as $entry) {
            if (!in_array($entry['key'], ['N', 'R', 'D'], true)) {
                continue;
            }

            array_push(
                $appearances,
                ...$this->summarizePdfAnnotationAppearanceEntry(
                    $entry['key'],
                    null,
                    $entry['value'],
                    $objects,
                    $base
                )
            );
        }

        return $appearances;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @param array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, source:string} $base
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>
     */
    private function summarizePdfAnnotationAppearanceEntry(
        string $appearance,
        ?string $stateName,
        array $value,
        array $objects,
        array $base
    ): array {
        $appearanceObject = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $dictionary = $objects[$reference] ?? null;
            $appearanceObject = $reference . ' R';
        } elseif ($value['kind'] === 'dictionary') {
            $dictionary = $value['value'];
        } else {
            return [];
        }

        if ($dictionary === null) {
            return [];
        }

        $source = $base['source'] . '.AP.' . $appearance;
        if ($stateName !== null && $stateName !== '') {
            $source .= '.' . $stateName;
        }

        if ($this->isPdfAnnotationAppearanceStreamDictionary($dictionary)) {
            return [
                $this->summarizePdfAnnotationAppearanceStream(
                    $dictionary,
                    $appearance,
                    $stateName,
                    $appearanceObject,
                    $source,
                    $objects,
                    $base
                ),
            ];
        }

        $appearances = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($dictionary) as $entry) {
            array_push(
                $appearances,
                ...$this->summarizePdfAnnotationAppearanceEntry(
                    $appearance,
                    $entry['key'],
                    $entry['value'],
                    $objects,
                    $base
                )
            );
        }

        return $appearances;
    }

    private function isPdfAnnotationAppearanceStreamDictionary(string $dictionary): bool
    {
        return $this->extractPdfStreamBytes($dictionary) !== null
            || $this->extractPdfNameToken($dictionary, 'Subtype') === 'Form'
            || $this->extractPdfValueForName($dictionary, 'BBox') !== null
            || $this->extractPdfValueForName($dictionary, 'Length') !== null;
    }

    /**
     * @param array<string, string> $objects
     * @param array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, source:string} $base
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, fieldName:string|null, selectedState:string|null, appearance:string, stateName:string|null, appearanceObject:string|null, source:string, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}
     */
    private function summarizePdfAnnotationAppearanceStream(
        string $dictionary,
        string $appearance,
        ?string $stateName,
        ?string $appearanceObject,
        string $source,
        array $objects,
        array $base
    ): array {
        $group = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'Group', $objects);
        $stream = $this->summarizePdfAnnotationAppearanceStreamBytes($dictionary);

        return [
            'page' => $base['page'],
            'pageObject' => $base['pageObject'],
            'annotationObject' => $base['annotationObject'],
            'subtype' => $base['subtype'],
            'fieldName' => $base['fieldName'],
            'selectedState' => $base['selectedState'],
            'appearance' => $appearance,
            'stateName' => $stateName,
            'appearanceObject' => $appearanceObject,
            'source' => $source,
            'bbox' => $this->extractPdfNumberArrayToken($dictionary, 'BBox', 4),
            'matrix' => $this->extractPdfNumberArrayToken($dictionary, 'Matrix', 6),
            'resourcesPresent' => $this->extractPdfValueForName($dictionary, 'Resources') !== null,
            'groupSubtype' => $group === null ? null : $this->extractPdfNameToken($group, 'S'),
            'groupColorSpace' => $group === null ? null : $this->extractPdfColorSpaceNameValue($group, 'CS', $objects),
            'groupIsolated' => $group === null ? null : $this->extractPdfBooleanToken($group, 'I'),
            'groupKnockout' => $group === null ? null : $this->extractPdfBooleanToken($group, 'K'),
            'filters' => $this->extractPdfFilterNames($dictionary, $objects),
            'streamBytes' => $stream['bytes'],
            'streamSha256' => $stream['sha256'],
            'streamSkipped' => $stream['skipped'],
        ];
    }

    /**
     * @return array{bytes:int|null, sha256:string|null, skipped:string|null}
     */
    private function summarizePdfAnnotationAppearanceStreamBytes(string $dictionary): array
    {
        $summary = [
            'bytes' => null,
            'sha256' => null,
            'skipped' => null,
        ];
        $bytes = $this->extractPdfStreamBytes($dictionary);
        if ($bytes === null) {
            return $summary;
        }

        $summary['bytes'] = strlen($bytes);
        if (strlen($bytes) > self::MAX_ANNOTATION_APPEARANCE_STREAM_BYTES) {
            $summary['skipped'] = 'too-large';

            return $summary;
        }

        $summary['sha256'] = hash('sha256', $bytes);

        return $summary;
    }

    /**
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}>
     */
    private function extractPdfAnnotations(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $annotations = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfAnnotationsFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $annotations,
                $pageNumber,
                0
            );
        }

        if ($annotations !== []) {
            return $annotations;
        }

        foreach ($objects as $reference => $body) {
            $subtype = $this->extractPdfNameToken($body, 'Subtype');
            if ($subtype === null || !$this->isPdfAnnotationSubtype($subtype)) {
                continue;
            }

            $summary = $this->summarizePdfAnnotation($body, $reference . ' R', null, null, $objects);
            if ($summary !== null) {
                $annotations[] = $summary;
            }
        }

        usort(
            $annotations,
            fn (array $left, array $right): int => $this->pdfReferenceSortKey($left['annotationObject'] ?? '')
                <=> $this->pdfReferenceSortKey($right['annotationObject'] ?? '')
        );

        return $annotations;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}> $annotations
     */
    private function collectPdfAnnotationsFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$annotations,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $this->collectPdfAnnotationsFromPage($body, $reference, $pageNumber, $objects, $annotations);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfAnnotationsFromPageTree(
                $objects,
                $this->pdfReferenceKey($kidReference),
                $visited,
                $annotations,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}> $annotations
     */
    private function collectPdfAnnotationsFromPage(
        string $pageDictionary,
        string $pageReference,
        int $pageNumber,
        array $objects,
        array &$annotations
    ): void {
        $array = $this->extractPdfArrayOrReferenceValue($pageDictionary, 'Annots', $objects);
        if ($array === null) {
            return;
        }

        $this->walkPdfArrayValues($array, function (array $value) use (&$annotations, $objects, $pageNumber, $pageReference): void {
            if (!in_array($value['kind'], ['reference', 'dictionary'], true)) {
                return;
            }

            $summary = $this->summarizePdfAnnotationValue(
                $value,
                $objects,
                $pageNumber,
                $pageReference . ' R'
            );
            if ($summary !== null) {
                $annotations[] = $summary;
            }
        });
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}|null
     */
    private function summarizePdfAnnotationValue(array $value, array $objects, int $pageNumber, string $pageReference): ?array
    {
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $dictionary = $objects[$reference] ?? null;

            return $dictionary === null
                ? null
                : $this->summarizePdfAnnotation($dictionary, $reference . ' R', $pageNumber, $pageReference, $objects);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfAnnotation($value['value'], 'inline', $pageNumber, $pageReference, $objects);
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, rect:list<float>|null, quadPoints:list<float>|null, contents:string|null, title:string|null, name:string|null, modified:string|null, iconName:string|null, replyTo:string|null, replyType:string|null, state:string|null, stateModel:string|null, flags:int, flagNames:list<string>, color:list<float>|null, border:list<float>|null, actionType:string|null, actionTarget:string|null, destPageObject:string|null, destFit:string|null, destTarget:string|null}|null
     */
    private function summarizePdfAnnotation(
        string $dictionary,
        ?string $annotationObject,
        ?int $pageNumber,
        ?string $pageReference,
        array $objects
    ): ?array {
        $subtype = $this->extractPdfNameToken($dictionary, 'Subtype');
        if ($subtype === null || !$this->isPdfAnnotationSubtype($subtype)) {
            return null;
        }

        $action = $this->extractPdfAnnotationAction($dictionary, $objects);
        $destination = $this->extractPdfAnnotationDestination($dictionary, $objects);
        $flags = $this->extractPdfIntegerToken($dictionary, 'F') ?? 0;

        return [
            'page' => $pageNumber ?? 0,
            'pageObject' => $pageReference,
            'annotationObject' => $annotationObject,
            'subtype' => $subtype,
            'rect' => $this->extractPdfNumberArrayToken($dictionary, 'Rect', 4),
            'quadPoints' => $this->extractPdfNumberArrayValues($dictionary, 'QuadPoints'),
            'contents' => $this->extractPdfStringOrNameValue($dictionary, 'Contents'),
            'title' => $this->extractPdfStringOrNameValue($dictionary, 'T'),
            'name' => $this->extractPdfStringOrNameValue($dictionary, 'NM'),
            'modified' => $this->extractPdfStringOrNameValue($dictionary, 'M'),
            'iconName' => $this->extractPdfStringOrNameValue($dictionary, 'Name'),
            'replyTo' => $this->extractPdfReferenceToken($dictionary, 'IRT'),
            'replyType' => $this->extractPdfStringOrNameValue($dictionary, 'RT'),
            'state' => $this->extractPdfStringOrNameValue($dictionary, 'State'),
            'stateModel' => $this->extractPdfStringOrNameValue($dictionary, 'StateModel'),
            'flags' => $flags,
            'flagNames' => $this->pdfAnnotationFlagNames($flags),
            'color' => $this->extractPdfNumberArrayValues($dictionary, 'C'),
            'border' => $this->extractPdfNumberArrayValues($dictionary, 'Border'),
            'actionType' => is_string($action['type'] ?? null) ? $action['type'] : null,
            'actionTarget' => is_string($action['target'] ?? null) ? $action['target'] : null,
            'destPageObject' => is_string($destination['pageObject'] ?? null)
                ? $destination['pageObject']
                : (is_string($action['pageObject'] ?? null) ? $action['pageObject'] : null),
            'destFit' => is_string($destination['fit'] ?? null)
                ? $destination['fit']
                : (is_string($action['fit'] ?? null) ? $action['fit'] : null),
            'destTarget' => is_string($destination['target'] ?? null) ? $destination['target'] : null,
        ];
    }

    /**
     * @return list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}>
     */
    private function extractPdfAnnotationReviewMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $metadata = [];
        $visited = [];
        $pageNumber = 0;
        $pagesReference = $catalog === null ? null : $this->extractPdfReferenceToken($catalog, 'Pages');

        if ($pagesReference !== null) {
            $this->collectPdfAnnotationReviewMetadataFromPageTree(
                $objects,
                $this->pdfReferenceKey($pagesReference),
                $visited,
                $metadata,
                $pageNumber,
                0
            );
        }

        if ($metadata !== []) {
            return $metadata;
        }

        foreach ($objects as $reference => $body) {
            $summary = $this->summarizePdfAnnotationReviewMetadata($body, $reference . ' R', null, null, $objects);
            if ($summary !== null) {
                $metadata[] = $summary;
            }
        }

        usort(
            $metadata,
            fn (array $left, array $right): int => $this->pdfReferenceSortKey($left['annotationObject'] ?? '')
                <=> $this->pdfReferenceSortKey($right['annotationObject'] ?? '')
        );

        return $metadata;
    }

    /**
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}> $metadata
     */
    private function collectPdfAnnotationReviewMetadataFromPageTree(
        array $objects,
        string $reference,
        array &$visited,
        array &$metadata,
        int &$pageNumber,
        int $depth
    ): void {
        if ($depth > 32 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $type = $this->extractPdfNameToken($body, 'Type');
        if ($type === 'Page') {
            $pageNumber++;
            $this->collectPdfAnnotationReviewMetadataFromPage($body, $reference, $pageNumber, $objects, $metadata);
            return;
        }

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfAnnotationReviewMetadataFromPageTree(
                $objects,
                $this->pdfReferenceKey($kidReference),
                $visited,
                $metadata,
                $pageNumber,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, string> $objects
     * @param list<array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}> $metadata
     */
    private function collectPdfAnnotationReviewMetadataFromPage(
        string $pageDictionary,
        string $pageReference,
        int $pageNumber,
        array $objects,
        array &$metadata
    ): void {
        $array = $this->extractPdfArrayOrReferenceValue($pageDictionary, 'Annots', $objects);
        if ($array === null) {
            return;
        }

        $this->walkPdfArrayValues($array, function (array $value) use (&$metadata, $objects, $pageNumber, $pageReference): void {
            if (!in_array($value['kind'], ['reference', 'dictionary'], true)) {
                return;
            }

            $summary = $this->summarizePdfAnnotationReviewMetadataValue(
                $value,
                $objects,
                $pageNumber,
                $pageReference . ' R'
            );
            if ($summary !== null) {
                $metadata[] = $summary;
            }
        });
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}|null
     */
    private function summarizePdfAnnotationReviewMetadataValue(array $value, array $objects, int $pageNumber, string $pageReference): ?array
    {
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $dictionary = $objects[$reference] ?? null;

            return $dictionary === null
                ? null
                : $this->summarizePdfAnnotationReviewMetadata($dictionary, $reference . ' R', $pageNumber, $pageReference, $objects);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfAnnotationReviewMetadata($value['value'], 'inline', $pageNumber, $pageReference, $objects);
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null}|null
     */
    private function summarizePdfAnnotationReviewMetadata(
        string $dictionary,
        ?string $annotationObject,
        ?int $pageNumber,
        ?string $pageReference,
        array $objects
    ): ?array {
        $subtype = $this->extractPdfNameToken($dictionary, 'Subtype');
        if ($subtype === null || !$this->isPdfAnnotationSubtype($subtype)) {
            return null;
        }

        $borderStyle = $this->extractPdfAnnotationBorderStyle($dictionary, $objects);
        $popup = $this->extractPdfAnnotationPopup($dictionary, $objects);
        $summary = [
            'page' => $pageNumber ?? 0,
            'pageObject' => $pageReference,
            'annotationObject' => $annotationObject,
            'subtype' => $subtype,
            'borderStyle' => $borderStyle['style'],
            'borderStyleLabel' => $borderStyle['styleLabel'],
            'borderWidth' => $borderStyle['width'],
            'borderDashPattern' => $borderStyle['dashPattern'],
            'popupObject' => $popup['object'],
            'popupRect' => $popup['rect'],
            'popupOpen' => $popup['open'],
            'popupParent' => $popup['parent'],
        ];

        return $this->pdfAnnotationReviewMetadataHasValues($summary) ? $summary : null;
    }

    /**
     * @param array{page:int, pageObject:string|null, annotationObject:string|null, subtype:string|null, borderStyle:string|null, borderStyleLabel:string|null, borderWidth:float|null, borderDashPattern:list<float>|null, popupObject:string|null, popupRect:list<float>|null, popupOpen:bool|null, popupParent:string|null} $summary
     */
    private function pdfAnnotationReviewMetadataHasValues(array $summary): bool
    {
        return $summary['borderStyle'] !== null
            || $summary['borderWidth'] !== null
            || $summary['borderDashPattern'] !== null
            || $summary['popupObject'] !== null
            || $summary['popupRect'] !== null
            || $summary['popupOpen'] !== null
            || $summary['popupParent'] !== null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{style:string|null, styleLabel:string|null, width:float|null, dashPattern:list<float>|null}
     */
    private function extractPdfAnnotationBorderStyle(string $dictionary, array $objects): array
    {
        $styleDictionary = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'BS', $objects);
        if ($styleDictionary === null) {
            return [
                'style' => null,
                'styleLabel' => null,
                'width' => null,
                'dashPattern' => null,
            ];
        }

        $style = $this->extractPdfNameToken($styleDictionary, 'S');

        return [
            'style' => $style,
            'styleLabel' => $style === null ? null : $this->pdfAnnotationBorderStyleLabel($style),
            'width' => $this->extractPdfNumberToken($styleDictionary, 'W'),
            'dashPattern' => $this->extractPdfNumberArrayValues($styleDictionary, 'D'),
        ];
    }

    private function pdfAnnotationBorderStyleLabel(string $style): ?string
    {
        return [
            'S' => 'solid',
            'D' => 'dashed',
            'B' => 'beveled',
            'I' => 'inset',
            'U' => 'underline',
        ][$style] ?? null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{object:string|null, rect:list<float>|null, open:bool|null, parent:string|null}
     */
    private function extractPdfAnnotationPopup(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'Popup');
        if ($value === null) {
            return [
                'object' => null,
                'rect' => null,
                'open' => null,
                'parent' => null,
            ];
        }

        $popupObject = null;
        $popupDictionary = null;
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $popupObject = $reference . ' R';
            $popupDictionary = $objects[$reference] ?? null;
        } elseif ($value['kind'] === 'dictionary') {
            $popupObject = 'inline';
            $popupDictionary = $value['value'];
        }

        if ($popupDictionary === null) {
            return [
                'object' => $popupObject,
                'rect' => null,
                'open' => null,
                'parent' => null,
            ];
        }

        return [
            'object' => $popupObject,
            'rect' => $this->extractPdfNumberArrayToken($popupDictionary, 'Rect', 4),
            'open' => $this->extractPdfBooleanToken($popupDictionary, 'Open'),
            'parent' => $this->extractPdfReferenceToken($popupDictionary, 'Parent'),
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, mixed>
     */
    private function extractPdfAnnotationAction(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'A');
        if ($value === null) {
            return [];
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfActionDictionary($value['value']) ?? [];
        }

        if ($value['kind'] !== 'reference') {
            return [];
        }

        $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;

        return $body === null ? [] : ($this->summarizePdfActionDictionary($body) ?? []);
    }

    /**
     * @param array<string, string> $objects
     * @return array<string, mixed>
     */
    private function extractPdfAnnotationDestination(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfValueForName($dictionary, 'Dest');
        if ($value === null) {
            return [];
        }

        return $this->summarizePdfNamedDestinationValue($value, $objects) ?? [];
    }

    /**
     * @return list<string>
     */
    private function pdfAnnotationFlagNames(int $flags): array
    {
        if ($flags === 0) {
            return [];
        }

        $definitions = [
            0x001 => 'invisible',
            0x002 => 'hidden',
            0x004 => 'print',
            0x008 => 'noZoom',
            0x010 => 'noRotate',
            0x020 => 'noView',
            0x040 => 'readOnly',
            0x080 => 'locked',
            0x100 => 'toggleNoView',
            0x200 => 'lockedContents',
        ];

        $names = [];
        foreach ($definitions as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function extractPdfLinkTargets(string $pdfBytes): array
    {
        $targets = [];
        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (!str_contains($body, '/URI')) {
                continue;
            }
            if (preg_match('/\/(?:Subtype\s*\/Link|S\s*\/URI)\b/s', $body) !== 1) {
                continue;
            }

            foreach ($this->extractPdfNamedStrings($body, 'URI') as $target) {
                $target = trim($target);
                if ($target !== '') {
                    $targets[] = $target;
                }
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * @return list<string>
     */
    private function extractPdfEmbeddedFileNames(string $pdfBytes): array
    {
        $names = [];
        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (
                !str_contains($body, '/EmbeddedFile')
                && !str_contains($body, '/EmbeddedFiles')
                && !str_contains($body, '/Filespec')
                && !str_contains($body, '/FileAttachment')
            ) {
                continue;
            }

            foreach (['UF', 'F'] as $key) {
                foreach ($this->extractPdfNamedStrings($body, $key) as $name) {
                    $name = trim($name);
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
            }
            if (str_contains($body, '/EmbeddedFiles')) {
                $embeddedFiles = $this->extractPdfNestedDictionary($body, 'EmbeddedFiles');
                if ($embeddedFiles !== null) {
                    foreach ($this->extractPdfNamesArrayStrings($embeddedFiles) as $name) {
                        $name = trim($name);
                        if ($name !== '') {
                            $names[] = $name;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}>
     */
    private function extractPdfEmbeddedFiles(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $files = [];
        $visited = [];

        if ($catalog !== null) {
            $names = $this->extractPdfDictionaryOrReferenceValue($catalog, 'Names', $objects);
            if ($names !== null) {
                $embeddedFiles = $this->extractPdfDictionaryOrReferenceValue($names, 'EmbeddedFiles', $objects);
                if ($embeddedFiles !== null) {
                    $this->collectPdfEmbeddedFileNameTree(
                        $files,
                        'catalog.Names.EmbeddedFiles',
                        $embeddedFiles,
                        $objects,
                        $visited,
                        0
                    );
                }
            }

            foreach ($this->extractPdfReferenceArray($catalog, 'AF') as $reference) {
                if (!isset($objects[$reference])) {
                    continue;
                }
                $this->addPdfEmbeddedFileEntry(
                    $files,
                    $this->summarizePdfFileSpec($objects[$reference], $objects, 'catalog.AF', $reference . ' R', null)
                );
            }
        }

        foreach ($this->extractPdfMarkedContentProperties($pdfBytes, $catalog) as $property) {
            $pageObject = isset($property['pageObject']) && is_string($property['pageObject']) ? $property['pageObject'] : 'page';
            $propertyName = isset($property['propertyName']) && is_string($property['propertyName']) ? $property['propertyName'] : 'unnamed';
            $source = 'marked-content:' . $pageObject . '.Properties.' . $propertyName . '.AF';
            foreach ($property['associatedFiles'] as $associatedFileReference) {
                if (preg_match('/\A(\d+)\s+(\d+)\s+R\z/', $associatedFileReference, $matches) !== 1) {
                    continue;
                }

                $reference = $matches[1] . ' ' . $matches[2];
                if (!isset($objects[$reference])) {
                    continue;
                }

                $this->addPdfEmbeddedFileEntry(
                    $files,
                    $this->summarizePdfFileSpec($objects[$reference], $objects, $source, $associatedFileReference, null)
                );
            }
        }

        foreach ($objects as $reference => $body) {
            $associatedFileSource = $this->pdfAssociatedFileSource($reference, $body);
            if ($associatedFileSource !== null) {
                foreach ($this->extractPdfReferenceArray($body, 'AF') as $associatedFileReference) {
                    if (!isset($objects[$associatedFileReference])) {
                        continue;
                    }

                    $this->addPdfEmbeddedFileEntry(
                        $files,
                        $this->summarizePdfFileSpec(
                            $objects[$associatedFileReference],
                            $objects,
                            $associatedFileSource,
                            $associatedFileReference . ' R',
                            null
                        )
                    );
                }
            }

            if (preg_match('/\/Subtype\s*\/FileAttachment\b/s', $body) === 1) {
                $value = $this->extractPdfValueForName($body, 'FS');
                if ($value !== null) {
                    $this->addPdfEmbeddedFileFromValue(
                        $files,
                        'annotation:' . $reference . ' R.FS',
                        $value,
                        $objects,
                        null
                    );
                }
            }

            if (preg_match('/\/Type\s*\/Filespec\b/s', $body) === 1 || (str_contains($body, '/EF') && preg_match('/\/(?:UF|F)\b/s', $body) === 1)) {
                $this->addPdfEmbeddedFileEntry(
                    $files,
                    $this->summarizePdfFileSpec($body, $objects, 'filespec:' . $reference . ' R', $reference . ' R', null)
                );
            }
        }

        $files = array_values($files);
        usort(
            $files,
            static fn (array $a, array $b): int => [
                $a['name'],
                $a['source'],
                $a['filespec'] ?? '',
                $a['embeddedFile'] ?? '',
            ] <=> [
                $b['name'],
                $b['source'],
                $b['filespec'] ?? '',
                $b['embeddedFile'] ?? '',
            ]
        );

        return $files;
    }

    private function pdfAssociatedFileSource(string $reference, string $body): ?string
    {
        if (!str_contains($body, '/AF')) {
            return null;
        }

        $references = $this->extractPdfReferenceArray($body, 'AF');
        if ($references === []) {
            return null;
        }

        $type = $this->extractPdfNameToken($body, 'Type');
        $pdfReference = $reference . ' R';
        if ($type === 'Catalog' || $type === 'Filespec') {
            return null;
        }
        if ($type === 'Page') {
            return 'page:' . $pdfReference . '.AF';
        }
        if ($type === 'StructElem') {
            return 'structure:' . $pdfReference . '.AF';
        }
        if ($type === 'Annot') {
            return 'annotation:' . $pdfReference . '.AF';
        }
        if ($type === 'XObject') {
            return 'xobject:' . $pdfReference . '.AF';
        }

        return 'object:' . $pdfReference . '.AF';
    }

    /**
     * @param array<string, array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}> $files
     * @param array<string, string> $objects
     * @param array<string, bool> $visited
     */
    private function collectPdfEmbeddedFileNameTree(
        array &$files,
        string $source,
        string $dictionary,
        array $objects,
        array &$visited,
        int $depth
    ): void {
        if ($depth > 16) {
            return;
        }

        $array = $this->extractPdfArrayValue($dictionary, 'Names');
        if ($array !== null) {
            $cursor = str_starts_with($array, '[') ? 1 : 0;
            $length = strlen($array);
            if (str_ends_with($array, ']')) {
                $length--;
            }

            while ($cursor < $length) {
                $name = $this->parsePdfValueAt($array, $cursor);
                if ($name === null) {
                    $cursor++;
                    continue;
                }
                $cursor = $name['next'];

                $value = $this->parsePdfValueAt($array, $cursor);
                if ($value === null) {
                    break;
                }
                $cursor = $value['next'];

                if (!in_array($name['kind'], ['literal', 'hex', 'name'], true)) {
                    continue;
                }

                $nameHint = trim($name['value']);
                if ($nameHint === '') {
                    $nameHint = null;
                }

                $this->addPdfEmbeddedFileFromValue($files, $source, $value, $objects, $nameHint);
            }
        }

        foreach ($this->extractPdfReferenceArray($dictionary, 'Kids') as $kidReference) {
            if (isset($visited[$kidReference]) || !isset($objects[$kidReference])) {
                continue;
            }

            $visited[$kidReference] = true;
            $this->collectPdfEmbeddedFileNameTree(
                $files,
                $source . '.Kids.' . $kidReference . ' R',
                $objects[$kidReference],
                $objects,
                $visited,
                $depth + 1
            );
        }
    }

    /**
     * @param array<string, array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}> $files
     * @param array{kind:string, value:string} $value
     * @param array<string, string> $objects
     */
    private function addPdfEmbeddedFileFromValue(array &$files, string $source, array $value, array $objects, ?string $nameHint): void
    {
        if ($value['kind'] === 'reference') {
            $reference = $this->pdfReferenceKey($value['value']);
            $body = $objects[$reference] ?? null;
            if ($body === null) {
                return;
            }

            $this->addPdfEmbeddedFileEntry(
                $files,
                $this->summarizePdfFileSpec($body, $objects, $source, $reference . ' R', $nameHint)
            );
            return;
        }

        if ($value['kind'] === 'dictionary') {
            $this->addPdfEmbeddedFileEntry(
                $files,
                $this->summarizePdfFileSpec($value['value'], $objects, $source, null, $nameHint)
            );
            return;
        }

        if (in_array($value['kind'], ['literal', 'hex', 'name'], true)) {
            $name = trim($value['value']);
            if ($name === '') {
                return;
            }

            $this->addPdfEmbeddedFileEntry($files, [
                'name' => $name,
                'unicodeName' => null,
                'description' => null,
                'afRelationship' => null,
                'filespec' => null,
                'embeddedFile' => null,
                'subtype' => null,
                'size' => null,
                'modDate' => null,
                'checksum' => null,
                'streamBytes' => null,
                'streamSha256' => null,
                'streamSkipped' => null,
                'collectionItems' => [],
                'source' => $source,
            ]);
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}|null
     */
    private function summarizePdfFileSpec(string $dictionary, array $objects, string $source, ?string $filespecReference, ?string $nameHint): ?array
    {
        $unicodeName = $this->extractPdfStringOrNameValue($dictionary, 'UF');
        $fallbackName = $this->extractPdfStringOrNameValue($dictionary, 'F');
        $name = $unicodeName ?? $fallbackName ?? $nameHint;
        if ($name === null || trim($name) === '') {
            return null;
        }

        $embedded = $this->extractPdfEmbeddedFileStreamForFileSpec($dictionary, $objects);

        return [
            'name' => trim($name),
            'unicodeName' => $unicodeName,
            'description' => $this->extractPdfStringOrNameValue($dictionary, 'Desc'),
            'afRelationship' => $this->extractPdfNameToken($dictionary, 'AFRelationship'),
            'filespec' => $filespecReference,
            'embeddedFile' => $embedded['reference'],
            'subtype' => $embedded['subtype'],
            'size' => $embedded['size'],
            'modDate' => $embedded['modDate'],
            'checksum' => $embedded['checksum'],
            'streamBytes' => $embedded['streamBytes'],
            'streamSha256' => $embedded['streamSha256'],
            'streamSkipped' => $embedded['streamSkipped'],
            'collectionItems' => $this->extractPdfFileSpecCollectionItems($dictionary, $objects),
            'source' => $source,
        ];
    }

    /**
     * @param array<string, string> $objects
     * @return list<array{name:string, value:string|int|float|bool|null, valueType:string}>
     */
    private function extractPdfFileSpecCollectionItems(string $dictionary, array $objects): array
    {
        $collectionItem = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'CI', $objects);
        if ($collectionItem === null) {
            return [];
        }

        $items = [];
        foreach ($this->extractPdfTopLevelDictionaryEntries($collectionItem) as $entry) {
            if ($entry['key'] === 'Type') {
                continue;
            }

            $summary = $this->summarizePdfFileSpecCollectionItemValue($entry['value'], $objects, 0);
            if ($summary === null) {
                continue;
            }

            $items[] = [
                'name' => $entry['key'],
                'value' => $summary['value'],
                'valueType' => $summary['valueType'],
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => strcmp($left['name'], $right['name'])
        );

        return $items;
    }

    /**
     * @param array{kind:string, value:string, next:int} $value
     * @param array<string, string> $objects
     * @return array{value:string|int|float|bool|null, valueType:string}|null
     */
    private function summarizePdfFileSpecCollectionItemValue(array $value, array $objects, int $depth): ?array
    {
        if ($depth > 8) {
            return null;
        }

        if ($value['kind'] === 'reference') {
            $body = trim($objects[$this->pdfReferenceKey($value['value'])] ?? '');
            if ($body === '') {
                return [
                    'value' => $value['value'],
                    'valueType' => 'reference',
                ];
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved === null) {
                return [
                    'value' => $value['value'],
                    'valueType' => 'reference',
                ];
            }

            return $this->summarizePdfFileSpecCollectionItemValue($resolved, $objects, $depth + 1);
        }

        if ($value['kind'] === 'literal' || $value['kind'] === 'hex') {
            return [
                'value' => trim($value['value']),
                'valueType' => 'string',
            ];
        }

        if ($value['kind'] === 'name') {
            return [
                'value' => trim($value['value']),
                'valueType' => 'name',
            ];
        }

        if ($value['kind'] === 'number') {
            $number = $value['value'];
            $numeric = preg_match('/[.Ee]/', $number) === 1 ? (float) $number : (int) $number;

            return [
                'value' => $numeric,
                'valueType' => is_int($numeric) ? 'integer' : 'number',
            ];
        }

        if ($value['kind'] === 'keyword') {
            if ($value['value'] === 'true' || $value['value'] === 'false') {
                return [
                    'value' => $value['value'] === 'true',
                    'valueType' => 'boolean',
                ];
            }

            return [
                'value' => null,
                'valueType' => 'null',
            ];
        }

        if ($value['kind'] === 'array') {
            return [
                'value' => count($this->pdfTopLevelArrayValues($value['value'])),
                'valueType' => 'array',
            ];
        }

        if ($value['kind'] === 'dictionary') {
            return [
                'value' => count($this->extractPdfTopLevelDictionaryEntries($value['value'])),
                'valueType' => 'dictionary',
            ];
        }

        return null;
    }

    /**
     * @param array<string, string> $objects
     * @return array{reference:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}
     */
    private function extractPdfEmbeddedFileStreamForFileSpec(string $dictionary, array $objects): array
    {
        $summary = [
            'reference' => null,
            'subtype' => null,
            'size' => null,
            'modDate' => null,
            'checksum' => null,
            'streamBytes' => null,
            'streamSha256' => null,
            'streamSkipped' => null,
        ];

        $ef = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'EF', $objects);
        $streamObject = null;
        if ($ef !== null) {
            foreach (['UF', 'F'] as $key) {
                $value = $this->extractPdfValueForName($ef, $key);
                if ($value === null) {
                    continue;
                }
                if ($value['kind'] === 'reference') {
                    $reference = $this->pdfReferenceKey($value['value']);
                    $streamObject = $objects[$reference] ?? null;
                    if ($streamObject !== null) {
                        $summary['reference'] = $reference . ' R';
                        break;
                    }
                } elseif ($value['kind'] === 'dictionary') {
                    $streamObject = $value['value'];
                    $summary['reference'] = 'inline';
                    break;
                }
            }
        } elseif (str_contains($dictionary, 'stream') && preg_match('/\/Type\s*\/EmbeddedFile\b/s', $dictionary) === 1) {
            $streamObject = $dictionary;
            $summary['reference'] = 'inline';
        }

        if ($streamObject === null) {
            return $summary;
        }

        $summary['subtype'] = $this->extractPdfNameToken($streamObject, 'Subtype');
        $params = $this->extractPdfDictionaryOrReferenceValue($streamObject, 'Params', $objects);
        if ($params !== null) {
            $summary['size'] = $this->extractPdfIntegerToken($params, 'Size');
            $summary['modDate'] = $this->extractPdfStringOrNameValue($params, 'ModDate');
            $summary['checksum'] = $this->extractPdfByteStringHexValue($params, 'CheckSum');
        }

        $streamBytes = $this->extractPdfStreamBytes($streamObject);
        if ($streamBytes === null) {
            return $summary;
        }

        $summary['streamBytes'] = strlen($streamBytes);
        if (preg_match('/\/Filter\b/s', $streamObject) === 1) {
            $summary['streamSkipped'] = 'filtered';

            return $summary;
        }
        if (strlen($streamBytes) > self::MAX_EMBEDDED_FILE_STREAM_BYTES) {
            $summary['streamSkipped'] = 'too-large';

            return $summary;
        }

        $summary['streamSha256'] = hash('sha256', $streamBytes);

        return $summary;
    }

    /**
     * @param array<string, array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}> $files
     * @param array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, collectionItems:list<array{name:string, value:string|int|float|bool|null, valueType:string}>, source:string}|null $entry
     */
    private function addPdfEmbeddedFileEntry(array &$files, ?array $entry): void
    {
        if ($entry === null) {
            return;
        }

        $key = $entry['filespec'] ?? null;
        if ($key === null || $key === '') {
            $key = implode("\0", [
                $entry['name'],
                $entry['source'],
                $entry['embeddedFile'] ?? '',
            ]);
        }

        if (!isset($files[$key])) {
            $files[$key] = $entry;
        }
    }

    /**
     * @return list<array{fieldName:string|null, fieldObject:string|null, fieldType:string|null, fieldTypeLabel:string|null, trigger:string, source:string, actionType:string, actionTarget:string|null, scriptBytes:int|null, scriptSha256:string|null}>
     */
    private function extractPdfFormFieldActions(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $candidates = [];
        $acroForm = $this->extractPdfAcroFormDictionary($pdfBytes, $catalog);

        if ($acroForm !== null) {
            $visited = [];
            foreach ($this->extractPdfReferenceArray($acroForm, 'Fields') as $reference) {
                $this->collectPdfFormFieldActionCandidates(
                    $objects,
                    $reference,
                    ['name' => null, 'type' => null],
                    $candidates,
                    $visited,
                    0
                );
            }
        }

        if ($candidates === []) {
            foreach ($objects as $reference => $body) {
                if ((!str_contains($body, '/A') && !str_contains($body, '/AA')) || !str_contains($body, '/FT')) {
                    continue;
                }

                $candidates[$reference] = $this->pdfFormFieldActionCandidate($body, $reference . ' R', null, null);
            }
        }

        $actions = [];
        foreach ($candidates as $candidate) {
            $this->addPdfFormFieldActionFromNamedValue($actions, $candidate, 'A', $candidate['dictionary'], 'A', $objects);

            $additionalActions = $this->extractPdfDictionaryOrReferenceValue($candidate['dictionary'], 'AA', $objects);
            if ($additionalActions === null) {
                continue;
            }

            foreach (['E', 'X', 'D', 'U', 'Fo', 'Bl', 'PO', 'PC', 'PV', 'PI', 'O', 'C', 'K', 'F', 'V', 'WC', 'WS', 'DS', 'WP', 'DP'] as $trigger) {
                $this->addPdfFormFieldActionFromNamedValue($actions, $candidate, 'AA.' . $trigger, $additionalActions, $trigger, $objects);
            }
        }

        $actions = array_values($actions);
        usort(
            $actions,
            static fn (array $left, array $right): int => [
                $left['fieldName'] ?? '',
                $left['fieldObject'] ?? '',
                $left['trigger'],
                $left['actionType'],
                $left['actionTarget'] ?? '',
            ] <=> [
                $right['fieldName'] ?? '',
                $right['fieldObject'] ?? '',
                $right['trigger'],
                $right['actionType'],
                $right['actionTarget'] ?? '',
            ]
        );

        return $actions;
    }

    /**
     * @param array<string, string> $objects
     * @param array{name:string|null, type:string|null} $inherited
     * @param array<string, array{dictionary:string, fieldObject:string|null, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null}> $candidates
     * @param array<string, bool> $visited
     */
    private function collectPdfFormFieldActionCandidates(
        array $objects,
        string $reference,
        array $inherited,
        array &$candidates,
        array &$visited,
        int $depth
    ): void {
        if ($depth > 16 || isset($visited[$reference]) || !isset($objects[$reference])) {
            return;
        }
        $visited[$reference] = true;

        $body = $objects[$reference];
        $candidate = $this->pdfFormFieldActionCandidate($body, $reference . ' R', $inherited['name'], $inherited['type']);
        $candidates[$reference] = $candidate;

        foreach ($this->extractPdfReferenceArray($body, 'Kids') as $kidReference) {
            $this->collectPdfFormFieldActionCandidates(
                $objects,
                $this->pdfReferenceKey($kidReference),
                [
                    'name' => $candidate['fieldName'],
                    'type' => $candidate['fieldType'],
                ],
                $candidates,
                $visited,
                $depth + 1
            );
        }
    }

    /**
     * @return array{dictionary:string, fieldObject:string|null, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null}
     */
    private function pdfFormFieldActionCandidate(
        string $dictionary,
        ?string $fieldObject,
        ?string $inheritedName,
        ?string $inheritedType
    ): array {
        $partialName = $this->extractPdfStringOrNameValue($dictionary, 'T');
        if ($partialName !== null && $partialName !== '') {
            $fieldName = $inheritedName !== null && $inheritedName !== ''
                ? $inheritedName . '.' . $partialName
                : $partialName;
        } else {
            $fieldName = $inheritedName;
        }

        $fieldType = $this->extractPdfNameToken($dictionary, 'FT') ?? $inheritedType;

        return [
            'dictionary' => $dictionary,
            'fieldObject' => $fieldObject,
            'fieldName' => $fieldName,
            'fieldType' => $fieldType,
            'fieldTypeLabel' => $fieldType === null ? null : $this->pdfFormFieldTypeLabel($fieldType),
        ];
    }

    /**
     * @param array<string, array{fieldName:string|null, fieldObject:string|null, fieldType:string|null, fieldTypeLabel:string|null, trigger:string, source:string, actionType:string, actionTarget:string|null, scriptBytes:int|null, scriptSha256:string|null}> $actions
     * @param array{dictionary:string, fieldObject:string|null, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null} $candidate
     * @param array<string, string> $objects
     */
    private function addPdfFormFieldActionFromNamedValue(
        array &$actions,
        array $candidate,
        string $trigger,
        string $dictionary,
        string $name,
        array $objects
    ): void {
        $value = $this->extractPdfValueForName($dictionary, $name);
        if ($value === null) {
            return;
        }

        $summary = $this->summarizePdfFormFieldActionValue($this->pdfFormFieldActionSource($candidate, $trigger), $value, $objects);
        if ($summary === null) {
            return;
        }

        $entry = [
            'fieldName' => $candidate['fieldName'],
            'fieldObject' => $candidate['fieldObject'],
            'fieldType' => $candidate['fieldType'],
            'fieldTypeLabel' => $candidate['fieldTypeLabel'],
            'trigger' => $trigger,
            'source' => $summary['source'],
            'actionType' => $summary['type'],
            'actionTarget' => $summary['target'],
            'scriptBytes' => $summary['scriptBytes'],
            'scriptSha256' => $summary['scriptSha256'],
        ];
        $key = implode("\0", [
            $entry['fieldObject'] ?? '',
            $entry['fieldName'] ?? '',
            $entry['trigger'],
            $entry['actionType'],
            $entry['actionTarget'] ?? '',
            (string) ($entry['scriptBytes'] ?? ''),
            $entry['scriptSha256'] ?? '',
        ]);
        $actions[$key] = $entry;
    }

    /**
     * @param array{dictionary:string, fieldObject:string|null, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null} $candidate
     */
    private function pdfFormFieldActionSource(array $candidate, string $trigger): string
    {
        $field = is_string($candidate['fieldObject']) && $candidate['fieldObject'] !== ''
            ? $candidate['fieldObject']
            : $this->pdfActionSourceToken($candidate['fieldName'] ?? 'unknown');

        return 'field:' . $field . '.' . $trigger;
    }

    /**
     * @param array{kind:string, value:string, next?:int} $value
     * @param array<string, string> $objects
     * @return array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}|null
     */
    private function summarizePdfFormFieldActionValue(string $source, array $value, array $objects, int $depth = 0): ?array
    {
        if ($depth > 8) {
            return null;
        }

        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return null;
            }

            $resolved = $this->parsePdfValueAt($body, 0);
            if ($resolved !== null && in_array($resolved['kind'], ['dictionary', 'reference', 'name', 'literal', 'hex'], true)) {
                return $this->summarizePdfFormFieldActionValue($source, $resolved, $objects, $depth + 1);
            }

            return $this->summarizePdfActiveActionDictionary($body, $source, $objects);
        }

        if ($value['kind'] === 'dictionary') {
            return $this->summarizePdfActiveActionDictionary($value['value'], $source, $objects);
        }

        if (in_array($value['kind'], ['name', 'literal', 'hex'], true)) {
            $target = trim($value['value']);
            if ($target === '') {
                return null;
            }

            return [
                'source' => $source,
                'type' => 'Named',
                'target' => $target,
                'scriptBytes' => null,
                'scriptSha256' => null,
            ];
        }

        return null;
    }

    /**
     * @return list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}>
     */
    private function extractPdfFormFields(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $fieldBodies = [];
        $acroForm = $this->extractPdfAcroFormDictionary($pdfBytes, $catalog);

        if ($acroForm !== null) {
            foreach ($this->extractPdfReferenceArray($acroForm, 'Fields') as $reference) {
                if (isset($objects[$reference])) {
                    $fieldBodies[$reference] = $objects[$reference];
                }
            }
        }

        if ($fieldBodies === []) {
            foreach ($this->pdfObjectBodies($pdfBytes) as $index => $body) {
                if (!str_contains($body, '/FT') || !preg_match('/\/(?:T|TU|TM)\b/s', $body)) {
                    continue;
                }

                $fieldBodies[(string) $index] = $body;
            }
        }

        $fields = [];
        foreach ($fieldBodies as $body) {
            $field = $this->summarizePdfFormField($body);
            if ($field === null) {
                continue;
            }

            $fields[$field['name'] . "\0" . $field['type']] = $field;
        }

        $fields = array_values($fields);
        usort($fields, static fn (array $a, array $b): int => [$a['name'], $a['type']] <=> [$b['name'], $b['type']]);

        return $fields;
    }

    private function extractPdfAcroFormDictionary(string $pdfBytes, ?string $catalog): ?string
    {
        if ($catalog !== null) {
            if (preg_match('/\/AcroForm\s+(\d+)\s+(\d+)\s+R\b/s', $catalog, $matches) === 1) {
                $objects = $this->pdfObjectBodiesByReference($pdfBytes);
                $key = $matches[1] . ' ' . $matches[2];
                if (isset($objects[$key])) {
                    return $objects[$key];
                }
            }

            $nested = $this->extractPdfNestedDictionary($catalog, 'AcroForm');
            if ($nested !== null) {
                return $nested;
            }
        }

        foreach ($this->pdfObjectBodies($pdfBytes) as $body) {
            if (
                str_contains($body, '/Fields')
                && (
                    str_contains($body, '/NeedAppearances')
                    || str_contains($body, '/SigFlags')
                    || str_contains($body, '/DR')
                )
            ) {
                return $body;
            }
        }

        return null;
    }

    /**
     * @return array{fieldReferences:list<string>, fieldCount:int, needAppearances:bool|null, sigFlags:int|null, sigFlagNames:list<string>, defaultResourcesPresent:bool, defaultAppearance:string|null, quadding:int|null, calculationOrder:list<string>, xfaPresent:bool, xfaPacketNames:list<string>}|array{}
     */
    private function extractPdfAcroFormMetadata(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $acroForm = $this->extractPdfAcroFormDictionary($pdfBytes, $catalog);
        if ($acroForm === null) {
            return [];
        }

        $fieldReferences = array_map(
            static fn (string $reference): string => $reference . ' R',
            $this->extractPdfReferenceArray($acroForm, 'Fields')
        );
        $calculationOrder = array_map(
            static fn (string $reference): string => $reference . ' R',
            $this->extractPdfReferenceArray($acroForm, 'CO')
        );
        $xfaValue = $this->extractPdfValueForName($acroForm, 'XFA');

        return [
            'fieldReferences' => $fieldReferences,
            'fieldCount' => count($fieldReferences),
            'needAppearances' => $this->extractPdfBooleanToken($acroForm, 'NeedAppearances'),
            'sigFlags' => $this->extractPdfIntegerToken($acroForm, 'SigFlags'),
            'sigFlagNames' => $this->pdfAcroFormSigFlagNames($this->extractPdfIntegerToken($acroForm, 'SigFlags') ?? 0),
            'defaultResourcesPresent' => $this->extractPdfDictionaryOrReferenceValue($acroForm, 'DR', $objects) !== null,
            'defaultAppearance' => $this->extractPdfStringOrNameValue($acroForm, 'DA'),
            'quadding' => $this->extractPdfIntegerToken($acroForm, 'Q'),
            'calculationOrder' => $calculationOrder,
            'xfaPresent' => $xfaValue !== null,
            'xfaPacketNames' => $xfaValue === null ? [] : $this->extractPdfXfaPacketNames($xfaValue, $objects),
        ];
    }

    /**
     * @return list<array{order:int, fieldObject:string, fieldName:string|null, fieldType:string|null, fieldTypeLabel:string|null, alternateName:string|null, mappingName:string|null, flags:int|null, flagNames:list<string>, missing:bool}>
     */
    private function extractPdfAcroFormCalculationOrder(string $pdfBytes, ?string $catalog): array
    {
        $objects = $this->pdfObjectBodiesByReference($pdfBytes);
        $acroForm = $this->extractPdfAcroFormDictionary($pdfBytes, $catalog);
        if ($acroForm === null) {
            return [];
        }

        $entries = [];
        foreach ($this->extractPdfReferenceArray($acroForm, 'CO') as $index => $reference) {
            $fieldObject = $reference . ' R';
            $body = $objects[$reference] ?? null;
            if ($body === null) {
                $entries[] = [
                    'order' => $index + 1,
                    'fieldObject' => $fieldObject,
                    'fieldName' => null,
                    'fieldType' => null,
                    'fieldTypeLabel' => null,
                    'alternateName' => null,
                    'mappingName' => null,
                    'flags' => null,
                    'flagNames' => [],
                    'missing' => true,
                ];
                continue;
            }

            $field = $this->summarizePdfFormField($body);
            $fieldType = $field['type'] ?? $this->extractPdfNameToken($body, 'FT');
            $flags = isset($field['flags']) && is_int($field['flags'])
                ? $field['flags']
                : $this->extractPdfIntegerToken($body, 'Ff');

            $entries[] = [
                'order' => $index + 1,
                'fieldObject' => $fieldObject,
                'fieldName' => $field['name'] ?? null,
                'fieldType' => $fieldType,
                'fieldTypeLabel' => $fieldType === null ? null : $this->pdfFormFieldTypeLabel($fieldType),
                'alternateName' => $field['alternateName'] ?? $this->extractPdfStringOrNameValue($body, 'TU'),
                'mappingName' => $field['mappingName'] ?? $this->extractPdfStringOrNameValue($body, 'TM'),
                'flags' => $flags,
                'flagNames' => $fieldType === null || $flags === null ? [] : $this->pdfFormFieldFlagNames($fieldType, $flags),
                'missing' => false,
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function pdfAcroFormSigFlagNames(int $flags): array
    {
        $names = [];
        if (($flags & 1) !== 0) {
            $names[] = 'signaturesExist';
        }
        if (($flags & 2) !== 0) {
            $names[] = 'appendOnly';
        }

        return $names;
    }

    /**
     * @param array{kind:string, value:string, next?:int} $value
     * @param array<string, string> $objects
     * @return list<string>
     */
    private function extractPdfXfaPacketNames(array $value, array $objects, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }
        if ($value['kind'] === 'array') {
            return $this->collectPdfStringsFromArray($value['value']);
        }
        if ($value['kind'] !== 'reference') {
            return [];
        }

        $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
        if ($body === null) {
            return [];
        }

        $resolved = $this->parsePdfValueAt($body, 0);

        return $resolved === null ? [] : $this->extractPdfXfaPacketNames($resolved, $objects, $depth + 1);
    }

    /**
     * @return list<string>
     */
    private function extractPdfReferenceArray(string $dictionary, string $name): array
    {
        $array = $this->extractPdfArrayValue($dictionary, $name);
        if ($array === null || preg_match_all('/\b(\d+)\s+(\d+)\s+R\b/s', $array, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $references = [];
        foreach ($matches as $match) {
            $references[] = $match[1] . ' ' . $match[2];
        }

        return array_values(array_unique($references));
    }

    /**
     * @return array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}|null
     */
    private function summarizePdfFormField(string $fieldDictionary): ?array
    {
        $type = $this->extractPdfNameToken($fieldDictionary, 'FT') ?? 'unknown';
        $name = $this->extractPdfStringOrNameValue($fieldDictionary, 'T');
        if ($name === null) {
            $name = $this->extractPdfStringOrNameValue($fieldDictionary, 'TU')
                ?? $this->extractPdfStringOrNameValue($fieldDictionary, 'TM');
        }
        if ($name === null || $name === '') {
            return null;
        }

        $flags = $this->extractPdfIntegerToken($fieldDictionary, 'Ff') ?? 0;

        return [
            'name' => $name,
            'type' => $type,
            'typeLabel' => $this->pdfFormFieldTypeLabel($type),
            'alternateName' => $this->extractPdfStringOrNameValue($fieldDictionary, 'TU'),
            'mappingName' => $this->extractPdfStringOrNameValue($fieldDictionary, 'TM'),
            'value' => $this->extractPdfStringOrNameValue($fieldDictionary, 'V'),
            'defaultValue' => $this->extractPdfStringOrNameValue($fieldDictionary, 'DV'),
            'flags' => $flags,
            'flagNames' => $this->pdfFormFieldFlagNames($type, $flags),
            'options' => $this->extractPdfStringArrayValue($fieldDictionary, 'Opt'),
        ];
    }

    private function pdfFormFieldTypeLabel(string $type): string
    {
        return match ($type) {
            'Btn' => 'button',
            'Ch' => 'choice',
            'Sig' => 'signature',
            'Tx' => 'text',
            default => $type === '' ? 'unknown' : $type,
        };
    }

    /**
     * @param list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}> $fields
     * @return array<string, int>
     */
    private function summarizePdfFormFieldTypes(array $fields): array
    {
        $types = [];
        foreach ($fields as $field) {
            $label = $field['typeLabel'];
            $types[$label] = ($types[$label] ?? 0) + 1;
        }

        ksort($types);

        return $types;
    }

    /**
     * @param list<array{actionType:string}> $actions
     * @return array<string, int>
     */
    private function summarizePdfFormFieldActionTypes(array $actions): array
    {
        $types = [];
        foreach ($actions as $action) {
            $type = $action['actionType'];
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        ksort($types);

        return $types;
    }

    private function extractPdfStringOrNameValue(string $dictionary, string $name): ?string
    {
        foreach ($this->extractPdfNamedStrings($dictionary, $name) as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        $value = $this->extractPdfNameToken($dictionary, $name);

        return $value === null || $value === '' ? null : $value;
    }

    private function extractPdfByteStringHexValue(string $dictionary, string $name): ?string
    {
        $needle = '/' . $name;
        $offset = 0;
        $length = strlen($dictionary);
        while (($position = strpos($dictionary, $needle, $offset)) !== false) {
            $cursor = $position + strlen($needle);
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $dictionary[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $length) {
                return null;
            }
            if ($dictionary[$cursor] === '<' && ($cursor + 1 >= $length || $dictionary[$cursor + 1] !== '<')) {
                $end = strpos($dictionary, '>', $cursor + 1);
                if ($end === false) {
                    return null;
                }

                $hex = strtoupper(preg_replace('/\s+/', '', substr($dictionary, $cursor + 1, $end - $cursor - 1)) ?? '');

                return $hex === '' ? null : $hex;
            }

            if ($dictionary[$cursor] === '(') {
                $parsed = $this->parsePdfLiteralString($dictionary, $cursor);
                if ($parsed !== null && $parsed['value'] !== '') {
                    return strtoupper(bin2hex($parsed['value']));
                }
            }

            $offset = $cursor + 1;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractPdfStringArrayValue(string $dictionary, string $name): array
    {
        $array = $this->extractPdfArrayValue($dictionary, $name);
        if ($array === null) {
            return [];
        }

        $values = [];
        $cursor = 0;
        $length = strlen($array);
        while ($cursor < $length) {
            $parsed = null;
            if ($array[$cursor] === '(') {
                $parsed = $this->parsePdfLiteralString($array, $cursor);
            } elseif (
                $array[$cursor] === '<'
                && ($cursor + 1 >= $length || $array[$cursor + 1] !== '<')
            ) {
                $parsed = $this->parsePdfHexString($array, $cursor);
            }

            if ($parsed !== null) {
                $value = trim($parsed['value']);
                if ($value !== '') {
                    $values[] = $value;
                }
                $cursor = $parsed['next'];
                continue;
            }

            $cursor++;
        }

        return array_values(array_unique($values));
    }

    private function extractPdfArrayValue(string $dictionary, string $name): ?string
    {
        $needle = '/' . $name;
        $offset = 0;
        $length = strlen($dictionary);
        while (($position = strpos($dictionary, $needle, $offset)) !== false) {
            $cursor = $position + strlen($needle);
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $dictionary[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($dictionary[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $length || $dictionary[$cursor] !== '[') {
                $offset = $cursor + 1;
                continue;
            }

            $parsed = $this->parsePdfArray($dictionary, $cursor);
            if ($parsed !== null) {
                return $parsed['value'];
            }

            $offset = $cursor + 1;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pdfFormFieldFlagNames(string $type, int $flags): array
    {
        if ($flags === 0) {
            return [];
        }

        $definitions = [
            0x000001 => 'readOnly',
            0x000002 => 'required',
            0x000004 => 'noExport',
        ];
        if ($type === 'Btn') {
            $definitions += [
                0x004000 => 'noToggleToOff',
                0x008000 => 'radio',
                0x010000 => 'pushbutton',
                0x2000000 => 'radiosInUnison',
            ];
        } elseif ($type === 'Tx') {
            $definitions += [
                0x001000 => 'multiline',
                0x002000 => 'password',
                0x100000 => 'fileSelect',
                0x400000 => 'doNotSpellCheck',
                0x800000 => 'doNotScroll',
                0x1000000 => 'comb',
                0x2000000 => 'richText',
            ];
        } elseif ($type === 'Ch') {
            $definitions += [
                0x020000 => 'combo',
                0x040000 => 'edit',
                0x080000 => 'sort',
                0x200000 => 'multiSelect',
                0x400000 => 'doNotSpellCheck',
                0x4000000 => 'commitOnSelChange',
            ];
        }

        $names = [];
        foreach ($definitions as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function extractPdfNamedStrings(string $objectBody, string $name): array
    {
        $values = [];
        $needle = '/' . $name;
        $offset = 0;
        $length = strlen($objectBody);
        while (($position = strpos($objectBody, $needle, $offset)) !== false) {
            $cursor = $position + strlen($needle);
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $objectBody[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($objectBody[$cursor])) {
                $cursor++;
            }

            $parsed = null;
            if ($cursor < $length && $objectBody[$cursor] === '(') {
                $parsed = $this->parsePdfLiteralString($objectBody, $cursor);
            } elseif (
                $cursor < $length
                && $objectBody[$cursor] === '<'
                && ($cursor + 1 >= $length || $objectBody[$cursor + 1] !== '<')
            ) {
                $parsed = $this->parsePdfHexString($objectBody, $cursor);
            }

            if ($parsed !== null) {
                $values[] = $parsed['value'];
                $offset = $parsed['next'];
                continue;
            }

            $offset = $cursor;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function extractPdfNamesArrayStrings(string $objectBody): array
    {
        $values = [];
        $offset = 0;
        $length = strlen($objectBody);
        while (($position = strpos($objectBody, '/Names', $offset)) !== false) {
            $cursor = $position + strlen('/Names');
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $objectBody[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($objectBody[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $length || $objectBody[$cursor] !== '[') {
                $offset = $cursor + 1;
                continue;
            }

            $cursor++;
            while ($cursor < $length) {
                if ($objectBody[$cursor] === ']') {
                    break;
                }

                $parsed = null;
                if ($objectBody[$cursor] === '(') {
                    $parsed = $this->parsePdfLiteralString($objectBody, $cursor);
                } elseif (
                    $objectBody[$cursor] === '<'
                    && ($cursor + 1 >= $length || $objectBody[$cursor + 1] !== '<')
                ) {
                    $parsed = $this->parsePdfHexString($objectBody, $cursor);
                }

                if ($parsed !== null) {
                    $values[] = $parsed['value'];
                    $cursor = $parsed['next'];
                    continue;
                }

                $cursor++;
            }

            $offset = $cursor + 1;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function pdfObjectBodies(string $pdfBytes): array
    {
        if (preg_match_all('/\b\d+\s+\d+\s+obj\b(.*?)\bendobj\b/s', $pdfBytes, $matches) < 1) {
            return [];
        }

        return $matches[1];
    }

    /**
     * @return array<string, string>
     */
    private function pdfObjectBodiesByReference(string $pdfBytes): array
    {
        if (preg_match_all('/\b(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj\b/s', $pdfBytes, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objects[$match[1] . ' ' . $match[2]] = $match[3];
        }

        return $objects;
    }

    /**
     * @return list<string>
     */
    private function extractPdfTitleStrings(string $objectBody): array
    {
        $titles = [];
        $offset = 0;
        $length = strlen($objectBody);
        while (($position = strpos($objectBody, '/Title', $offset)) !== false) {
            $cursor = $position + strlen('/Title');
            if ($cursor < $length && preg_match('/[A-Za-z0-9_.-]/', $objectBody[$cursor]) === 1) {
                $offset = $cursor;
                continue;
            }
            while ($cursor < $length && ctype_space($objectBody[$cursor])) {
                $cursor++;
            }

            $parsed = null;
            if ($cursor < $length && $objectBody[$cursor] === '(') {
                $parsed = $this->parsePdfLiteralString($objectBody, $cursor);
            } elseif (
                $cursor < $length
                && $objectBody[$cursor] === '<'
                && ($cursor + 1 >= $length || $objectBody[$cursor + 1] !== '<')
            ) {
                $parsed = $this->parsePdfHexString($objectBody, $cursor);
            }

            if ($parsed !== null) {
                $title = trim($parsed['value']);
                if ($title !== '') {
                    $titles[] = $title;
                }
                $offset = $parsed['next'];
                continue;
            }

            $offset = $cursor + 1;
        }

        return $titles;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function parsePdfDictionary(string $bytes, int $offset): ?array
    {
        $length = strlen($bytes);
        if ($offset + 1 >= $length || substr($bytes, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 1;
        for ($i = $offset + 2; $i < $length - 1; $i++) {
            if ($bytes[$i] === '(') {
                $end = $this->pdfLiteralStringEnd($bytes, $i);
                if ($end !== null) {
                    $i = $end - 1;
                }
                continue;
            }
            if ($bytes[$i] === '<' && $bytes[$i + 1] === '<') {
                $depth++;
                $i++;
                continue;
            }
            if ($bytes[$i] === '>' && $bytes[$i + 1] === '>') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return ['value' => substr($bytes, $offset, $i + 1 - $offset), 'next' => $i + 1];
                }
            }
        }

        return null;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function parsePdfArray(string $bytes, int $offset): ?array
    {
        $length = strlen($bytes);
        if ($offset >= $length || $bytes[$offset] !== '[') {
            return null;
        }

        $depth = 1;
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($bytes[$i] === '(') {
                $end = $this->pdfLiteralStringEnd($bytes, $i);
                if ($end !== null) {
                    $i = $end - 1;
                }
                continue;
            }
            if ($bytes[$i] === '<' && $i + 1 < $length && $bytes[$i + 1] === '<') {
                $parsed = $this->parsePdfDictionary($bytes, $i);
                if ($parsed !== null) {
                    $i = $parsed['next'] - 1;
                }
                continue;
            }
            if ($bytes[$i] === '[') {
                $depth++;
                continue;
            }
            if ($bytes[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    return ['value' => substr($bytes, $offset, $i + 1 - $offset), 'next' => $i + 1];
                }
            }
        }

        return null;
    }

    private function pdfLiteralStringEnd(string $bytes, int $offset): ?int
    {
        $length = strlen($bytes);
        if ($offset >= $length || $bytes[$offset] !== '(') {
            return null;
        }

        $depth = 1;
        for ($i = $offset + 1; $i < $length; $i++) {
            $char = $bytes[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $i++;
                }
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function parsePdfLiteralString(string $bytes, int $offset): ?array
    {
        $length = strlen($bytes);
        if ($offset >= $length || $bytes[$offset] !== '(') {
            return null;
        }

        $depth = 1;
        $value = '';
        for ($i = $offset + 1; $i < $length; $i++) {
            $char = $bytes[$i];
            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    break;
                }
                $next = $bytes[++$i];
                if ($next === "\r" || $next === "\n") {
                    if ($next === "\r" && $i + 1 < $length && $bytes[$i + 1] === "\n") {
                        $i++;
                    }
                    continue;
                }
                if ($next >= '0' && $next <= '7') {
                    $octal = $next;
                    for ($j = 0; $j < 2 && $i + 1 < $length && $bytes[$i + 1] >= '0' && $bytes[$i + 1] <= '7'; $j++) {
                        $octal .= $bytes[++$i];
                    }
                    $value .= chr(octdec($octal));
                    continue;
                }

                $value .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0c",
                    default => $next,
                };
                continue;
            }
            if ($char === '(') {
                $depth++;
                $value .= $char;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return ['value' => $this->pdfTextBytesToUtf8($value), 'next' => $i + 1];
                }
                $value .= $char;
                continue;
            }

            $value .= $char;
        }

        return null;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function parsePdfHexString(string $bytes, int $offset): ?array
    {
        $end = strpos($bytes, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        $hex = preg_replace('/\s+/', '', substr($bytes, $offset + 1, $end - $offset - 1)) ?? '';
        if ($hex === '' || preg_match('/\A[0-9A-Fa-f]+\z/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $raw = hex2bin($hex);
        if ($raw === false) {
            return null;
        }

        return ['value' => $this->pdfTextBytesToUtf8($raw), 'next' => $end + 1];
    }

    private function pdfTextBytesToUtf8(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return $this->utf16BytesToUtf8(substr($bytes, 2), true);
        }
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return $this->utf16BytesToUtf8(substr($bytes, 2), false);
        }
        if (preg_match('//u', $bytes) === 1) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', '', $bytes) ?? $bytes;
        }

        return preg_replace('/[^\x20-\x7E]+/', '', $bytes) ?? '';
    }

    private function utf16BytesToUtf8(string $bytes, bool $bigEndian): string
    {
        $output = '';
        $length = strlen($bytes) - (strlen($bytes) % 2);
        for ($i = 0; $i < $length; $i += 2) {
            $first = ord($bytes[$i]);
            $second = ord($bytes[$i + 1]);
            $codepoint = $bigEndian ? (($first << 8) | $second) : (($second << 8) | $first);
            if ($codepoint >= 0xD800 && $codepoint <= 0xDBFF && $i + 3 < $length) {
                $third = ord($bytes[$i + 2]);
                $fourth = ord($bytes[$i + 3]);
                $low = $bigEndian ? (($third << 8) | $fourth) : (($fourth << 8) | $third);
                if ($low >= 0xDC00 && $low <= 0xDFFF) {
                    $codepoint = 0x10000 + (($codepoint - 0xD800) << 10) + ($low - 0xDC00);
                    $i += 2;
                }
            }
            if ($codepoint === 0) {
                continue;
            }

            $output .= $this->codepointToUtf8($codepoint);
        }

        return $output;
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10FFFF || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            return '';
        }
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
    }

    private function normalizeRelativePath(string $path, string $label): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            throw new \InvalidArgumentException($label . ' must not be empty');
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
        if (str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:\//', $path) === 1) {
            throw new \InvalidArgumentException($label . ' must be relative to the handoff workspace');
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \InvalidArgumentException($label . ' must not contain parent-directory segments');
            }
            $parts[] = $part;
        }

        if ($parts === []) {
            throw new \InvalidArgumentException($label . ' must name a file');
        }

        return implode('/', $parts);
    }

    /**
     * @return list<string>
     */
    private function normalizeRelativePathList(mixed $value, string $label): array
    {
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException($label . ' list must contain strings');
        }

        $paths = [];
        foreach ($value as $path) {
            $paths[] = $this->normalizeRelativePath($this->requireString($path, $label), $label);
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function normalizeResourceFileList(mixed $value, string $label): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException($label . ' list must contain strings');
        }

        $paths = [];
        foreach ($value as $path) {
            $path = str_replace('\\', '/', trim($this->requireString($path, $label)));
            if ($this->isUriResourceReference($path)) {
                throw new \InvalidArgumentException($label . ' must be a relative file path, not a URI');
            }
            if (str_starts_with($path, '#') || str_contains($path, '?') || str_contains($path, '#')) {
                throw new \InvalidArgumentException($label . ' must be a direct relative file path without query or fragment');
            }
            $paths[] = $this->normalizeRelativePath($path, $label);
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('PDF engine options must be strings');
        }

        $strings = [];
        foreach ($value as $item) {
            $strings[] = $this->requireString($item, 'PDF engine option');
        }

        return $strings;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeVariables(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new \InvalidArgumentException('PDF template variables must be a keyed array');
        }

        $variables = [];
        foreach ($value as $name => $item) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('PDF template variable names must be strings');
            }
            $this->requireTemplateVariableName($name);
            $variables[$name] = $this->normalizeVariableValue($item, $name);
        }

        return $variables;
    }

    private function normalizeVariableValue(mixed $value, string $name): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            if (str_contains($value, "\0")) {
                throw new \InvalidArgumentException('PDF template variable ' . $name . ' must not contain NUL bytes');
            }

            return $value;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('PDF template variable ' . $name . ' must be scalar or an array');
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $this->requireTemplateVariableName($key);
            }
            $normalized[$key] = $this->normalizeVariableValue($item, $name . '.' . $key);
        }

        return $normalized;
    }

    private function requireTemplateVariableName(string $name): void
    {
        if ($name === '' || preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_.:-]*\z/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid PDF template variable name: ' . $name);
        }
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, string>
     */
    private function normalizeFileMap(array $files): array
    {
        $normalized = [];
        foreach ($files as $path => $bytes) {
            $normalized[$this->normalizeRelativePath((string) $path, 'fake runner file path')] = $this->requireFileBytes($bytes);
        }

        return $normalized;
    }

    private function requireFileBytes(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('fake runner file bytes must be a string');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function normalizePlanStringList(mixed $value, string $label): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException($label . ' plan key must be a list of strings');
        }

        $strings = [];
        foreach ($value as $item) {
            $strings[] = $this->normalizeRelativePath($this->requireString($item, $label), $label);
        }

        return $strings;
    }

    private function requireString(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($label . ' must be a string');
        }
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' must not be empty');
        }
        if (str_contains($value, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function requirePlanString(array $plan, string $key): string
    {
        if (!isset($plan[$key]) || !is_string($plan[$key]) || $plan[$key] === '') {
            throw new \InvalidArgumentException('PDF fake runner requires plan key: ' . $key);
        }

        return $plan[$key];
    }
}
