<?php

namespace cailw\rbac\rest\controllers;

use Yii;
use common\models\ar\Menu;
use cailw\rbac\rest\models\AuthItem;
use cailw\rbac\rest\models\BizRule;
use cailw\rbac\rest\models\searchs\BizRule as BizRuleSearch;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

/**
 * Description of RuleController
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RuleController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function verbs()
    {
        return [
            'delete' => ['post'],
            'create'=>['post'],
            'update'=>['post'],
        ];
    }
    
    /**
     * Lists all AuthItem models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new BizRuleSearch();
        return $searchModel->search();

    }

    /**
     * Displays a single AuthItem model.
     * @param  string $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('view', ['model' => $model]);
    }

    /**
     * Creates a new AuthItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new BizRule(null);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Menu::invalidate();

            return $this->success($model);
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
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Menu::invalidate();
            return $this->success($model);
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
        Yii::$app->authManager->remove($model->item);
        Menu::invalidate();

        return $this->success();
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
        $item = Yii::$app->authManager->getRule($id);
        if ($item) {
            return new BizRule($item);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
