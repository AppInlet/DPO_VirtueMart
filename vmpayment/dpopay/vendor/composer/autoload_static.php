<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit78c89c3fc2eab4d9c776745e4a93faac
{
    public static $prefixLengthsPsr4 = array (
        'D' => 
        array (
            'Dpo\\VirtueMart\\' => 15,
            'Dpo\\Common\\' => 11,
            'DpoPay\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Dpo\\VirtueMart\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Dpo\\Common\\' => 
        array (
            0 => __DIR__ . '/..' . '/dpo/dpo-pay-common/src',
        ),
        'DpoPay\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit78c89c3fc2eab4d9c776745e4a93faac::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit78c89c3fc2eab4d9c776745e4a93faac::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit78c89c3fc2eab4d9c776745e4a93faac::$classMap;

        }, null, ClassLoader::class);
    }
}
