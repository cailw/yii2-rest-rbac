<?php

namespace cailw\rbac\rest\controllers;

use cailw\rbac\rest\models\Menu;
use Yii;
use cailw\rbac\rest\models\searchs\Assignment as AssignmentSearch;
use yii\web\NotFoundHttpException;
use yii\helpers\Html;
use yii\rbac\Assignment;
use yii\rbac\Item;

/**
 * AssignmentController implements the CRUD actions for Assignment model.
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class AssignmentController extends BaseController
{
    

    public $modelClass='';
    
    public $userClassName;
    public $idField = 'id';
    public $usernameField = 'username';
    public $searchClass;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->userClassName === null) {
            $this->userClassName = Yii::$app->getUser()->identityClass;
        }
    }

    public function actions(){
        return [];
    }
    
    /**
     * @inheritdoc
     */
    public function verbs()
    {
        return [
            'assign' => ['post'],
        ];
    }

    /**
     * Lists all Assignment models.
     * @return mixed
     */
    public function actionIndex()
    {

        if ($this->searchClass === null) {
            $searchModel = new AssignmentSearch;
        } else {
            $class = $this->searchClass;
            $searchModel = new $class;
        }

        $dataProvider = $searchModel->search(\Yii::$app->request->getQueryParams(), $this->userClassName, $this->usernameField);

        return $dataProvider;
        
    }

    /**
     * Displays a single Assignment model.
     * @param  integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $authManager = Yii::$app->authManager;
        $avaliable = [];
        $assigned = [];
        foreach ($authManager->getRolesByUser($id) as $role) {
            //$type = $role->type;
            //$assigned[$type == Item::TYPE_ROLE ? 'Roles' : 'Permissions'][$role->name] = $role->name;
            
            $assigned[]=$role->name;
        }
        foreach ($authManager->getRoles() as $role) {
            if (!isset($assigned['Roles'][$role->name])) {
                $avaliable[]=['label'=>'[Roles]'.$role->name,'key'=>$role->name,'disabled'=>false];
                //$avaliable['Roles'][$role->name] = $role->name;
            }
        }
        foreach ($authManager->getPermissions() as $role) {
            if ($role->name[0] !== '/' && !isset($assigned['Permissions'][$role->name])) {
                //$avaliable['Permissions'][$role->name] = $role->name;
                $avaliable[]=['label'=>'[Permissions]'.$role->name,'key'=>$role->name,'disabled'=>false];
            }
        }
        return ['avaliable'=>$avaliable,'assigned'=>$assigned];        
    }

    /**
     * Assign or revoke assignment to user
     * @param  integer $id
     * @param  string  $action
     * @return mixed
     */
    public function actionAssign($id, $action)
    {
        $assignments = Yii::$app->request->post('assignments');
        $manager = Yii::$app->authManager;
        $error = [];
        if ($action == 'assign') {
            foreach ($assignments as $name) {
                try {
                    $item = $manager->getRole($name);
                    $item = $item ? : $manager->getPermission($name);
                    $manager->assign($item, $id);
                } catch (\Exception $exc) {
                    $error[] = $exc->getMessage();
                }
            }
        } else {
            foreach ($assignments as $name) {
                try {
                    $item = $manager->getRole($name);
                    $item = $item ? : $manager->getPermission($name);
                    $manager->revoke($item, $id);
                } catch (\Exception $exc) {
                    $error[] = $exc->getMessage();
                }
            }
        }
        Menu::invalidate();
        if(empty($error)){
            return $this->success();
        }
        return $this->dataValidationError($error[0]);
    }

    /**
     * Search roles of user
     * @param  integer $id
     * @param  string  $target
     * @param  string  $term
     * @return string
     */
    public function actionRoleSearch($id, $target, $term = '')
    {
        $authManager = Yii::$app->authManager;
        $avaliable = [];
        $assigned = [];
        foreach ($authManager->getRolesByUser($id) as $role) {
            $type = $role->type;
            $assigned[$type == Item::TYPE_ROLE ? 'Roles' : 'Permissions'][$role->name] = $role->name;
        }
        foreach ($authManager->getRoles() as $role) {
            if (!isset($assigned['Roles'][$role->name])) {
                $avaliable['Roles'][$role->name] = $role->name;
            }
        }
        foreach ($authManager->getPermissions() as $role) {
            if ($role->name[0] !== '/' && !isset($assigned['Permissions'][$role->name])) {
                $avaliable['Permissions'][$role->name] = $role->name;
            }
        }

        $result = [];
        $var = ${$target};
        if (!empty($term)) {
            foreach (['Roles', 'Permissions'] as $type) {
                if (isset($var[$type])) {
                    foreach ($var[$type] as $role) {
                        if (strpos($role, $term) !== false) {
                            $result[$type][$role] = $role;
                        }
                    }
                }
            }
        } else {
            $result = $var;
        }
        return Html::renderSelectOptions('', $result);
    }

    /**
     * Finds the Assignment model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param  integer $id
     * @return Assignment the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $class = $this->userClassName;
        if (($model = $class::findIdentity($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}