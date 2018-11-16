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

return array(
    'name' => 'taoStaticDeliveries',
    'label' => 'Static deliveries',
    'description' => 'TAO Static Deliveries to expose static delivery with runtime and map.',
    'license' => 'GPL-2.0',
    'version' => '0.1.0',
    'author' => 'Open Assessment Technologies SA',
    'requires' => array(
        'tao' => '>=19.17.1',
        'taoQtiTest' => '>=25.7.0',
        'taoDelivery' => '>=9.12.0',
        'taoDeliveryRdf' => '>=5.0.0',
    ),
    'managementRole' => 'http://www.taotesting.com/Ontologies/generis.rdf#taoStaticDeliveries',
    'acl' => [
        ['grant', 'http://www.taotesting.com/Ontologies/generis.rdf#taoStaticDeliveries', ['ext'=>'taoStaticDeliveries']],
    ],
    'install' => [
        'rdf' => [
        ],
        'php' => [
            oat\taoQtiTest\scripts\cli\SetNewTestRunner::class,
            oat\taoStaticDeliveries\scripts\install\RegisterDeleteDeliveryExecutionService::class,
            oat\taoStaticDeliveries\scripts\install\RegisterFileSystem::class
        ]
    ],
    'uninstall' => array(
    ),
    'update' => oat\taoStaticDeliveries\scripts\update\Updater::class,
    'routes' => array(
        '/taoStaticDeliveries' => 'oat\\taoStaticDeliveries\\controller'
    ),
    'constants' => array(
        # views directory
        "DIR_VIEWS" => dirname(__FILE__).DIRECTORY_SEPARATOR."views".DIRECTORY_SEPARATOR,

        #BASE URL (usually the domain root)
        'BASE_URL' => ROOT_URL.'taoStaticDeliveries/',
    ),
    'extra' => array(
    ),
);
