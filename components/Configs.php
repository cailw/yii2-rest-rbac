<?php

namespace cailw\rbac\rest\components;

use Yii;
use yii\db\Connection;
use yii\caching\Cache;
use yii\helpers\ArrayHelper;
use yii\base\BaseObject;

/**
 * Configs
 * Used for configure some value. To set config you can use [[\yii\base\Application::$params]]
 * 
 * ~~~
 * return [
 *     
 *     'common.modules.rbac.configs' => [
 *         'db' => 'customDb',
 *         'menuTable' => 'admin_menu',
 *     ]
 * ];
 * ~~~
 * 
 * or use [[\Yii::$container]]
 * 
 * ~~~
 * Yii::$container->set('cailw\rbac\rest\components\Configs',[
 *     'db' => 'customDb',
 *     'menuTable' => 'admin_menu',
 * ]);
 * ~~~
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Configs extends BaseObject
{
    /**
     * @var Connection Database connection.
     */
    public $db = 'db';

    /**
     * @var Cache Cache component.
     */
    public $cache = 'cache';

    /**
     * @var integer Cache duration. Default to a month.
     */
    public $cacheDuration = 2592000;

    /**
     * @var string Menu table name.
     */
    public $menuTable = '{{%menu}}';
    
    /**
     * @var self Instance of self
     */
    private static $_instance;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->db !== null && !($this->db instanceof Connection)) {
            if (is_string($this->db) && strpos($this->db, '\\') === false) {
                $this->db = Yii::$app->get($this->db, false);
            } else {
                $this->db = Yii::createObject($this->db);
            }
        }
        if ($this->cache !== null && !($this->cache instanceof Cache)) {
            if (is_string($this->cache) && strpos($this->cache, '\\') === false) {
                $this->cache = Yii::$app->get($this->cache, false);
            } else {
                $this->cache = Yii::createObject($this->cache);
            }
        }
        parent::init();
    }

    /**
     * Create instance of self
     * @return static
     */
    public static function instance()
    {
        if (self::$_instance === null) {
            $type = ArrayHelper::getValue(Yii::$app->params, 'common.modules.rbac.configs', []);
            if (is_array($type) && !isset($type['class'])) {
                $type['class'] = static::className();
            }
            return self::$_instance = Yii::createObject($type);
        }

        return self::$_instance;
    }
}
