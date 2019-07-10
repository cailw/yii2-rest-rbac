<?php
namespace cailw\rbac\rest\controllers;

use Yii;
use yii\filters\RateLimiter;
use yii\filters\VerbFilter;
use yii\rest\ActiveController;
use yii\web\UnprocessableEntityHttpException;

class BaseController extends ActiveController
{

    public $modelClass = '';

    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items'
    ];

    public function behaviors()
    {
        return [
            'verbFilter' => [
                'class' => VerbFilter::className(),
                'actions' => $this->verbs()
            ],
            'rateLimiter' => [
                'class' => RateLimiter::className()
            ]
        ];
    }

    /**
     *
     * {@inheritdoc}
     * @see \yii\rest\ActiveController::actions()
     */
    public function actions()
    {
        return [];
    }

    public function success($data = [], $message = '')
    {
        return [
            'data' => $data,
            'result' => 'success',
            'message' => $message
        ];
    }

    public function error($data=[],$status=422,$message='Unprocessable entity'){
        Yii::$app->response->setStatusCode($status,$message);
        return $data;
    }
    
    
    /**
     * UnprocessableEntityHttpException
     *
     * @param string $message
     * @throws UnprocessableEntityHttpException
     */
    public function dataValidationError($message)
    {
        throw new UnprocessableEntityHttpException($message);
    }
    
    
    
    
}



