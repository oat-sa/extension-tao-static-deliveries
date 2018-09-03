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

namespace oat\taoStaticDeliveries\scripts\install;

use oat\oatbox\extension\InstallAction;
use oat\oatbox\filesystem\FileSystemService;
use oat\taoStaticDeliveries\model\assembly\StaticDeliveryExporter;
use common_report_Report as Report;
use oat\oatbox\service\exception\InvalidServiceManagerException;

class RegisterFileSystem extends InstallAction
{
    /**
     * @param $params
     * @return Report
     * @throws \common_Exception
     * @throws InvalidServiceManagerException
     */
    public function __invoke($params)
    {
        /** @var FileSystemService $service */
        $service = $this->getServiceManager()->get(FileSystemService::SERVICE_ID);
        if (!$service->hasDirectory(StaticDeliveryExporter::SERVICE_ID)) {
            $service->createFileSystem(StaticDeliveryExporter::ASSEMBLY_DIRECTORY);
            $this->registerService(FileSystemService::SERVICE_ID, $service);
        }

        return new Report(
            Report::TYPE_SUCCESS,
            "Directory for static delivery exporter has been successfully created."
        );
    }
}