<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Magento
 * @package    tools
 * @copyright  Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Test\Tools\Migration\System\Writer;

require_once realpath(__DIR__ . '/../../../../../../../../../')
    . '/tools/Magento/Tools/Migration/System/Writer/Factory.php';
require_once realpath(__DIR__ . '/../../../../../../../../../')
    . '/tools/Magento/Tools/Migration/System/Writer/FileSystem.php';

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Tools\Migration\System\Writer\Factory
     */
    protected $_model;

    protected function setUp()
    {
        $this->_model = new \Magento\Tools\Migration\System\Writer\Factory();
    }

    public function testGetWriterReturnsProperWriter()
    {
        $this->assertInstanceOf('Magento\Tools\Migration\System\Writer\FileSystem', $this->_model->getWriter('write'));
        $this->assertInstanceOf('Magento\Tools\Migration\System\Writer\Memory', $this->_model->getWriter('someWriter'));
    }
}
