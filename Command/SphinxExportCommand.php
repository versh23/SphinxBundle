<?php

namespace Versh\SphinxBundle\Command;

use Doctrine\Common\Annotations\FileCacheReader;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Versh\SphinxBundle\Annotation\Attr;
use Versh\SphinxBundle\Annotation\Dql;
use Versh\SphinxBundle\Annotation\Field;
use Versh\SphinxBundle\Annotation\Schema;
use Versh\SphinxBundle\Classes\XmlPipe;

class SphinxExportCommand extends ContainerAwareCommand
{

    /**
     * @var FileCacheReader
     */
    private $reader;

    /**
     * @var EntityManager
     */
    private $em;
    private $indexes;

    protected function configure()
    {
        $this
            ->setName('sphinx:export')
            ->setDescription('Export xmlpipe2 file')
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'What is the name of the index?'
            )
            ->addOption('debug', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $this->reader = $this->getContainer()->get('annotation_reader');
        $config = $this->getContainer()->getParameter('versh_sphinx.config');
        $this->indexes = $config['indexes'];

        $index = $input->getArgument('index');
        $debug = $input->getOption('debug');

        $this->export($index, $debug);
    }

    private function export($name, $debug = false)
    {
        $class = $this->indexes[$name];
        list($attributes, $fields, $dql) = $this->loadClassMetadata($class);

        $doc = new XmlPipe(array(
            'indent' => $debug
        ));
        $doc->setFields(array_keys($fields));

        $doc->setAttributes($attributes);

        $doc->beginOutput();

        $currentId = 0;

        if (!$dql) {
            $dql = "select a FROM $class a where a.id > :id";
        }

        $query = $this->em->createQuery($dql)->setMaxResults(250);


        while (true) {
            $query->setParameter('id', $currentId);
            $objects = $query->getResult();

            if (count($objects)) {
                foreach ($objects as $object) {
                    $currentId = $object->getId();
                    $data = array();

                    foreach ($attributes as $k => $attribute) {
                        $t = $this->get($object, $attribute['source']);
                        $data[$k] = $t;
                    }

                    foreach ($fields as $k => $source) {
                        $data[$k] = $this->get($object, $source['source']);
                    }
                    $data['id'] = $currentId;
                    $doc->addDocument($data);
                }

                echo $doc->flush(true);
                $this->em->clear();
            } else {
                break;
            }
        }


        $doc->endOutput();
    }

    private function loadClassMetadata($class)
    {
        $attributes = [];
        $fields = [];
        $dql = null;

        $reflClass = new \ReflectionClass($class);

        $annotations = $this->reader->getClassAnnotations($reflClass);

        foreach ($annotations as $item) {
            if ($item instanceof Schema) {
                foreach ($item->schema as $constraint) {
                    if ($constraint instanceof Attr) {
                        $constraint->name = strtolower($constraint->name);
                        $attributes[$constraint->name] = (array)$constraint;
                    } elseif ($constraint instanceof Field) {
                        $constraint->name = strtolower($constraint->name);
                        $fields[$constraint->name] = (array)$constraint;
                    }
                }
            } elseif ($item instanceof Dql) {
                $dql = $item->dql;
            }
        }

        return [
            $attributes,
            $fields,
            $dql
        ];
    }

    private function get($obj, $str)
    {
        $v = null;

        if ('@' == $str[0]) { // repo function
            $method = substr($str, 1);
            $repo = $this->em->getRepository(get_class($obj));
            $v = $repo->$method($obj);

        } else {
            $v = $obj;

            $str = explode('.', $str);
            foreach ($str as $s) {
                $method = 'get' . ucfirst($s);
                $v = $v->$method();
            }
        }

        if (is_array($v)) {
            $v = implode(',', $v);
        }

        return $v;
    }

}