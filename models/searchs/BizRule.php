<?php

namespace cailw\rbac\rest\models\searchs;

use Yii;
use cailw\rbac\rest\models\Model;
use cailw\rbac\rest\models\BizRule as MBizRule;
use cailw\rbac\rest\components\RouteRule;

/**
 * Description of BizRule
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class BizRule extends Model
{
    /**
     * @var string name of the rule
     */
    public $name;

    public function rules()
    {
        return [
            [['name'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('rbac-admin', 'Name'),
        ];
    }

    /**
     * Search BizRule
     * @param array $params
     * @return \yii\data\ActiveDataProvider|\yii\data\ArrayDataProvider
     */
    public function search()
    {
        $authManager = Yii::$app->authManager;
        $models = [];
        foreach ($authManager->getRules() as $name => $item) {
            if ($name != RouteRule::RULE_NAME) {
                $models[] = new MBizRule($item);
            }
        }
        
        return $models;
        
    }
}
