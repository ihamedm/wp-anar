<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf493960dbf3229f8998effb35c5e910d
{
    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'Anar\\Wizard\\' => 12,
            'Anar\\Product\\' => 13,
            'Anar\\Lib\\BackgroundProcessing\\' => 30,
            'Anar\\Lib\\' => 9,
            'Anar\\Init\\' => 10,
            'Anar\\Core\\' => 10,
            'Anar\\Admin\\' => 11,
            'Anar\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Anar\\Wizard\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/wizard',
        ),
        'Anar\\Product\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/product',
        ),
        'Anar\\Lib\\BackgroundProcessing\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/lib/BackgroundProcessing',
        ),
        'Anar\\Lib\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/lib',
        ),
        'Anar\\Init\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/init',
        ),
        'Anar\\Core\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/core',
        ),
        'Anar\\Admin\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes/admin',
        ),
        'Anar\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'Parsedown' => 
            array (
                0 => __DIR__ . '/..' . '/erusev/parsedown',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf493960dbf3229f8998effb35c5e910d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf493960dbf3229f8998effb35c5e910d::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitf493960dbf3229f8998effb35c5e910d::$prefixesPsr0;
            $loader->classMap = ComposerStaticInitf493960dbf3229f8998effb35c5e910d::$classMap;

        }, null, ClassLoader::class);
    }
}
