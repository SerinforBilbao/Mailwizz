<?php declare(strict_types=1);
if (!defined('MW_PATH')) {
    exit('No direct script access allowed');
}

/**
 * InternalApiController
 *
 * Handles internal API operations requiring specific privileges (e.g., admin).
 * This controller MUST NOT be added to 'unprotectedControllers'.
 */
class Custom_customersController extends Controller
{
    /**
     * Default access rules.
     * Rely on the main API authentication (X-Api-Key / Signature)
     * since this controller won't be in 'unprotectedControllers'.
     * @return array
     */
    public function accessRules()
    {
        // Allow access if the core API authentication passes.
        return [
            ['allow'],
        ];
    }

    /**
     * Get customer and this group
     * Intended to be called by a trusted backend using admin credentials.
     *
     * @param string $customer_uid
     * @return void // Outputs JSON
     * @throws CHttpException
     */
    public function actionGetcustomerinfo(string $customer_uid)
    {
        try {
            //get general customer info
            $customer = Customer::model()->findByUid($customer_uid);
            
            if (empty($customer)) {
                // Use 404 if the customer UID itself is not found
                $this->renderJson([
                    'status' => 'error',
                    'error'  => t('api', 'The specified customer was not found.'),
                ],404);
            }

            $customerInfo = [
                "customer_uid" => $customer->customer_uid,
                "first_name" => $customer->first_name,
                "last_name" => $customer->last_name,
                "email" => $customer->email,
            ];

            //get customer group
            $group = CustomerGroup::model()->findByAttributes([
                'group_id'   => (int)$customer->group_id,
            ]);

            if (empty($group)) {
                $group = null;
            }
            else{
                $options = [
                    'contacts'  => null,
                    'campaigns' => null,
                    'sendings'  => null,
                    'time_unit' => null,
                ];

                //get customer group options
                $groupOptions = CustomerGroupOption::model()->findAllByAttributes([
                    'group_id'   => (int)$customer->group_id,
                ]);

                if(!empty($groupOptions)){
                    foreach($groupOptions as $go){
                        switch ($go->code) {
                            case 'system.customer_lists.max_subscribers':
                                $options['contacts'] = $go->value;
                                break;
                            case 'system.customer_campaigns.max_active_campaigns':
                                $options['campaigns'] = $go->value;
                                break;
                            case 'system.customer_sending.quota':
                                $options['sendings'] = $go->value;
                                break;
                            case 'system.customer_sending.quota_time_unit':
                                $options['time_unit'] = $go->value;
                                break;
                        }
                    }
                }

                //group data
                $group = [
                    "group_id" => $group->group_id,
                    "name" => $group->name,
                    "options" => $options
                ];


            }

            //add group data to customer info
            $customerInfo['group'] = $group;


            $this->renderJson([
                'status'                => 'success',
                'customer'              => $customerInfo
            ], 200);

        } catch (Exception $e) {
            $this->renderJson([
                'status' => 'error',
                'error'  => 'An internal server error occurred.',
            ], 500);
        }
    }

    /**
     * Update customer group
     * Intended to be called by a trusted backend using admin credentials.
     *
     * @param string $customer_uid
     * @return void // Outputs JSON
     * @throws CHttpException
     */
    public function actionUpdatecustomergroup(string $customer_uid){
        try {
            //validate PUT request
            if (!request()->getIsPutRequest()) {
                $this->renderJson([
                    'status'    => 'error',
                    'error'     => t('api', 'Only PUT requests allowed for this endpoint.'),
                ], 400);
                return;
            }

            //get put request
            $rawBody = Yii::app()->request->getRawBody(); 
            $attributes = CJSON::decode($rawBody, true);

            //validate if exist data
            if (!isset($attributes['group_id']) || !is_numeric($attributes['group_id'])) {
                $this->renderJson([
                    'status' => 'error',
                    'error'  => 'The "group_id" field is required and must be numeric.',
                ], 400); 
                return;
            }

            //validate if exists group
            $groupExists = CustomerGroup::model()->exists('group_id = :gid', [':gid' => (int) $attributes['group_id']]);
            if (!$groupExists) {
                $this->renderJson([
                    'status' => 'error',
                    'error'  => 'The specified group does not exist.',
                ], 400); 
                return;
            }

            //get general customer info
            $customer = Customer::model()->findByUid($customer_uid);
            
            if (empty($customer)) {
                // Use 404 if the customer UID itself is not found
                $this->renderJson([
                    'status' => 'error',
                    'error'  => t('api', 'The specified customer was not found.'),
                ],404);
                return;
            }

            
            $customer->group_id = (int) $attributes['group_id'];

            if ($customer->save(false)) {
                $this->renderJson([
                    'status' => 'success', 
                    'success' => 'Customer group updated successfully.'], 200);
                return;
            }
            else{
                $this->renderJson([
                    'status' => 'error', 
                    'error' => 'Failed to save the customer for an unknown reason.'], 500);
                return;    
            }

        } catch (Exception $e) {
            $this->renderJson([
                    'status' => 'error', 
                    'error' => 'An internal server error occurred'], 500);
                return;  
        }
    }

    /**
     * get sendings used by customer in current month
     * Intended to be called by a trusted backend using admin credentials.
     *
     * @param string $customer_uid
     * @return void // Outputs JSON
     * @throws CHttpException
     */
    public function actionGetsendings(string $customer_uid){

        try {
            $sql = "SELECT 
                cust.email AS customer_email,
                COUNT(cdl.log_id) AS total_sends_current_month,
                gopt.value AS max_sends_per_month
                FROM mw_campaign_delivery_log cdl
                JOIN mw_campaign c ON cdl.campaign_id = c.campaign_id
                JOIN mw_customer cust ON c.customer_id = cust.customer_id
                JOIN mw_customer_group_option gopt 
                    ON gopt.group_id = cust.group_id 
                    AND gopt.code = 'system.customer_sending.quota'
                WHERE cust.customer_uid = :customer_uid
                AND cdl.status = 'success'
                AND cdl.date_added >= DATE_FORMAT(NOW(), '%Y-%m-01')
                AND cdl.date_added <  DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 1 MONTH)
                GROUP BY cust.email, gopt.value;";

            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":customer_uid", $customer_uid);
            $result = $command->queryRow();

            if (!$result) {
                $result = [
                    'customer_email'            => null,
                    'total_sends_current_month' => 0,
                    'max_sends_per_month'       => null,
                ];
            }

            // Respuesta en JSON
            $this->renderJson([
                'status' => true,
                'data'    => $result,
            ],200);


            
        } catch (Exception $e) {
            $this->renderJson([
                'status' => false,
                'error'   => $e->getMessage(),
            ],500);
        }
    }
}