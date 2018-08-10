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

use core_kernel_classes_Resource;
use oat\oatbox\filesystem\FileSystemService;
use oat\oatbox\filesystem\File;
use oat\oatbox\log\LoggerAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\user\User;
use oat\taoDelivery\model\AssignmentService;
use oat\taoDelivery\model\execution\Delete\DeliveryExecutionDeleteRequest;
use oat\taoDelivery\model\execution\Delete\DeliveryExecutionDeleteService;
use oat\taoDelivery\model\execution\DeliveryExecutionInterface;
use oat\taoDelivery\model\execution\StateServiceInterface;
use oat\taoDeliveryRdf\model\AssemblerServiceInterface;
use oat\taoDeliveryRdf\model\guest\GuestTestUser;
use oat\taoQtiTest\models\runner\QtiRunnerService;
use oat\taoQtiTest\models\runner\RunnerServiceContext;

/**
 * Class StaticDeliveryExporter
 *
 * An extension of the TAO AssemblerService class aiming at compiling static Delivery Assemblies.
 *
 * @package oat\taoStaticDeliveries\model\assemblies
 */
class StaticDeliveryExporter extends ConfigurableService
{
    use LoggerAwareTrait;

    const SERVICE_ID = 'taoStaticDeliveries/staticDeliveryExporter';

    const ASSEMBLY_DIRECTORY = 'assemblyExport';

    /**
     * @param core_kernel_classes_Resource $compiledDelivery
     * @return File
     * @throws \Exception
     * @throws \common_Exception
     */
    public function exportCompiledDelivery(core_kernel_classes_Resource $compiledDelivery)
    {
        if (!$compiledDelivery->exists()) {
            throw new \common_exception_NotFound("Delivery with identifier '" . $compiledDelivery->getUri() . "' not found.");
        }

        /** @var AssemblerServiceInterface $assemblerService */
        $assemblerService = $this->getServiceLocator()->get(AssemblerServiceInterface::SERVICE_ID);
        $path = $assemblerService->exportCompiledDelivery($compiledDelivery);

        $zipArchive = new \ZipArchive();
        $zipArchive->open($path, \ZipArchive::CREATE);

        $this->logDebug("Transforming Delivery Assembly '" . $compiledDelivery->getUri() . "' into a static Assembly...");

        AssembliesUtils::transformToStaticAssembly($zipArchive);

        // We now have to instantiate a Delivery Execution of this Delivery, in order to be able
        // to provide the initialization data to an assembly consumer.

        try {
            // 1. Initialize the Delivery Execution.
            $user = new GuestTestUser();
            $deliveryExecution = $this->createDeliveryExecution($compiledDelivery, $user);

            // 2. Retrieve QTI Test Runner context information.
            $runnerContext = $this->retrieveRunnerContext($deliveryExecution, $user);

            // 3. Initialize QTI Test Runner execution.
            /** @var QtiRunnerService $runnerService */
            $runnerService = $this->getServiceLocator()->get(QtiRunnerService::SERVICE_ID);
            $runnerService->initServiceContext($runnerContext);
            $runnerService->init($runnerContext);

            // 4. Retrieve required runtime information from the assembly.
            $testDataStructure = $this->buildRuntimeData($runnerContext);

            // 5. Finalize assembly.
            $zipArchive->addFromString('testData.json', json_encode($testDataStructure, JSON_PRETTY_PRINT));

            // 6. Delete execution data.
            $this->removeExecutionData($deliveryExecution, $runnerContext);

            $zipArchive->close();

            /** @var FileSystemService $fileSystemService */
            $storagePath = self::fsExportPath($compiledDelivery->getUri());
            $fileSystemService = $this->getServiceLocator()->get(FileSystemService::SERVICE_ID);
            $directory = $fileSystemService->getDirectory(self::ASSEMBLY_DIRECTORY);
            $file = $directory->getFile($storagePath);

            if (!$file->exists()) {
                $handle = fopen($path, 'r');
                $file->put($handle);
                fclose($handle);
            }

            return $file;

        } catch (\Exception $e) {
            throw new \common_Exception(
                "An unexpected error occurred while instantiating static Assembly Delivery execution.",
                0,
                $e
            );
        }
    }

