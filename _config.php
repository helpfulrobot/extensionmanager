<?php
require_once __DIR__ . '/vendor/autoload.php';

FulltextSearchable::enable();

Object::add_extension('Member', 'ExtensionsMemberDecorator');