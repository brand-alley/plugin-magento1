<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

class Trustpilot_Reviews_Helper_PastOrders extends Mage_Core_Helper_Abstract
{
    private $_helper;
    private $_trustpilot_api;
    private $_orders;
    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
        $this->_orders = Mage::helper('trustpilot/OrderData');
        $this->_trustpilot_api = Mage::helper('trustpilot/TrustpilotHttpClient');
    }

    public function sync($period_in_days, $websiteId, $storeId)
    {
        $this->_helper->setConfig('sync_in_progress', 'true', $websiteId, $storeId);
        $this->_helper->setConfig("show_past_orders_initial", 'false', $websiteId, $storeId);
        try {
            $key = $this->_helper->getKey($websiteId, $storeId);
            $collect_product_data = Trustpilot_Reviews_Model_Config::WITHOUT_PRODUCT_DATA;
            if (!is_null($key)) {
                $this->_helper->setConfig('past_orders', 0, $websiteId, $storeId);
                $pageId = 0;
                $sales_collection = $this->getSalesCollection($period_in_days, $websiteId, $storeId);
                $post_batch = $this->getInvitationsForPeriod($sales_collection, $collect_product_data, $pageId);
                while ($post_batch) {
                    set_time_limit(30);
                    $batch = null;
                    if (!is_null($post_batch)) {
                        $batch['invitations'] = $post_batch;
                        $batch['type'] = $collect_product_data;
                        $response = $this->_trustpilot_api->postBatchInvitations($key, $websiteId, $storeId, $batch);
                        $code = $this->handleTrustpilotResponse($response, $batch, $websiteId, $storeId);
                        if ($code == 202) {
                            $collect_product_data = Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA;
                            $batch['invitations'] = $this->getInvitationsForPeriod($sales_collection, $collect_product_data, $pageId);
                            $batch['type'] = $collect_product_data;
                            $response = $this->_trustpilot_api->postBatchInvitations($key, $websiteId, $storeId, $batch);
                            $code = $this->handleTrustpilotResponse($response, $batch, $websiteId, $storeId);
                        }
                        if ($code < 200 || $code > 202) {
                            $this->_helper->setConfig('show_past_orders_initial', 'true', $websiteId, $storeId);
                            $this->_helper->setConfig('sync_in_progress', 'false', $websiteId, $storeId);
                            $this->_helper->setConfig('past_orders', 0, $websiteId, $storeId);
                            $this->_helper->setConfig('failed_orders', '{}', $websiteId, $storeId);
                            return;
                        }
                    }
                    $pageId = $pageId + 1;
                    $post_batch = $this->getInvitationsForPeriod($sales_collection, $collect_product_data, $pageId);
                }
            }
        } catch (\Throwable $e) {
            $this->_helper->log('Failed to sync past orders', $e, 'sync');
        } catch (\Exception $e) {
            $this->_helper->log('Failed to sync past orders', $e, 'sync');
        }
        $this->_helper->setConfig('sync_in_progress', 'false', $websiteId, $storeId);
    }

    public function resync($websiteId, $storeId)
    {
        $this->_helper->setConfig('sync_in_progress', 'true', $websiteId, $storeId);
        try {
            $key = $this->_helper->getKey($websiteId, $storeId);
            $failed_orders_object = json_decode($this->_helper->getConfig('failed_orders', $websiteId, $storeId));
            $collect_product_data = Trustpilot_Reviews_Model_Config::WITHOUT_PRODUCT_DATA;
            if (!is_null($key)) {
                $failed_orders_array = array();
                foreach ($failed_orders_object as $id => $value) {
                    array_push($failed_orders_array, $id);
                }

                $chunked_failed_orders = array_chunk($failed_orders_array, 10, true);
                foreach ($chunked_failed_orders as $failed_orders_chunk) {
                    set_time_limit(30);
                    $post_batch = $this->trustpilotGetOrdersByIds($collect_product_data, $failed_orders_chunk);
                    $batch = null;
                    $batch['invitations'] = $post_batch;
                    $batch['type'] = $collect_product_data;
                    $response = $this->_trustpilot_api->postBatchInvitations($key, $websiteId, $storeId, $batch);
                    $code = $this->handleTrustpilotResponse($response, $batch, $websiteId, $storeId);

                    if ($code == 202) {
                        $collect_product_data = Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA;
                        $batch['invitations'] = $this->trustpilotGetOrdersByIds($collect_product_data, $failed_orders_chunk);
                        $batch['type'] = $collect_product_data;
                        $response = $this->_trustpilot_api->postBatchInvitations($key, $websiteId, $storeId, $batch);
                        $code = $this->handleTrustpilotResponse($response, $batch, $websiteId, $storeId);
                    }
                    if ($code < 200 || $code > 202) {
                        $this->_helper->setConfig('sync_in_progress', 'false', $websiteId, $storeId);
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->_helper->log('Failed to resync failed order', $e, 'sync');
        } catch (\Exception $e) {
            $this->_helper->log('Failed to resync failed order', $e, 'sync');
        }
        $this->_helper->setConfig('sync_in_progress', 'false', $websiteId, $storeId);
    }

    private function trustpilotGetOrdersByIds($collect_product_data, $order_ids) {
        $invitations = array();

        try {
            foreach ($order_ids as $id) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($id);
                $invitation =  $this->_orders->getInvitation($order, 'past-orders', $collect_product_data);

                if (!is_null($invitation)) {
                    array_push($invitations, $invitation);
                }
            }
        } catch (\Throwable $e) {
            $this->_helper->log('Failed trying to get order by id', $e, 'trustpilotGetOrdersByIds');
        } catch (\Exception $e) {
            $this->_helper->log('Failed trying to get order by id', $e, 'trustpilotGetOrdersByIds');
        }
        return $invitations;
    }

    public function getPastOrdersInfo($websiteId = null, $storeId = null)
    {
        $syncInProgress = $this->_helper->getConfig('sync_in_progress', $websiteId, $storeId);
        $showInitial = $this->_helper->getConfig('show_past_orders_initial', $websiteId, $storeId);
        try {
            if ($syncInProgress === 'false') {
                $synced_orders = (int)$this->_helper->getConfig('past_orders', $websiteId, $storeId);
                $failed_orders = json_decode($this->_helper->getConfig('failed_orders', $websiteId, $storeId));

                $failed_orders_result = array();
                foreach ($failed_orders as $key => $value) {
                    $item = array(
                        'referenceId' => $key,
                        'error' => $value
                    );
                    array_push($failed_orders_result, $item);
                }

                return array(
                    'pastOrders' => array(
                        'synced' => $synced_orders,
                        'unsynced' => count($failed_orders_result),
                        'failed' => $failed_orders_result,
                        'syncInProgress' => $syncInProgress === 'true',
                        'showInitial' => $showInitial === 'true',
                    )
                );
            } else {
                return array(
                    'pastOrders' => array(
                        'syncInProgress' => $syncInProgress === 'true',
                        'showInitial' => $showInitial === 'true',
                    )
                );
            }
        } catch (\Throwable $e) {
            $vars = array(
                'syncInProgress' => isset($syncInProgress) ? $syncInProgress : null,
                'showInitial' => isset($showInitial) ? $showInitial : null,
            );
            $this->_helper->log('Error while getting past order information', $e, 'getPastOrdersInfo', $vars);
        } catch (\Exception $e) {
            $vars = array(
                'syncInProgress' => isset($syncInProgress) ? $syncInProgress : null,
                'showInitial' => isset($showInitial) ? $showInitial : null,
            );
            $this->_helper->log('Error while getting past order information', $e, 'getPastOrdersInfo', $vars);
        }
    }

    private function getSalesCollection($period_in_days, $websiteId, $storeId) {
        $date = new DateTime();
        $args = array(
            'date_created' => $date->setTimestamp(time() - (86400 * $period_in_days))->format('Y-m-d'),
            'limit' => 20,
            'past_order_statuses' => json_decode($this->_helper->getConfig('master_settings_field', $websiteId, $storeId))->pastOrderStatuses
        );

        return $salesCollection = Mage::getModel("sales/order")->getCollection()
            ->addFieldToFilter('status', $args['past_order_statuses'])
            ->addAttributeToFilter('created_at', array('gteq' => $args['date_created']))
            ->setPageSize($args['limit']);
    }

    private function getInvitationsForPeriod($sales_collection, $collect_product_data, $page_id)
    {
        if ($page_id < $sales_collection->getLastPageNumber()) {
            $sales_collection->setCurPage($page_id)->load();
            $orders = array();
            foreach($sales_collection as $order) {
                array_push($orders, $this->_orders->getInvitation($order, 'past-orders', $collect_product_data));
            }
            $sales_collection->clear();
            return $orders;
        } else {
            return null;
        }
    }

    private function handleTrustpilotResponse($response, $post_batch, $websiteId, $storeId)
    {
        $synced_orders = (int)$this->_helper->getConfig('past_orders', $websiteId, $storeId);
        $failed_orders = json_decode($this->_helper->getConfig('failed_orders', $websiteId, $storeId));

        $data = array();
        if (isset($response['data']))
        {
            $data = $response['data'];
        }

        // all succeeded
        if ($response['code'] == 201 && count($data) == 0) {
            $this->saveSyncedOrders($synced_orders, $post_batch['invitations'], $websiteId, $storeId);
            $this->saveFailedOrders($failed_orders, $post_batch['invitations'], $websiteId, $storeId);
        }
        // all/some failed
        if ($response['code'] == 201 && count($data) > 0) {
            $failed_order_ids = $this->selectColumn($data, 'referenceId');
            $succeeded_orders = array_filter($post_batch['invitations'], function ($invitation) use ($failed_order_ids)  {
                return !(in_array($invitation['referenceId'], $failed_order_ids));
            });

            $this->saveSyncedOrders($synced_orders, $succeeded_orders, $websiteId, $storeId);
            $this->saveFailedOrders($failed_orders, $succeeded_orders, $websiteId, $storeId, $data);
        }
        return $response['code'];

    }

    private function selectColumn($array, $column)
    {
        if (version_compare(phpversion(), '7.2.10', '<')) {
            $newarr = array();
            foreach ($array as $row) {
                array_push($newarr, $row->{$column});
            }
            return $newarr;
        } else {
            return array_column($array, $column);
        }
    }

    private function saveSyncedOrders($synced_orders, $new_orders, $websiteId, $storeId)
    {
        if (count($new_orders) > 0) {
            $synced_orders = (int)($synced_orders + count($new_orders));
            $this->_helper->setConfig('past_orders', $synced_orders, $websiteId, $storeId);
        }
    }

    private function saveFailedOrders($failed_orders, $succeeded_orders, $websiteId, $storeId, $new_failed_orders = array())
    {
        $update_needed = false;
        if (count($succeeded_orders) > 0) {
            $update_needed = true;
            foreach ($succeeded_orders as $order) {
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                }
            }
        }

        if (count($new_failed_orders) > 0) {
            $update_needed = true;
            foreach ($new_failed_orders as $failed_order) {
                $failed_orders->{$failed_order->referenceId} = base64_encode($failed_order->error);
            }
        }

        if ($update_needed) {
            $this->_helper->setConfig('failed_orders', json_encode($failed_orders), $websiteId, $storeId);
        }
    }
}
