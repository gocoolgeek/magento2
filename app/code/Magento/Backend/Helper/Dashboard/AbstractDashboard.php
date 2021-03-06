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
 * @category    Magento
 * @package     Magento_Backend
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml abstract  dashboard helper.
 */
namespace Magento\Backend\Helper\Dashboard;

abstract class AbstractDashboard extends \Magento\Core\Helper\Data
{
    /**
     * Helper collection
     *
     * @var \Magento\Core\Model\Resource\Db\Collection\AbstractCollection|\Magento\Eav\Model\Entity\Collection\Abstract|array
     */
    protected  $_collection;

    /**
     * Parameters for helper
     *
     * @var array
     */
    protected  $_params = array();

    public function getCollection()
    {
        if (is_null($this->_collection)) {
            $this->_initCollection();
        }
        return $this->_collection;
    }

    abstract protected  function _initCollection();

    /**
     * Returns collection items
     *
     * @return array
     */
    public function getItems()
    {
        return is_array($this->getCollection()) ? $this->getCollection() : $this->getCollection()->getItems();
    }

    public function getCount()
    {
        return sizeof($this->getItems());
    }

    public function getColumn($index)
    {
        $result = array();
        foreach ($this->getItems() as $item) {
            if (is_array($item)) {
                if (isset($item[$index])) {
                    $result[] = $item[$index];
                } else {
                    $result[] = null;
                }
            } elseif ($item instanceof \Magento\Object) {
                $result[] = $item->getData($index);
            } else {
                $result[] = null;
            }
        }
        return $result;
    }

    public function setParam($name, $value)
    {
        $this->_params[$name] = $value;
    }

    public function setParams(array $params)
    {
        $this->_params = $params;
    }

    public function getParam($name)
    {
        if (isset($this->_params[$name])) {
            return $this->_params[$name];
        }

        return null;
    }

    public function getParams()
    {
        return $this->_params;
    }
}
