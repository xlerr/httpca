<?php

namespace xlerr\httpca;

use yii\base\InvalidConfigException;
use yii\di\Instance;

trait ComponentTrait
{
    /**
     * @return string
     */
    public static function componentName()
    {
        return strtr(self::class, ['\\' => '_']);
    }

    /**
     * @return object|self
     * @throws InvalidConfigException
     */
    public static function instance()
    {
        return Instance::ensure(self::componentName(), self::class);
    }
}
