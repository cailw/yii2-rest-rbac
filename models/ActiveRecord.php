<?php
namespace cailw\rbac\rest\models;

use Yii;
use yii\db\ActiveRecord as AR;

/**
 * @inheritdoc
 */
class ActiveRecord extends AR
{

    /**
     *
     * @return string
     */
    public function getError()
    {
        $errors = $this->getFirstErrors();
        if (is_array($errors) && ! empty($errors)) {
            return array_values($errors)[0];
        }
        return '';
    }
}