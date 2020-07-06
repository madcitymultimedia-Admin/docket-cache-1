<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite83f272e926b05c6a29fbd03b6aa4f65
{
    public static $files = array (
        'a4a119a56e50fbb293281d9a48007e0e' => __DIR__ . '/..' . '/symfony/polyfill-php80/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Php80\\' => 23,
            'Symfony\\Component\\VarExporter\\' => 30,
        ),
        'N' => 
        array (
            'Nawawi\\Docket_Cache\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Php80\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-php80',
        ),
        'Symfony\\Component\\VarExporter\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/var-exporter',
        ),
        'Nawawi\\Docket_Cache\\' => 
        array (
            0 => __DIR__ . '/../../..' . '/includes/src',
        ),
    );

    public static $classMap = array (
        'Nawawi\\Docket_Cache\\CLI\\Command' => __DIR__ . '/../../..' . '/includes/src/CLI/Command.php',
        'Nawawi\\Docket_Cache\\Constans\\Constans' => __DIR__ . '/../../..' . '/includes/src/Constans.php',
        'Nawawi\\Docket_Cache\\Plugin' => __DIR__ . '/../../..' . '/includes/src/Plugin.php',
        'Stringable' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/Stringable.php',
        'Symfony\\Component\\VarExporter\\Exception\\ClassNotFoundException' => __DIR__ . '/..' . '/symfony/var-exporter/Exception/ClassNotFoundException.php',
        'Symfony\\Component\\VarExporter\\Exception\\ExceptionInterface' => __DIR__ . '/..' . '/symfony/var-exporter/Exception/ExceptionInterface.php',
        'Symfony\\Component\\VarExporter\\Exception\\NotInstantiableTypeException' => __DIR__ . '/..' . '/symfony/var-exporter/Exception/NotInstantiableTypeException.php',
        'Symfony\\Component\\VarExporter\\Instantiator' => __DIR__ . '/..' . '/symfony/var-exporter/Instantiator.php',
        'Symfony\\Component\\VarExporter\\Internal\\Exporter' => __DIR__ . '/..' . '/symfony/var-exporter/Internal/Exporter.php',
        'Symfony\\Component\\VarExporter\\Internal\\Hydrator' => __DIR__ . '/..' . '/symfony/var-exporter/Internal/Hydrator.php',
        'Symfony\\Component\\VarExporter\\Internal\\Reference' => __DIR__ . '/..' . '/symfony/var-exporter/Internal/Reference.php',
        'Symfony\\Component\\VarExporter\\Internal\\Registry' => __DIR__ . '/..' . '/symfony/var-exporter/Internal/Registry.php',
        'Symfony\\Component\\VarExporter\\Internal\\Values' => __DIR__ . '/..' . '/symfony/var-exporter/Internal/Values.php',
        'Symfony\\Component\\VarExporter\\VarExporter' => __DIR__ . '/..' . '/symfony/var-exporter/VarExporter.php',
        'Symfony\\Polyfill\\Php80\\Php80' => __DIR__ . '/..' . '/symfony/polyfill-php80/Php80.php',
        'ValueError' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/ValueError.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite83f272e926b05c6a29fbd03b6aa4f65::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite83f272e926b05c6a29fbd03b6aa4f65::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite83f272e926b05c6a29fbd03b6aa4f65::$classMap;

        }, null, ClassLoader::class);
    }
}
