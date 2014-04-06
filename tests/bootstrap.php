<?php

$loader = require 'vendor/autoload.php';

// Could not figure out how to add this to the loader
require 'TestCase.php';

// Add stuff to autoload
$loader->add('', 'tests/models');
$loader->add('', 'tests/seeds');
