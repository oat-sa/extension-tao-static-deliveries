<?php
/**
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA.
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