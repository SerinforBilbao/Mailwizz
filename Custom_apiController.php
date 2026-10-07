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
class Custom_apiController extends Controller
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
     * Get customer API keys 
     * Intended to be called by a trusted backend using admin credentials.
     *
     * @param string $customer_uid
     * @return void // Outputs JSON
     * @throws CHttpException
     */
    public function actionGetapikey(string $customer_uid)
    {
        $api_key = null;
        $customer = Customer::model()->findByUid($customer_uid);
        
        if (empty($customer)) {
            // Use 404 if the customer UID itself is not found
            throw new CHttpException(404, t('api', 'The specified customer was not found.'));
        }

        $model = CustomerApiKey::model()->findByAttributes([
            'customer_id'   => (int)$customer->customer_id,
        ]);

        if (empty($model)) {
            throw new CHttpException(404, t('api', 'API key not found for this customer.'));
        }

        
        $api_key = $model->key;

        $this->renderJson([
            'status'                => 'success',
            'customer_uid'          => $customer_uid,
            'api_key'               => $api_key
        ], 200);
    }
}