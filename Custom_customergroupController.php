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
class Custom_customergroupController extends Controller
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
     * Get all groups
     * Intended to be called by a trusted backend using admin credentials.
     *
     * @param string $customer_uid
     * @return void // Outputs JSON
     * @throws CHttpException
     */
    public function actionGetall()
    {
        try {
            //aceptar solo request de tipo GET
            if (Yii::app()->request->requestType !== 'GET') {
                $this->renderJson(['status' => 'error', 'error' => 'Only GET requests are allowed.'], 405);
                return;
            }

            // Criterio para seleccionar solo los campos necesarios y ordenar
            $criteria = new CDbCriteria();
            $criteria->select = 'group_id, name';
            $criteria->order = 'group_id ASC';
            // Opcional: Excluir grupos que no son planes públicos
            // $criteria->addNotInCondition('name', ['Internal', 'Test Group']); 

            $groups = CustomerGroup::model()->findAll($criteria);

            // Formatear la salida para que sea un array limpio de objetos
            $formattedGroups = [];
            foreach ($groups as $group) {
                $formattedGroups[] = [
                    'id'   => (int)$group->group_id,
                    'name' => $group->name,
                ];
            }

            $this->renderJson([
                'status' => 'success',
                'groups' => $formattedGroups,
            ], 200);

        } catch (Exception $e) {
            $this->renderJson(['status' => 'error', 'error' => 'An internal server error occurred.'.$e], 500);
        }
    }
}