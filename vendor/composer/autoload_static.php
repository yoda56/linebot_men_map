<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8518c9ec9aa6a8f88253941ce6020558
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LINE\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LINE\\' => 
        array (
            0 => __DIR__ . '/..' . '/linecorp/line-bot-sdk/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8518c9ec9aa6a8f88253941ce6020558::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8518c9ec9aa6a8f88253941ce6020558::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
