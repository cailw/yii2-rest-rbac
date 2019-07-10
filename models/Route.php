<?php
namespace cailw\rbac\rest\models;
/**
 * Route
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Route extends Model
{
    /**
     * @var string Route value. 
     */
    public $route;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return[
            [['route'],'safe'],
        ];
    }
}
