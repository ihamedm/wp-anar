<?php return array(
    'root' => array(
        'name' => '__root__',
        'pretty_version' => 'dev-develop',
        'version' => 'dev-develop',
        'reference' => 'b53e153014db62d0f0ccbd4cc27ac8334e4cb0b3',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        '__root__' => array(
            'pretty_version' => 'dev-develop',
            'version' => 'dev-develop',
            'reference' => 'b53e153014db62d0f0ccbd4cc27ac8334e4cb0b3',
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'johnpbloch/wordpress-core' => array(
            'pretty_version' => '6.7.1',
            'version' => '6.7.1.0',
            'reference' => '1975a1deaef23914b391f37314cc0e6a23ae16d7',
            'type' => 'wordpress-core',
            'install_path' => __DIR__ . '/../johnpbloch/wordpress-core',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'wordpress/core-implementation' => array(
            'dev_requirement' => false,
            'provided' => array(
                0 => '6.7.1',
            ),
        ),
    ),
);
