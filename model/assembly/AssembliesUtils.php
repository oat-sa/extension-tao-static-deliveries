<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoStaticDeliveries\model\assembly;

use qtism\data\AssessmentTest;
use qtism\data\storage\php\PhpDocument;
use qtism\data\storage\php\PhpStorageException;

/***
 * Class AssembliesUtils
 *
 * This class provides utility methods to deal with static assemblies.
 *
 * @package oat\taoStaticDeliveries\model\assembly
 */
class AssembliesUtils
{
    /**
     * Transform to Static Assembly
     *
     * Transforms a given TAO Assembly archive into a static assembly.
     *
     * @param \ZipArchive $zipArchive
     * @throws \Exception
     */
    public static function transformToStaticAssembly(\ZipArchive $zipArchive)
    {
        $files = \tao_helpers_File::getAllZipNames($zipArchive);
        $testDefinition = self::getTestDefinition($zipArchive, $files);
        $manifest = json_decode($zipArchive->getFromName('manifest.json'), true);
        $map = self::sortItemAssemblyFiles($testDefinition->getDocumentComponent(), $files, $manifest);

        $renameMap = [];
        foreach ($map as $privatePath => $publicPath) {

            $itemIdentifier = self::getItemIdentifierFromPrivatePath($privatePath, $manifest, $testDefinition->getDocumentComponent());
            $itemLanguages = self::getLanguagesFromItemPrivateDirectory($files, $privatePath);
            $itemLanguagesToExclude = [];

            if (count($itemLanguages) > 1) {
                $itemLanguagesToExclude = $itemLanguages;
                unset($itemLanguagesToExclude[0]);
            }

            foreach ($itemLanguagesToExclude as $itemLanguageToExclude) {
                $quoted = preg_quote("${privatePath}/${itemLanguageToExclude}/", '/');
                \tao_helpers_File::excludeFromZip($zipArchive, "/${quoted}.+/");
            }

            $renameMap[$privatePath . '/' . $itemLanguages[0]] = "items/${itemIdentifier}";

            if ($publicPath !== null) {
                $renameMap[$publicPath . '/' . $itemLanguages[0]] = "items/${itemIdentifier}";

                foreach ($itemLanguagesToExclude as $itemLanguageToExclude) {
                    $quoted = preg_quote("${publicPath}/${itemLanguageToExclude}/", '/');
                    \tao_helpers_File::excludeFromZip($zipArchive, "/${quoted}.+/");
                }
            }
        }

        foreach ($renameMap as $oldname => $newname) {
            \tao_helpers_File::renameInZip($zipArchive, $oldname, $newname);
        }

        //move the test metadata file at the root of the archive
        $testMetadataFile = self::getTestMetadataPath($files);
        if ($testMetadataFile != false){

            \tao_helpers_File::renameInZip($zipArchive, $testMetadataFile, \taoQtiTest_models_classes_QtiTestService::TEST_COMPILED_METADATA_FILENAME);
        }

        \tao_helpers_File::excludeFromZip($zipArchive, '/delivery\.rdf$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/manifest\.json$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/\.idx$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/.php$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/\.xml$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/\.index/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/adaptive-section-map\.json$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/compilation-info\.json$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/test-index\.json$/');
        \tao_helpers_File::excludeFromZip($zipArchive, '/\/$/');
    }

    /**
     * @param AssessmentTest $assessmentTest
     * @param array $files
     * @param array $manifest
     * @return array
     */
    private static function sortItemAssemblyFiles(AssessmentTest $assessmentTest, array $files, array $manifest)
    {
        $map = [];

        foreach ($assessmentTest->getComponentsByClassName('assessmentItemRef') as $assessmentItemRef) {
            $href = $assessmentItemRef->getHref();
            $hrefValues = explode('|', $href);
            list(, $public, $private) = $hrefValues;

            if (isset($manifest['dir'][$private])) {
                $map[$manifest['dir'][$private]] = null;

                if (isset($manifest['dir'][$public]) && self::isDirectoryAvailable($files, $manifest['dir'][$public])) {
                    $map[$manifest['dir'][$private]] = $manifest['dir'][$public];
                }
            }
        }

        return $map;
    }

    /**
     * @param array $zipFiles
     * @param string $path
     * @return bool
     */
    private static function isDirectoryAvailable(array $zipFiles, $path)
    {
        foreach ($zipFiles as $zipFile) {
            $quotedPath = preg_quote($path, '/');
            $pattern = "/^${quotedPath}/";

            if (preg_match($pattern, $zipFile) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @param array $map
     * @param AssessmentTest $assessmentTest
     * @return bool
     */
    private static function getItemIdentifierFromPrivatePath($path, array $map, AssessmentTest $assessmentTest)
    {
        $privateDir = array_search($path, $map['dir']);

        foreach ($assessmentTest->getComponentsByClassName('assessmentItemRef') as $itemRef) {
            $parts = explode('|', $itemRef->getHref());
            if ($privateDir === $parts[2]) {
                return $itemRef->getIdentifier();
            }
        }

        return false;
    }

    /**
     * @param array $zipFiles
     * @param string $path
     * @return array
     */
    private static function getLanguagesFromItemPrivateDirectory(array $zipFiles, $path)
    {
        $languages = [];

        foreach ($zipFiles as $zipFile) {
            $quotedPath = preg_quote($path, '/');
            $pattern = '/^' . "${quotedPath}\/(\w+-\w+)\//";
            $matches = [];
            preg_match($pattern, $zipFile, $matches);
            if (isset($matches[1])) {
                $languages[] = $matches[1];
            }
        }

        return array_unique($languages);
    }

    /**
     * @param \ZipArchive $zipArchive
     * @param array $zipFiles
     * @return null|PhpDocument
     * @throws PhpStorageException
     */
    private static function getTestDefinition(\ZipArchive $zipArchive, array $zipFiles)
    {
        foreach ($zipFiles as $zipFile) {
            if (mb_strpos($zipFile, \taoQtiTest_models_classes_QtiTestService::TEST_COMPILED_FILENAME) !== false) {
                $testDefinition = new PhpDocument();
                $content = $zipArchive->getFromName($zipFile);
                $testDefinition->loadFromString($content);

                return $testDefinition;
            }
        }

        return null;
    }

    /**
     * Get the path of the test metadata file within the archive files
     * @param array $zipFiles
     * @return false|string the file path
     */
    private static function getTestMetadataPath(array $zipFiles)
    {
        foreach ($zipFiles as $zipFile) {
            if (preg_match('/' . preg_quote(\taoQtiTest_models_classes_QtiTestService::TEST_COMPILED_METADATA_FILENAME) . '$/', $zipFile) === 1) {
                return $zipFile;
            }
        }

        return false;
    }
}
