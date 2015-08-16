<?php

namespace Versh\SphinxBundle\Command;

use Doctrine\Common\Annotations\AnnotationReader;
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
     * @var AnnotationReader
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
            ->setName('versh:sphinx:export')
            ->setDescription('Export xmlpipe2 file')
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'What is the name of the index?'
            )
            ->addOption('debug', 'd', InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $config = $this->getContainer()->getParameter('versh_sphinx.config');
        $this->indexes = $config['indexes'];

        $index = $input->getArgument('index');
        $debug = $input->getOption('debug');

        $this->export($index, $debug);
    }

    private function export($name, $debug = false)
    {
        $service = $this->getContainer()->get('sphinx');
        $class = $this->indexes[$name]['class'];

        list($attributes, $fields, $dql) = $service->loadClassMetadata($class);

        $doc = new XmlPipe(array(
            'indent' => $debug
        ));

        $doc->setFields($fields);

        $doc->setAttributes($attributes);

        $doc->beginOutput();

        $currentId = 0;

        if (!$dql) {
            $dql = "select a FROM $class a where a.id > :id";
        }

        $query = $this->em->createQuery($dql)->setMaxResults(500);

        while (true) {
            $query->setParameter('id', $currentId);
            $objects = $query->getResult();

            if (count($objects)) {
                foreach ($objects as $object) {
                    $currentId = $object->getId();
                    $data = array();

                    foreach ($attributes as $k => $attribute) {
                        $t = $service->get($object, $attribute['source']);
                        $data[$k] = $t;
                    }

                    foreach ($fields as $k => $source) {
                        $data[$k] = $service->get($object, $source['source']);
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


}