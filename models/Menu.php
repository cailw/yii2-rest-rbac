<?php

namespace cailw\rbac\rest\models;

use Yii;
use yii\caching\TagDependency;
use yii\helpers\Url;

/**
 * This is the model class for table "menu".
 *
 * @property integer $id Menu id(autoincrement)
 * @property string $name Menu name
 * @property integer $parent Menu parent
 * @property string $route Route for this menu
 * @property string $path Path for this client
 * @property integer $order Menu order
 * @property string $data Extra information for this menu
 *
 * @property Menu $menuParent Menu parent
 * @property Menu[] $menus Menu children
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Menu extends ActiveRecord
{
    public $parent_name;

    public $label;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%menu}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['parent'], 'exist','targetClass'=>self::className(),'targetAttribute'=>'id'],
            [['parent', 'route', 'data', 'order','path'], 'default'],
            [['order'], 'integer'],
            [['route'], 'in',
                'range' => static::getSavedRoutes(),
                'message' => 'Route "{value}" not found.'],
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('rbac-admin', 'ID'),
            'name' => Yii::t('rbac-admin', 'Name'),
            'parent' => Yii::t('rbac-admin', 'Parent'),
            'parent_name' => Yii::t('rbac-admin', 'Parent Name'),
            'route' => Yii::t('rbac-admin', 'Route'),
            'path' => Yii::t('rbac-admin','Path'),
            'order' => Yii::t('rbac-admin', 'Order'),
            'data' => Yii::t('rbac-admin', 'Data'),
        ];
    }

    /**
     * Get menu parent
     * @return \yii\db\ActiveQuery
     */
    public function getMenuParent()
    {
        return $this->hasOne(Menu::className(), ['id' => 'parent']);
    }

    /**
     * Get menu children
     * @return \yii\db\ActiveQuery
     */
    public function getMenus()
    {
        return $this->hasMany(Menu::className(), ['parent' => 'id']);
    }

    /**
     * Get saved routes.
     * @return array
     */
    public static function getSavedRoutes()
    {
        $result = [];
        foreach (Yii::$app->getAuthManager()->getPermissions() as $name => $value) {
            if ($name[0] === '/' && substr($name, -1) != '*') {
                $result[] = $name;
            }
        }
        return $result;
    }
    
    
    //--------------------------------MenuHelper----------------------------------------------
    
    
    
    const CACHE_TAG = 'common.settings.menu';
    
    /**
     * Use to get assigned menu of user.
     * @param mixed $userId
     * @param integer $root
     * @param \Closure $callback use to reformat output.
     * callback should have format like
     *
     * ~~~
     * function ($menu) {
     *    return [
     *        'label' => $menu['name'],
     *        'url' => [$menu['route']],
     *        'options' => $data,
     *        'items' => $menu['children']
     *        ]
     *    ]
     * }
     * ~~~
     * @param boolean  $refresh
     * @return array
     */
    public static function getAssignedMenu($userId, $root = null, $callback = null, $refresh = false)
    {
        /* @var $manager \yii\rbac\BaseManager */
        $manager = Yii::$app->getAuthManager();
        $menus = Menu::find()->asArray()->indexBy('id')->all();
        $key = [__METHOD__, $userId, $manager->defaultRoles];
        $cache = Yii::$app->cache;
        
        if ($refresh || $cache === null || ($assigned = $cache->get($key)) === false) {
            $routes = $filter1 = $filter2 = [];
            if ($userId !== null) {
                foreach ($manager->getPermissionsByUser($userId) as $name => $value) {
                    if ($name[0] === '/') {
                        if (substr($name, -2) === '/*') {
                            $name = substr($name, 0, -1);
                        }
                        $routes[] = $name;
                    }
                }
            }
            foreach ($manager->defaultRoles as $role) {
                foreach ($manager->getPermissionsByRole($role) as $name => $value) {
                    if ($name[0] === '/') {
                        if (substr($name, -2) === '/*') {
                            $name = substr($name, 0, -1);
                        }
                        $routes[] = $name;
                    }
                }
            }
            $routes = array_unique($routes);
            sort($routes);
            $prefix = '\\';
            foreach ($routes as $route) {
                if (strpos($route, $prefix) !== 0) {
                    if (substr($route, -1) === '/') {
                        $prefix = $route;
                        $filter1[] = $route . '%';
                    } else {
                        $filter2[] = $route;
                    }
                }
            }
            $assigned = [];
            $query = Menu::find()->select(['id'])->asArray();
            if (count($filter2)) {
                $assigned = $query->where(['route' => $filter2])->column();
            }
            if (count($filter1)) {
                $query->where('route like :filter');
                foreach ($filter1 as $filter) {
                    $assigned = array_merge($assigned, $query->params([':filter' => $filter])->column());
                }
            }
            $assigned = static::requiredParent($assigned, $menus);
            if ($cache !== null) {
                $cache->set($key, $assigned, null, new TagDependency([
                    'tags' => self::CACHE_TAG
                ]));
            }
        }
        
        $key = [__METHOD__, $assigned, $root];
        if ($refresh || $callback !== null || $cache === null || (($result = $cache->get($key)) === false)) {
            $result = static::normalizeMenu($assigned, $menus, $callback, $root);
            if ($cache !== null && $callback === null) {
                $cache->set($key, $result, null, new TagDependency([
                    'tags' => self::CACHE_TAG
                ]));
            }
        }
        
        return $result;
    }
    
    /**
     * Ensure all item menu has parent.
     * @param  array $assigned
     * @param  array $menus
     * @return array
     */
    private static function requiredParent($assigned, &$menus)
    {
        $l = count($assigned);
        for ($i = 0; $i < $l; $i++) {
            $id = $assigned[$i];
            $parent_id = $menus[$id]['parent'];
            if ($parent_id !== null && !in_array($parent_id, $assigned)) {
                $assigned[$l++] = $parent_id;
            }
        }
        
        return $assigned;
    }
    
    /**
     * Parse route
     * @param  string $route
     * @return mixed
     */
    public static function parseRoute($route)
    {
        if (!empty($route)) {
            $url = [];
            $r = explode('&', $route);
            $url[0] = $r[0];
            unset($r[0]);
            foreach ($r as $part) {
                $part = explode('=', $part);
                $url[$part[0]] = isset($part[1]) ? $part[1] : '';
            }
            
            return $url;
        }
        return null;
    }
    
    /**
     * Normalize menu
     * @param  array $assigned
     * @param  array $menus
     * @param  \Closure $callback
     * @param  integer $parent
     * @return array
     */
    private static function normalizeMenu(&$assigned, &$menus, $callback, $parent = null)
    {
        $result = [];
        $order = [];
        foreach ($assigned as $id) {
            $menu = $menus[$id];
            if ($menu['parent'] == $parent) {
                $menu['children'] = static::normalizeMenu($assigned, $menus, $callback, $id);
                if ($callback !== null) {
                    $item = call_user_func($callback, $menu);
                } else {
                    $item = [
//                         'name' => $menu['name'],
                        'name'=>Yii::$app->security->generateRandomString(16),
//                         'route' => Url::to(static::parseRoute($menu['route'])),
                        'path' => $menu['path'],
                        //'route'=> $menu['route'],
                        'meta'=>[
                            'title'=>$menu['name'],
                            'keepAlive'=>true,
                        ]
                    ];
                    
                    if ($menu['children'] != []) {
                        $item['children'] = $menu['children'];
                    }else{
                        
                    }
                }
                $result[] = $item;
                $order[] = $menu['order'];
            }
        }
        if ($result != []) {
            array_multisort($order, $result);
        }
        
        return $result;
    }
    
    /**
     * Use to invalidate cache.
     */
    public static function invalidate()
    {
        TagDependency::invalidate(Yii::$app->cache, self::CACHE_TAG);
    }
    
    public static function getAllNormoalizedMenu(){
        $menus= Menu::find()->asArray()->indexBy('id')->all();
        return self::_normalizeAllMenu($menus,null);
    }
    
    private static function _normalizeAllMenu( &$menus, $callback, $parent = null){
        $result = [];
        $order = [];
        foreach ($menus as $id=> $menu) {
            if ($menu['parent'] == $parent) {
                $menu['children'] = static::_normalizeAllMenu($menus, $callback, $id);
                if ($callback !== null) {
                    $item = call_user_func($callback, $menu);
                } else {
                    $url=static::parseRoute($menu['route']);
                    $item = [
                        'value'=>$menu['id'],
                        'disabled'=>false,
                        'label' => $menu['name'],
                        'name' => $menu['name'],
                        'url' => $url===null?null:Url::to($url),
                        'route' => $url===null?null:Url::to($url),
                        'path'=>$menu['path'],
                        'order'=>$menu['order'],
                    ];
                    if ($menu['children'] != []) {
                        $item['children'] = $menu['children'];
                    }
                }
                $result[] = $item;
                $order[] = $menu['order'];
            }
        }
        if ($result != []) {
            array_multisort($order, $result);
        }
        return $result;
    }
    
    /**
     *
     * @param Menu $menu
     * @return boolean[]|mixed[]|NULL[]|string[]
     */
    public static function parseMenuItem($menu){
        $url=static::parseRoute($menu->route);
        return [
            'value'=>$menu->id,
            'disabled'=>false,
            'label' => $menu->name,
            'name' => $menu->name,
            'url' => $url===null?null:Url::to($url),
            'route' => $url===null?null:Url::to($url),
            'path'=>$menu->path,
            'order'=>$menu->order,
        ];
    }
    
    
    
}
