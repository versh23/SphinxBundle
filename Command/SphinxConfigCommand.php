<?php

namespace Versh\SphinxBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SphinxConfigCommand extends ContainerAwareCommand
{



    protected function configure()
    {
        $this
            ->setName('versh:sphinx:rt-conf')
            ->setDescription('get simple rt config')
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

            //        index firm_rt
            //{
            //    type = rt
            //    path = /var/lib/sphinxsearch/data/firm_rt
            //    rt_field = street
            //    rt_field = country
            //    rt_attr_string = street_atr
            //}

        $output->writeln("index $rtName");
        $output->writeln('{');
        $output->writeln('  type = rt');
        $output->writeln("  path = /var/lib/sphinxsearch/data/$rtName");

        list($attributes, $fields, $dql) = $service->loadClassMetadata($class);

        foreach ($attributes as $k => $v )
        {
            $type = $v['type'];
            if($type == 'int') $type = 'uint';
            $output->writeln('  rt_attr_' . $type . ' = ' . $k);
        }
        foreach ($fields as $k => $v )
        {
            $output->writeln('  rt_field = ' . $k);
        }

        $output->writeln('}');


    }


}