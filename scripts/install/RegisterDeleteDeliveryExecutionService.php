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
use oat\taoDelivery\model\execution\Delete\DeliveryExecutionDeleteService;
use common_report_Report as Report;

class RegisterDeleteDeliveryExecutionService extends InstallAction
{
    /**
     * @param $params
     * @return Report
     * @throws \common_Exception
     * @throws \common_exception_Error
     */
    public function __invoke($params)
    {
        $deliveryExecutionDelete = new DeliveryExecutionDeleteService([
            DeliveryExecutionDeleteService::OPTION_DELETE_DELIVERY_EXECUTION_DATA_SERVICES => [
                'taoQtiTest/ExtendedStateService',
                'taoQtiTest/TestSessionService',
                'taoQtiTest/QtiTimerFactory',
                'taoQtiTest/QtiRunnerService',
            ],
        ]);

        $this->registerService(DeliveryExecutionDeleteService::SERVICE_ID, $deliveryExecutionDelete);

        return new Report(
            Report::TYPE_SUCCESS,
            "Service 'DeliveryExecutionDeleteService' successfully registered."
        );
    }
}