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

namespace oat\taoStaticDeliveries\scripts\tools;

use oat\oatbox\extension\script\ScriptAction;
use oat\taoStaticDeliveries\model\assembly\StaticDeliveryExporter;
use common_report_Report as Report;

/**
 * Class ExportStaticAssembly
 *
 * This script aims at exporting Static Assemblies.
 *
 * Required Arguments:
 *  -i deliveryIdentifier, --deliveryIdentifier deliveryIdentifier
 *    The identifier of the Delivery to be exported as a Mobile Assembly
 *
 * Optional Arguments:
 *  -d destination, --destination destination
 *    A destination path on the local file system.
 *  -h help, --help help
 *    Prints a help statement
 *
 * @package oat\taoStaticDeliveries\scripts\tools
 */
class ExportStaticAssembly extends ScriptAction
{
    /**
     * @return string
     */
    protected function provideDescription()
    {
        return 'TAO Static Deliveries - Export Static Assembly';
    }

    /**
     * @return array
     */
    protected function provideUsage()
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Prints a help statement'
        ];
    }

    /**
     * @return array
     */
    protected function provideOptions()
    {
        return [
            'deliveryIdentifier' => [
                'prefix' => 'i',
                'longPrefix' => 'deliveryIdentifier',
                'required' => true,
                'description' => 'The identifier of the Delivery to be exported as a Static Assembly'
            ],
            'destination' => [
                'prefix' => 'd',
                'longPrefix' => 'destination',
                'required' => false,
                'description' => 'A destination path on the local file system.'
            ]
        ];
    }

    /**
     * @return \common_report_Report
     * @throws \Exception
     * @throws \common_Exception
     */
    protected function run()
    {
        // Main report.
        $report = new Report(
            Report::TYPE_INFO,
            "Script ended gracefully."
        );

        $exporter = $this->getServiceLocator()->get(StaticDeliveryExporter::SERVICE_ID);
        $file = $exporter->exportCompiledDelivery(new \core_kernel_classes_Resource($this->getOption('deliveryIdentifier')));

        $report->add(
            new Report(Report::TYPE_INFO, "Static Assembly exported in shared file system with file name '" . $file->getBasename()  . "'")
        );

        if ($this->hasOption('destination')) {
            $source = $file->readStream();
            if (($dest = @fopen($this->getOption('destination'), 'w')) !== false) {
                stream_copy_to_stream($source, $dest);

                $report->add(
                    new Report(Report::TYPE_INFO, "Static Assembly copied at '" . $this->getOption('destination') . "'.")
                );

                @fclose($source);
                @fclose($dest);
            } else {
                return new Report(Report::TYPE_ERROR, "Destination '" . $this->getOption('destination') . "' could not be open.");
            }
        }

        return $report;
    }
}
