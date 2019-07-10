<?php
namespace cailw\rbac\rest\models;

use Yii;
use yii\base\Model as M;

/**
 * @inheritdoc
 */
class Model extends M
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