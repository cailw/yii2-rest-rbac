<?php

namespace cailw\rbac\rest\controllers;

use Yii;
use cailw\rbac\rest\models\Menu;
use cailw\rbac\rest\models\Route;
use yii\caching\TagDependency;
use cailw\rbac\rest\components\RouteRule;
use cailw\rbac\rest\components\Configs;
use yii\helpers\Inflector;
use yii\helpers\VarDumper;
use Exception;

/**
 * Description of RuleController
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RouteController extends BaseController
{
    const CACHE_TAG = 'common.modules.rbac.route';

    /**
     * Lists all Route models.
     * @return mixed
     */
    public function actionIndex()
    {
        $manager = Yii::$app->getAuthManager();

        $exists = $existsOptions = $routes = [];
        
        $all=$this->getAppRoutes(); 
        sort($all);
        foreach ($all as $route){
            $routes[$route]=['label'=>$route,'key'=>$route,'disabled'=>false];
        }
        foreach ($manager->getPermissions() as $name => $permission) {
            if ($name[0] !== '/') {
                continue;
            }
            
            if(isset($routes[$name])){
                $exists[] = $name;
            }else{
                $r = explode('&', $name);
                if(in_array($r[0], $all)){
                    $routes[$name]=['label'=>$name,'key'=>$name,'disabled'=>false];
                    $exists[] = $name;
                }else{
                    $routes[$name]=['label'=>'[LOST!]'.$name,'key'=>$name,'disabled'=>false];
                    $exists[] = $name;
                }
            }
        }
        return ['routes' => array_values($routes), 'exists' => array_values($exists)];
    }

    /**
     * Creates a new AuthItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Route;
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($model->validate()) {
                $routes = preg_split('/\s*,\s*/', trim($model->route), -1, PREG_SPLIT_NO_EMPTY);
                $this->saveNew($routes);
                Menu::invalidate();
            }
        }
    }

    /**
     * Assign or remove items
     * @param string $action
     * @return array
     */
    public function actionAssign($action)
    {
        $post = Yii::$app->getRequest()->post();
        
        $routes = $post['routes'];
        $manager = Yii::$app->getAuthManager();
        $error = [];
        if ($action == 'assign') {
            $this->saveNew($routes);
        } else {
            foreach ($routes as $route) {
                $child = $manager->getPermission($route);
                try {
                    $manager->remove($child);
                } catch (Exception $exc) {
                    $error[] = $exc->getMessage();
                }
            }
        }
        Menu::invalidate();
        return true;
    }

    /**
     * Search Route
     * @param string $target
     * @param string $term
     * @param string $refresh
     * @return array
     * @menu 刷新路由
     */
    public function actionRouteRefresh()
    {
        $this->invalidate();
        
//         $routes=$this->getAppRoutes();
//         $children=[];
//         foreach ($routes as $route){
//             $children[]=['parent'=>'super_admin','child'=>$route];
//         }
//         return Yii::$app->db->createCommand()->batchInsert('{{%auth_item_child}}', array_keys($children[0]), $children)->execute();
        
        $this->saveNew($this->getAppRoutes());
        return $this->success();
    }

    /**
     * Save one or more route(s)
     * @param array $routes
     */
    private function saveNew($routes)
    {
        $manager = Yii::$app->getAuthManager();
        foreach ($routes as $route) {
            try {
                $r = explode('&', $route);
                $item = $manager->createPermission('/' . trim($route, '/'));
                if (count($r) > 1) {
                    $action = '/' . trim($r[0], '/');
                    if (($itemAction = $manager->getPermission($action)) === null) {
                        $itemAction = $manager->createPermission($action);
                        $manager->add($itemAction);
                    }
                    unset($r[0]);
                    foreach ($r as $part) {
                        $part = explode('=', $part);
                        $item->data['params'][$part[0]] = isset($part[1]) ? $part[1] : '';
                    }
                    $this->setDefaultRule();
                    $item->ruleName = RouteRule::RULE_NAME;
                    $manager->add($item);
                    $manager->addChild($item, $itemAction);
                } else {
                    $manager->add($item);
                }
            } catch (Exception $e) {

            }
        }
    }

    /**
     * Get list of application routes
     * @return array
     */
    public function getAppRoutes()
    {
        $key = __METHOD__;
        $cache = Configs::instance()->cache;
        if ($cache === null || ($result = $cache->get($key)) === false) {
            $result = [];
            $this->getRouteRecrusive(Yii::$app, $result);
            if ($cache !== null) {
                $cache->set($key, $result, Configs::instance()->cacheDuration, new TagDependency([
                    'tags' => self::CACHE_TAG
                ]));
            }
        }

        return $result;
    }

    /**
     * Get route(s) recrusive
     * @param \yii\base\Module $module
     * @param array $result
     */
    private function getRouteRecrusive($module, &$result)
    {
        $token = "Get Route of '" . get_class($module) . "' with id '" . $module->uniqueId . "'";
        Yii::beginProfile($token, __METHOD__);
        try {
            foreach ($module->getModules() as $id => $child) {
                if (($child = $module->getModule($id)) !== null) {
                    $this->getRouteRecrusive($child, $result);
                }
            }

            foreach ($module->controllerMap as $id => $type) {
                $this->getControllerActions($type, $id, $module, $result);
            }

            $namespace = trim($module->controllerNamespace, '\\') . '\\';
            $this->getControllerFiles($module, $namespace, '', $result);
//             $result[] = ($module->uniqueId === '' ? '' : '/' . $module->uniqueId) . '/*';
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    /**
     * Get list controller under module
     * @param \yii\base\Module $module
     * @param string $namespace
     * @param string $prefix
     * @param mixed $result
     * @return mixed
     */
    private function getControllerFiles($module, $namespace, $prefix, &$result)
    {
        $path = @Yii::getAlias('@' . str_replace('\\', '/', $namespace));
        $token = "Get controllers from '$path'";
        Yii::beginProfile($token, __METHOD__);
        try {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (is_dir($path . '/' . $file)) {
                    $this->getControllerFiles($module, $namespace . $file . '\\', $prefix . $file . '/', $result);
                } elseif (strcmp(substr($file, -14), 'Controller.php') === 0) {
                    $id = Inflector::camel2id(substr(basename($file), 0, -14));
                    $className = $namespace . Inflector::id2camel($id) . 'Controller';
                    if (strpos($className, '-') === false && class_exists($className) && is_subclass_of($className, 'yii\base\Controller')) {
                        $this->getControllerActions($className, $prefix . $id, $module, $result);
                    }
                }
            }
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    /**
     * Get list action of controller
     * @param mixed $type
     * @param string $id
     * @param \yii\base\Module $module
     * @param string $result
     */
    private function getControllerActions($type, $id, $module, &$result)
    {
        $token = "Create controller with cofig=" . VarDumper::dumpAsString($type) . " and id='$id'";
        Yii::beginProfile($token, __METHOD__);
        try {
            /* @var $controller \yii\base\Controller */
            $controller = Yii::createObject($type, [$id, $module]);
            $this->getActionRoutes($controller, $result);
//             $result[] = '/' . $controller->uniqueId . '/*';
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    /**
     * Get route of action
     * @param \yii\base\Controller $controller
     * @param array $result all controller action.
     */
    private function getActionRoutes($controller, &$result)
    {
        $token = "Get actions of controller '" . $controller->uniqueId . "'";
        Yii::beginProfile($token, __METHOD__);
        try {
            $prefix = '/' . $controller->uniqueId . '/';
            foreach ($controller->actions() as $id => $value) {
                $result[] = $prefix . $id;
            }
            $class = new \ReflectionClass($controller);
            foreach ($class->getMethods() as $method) {
                $name = $method->getName();
                if ($method->isPublic() && !$method->isStatic() && strpos($name, 'action') === 0 && $name !== 'actions') {
                    $result[] = $prefix . Inflector::camel2id(substr($name, 6));
                }
            }
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
        Yii::endProfile($token, __METHOD__);
    }

    /**
     * Ivalidate cache
     */
    protected function invalidate()
    {
        if (Configs::instance()->cache !== null) {
            TagDependency::invalidate(Configs::instance()->cache, self::CACHE_TAG);
        }
    }

    /**
     * Set default rule of parameterize route.
     */
    protected function setDefaultRule()
    {
        if (Yii::$app->authManager->getRule(RouteRule::RULE_NAME) === null) {
            Yii::$app->authManager->add(Yii::createObject([
                    'class' => RouteRule::className(),
                    'name' => RouteRule::RULE_NAME]
            ));
        }
    }
}
