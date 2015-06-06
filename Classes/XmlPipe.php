<?php

namespace Versh\SphinxBundle\Classes;

/**
 * @author Linnik Sergey <linniksa@gmail.com>
 */
class XmlPipe  extends \XMLWriter{

    private $fields = array();
    private $attributes = array();
    private $docsWriten;

    public function __construct($options = array())
    {
        $defaults = array(
            'indent' => false,
        );
        $options = array_merge($defaults, $options);

        $this->openMemory();

        if ($options['indent']) {
            $this->setIndent(true);
            $this->setIndentString('  ');
        }

        $this->docsWriten = 0;
    }

    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function beginOutput()
    {
        $this->startDocument('1.0', 'UTF-8');
        $this->startElement('sphinx:docset');
        $this->startElement('sphinx:schema');

        // add fields to the schema
        foreach ($this->fields as $field) {
            $this->startElement('sphinx:field');
            $this->writeAttribute('name', $field);
            $this->endElement();
        }

        // add attributes to the schema
        foreach ($this->attributes as $attr_name => $attr_data) {
            $this->startElement('sphinx:attr');
            foreach ($attr_data as $key => $value) {
                if ('str2ordinal' == $value) $value = 'int';
                if($key !== 'source' && !is_null($value))
                    $this->writeAttribute($key, $value);
            }
            $this->endElement();
        }

        // end sphinx:schema
        $this->endElement();
    }

    public function addDocument($doc)
    {
        $this->startElement('sphinx:document');
        $this->writeAttribute('id', $doc['id']);

        foreach ($doc as $key => $value) {
            // Skip the id key since that is an element attribute
            if ($key == 'id') continue;

            $this->startElement($key);
            $this->text($value);
            $this->endElement();
        }

        $this->endElement();

        if (0 == ++$this->docsWriten % 50) {
            echo $this->outputMemory();
        }
    }

    public function endOutput()
    {
        // end sphinx:docset
        $this->endElement();
        echo $this->outputMemory();
    }

}