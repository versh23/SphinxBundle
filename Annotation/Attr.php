<?php

namespace Versh\SphinxBundle\Annotation;

/** @Annotation */
class Attr {

    public $source;
    public $name;
    public $type = 'int';
    public $bits;
    public $default;


}