    /**
     * @param RunnerServiceContext $runnerServiceContext
     * @return array
     */
    protected function buildRuntimeData(RunnerServiceContext $runnerServiceContext)
    {
        $this->logDebug('Building runtime data...');

        $runnerService = $this->getServiceLocator()->get(QtiRunnerService::SERVICE_ID);

        return [
            'testData' => $runnerService->getTestData($runnerServiceContext),
            'testContext' => $runnerService->getTestContext($runnerServiceContext),
            'testMap' => $runnerService->getTestMap($runnerServiceContext)
        ];
    }

    /**
     * @param DeliveryExecutionInterface $deliveryExecution
     * @param User $user
     * @return \oat\taoQtiTest\models\runner\QtiRunnerServiceContext
     * @throws \common_Exception
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     */
    protected function retrieveRunnerContext(DeliveryExecutionInterface $deliveryExecution, User $user)
    {
        $this->logDebug("Retrieving Test Runner context for Delivery Execution '" . $deliveryExecution->getIdentifier() . "' and Guest User '" . $user->getIdentifier() . "'...");

        /** @var AssignmentService $assignmentService */
        $assignmentService = $this->getServiceLocator()->get(AssignmentService::SERVICE_ID);
        $runtime = $assignmentService->getRuntime($deliveryExecution->getDelivery()->getUri());
        $contextParams = \tao_models_classes_service_ServiceCallHelper::getInputValues($runtime, []);

        /** @var QtiRunnerService $runnerService */
        $runnerService = $this->getServiceLocator()->get(QtiRunnerService::SERVICE_ID);
        $runnerContext = $runnerService->getServiceContext(
            $contextParams['QtiTestDefinition'],
            $contextParams['QtiTestCompilation'],
            $deliveryExecution->getIdentifier(),
            $user->getIdentifier()
        );

        return $runnerContext;
    }

    /**
     * @param core_kernel_classes_Resource $compiledDelivery
     * @param User $user
     * @return DeliveryExecutionInterface
     */
    protected function createDeliveryExecution(core_kernel_classes_Resource $compiledDelivery, User $user)
    {
        $this->logDebug("Creating Delivery Execution for Delivery Assembly '" . $compiledDelivery->getUri() . "'...");

        $deliveryUri = $compiledDelivery->getUri();

        /** @var StateServiceInterface $stateService */
        $deliveryExecutionStateService = $this->getServiceLocator()->get(StateServiceInterface::SERVICE_ID);
        $deliveryExecution = $deliveryExecutionStateService->createDeliveryExecution(
            $deliveryUri,
            $user,
            "Static Assembly Delivery Execution '${deliveryUri}'"
        );

        return $deliveryExecution;
    }

    /**
     * @param DeliveryExecutionInterface $deliveryExecution
     * @param RunnerServiceContext $runnerServiceContext
     * @throws \Exception
     */
    protected function removeExecutionData(DeliveryExecutionInterface $deliveryExecution, RunnerServiceContext $runnerServiceContext)
    {
        $this->logDebug("Removing Delivery Execution data from storage for Delivery Execution '" . $deliveryExecution->getIdentifier() . "'...");

        /** @var DeliveryExecutionDeleteService $deleteDeliveryExecutionService */
        $deleteDeliveryExecutionService = $this->getServiceLocator()->get(DeliveryExecutionDeleteService::SERVICE_ID);
        $deleteDeliveryExecutionRequest = new DeliveryExecutionDeleteRequest(
            $deliveryExecution->getDelivery(),
            $deliveryExecution,
            $runnerServiceContext->getTestSession()
        );
        $deleteDeliveryExecutionService->execute($deleteDeliveryExecutionRequest);
    }

    private static function fsExportPath($deliveryIdentifier)
    {
        return md5($deliveryIdentifier) . '.zip';
    }
}