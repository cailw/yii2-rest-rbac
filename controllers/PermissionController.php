<?php

namespace cailw\rbac\rest\controllers;

use Yii;
use common\models\ar\Menu;
use cailw\rbac\rest\models\AuthItem;
use cailw\rbac\rest\models\searchs\AuthItem as AuthItemSearch;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\rbac\Item;
use yii\helpers\Html;

/**
 * AuthItemController implements the CRUD actions for AuthItem model.
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class PermissionController extends BaseController
{

    /**
     * @inheritdoc
     */
    public function verbs()
    {
        return [
          'delete' => ['post'],
        ];
    }

    /**
     * Lists all AuthItem models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new AuthItemSearch(['type' => Item::TYPE_PERMISSION]);

        return array_values($searchModel->search());
        
        return $this->render('index', [
                'dataProvider' => $dataProvider,
                'searchModel' => $searchModel,
        ]);
    }

    /**
     * Displays a single AuthItem model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        $authManager = Yii::$app->getAuthManager();
        $avaliable = $assigned = [];
        
        $item= $authManager->getItem($id);
        foreach ($authManager->getPermissions() as $name => $role) {
            if ($name==$id ||!$authManager->canAddChild($item, $role)) {
                continue;
            }
            $type=$name[0] === '/' ? '[Routes]' : '[Permission]';
            $avaliable[]=['label'=>$type.$name,'key'=>$name,'disabled'=>false];
        }
        foreach ($authManager->getChildren($id) as $name => $child) {
            $assigned[]=$name;
        }
        $avaliable = array_filter($avaliable);
        $assigned = array_filter($assigned);
        return ['avaliable' => $avaliable, 'assigned' => $assigned];
    }

    
    
    /**
     * Creates a new AuthItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new AuthItem(null);
        $model->type = Item::TYPE_PERMISSION;
        if ($model->load(Yii::$app->getRequest()->post()) && $model->save()) {
            Menu::invalidate();
            return $this->success($model->getItem());
        }
        return $this->dataValidationError($model->getError());
    }

    /**
     * Updates an existing AuthItem model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param  string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->getRequest()->post()) && $model->save()) {
            Menu::invalidate();
            return $this->success();
        }
        
        return $this->dataValidationError($model->getError());
    }

    /**
     * Deletes an existing AuthItem model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        Yii::$app->getAuthManager()->remove($model->item);
        Menu::invalidate();

        return $this->success();
    }

    /**
     * Assign or remove items
     * @param string $id
     * @param string $action
     * @return array
     */
    public function actionAssign($id, $action)
    {
        $post = Yii::$app->getRequest()->post();
        $roles = $post['permissions'];
        $manager = Yii::$app->getAuthManager();
        $parent = $manager->getPermission($id);
        $error = [];
        if ($action == 'assign') {
            foreach ($roles as $role) {
                $child = $manager->getPermission($role);
                try {
                    $manager->addChild($parent, $child);
                } catch (\Exception $exc) {
                    $error[] = $exc->getMessage();
                }
            }
        } else {
            foreach ($roles as $role) {
                $child = $manager->getPermission($role);
                try {
                    $manager->removeChild($parent, $child);
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
     * Search role
     * @param string $id
     * @param string $target
     * @param string $term
     * @return array
     */
    public function actionRoleSearch($id, $target, $term = '')
    {
        $result = [
            'Permission' => [],
            'Routes' => [],
        ];
        $authManager = Yii::$app->getAuthManager();
        if ($target == 'avaliable') {
            $children = array_keys($authManager->getChildren($id));
            $children[] = $id;
            foreach ($authManager->getPermissions() as $name => $role) {
                if (in_array($name, $children)) {
                    continue;
                }
                if (empty($term) or strpos($name, $term) !== false) {
                    $result[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name;
                }
            }
        } else {
            foreach ($authManager->getChildren($id) as $name => $child) {
                if (empty($term) or strpos($name, $term) !== false) {
                    $result[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name;
                }
            }
        }

        return Html::renderSelectOptions('', array_filter($result));
    }

    /**
     * Finds the AuthItem model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param  string        $id
     * @return AuthItem      the loaded model
     * @throws HttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $item = Yii::$app->getAuthManager()->getPermission($id);
        if ($item) {
            return new AuthItem($item);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
