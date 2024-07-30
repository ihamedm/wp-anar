<?php

namespace Anar;
require 'puc/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class AWCA_Updater{

    public $awca_update_checker;

    public function __constructor(){
        $this->awca_update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/ihamedm/wp-anar',
            __FILE__,
            'wp-anar'
        );

        //Set the branch that contains the stable release.
        $this->awca_update_checker->setBranch('main');
    }
}