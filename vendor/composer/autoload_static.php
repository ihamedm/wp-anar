<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit851a34b69a3a07e84c08321aa992a38a
{
    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'Anar\\Wizard\\' => 12,
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

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit851a34b69a3a07e84c08321aa992a38a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit851a34b69a3a07e84c08321aa992a38a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit851a34b69a3a07e84c08321aa992a38a::$classMap;

        }, null, ClassLoader::class);
    }
}