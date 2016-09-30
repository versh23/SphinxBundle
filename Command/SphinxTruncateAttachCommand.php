<?php

namespace Versh\SphinxBundle\Command;


use Foolz\SphinxQL\Helper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SphinxTruncateAttachCommand extends ContainerAwareCommand
{



    protected function configure()
    {
        $this
            ->setName('versh:sphinx:attach')
            ->setDescription('truncate and attach index to rt')
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'What is the name of the index?'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getContainer()->getParameter('versh_sphinx.config');
        $service = $this->getContainer()->get('sphinx');

        $index = $input->getArgument('index');


        $class = $config['indexes'][$index]['class'];

        $rtName = $service->getRtNameByClass($class);

        $service->getBuilder()->query('TRUNCATE RTINDEX ' . $rtName)->execute();

        Helper::create($service->getConnection())->attachIndex($index, $rtName)->execute();


    }


}
