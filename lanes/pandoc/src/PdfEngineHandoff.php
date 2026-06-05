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
    private const MAX_EMBEDDED_FONT_STREAM_BYTES = 262144;
    private const MAX_IMAGE_STREAM_BYTES = 262144;
    private const MAX_FORM_XOBJECT_STREAM_BYTES = 262144;
    private const MAX_XREF_STREAM_BYTES = 262144;
    private const MAX_OBJECT_STREAM_BYTES = 262144;
    private const MAX_TRANSCRIPT_BYTES = 1048576;

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
     *     pdfPageLabels: list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>,
     *     pdfPageTimings: list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>,
     *     pdfFonts: list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>,
     *     pdfFontSubtypes: array<string, int>,
     *     pdfImages: list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfImageColorSpaces: array<string, int>,
     *     pdfImageFilters: array<string, int>,
     *     pdfFormXObjects: list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     pdfFormXObjectFilters: array<string, int>,
     *     pdfOutlineTitles: list<string>,
     *     pdfOutlines: list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}>,
     *     pdfDocumentInfo: array<string, string>,
     *     pdfXmpMetadata: array<string, mixed>,
     *     pdfOutputIntents: list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     pdfLanguage: string|null,
     *     pdfPageLayout: string|null,
     *     pdfPageMode: string|null,
     *     pdfOpenAction: array<string, mixed>|null,
     *     pdfNamedDestinations: list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>,
     *     pdfViewerPreferences: array<string, bool|int|string>,
     *     pdfTaggingMetadata: array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{},
     *     pdfStructureElements: list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>,
     *     pdfOptionalContentGroups: list<array{object:string, name:string|null, intent:list<string>, usageViewState:string|null, usagePrintState:string|null, usageExportState:string|null, usageCreator:string|null, usageCreatorSubtype:string|null, usageLanguage:string|null, usageLanguagePreferred:bool|null, usageZoomMin:float|null, usageZoomMax:float|null}>,
     *     pdfOptionalContentConfig: array{name:string|null, creator:string|null, baseState:string|null, listMode:string|null, on:list<string>, off:list<string>, order:list<string>, orderLabels:list<string>}|array{},
     *     pdfCollectionMetadata: array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{},
     *     pdfAcroFormMetadata: array{fieldReferences:list<string>, fieldCount:int, needAppearances:bool|null, sigFlags:int|null, sigFlagNames:list<string>, defaultResourcesPresent:bool, defaultAppearance:string|null, quadding:int|null, calculationOrder:list<string>, xfaPresent:bool, xfaPacketNames:list<string>}|array{},
     *     pdfThreads: list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>,
     *     pdfSignatures: list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     pdfSignatureSubFilters: array<string, int>,
     *     pdfActiveActions: list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     pdfActiveActionTypes: array<string, int>,
     *     pdfAnnotationTypes: array<string, int>,
     *     pdfLinkTargets: list<string>,
     *     pdfEmbeddedFileNames: list<string>,
     *     pdfEmbeddedFiles: list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}>,
     *     pdfFormFields: list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}>,
     *     pdfFormFieldTypes: array<string, int>,
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
        $pdfPageLabels = [];
        $pdfPageTimings = [];
        $pdfFonts = [];
        $pdfFontSubtypes = [];
        $pdfImages = [];
        $pdfImageColorSpaces = [];
        $pdfImageFilters = [];
        $pdfFormXObjects = [];
        $pdfFormXObjectFilters = [];
        $pdfOutlineTitles = [];
        $pdfOutlines = [];
        $pdfDocumentInfo = [];
        $pdfXmpMetadata = [];
        $pdfOutputIntents = [];
        $pdfLanguage = null;
        $pdfPageLayout = null;
        $pdfPageMode = null;
        $pdfOpenAction = null;
        $pdfNamedDestinations = [];
        $pdfViewerPreferences = [];
        $pdfTaggingMetadata = [];
        $pdfStructureElements = [];
        $pdfOptionalContentGroups = [];
        $pdfOptionalContentConfig = [];
        $pdfCollectionMetadata = [];
        $pdfAcroFormMetadata = [];
        $pdfThreads = [];
        $pdfSignatures = [];
        $pdfSignatureSubFilters = [];
        $pdfActiveActions = [];
        $pdfActiveActionTypes = [];
        $pdfAnnotationTypes = [];
        $pdfLinkTargets = [];
        $pdfEmbeddedFileNames = [];
        $pdfEmbeddedFiles = [];
        $pdfFormFields = [];
        $pdfFormFieldTypes = [];
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
                $pdfPageLabels = $pdfInspection['pageLabels'];
                $pdfPageTimings = $pdfInspection['pageTimings'];
                $pdfFonts = $pdfInspection['fonts'];
                $pdfFontSubtypes = $pdfInspection['fontSubtypes'];
                $pdfImages = $pdfInspection['images'];
                $pdfImageColorSpaces = $pdfInspection['imageColorSpaces'];
                $pdfImageFilters = $pdfInspection['imageFilters'];
                $pdfFormXObjects = $pdfInspection['formXObjects'];
                $pdfFormXObjectFilters = $pdfInspection['formXObjectFilters'];
                $pdfOutlineTitles = $pdfInspection['outlineTitles'];
                $pdfOutlines = $pdfInspection['outlines'];
                $pdfDocumentInfo = $pdfInspection['documentInfo'];
                $pdfXmpMetadata = $pdfInspection['xmpMetadata'];
                $pdfOutputIntents = $pdfInspection['outputIntents'];
                $pdfLanguage = $pdfInspection['language'];
                $pdfPageLayout = $pdfInspection['pageLayout'];
                $pdfPageMode = $pdfInspection['pageMode'];
                $pdfOpenAction = $pdfInspection['openAction'];
                $pdfNamedDestinations = $pdfInspection['namedDestinations'];
                $pdfViewerPreferences = $pdfInspection['viewerPreferences'];
                $pdfTaggingMetadata = $pdfInspection['taggingMetadata'];
                $pdfStructureElements = $pdfInspection['structureElements'];
                $pdfOptionalContentGroups = $pdfInspection['optionalContentGroups'];
                $pdfOptionalContentConfig = $pdfInspection['optionalContentConfig'];
                $pdfCollectionMetadata = $pdfInspection['collectionMetadata'];
                $pdfAcroFormMetadata = $pdfInspection['acroFormMetadata'];
                $pdfThreads = $pdfInspection['threads'];
                $pdfSignatures = $pdfInspection['signatures'];
                $pdfSignatureSubFilters = $pdfInspection['signatureSubFilters'];
                $pdfActiveActions = $pdfInspection['activeActions'];
                $pdfActiveActionTypes = $pdfInspection['activeActionTypes'];
                $pdfAnnotationTypes = $pdfInspection['annotationTypes'];
                $pdfLinkTargets = $pdfInspection['linkTargets'];
                $pdfEmbeddedFileNames = $pdfInspection['embeddedFileNames'];
                $pdfEmbeddedFiles = $pdfInspection['embeddedFiles'];
                $pdfFormFields = $pdfInspection['formFields'];
                $pdfFormFieldTypes = $pdfInspection['formFieldTypes'];
                $pdfEncryption = $pdfInspection['encryption'];
                $pdfEncrypted = $pdfEncryption['encrypted'];
                $pdfEncryptionFilter = $pdfEncryption['filter'];
                $pdfEncryptionVersion = $pdfEncryption['version'];
                $pdfEncryptionRevision = $pdfEncryption['revision'];
                $pdfEncryptionLength = $pdfEncryption['length'];
                $pdfPermissionInteger = $pdfEncryption['permissions'];
                $pdfPermissionFlags = $pdfEncryption['permissionFlags'];
                $pdfEncryptMetadata = $pdfEncryption['encryptMetadata'];
                if ($pdfPageCount !== null) {
                    $diagnostics[] = 'pdf-byte-page-count:' . $pdfPageCount;
                }
                if ($pdfPageBoxes !== []) {
                    $diagnostics[] = 'pdf-byte-page-boxes:' . count($pdfPageBoxes);
                }
                if ($pdfPageRotations !== []) {
                    $diagnostics[] = 'pdf-byte-page-rotations:' . count($pdfPageRotations);
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
                if ($pdfDocumentInfo !== []) {
                    $diagnostics[] = 'pdf-byte-document-info:' . count($pdfDocumentInfo);
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
                if ($pdfViewerPreferences !== []) {
                    $diagnostics[] = 'pdf-byte-viewer-preferences:' . count($pdfViewerPreferences);
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
                    $embeddedStreamSkips = [];
                    foreach ($pdfEmbeddedFiles as $embeddedFile) {
                        if (($embeddedFile['streamBytes'] ?? null) !== null) {
                            $embeddedStreams++;
                        }
                        if (is_string($embeddedFile['streamSkipped'] ?? null) && $embeddedFile['streamSkipped'] !== '') {
                            $embeddedStreamSkips[$embeddedFile['streamSkipped']] = true;
                        }
                    }
                    if ($embeddedStreams > 0) {
                        $diagnostics[] = 'pdf-byte-embedded-file-streams:' . $embeddedStreams;
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
            'pdfPageLabels' => $pdfPageLabels,
            'pdfPageTimings' => $pdfPageTimings,
            'pdfFonts' => $pdfFonts,
            'pdfFontSubtypes' => $pdfFontSubtypes,
            'pdfImages' => $pdfImages,
            'pdfImageColorSpaces' => $pdfImageColorSpaces,
            'pdfImageFilters' => $pdfImageFilters,
            'pdfFormXObjects' => $pdfFormXObjects,
            'pdfFormXObjectFilters' => $pdfFormXObjectFilters,
            'pdfOutlineTitles' => $pdfOutlineTitles,
            'pdfOutlines' => $pdfOutlines,
            'pdfDocumentInfo' => $pdfDocumentInfo,
            'pdfXmpMetadata' => $pdfXmpMetadata,
            'pdfOutputIntents' => $pdfOutputIntents,
            'pdfLanguage' => $pdfLanguage,
            'pdfPageLayout' => $pdfPageLayout,
            'pdfPageMode' => $pdfPageMode,
            'pdfOpenAction' => $pdfOpenAction,
            'pdfNamedDestinations' => $pdfNamedDestinations,
            'pdfViewerPreferences' => $pdfViewerPreferences,
            'pdfTaggingMetadata' => $pdfTaggingMetadata,
            'pdfStructureElements' => $pdfStructureElements,
            'pdfOptionalContentGroups' => $pdfOptionalContentGroups,
            'pdfOptionalContentConfig' => $pdfOptionalContentConfig,
            'pdfCollectionMetadata' => $pdfCollectionMetadata,
            'pdfAcroFormMetadata' => $pdfAcroFormMetadata,
            'pdfThreads' => $pdfThreads,
            'pdfSignatures' => $pdfSignatures,
            'pdfSignatureSubFilters' => $pdfSignatureSubFilters,
            'pdfActiveActions' => $pdfActiveActions,
            'pdfActiveActionTypes' => $pdfActiveActionTypes,
            'pdfAnnotationTypes' => $pdfAnnotationTypes,
            'pdfLinkTargets' => $pdfLinkTargets,
            'pdfEmbeddedFileNames' => $pdfEmbeddedFileNames,
            'pdfEmbeddedFiles' => $pdfEmbeddedFiles,
            'pdfFormFields' => $pdfFormFields,
            'pdfFormFieldTypes' => $pdfFormFieldTypes,
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
     *     finalPdfPageCount: int|null,
     *     finalPdfPageBoxes: list<array{page:int, pageObject:string|null, mediaBox:list<float>|null, cropBox:list<float>|null, bleedBox:list<float>|null, trimBox:list<float>|null, artBox:list<float>|null, rotation:int|null, inherited:list<string>}>,
     *     finalPdfPageRotations: array<int, int>,
     *     finalPdfPageLabels: list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>,
     *     finalPdfPageTimings: list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>,
     *     finalPdfFonts: list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>,
     *     finalPdfFontSubtypes: array<string, int>,
     *     finalPdfImages: list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfImageColorSpaces: array<string, int>,
     *     finalPdfImageFilters: array<string, int>,
     *     finalPdfFormXObjects: list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     finalPdfFormXObjectFilters: array<string, int>,
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
     *     finalPdfDocumentInfo: array<string, string>,
     *     finalPdfXmpMetadata: array<string, mixed>,
     *     finalPdfOutputIntents: list<array{type:string|null, subtype:string|null, outputConditionIdentifier:string|null, outputCondition:string|null, registryName:string|null, info:string|null, destOutputProfile:string|null, profileComponents:int|null, profileAlternate:string|null, profileBytes:int|null, profileSha256:string|null, profileSkipped:string|null}>,
     *     finalPdfLanguage: string|null,
     *     finalPdfPageLayout: string|null,
     *     finalPdfPageMode: string|null,
     *     finalPdfOpenAction: array<string, mixed>|null,
     *     finalPdfNamedDestinations: list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>,
     *     finalPdfViewerPreferences: array<string, bool|int|string>,
     *     finalPdfTaggingMetadata: array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{},
     *     finalPdfStructureElements: list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>,
     *     finalPdfOptionalContentGroups: list<array{object:string, name:string|null, intent:list<string>, usageViewState:string|null, usagePrintState:string|null, usageExportState:string|null, usageCreator:string|null, usageCreatorSubtype:string|null, usageLanguage:string|null, usageLanguagePreferred:bool|null, usageZoomMin:float|null, usageZoomMax:float|null}>,
     *     finalPdfOptionalContentConfig: array{name:string|null, creator:string|null, baseState:string|null, listMode:string|null, on:list<string>, off:list<string>, order:list<string>, orderLabels:list<string>}|array{},
     *     finalPdfCollectionMetadata: array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{},
     *     finalPdfAcroFormMetadata: array{fieldReferences:list<string>, fieldCount:int, needAppearances:bool|null, sigFlags:int|null, sigFlagNames:list<string>, defaultResourcesPresent:bool, defaultAppearance:string|null, quadding:int|null, calculationOrder:list<string>, xfaPresent:bool, xfaPacketNames:list<string>}|array{},
     *     finalPdfThreads: list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>,
     *     finalPdfSignatures: list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     finalPdfSignatureSubFilters: array<string, int>,
     *     finalPdfActiveActions: list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     finalPdfActiveActionTypes: array<string, int>,
     *     finalPdfAnnotationTypes: array<string, int>,
     *     finalPdfLinkTargets: list<string>,
     *     finalPdfEmbeddedFileNames: list<string>,
     *     finalPdfEmbeddedFiles: list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}>,
     *     finalPdfFormFields: list<array{name:string, type:string, typeLabel:string, alternateName:string|null, mappingName:string|null, value:string|null, defaultValue:string|null, flags:int, flagNames:list<string>, options:list<string>}>,
     *     finalPdfFormFieldTypes: array<string, int>,
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
            'finalPdfPageCount' => is_array($finalRun) && is_int($finalRun['pdfPageCount'] ?? null) ? $finalRun['pdfPageCount'] : null,
            'finalPdfPageBoxes' => is_array($finalRun) && is_array($finalRun['pdfPageBoxes'] ?? null) ? $finalRun['pdfPageBoxes'] : [],
            'finalPdfPageRotations' => is_array($finalRun) && is_array($finalRun['pdfPageRotations'] ?? null) ? $finalRun['pdfPageRotations'] : [],
            'finalPdfPageLabels' => is_array($finalRun) && is_array($finalRun['pdfPageLabels'] ?? null) ? $finalRun['pdfPageLabels'] : [],
            'finalPdfPageTimings' => is_array($finalRun) && is_array($finalRun['pdfPageTimings'] ?? null) ? $finalRun['pdfPageTimings'] : [],
            'finalPdfFonts' => is_array($finalRun) && is_array($finalRun['pdfFonts'] ?? null) ? $finalRun['pdfFonts'] : [],
            'finalPdfFontSubtypes' => is_array($finalRun) && is_array($finalRun['pdfFontSubtypes'] ?? null) ? $finalRun['pdfFontSubtypes'] : [],
            'finalPdfImages' => is_array($finalRun) && is_array($finalRun['pdfImages'] ?? null) ? $finalRun['pdfImages'] : [],
            'finalPdfImageColorSpaces' => is_array($finalRun) && is_array($finalRun['pdfImageColorSpaces'] ?? null) ? $finalRun['pdfImageColorSpaces'] : [],
            'finalPdfImageFilters' => is_array($finalRun) && is_array($finalRun['pdfImageFilters'] ?? null) ? $finalRun['pdfImageFilters'] : [],
            'finalPdfFormXObjects' => is_array($finalRun) && is_array($finalRun['pdfFormXObjects'] ?? null) ? $finalRun['pdfFormXObjects'] : [],
            'finalPdfFormXObjectFilters' => is_array($finalRun) && is_array($finalRun['pdfFormXObjectFilters'] ?? null) ? $finalRun['pdfFormXObjectFilters'] : [],
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
            'finalPdfDocumentInfo' => is_array($finalRun) && is_array($finalRun['pdfDocumentInfo'] ?? null) ? $finalRun['pdfDocumentInfo'] : [],
            'finalPdfXmpMetadata' => is_array($finalRun) && is_array($finalRun['pdfXmpMetadata'] ?? null) ? $finalRun['pdfXmpMetadata'] : [],
            'finalPdfOutputIntents' => is_array($finalRun) && is_array($finalRun['pdfOutputIntents'] ?? null) ? $finalRun['pdfOutputIntents'] : [],
            'finalPdfLanguage' => is_array($finalRun) && is_string($finalRun['pdfLanguage'] ?? null) ? $finalRun['pdfLanguage'] : null,
            'finalPdfPageLayout' => is_array($finalRun) && is_string($finalRun['pdfPageLayout'] ?? null) ? $finalRun['pdfPageLayout'] : null,
            'finalPdfPageMode' => is_array($finalRun) && is_string($finalRun['pdfPageMode'] ?? null) ? $finalRun['pdfPageMode'] : null,
            'finalPdfOpenAction' => is_array($finalRun) && is_array($finalRun['pdfOpenAction'] ?? null) ? $finalRun['pdfOpenAction'] : null,
            'finalPdfNamedDestinations' => is_array($finalRun) && is_array($finalRun['pdfNamedDestinations'] ?? null) ? $finalRun['pdfNamedDestinations'] : [],
            'finalPdfViewerPreferences' => is_array($finalRun) && is_array($finalRun['pdfViewerPreferences'] ?? null) ? $finalRun['pdfViewerPreferences'] : [],
            'finalPdfTaggingMetadata' => is_array($finalRun) && is_array($finalRun['pdfTaggingMetadata'] ?? null) ? $finalRun['pdfTaggingMetadata'] : [],
            'finalPdfStructureElements' => is_array($finalRun) && is_array($finalRun['pdfStructureElements'] ?? null) ? $finalRun['pdfStructureElements'] : [],
            'finalPdfOptionalContentGroups' => is_array($finalRun) && is_array($finalRun['pdfOptionalContentGroups'] ?? null) ? $finalRun['pdfOptionalContentGroups'] : [],
            'finalPdfOptionalContentConfig' => is_array($finalRun) && is_array($finalRun['pdfOptionalContentConfig'] ?? null) ? $finalRun['pdfOptionalContentConfig'] : [],
            'finalPdfCollectionMetadata' => is_array($finalRun) && is_array($finalRun['pdfCollectionMetadata'] ?? null) ? $finalRun['pdfCollectionMetadata'] : [],
            'finalPdfAcroFormMetadata' => is_array($finalRun) && is_array($finalRun['pdfAcroFormMetadata'] ?? null) ? $finalRun['pdfAcroFormMetadata'] : [],
            'finalPdfThreads' => is_array($finalRun) && is_array($finalRun['pdfThreads'] ?? null) ? $finalRun['pdfThreads'] : [],
            'finalPdfSignatures' => is_array($finalRun) && is_array($finalRun['pdfSignatures'] ?? null) ? $finalRun['pdfSignatures'] : [],
            'finalPdfSignatureSubFilters' => is_array($finalRun) && is_array($finalRun['pdfSignatureSubFilters'] ?? null) ? $finalRun['pdfSignatureSubFilters'] : [],
            'finalPdfActiveActions' => is_array($finalRun) && is_array($finalRun['pdfActiveActions'] ?? null) ? $finalRun['pdfActiveActions'] : [],
            'finalPdfActiveActionTypes' => is_array($finalRun) && is_array($finalRun['pdfActiveActionTypes'] ?? null) ? $finalRun['pdfActiveActionTypes'] : [],
            'finalPdfAnnotationTypes' => is_array($finalRun) && is_array($finalRun['pdfAnnotationTypes'] ?? null) ? $finalRun['pdfAnnotationTypes'] : [],
            'finalPdfLinkTargets' => is_array($finalRun) && is_array($finalRun['pdfLinkTargets'] ?? null) ? $finalRun['pdfLinkTargets'] : [],
            'finalPdfEmbeddedFileNames' => is_array($finalRun) && is_array($finalRun['pdfEmbeddedFileNames'] ?? null) ? $finalRun['pdfEmbeddedFileNames'] : [],
            'finalPdfEmbeddedFiles' => is_array($finalRun) && is_array($finalRun['pdfEmbeddedFiles'] ?? null) ? $finalRun['pdfEmbeddedFiles'] : [],
            'finalPdfFormFields' => is_array($finalRun) && is_array($finalRun['pdfFormFields'] ?? null) ? $finalRun['pdfFormFields'] : [],
            'finalPdfFormFieldTypes' => is_array($finalRun) && is_array($finalRun['pdfFormFieldTypes'] ?? null) ? $finalRun['pdfFormFieldTypes'] : [],
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
     *     pageLabels:list<array{pageIndex:int, pageNumber:int, style:string|null, styleLabel:string|null, prefix:string, start:int, firstLabel:string, source:string}>,
     *     pageTimings:list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}>,
     *     fonts:list<array{page:int, pageObject:string|null, resourceName:string, fontObject:string|null, inherited:bool, subtype:string|null, baseFont:string|null, encoding:string|null, toUnicode:string|null, descendantFonts:list<string>, descriptor:string|null, descriptorFontName:string|null, descriptorFontFamily:string|null, descriptorFlags:int|null, descriptorItalicAngle:float|null, descriptorFontWeight:int|null, embedded:bool, embeddedFile:string|null, embeddedFileKind:string|null, embeddedFileSubtype:string|null, embeddedFileBytes:int|null, embeddedFileSha256:string|null, embeddedFileSkipped:string|null}>,
     *     fontSubtypes:array<string, int>,
     *     images:list<array{page:int, pageObject:string|null, resourceName:string, imageObject:string|null, inherited:bool, width:int|null, height:int|null, bitsPerComponent:int|null, colorSpace:string|null, filters:list<string>, interpolate:bool|null, imageMask:bool|null, softMask:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     imageColorSpaces:array<string, int>,
     *     imageFilters:array<string, int>,
     *     formXObjects:list<array{page:int, pageObject:string|null, resourceName:string, formObject:string|null, inherited:bool, bbox:list<float>|null, matrix:list<float>|null, resourcesPresent:bool, groupSubtype:string|null, groupColorSpace:string|null, groupIsolated:bool|null, groupKnockout:bool|null, filters:list<string>, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null}>,
     *     formXObjectFilters:array<string, int>,
     *     outlineTitles:list<string>,
     *     outlines:list<array{object:string, title:string, parent:string|null, prev:string|null, next:string|null, first:string|null, last:string|null, count:int|null, open:bool|null, destPageObject:string|null, destFit:string|null, actionType:string|null, actionTarget:string|null}>,
     *     documentInfo:array<string, string>,
     *     xmpMetadata:array<string, mixed>,
     *     language:string|null,
     *     pageLayout:string|null,
     *     pageMode:string|null,
     *     openAction:array<string, mixed>|null,
     *     namedDestinations:list<array{name:string, source:string, target:string|null, pageObject:string|null, fit:string|null}>,
     *     viewerPreferences:array<string, bool|int|string>,
     *     taggingMetadata:array{marked:bool|null, userProperties:bool|null, suspects:bool|null, structTreeRoot:string|null, roleMap:array<string, string>, structureChildren:int|null, parentTree:string|null, parentTreeNextKey:int|null, idTree:string|null}|array{},
     *     structureElements:list<array{object:string, type:string|null, parent:string|null, pageObject:string|null, alt:string|null, actualText:string|null, language:string|null, title:string|null, childCount:int|null}>,
     *     collectionMetadata:array{type:string|null, view:string|null, defaultDocument:string|null, schemaFields:list<array{name:string, subtype:string|null, title:string|null, order:int|null, visible:bool|null, editable:bool|null}>, sort:array{fields:list<string>, ascending:list<bool>}|array{}}|array{},
     *     threads:list<array{object:string, infoTitle:string|null, infoAuthor:string|null, infoSubject:string|null, firstBead:string|null, beadCount:int, beads:list<array{object:string, pageObject:string|null, rect:list<float>|null, next:string|null, prev:string|null}>}>,
     *     signatures:list<array{fieldName:string|null, fieldObject:string|null, signatureObject:string|null, filter:string|null, subFilter:string|null, name:string|null, reason:string|null, location:string|null, contactInfo:string|null, signingTime:string|null, byteRange:list<int>, byteRangeSegmentCount:int, coveredBytes:int|null, contentsBytes:int|null, contentsSha256:string|null, contentsSkipped:string|null, referenceTransforms:list<array{transformMethod:string|null, transformParamsType:string|null, permissions:int|null, action:string|null, fields:list<string>}>}>,
     *     signatureSubFilters:array<string, int>,
     *     activeActions:list<array{source:string, type:string, target:string|null, scriptBytes:int|null, scriptSha256:string|null}>,
     *     activeActionTypes:array<string, int>,
     *     annotationTypes:array<string, int>,
     *     linkTargets:list<string>,
     *     embeddedFileNames:list<string>,
     *     embeddedFiles:list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}>,
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
     *     }
     * }
     */
    private function inspectPdfOutput(string $pdfBytes): array
    {
        $catalog = $this->extractPdfCatalogDictionary($pdfBytes);
        $formFields = $this->extractPdfFormFields($pdfBytes, $catalog);
        $trailerRevisions = $this->extractPdfTrailerRevisions($pdfBytes);
        $xrefStreams = $this->extractPdfXrefStreams($pdfBytes);
        $objectStreams = $this->extractPdfObjectStreams($pdfBytes);
        $pageBoxes = $this->extractPdfPageBoxes($pdfBytes, $catalog);
        $pageTimings = $this->extractPdfPageTimings($pdfBytes, $catalog);
        $fonts = $this->extractPdfFonts($pdfBytes, $catalog);
        $images = $this->extractPdfImages($pdfBytes, $catalog);
        $formXObjects = $this->extractPdfFormXObjects($pdfBytes, $catalog);
        $optionalContent = $this->extractPdfOptionalContent($pdfBytes, $catalog);
        $signatures = $this->extractPdfSignatures($pdfBytes, $catalog);
        $activeActions = $this->extractPdfActiveActions($pdfBytes, $catalog);
        $embeddedFiles = $this->extractPdfEmbeddedFiles($pdfBytes, $catalog);
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
            'pageLabels' => $this->extractPdfPageLabels($pdfBytes, $catalog),
            'pageTimings' => $pageTimings,
            'fonts' => $fonts,
            'fontSubtypes' => $this->summarizePdfFontSubtypes($fonts),
            'images' => $images,
            'imageColorSpaces' => $this->summarizePdfImageColorSpaces($images),
            'imageFilters' => $this->summarizePdfImageFilters($images),
            'formXObjects' => $formXObjects,
            'formXObjectFilters' => $this->summarizePdfFormXObjectFilters($formXObjects),
            'outlineTitles' => $this->extractPdfOutlineTitles($pdfBytes),
            'outlines' => $this->extractPdfOutlines($pdfBytes, $catalog),
            'documentInfo' => $this->extractPdfDocumentInfo($pdfBytes),
            'xmpMetadata' => $this->extractPdfXmpMetadata($pdfBytes, $catalog),
            'outputIntents' => $this->extractPdfOutputIntents($pdfBytes, $catalog),
            'language' => $this->extractPdfCatalogLanguage($pdfBytes, $catalog),
            'pageLayout' => $this->extractPdfCatalogName($catalog, 'PageLayout'),
            'pageMode' => $this->extractPdfCatalogName($catalog, 'PageMode'),
            'openAction' => $this->extractPdfOpenAction($pdfBytes, $catalog),
            'namedDestinations' => $this->extractPdfNamedDestinations($pdfBytes, $catalog),
            'viewerPreferences' => $this->extractPdfViewerPreferences($pdfBytes, $catalog),
            'taggingMetadata' => $this->extractPdfTaggingMetadata($pdfBytes, $catalog),
            'structureElements' => $this->extractPdfStructureElements($pdfBytes),
            'optionalContentGroups' => $optionalContent['groups'],
            'optionalContentConfig' => $optionalContent['config'],
            'collectionMetadata' => $this->extractPdfCollectionMetadata($pdfBytes, $catalog),
            'acroFormMetadata' => $this->extractPdfAcroFormMetadata($pdfBytes, $catalog),
            'threads' => $this->extractPdfThreads($pdfBytes, $catalog),
            'signatures' => $signatures,
            'signatureSubFilters' => $this->summarizePdfSignatureSubFilters($signatures),
            'activeActions' => $activeActions,
            'activeActionTypes' => $this->summarizePdfActiveActionTypes($activeActions),
            'annotationTypes' => $this->extractPdfAnnotationTypes($pdfBytes),
            'linkTargets' => $this->extractPdfLinkTargets($pdfBytes),
            'embeddedFileNames' => $embeddedFileNames,
            'embeddedFiles' => $embeddedFiles,
            'formFields' => $formFields,
            'formFieldTypes' => $this->summarizePdfFormFieldTypes($formFields),
            'encryption' => $this->extractPdfEncryptionInfo($pdfBytes),
        ];
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
     * @return array<string, mixed>
     */
    private function extractPdfXmpMetadata(string $pdfBytes, ?string $catalog): array
    {
        $stream = $this->extractPdfMetadataStream($pdfBytes, $catalog);
        if ($stream === null) {
            return [];
        }

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

        $pdfaPart = $this->xmpScalarText($xml, 'part');
        $pdfaConformance = $this->xmpScalarText($xml, 'conformance');
        if ($pdfaPart !== null || $pdfaConformance !== null) {
            $metadata['pdfaIdentification'] = [
                'part' => $pdfaPart,
                'conformance' => $pdfaConformance,
            ];
        }

        return $metadata;
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
        $block = $this->xmpElementBlock($xml, $name);
        if ($block === null) {
            return $this->xmpScalarText($xml, $name);
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
            $values = $this->extractPdfNamedStrings($dictionary, 'F');
            $target = $values === [] ? null : trim($values[0]);

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
    private function addPdfActiveActionFromValue(array &$actions, string $source, array $value, array $objects, ?string $nameHint = null): void
    {
        if ($value['kind'] === 'reference') {
            $body = $objects[$this->pdfReferenceKey($value['value'])] ?? null;
            if ($body === null) {
                return;
            }

            $summary = $this->summarizePdfActiveActionDictionary($body, $source, $objects, $nameHint);
        } elseif ($value['kind'] === 'dictionary') {
            $summary = $this->summarizePdfActiveActionDictionary($value['value'], $source, $objects, $nameHint);
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
            'target' => $this->pdfActiveActionTarget($dictionary, $type, $nameHint),
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

    private function pdfActiveActionTarget(string $dictionary, string $type, ?string $nameHint): ?string
    {
        if ($type === 'JavaScript') {
            return $nameHint;
        }
        if ($type === 'Named') {
            return $this->extractPdfStringOrNameValue($dictionary, 'N');
        }
        if (in_array($type, ['Launch', 'SubmitForm', 'GoToR', 'ImportData'], true)) {
            return $this->extractPdfStringOrNameValue($dictionary, 'F');
        }
        if ($type === 'Hide') {
            return $this->extractPdfStringOrNameValue($dictionary, 'T');
        }

        return null;
    }

    private function pdfActionSourceToken(string $value): string
    {
        $token = preg_replace('/[^A-Za-z0-9_.:-]+/', '-', trim($value)) ?? '';
        $token = trim($token, '-');

        return $token === '' ? 'unnamed' : $token;
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function extractPdfViewerPreferences(string $pdfBytes, ?string $catalog): array
    {
        if ($catalog === null || !str_contains($catalog, '/ViewerPreferences')) {
            return [];
        }

        $dictionary = null;
        if (preg_match('/\/ViewerPreferences\s+(\d+)\s+(\d+)\s+R\b/s', $catalog, $matches) === 1) {
            $objects = $this->pdfObjectBodiesByReference($pdfBytes);
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

        return $preferences;
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
     * @return array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}
     */
    private function summarizePdfPageTiming(string $dictionary, ?string $reference, array $objects): array
    {
        $transition = $this->extractPdfDictionaryOrReferenceValue($dictionary, 'Trans', $objects);

        return [
            'page' => 0,
            'pageObject' => $reference === null ? null : $reference . ' R',
            'duration' => $this->extractPdfNumberToken($dictionary, 'Dur'),
            'transitionType' => $transition === null ? null : $this->extractPdfNameToken($transition, 'S'),
            'transitionDuration' => $transition === null ? null : $this->extractPdfNumberToken($transition, 'D'),
            'direction' => $transition === null ? null : $this->extractPdfTransitionDirection($transition),
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
     * @param list<array{page:int, pageObject:string|null, duration:float|null, transitionType:string|null, transitionDuration:float|null, direction:string|null, dimension:string|null, motion:string|null, scale:float|null, background:bool|null}> $pageTimings
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
     * @return list<array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}>
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

        foreach ($objects as $reference => $body) {
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

    /**
     * @param array<string, array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}> $files
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
     * @param array<string, array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}> $files
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
                'source' => $source,
            ]);
        }
    }

    /**
     * @param array<string, string> $objects
     * @return array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}|null
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
            'source' => $source,
        ];
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
     * @param array<string, array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}> $files
     * @param array{name:string, unicodeName:string|null, description:string|null, afRelationship:string|null, filespec:string|null, embeddedFile:string|null, subtype:string|null, size:int|null, modDate:string|null, checksum:string|null, streamBytes:int|null, streamSha256:string|null, streamSkipped:string|null, source:string}|null $entry
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
