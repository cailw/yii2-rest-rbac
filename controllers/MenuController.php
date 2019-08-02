<?php

namespace cailw\rbac\rest\controllers;

use Yii;
use cailw\rbac\rest\models\Menu;
use yii\web\NotFoundHttpException;

/**
 * MenuController implements the CRUD actions for Menu model.
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class MenuController extends BaseController
{
    
    public function init()
    {
        parent::init();
        if (!isset(Yii::$app->i18n->translations['rbac-admin'])) {
            Yii::$app->i18n->translations['rbac-admin'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en',
                'basePath' => '@common/modules/rbac/messages'
            ];
        }
    }
    
    public function actionUserMenu(){
        Menu::invalidate();
        
        return Menu::getAssignedMenu(Yii::$app->user->id);
    }
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
     * Lists all Menu models.
     * @return mixed
     */
    public function actionIndex($parent=null)
    {
        return Menu::find()->where(['parent'=>$parent])->orderBy(['order'=>SORT_ASC])->all();
    }
    /**
     * create or update Menu
     * @param integer $id
     */
    public function actionSave($id=null){
        $model=$id===null?new Menu():$this->findModel($id);
        
        $model->load(Yii::$app->request->post());
        if($model->save()){
            Menu::invalidate();
            return $model;
        }
        return $this->dataValidationError($model->getError());
    }
    
    public function actionRouteSuggestion(){
        return Menu::getSavedRoutes();
    }
    
    /**
     * Deletes an existing Menu model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Menu::invalidate();
        return $this->success();
    }

    /**
     * Finds the Menu model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param  integer $id
     * @return Menu the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    
    
    protected function findModel($id)
    {
        if (($model = Menu::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

}
