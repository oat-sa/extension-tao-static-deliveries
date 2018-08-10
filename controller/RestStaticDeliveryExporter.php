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

namespace oat\taoStaticDeliveries\controller;

use oat\generis\model\OntologyAwareTrait;
use oat\taoStaticDeliveries\model\assembly\StaticDeliveryExporter;

class RestStaticDeliveryExporter extends \tao_actions_RestController
{
    use OntologyAwareTrait;

    /**
     * @throws \common_exception_BadRequest
     * @throws \common_exception_MethodNotAllowed
     * @throws \common_exception_NotFound
     * @throws \common_exception_NotImplemented
     */
    public function assembly()
    {
        if ($this->getRequestMethod() != \Request::HTTP_GET) {
            throw new \common_exception_MethodNotAllowed("Only GET method is accepted to export 'Assembly'.");
        }

        // Retrieve delivery information.
        if (!$this->hasRequestParameter('deliveryIdentifier') || empty($this->getRequestParameter('deliveryIdentifier'))) {
            throw new \common_exception_BadRequest("Missing 'deliveryIdentifier' parameter for the 'Assembly' Service.");
        }
        $deliveryIdentifier = $this->getRequestParameter('deliveryIdentifier');

        // Retrieve delivery from storage.
        $deliveryResource = $this->getResource($deliveryIdentifier);
        if (!$deliveryResource->exists()) {
            throw new \common_exception_NotFound("Delivery resource with identifier '" . $deliveryIdentifier . "' could not be found while invoking the 'Assembly' service.");
        }

        try {
            /** @var StaticDeliveryExporter $staticDeliveryExporter */
            $staticDeliveryExporter = $this->getServiceLocator()->get(StaticDeliveryExporter::SERVICE_ID);
            $data = $staticDeliveryExporter->exportCompiledDelivery($deliveryResource);
            \tao_helpers_Http::returnStream($data->readPsrStream());
        } catch (\Exception $e) {
            $this->returnFailure($e);
        }
    }

    /**
     * @return array
     */
    protected function getAcceptableMimeTypes()
    {
        return ['application/zip'];
    }
}