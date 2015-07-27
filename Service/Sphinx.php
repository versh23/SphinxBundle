<?php

namespace Versh\SphinxBundle\Service;

use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Doctrine\ORM\EntityManager;
use Foolz\SphinxQL\SphinxQL;

class Sphinx
{
    const FETCH_TYPE_SORTING = 1, FETCH_TYPE_QUERYING = 2;

    /**
     * @var Connection
     */
    private $sphinxCon;

    /**
     * @var EntityManager
     */
    private $em;

    private $indexes;


    public function __construct($config, EntityManager $em)
    {
        $conn = new Connection();
        $conn->setParams(array('host' => $config['host'], 'port' => $config['port']));
        $this->sphinxCon = $conn;
        $this->em = $em;
        $this->indexes = $config['indexes'];
    }

    /**
     * @return SphinxQL
     */
    public function getBuilder()
    {
        return SphinxQL::create($this->sphinxCon);
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->sphinxCon;
    }

    public function toEntity($index, $results, $type = self::FETCH_TYPE_SORTING)
    {
        $class = $this->indexes[$index];
        $uniqFiled = $this->em->getClassMetadata($class)->getIdentifierFieldNames();
        $getter = 'get'.ucfirst($uniqFiled[0]);
        $out = [];

        if($type == self::FETCH_TYPE_SORTING)
        {
            $geoIds = [];
            $sort = 0;
            foreach ($results as $el) {
                $geoIds[$el['id']] = $sort;
                $sort++;
            }
            $entities = $this->em->getRepository($class)->findBy([
                'id'    =>  array_keys($geoIds)
            ]);

            $ids2 = [];
            foreach($entities as $en)
            {
                $eid = $en->$getter();
                $sortId = $geoIds[$eid];
                $ids2[$sortId] = $en;
            }
            ksort($ids2);

            $out = $ids2;
        }
        else{
            foreach ($results as $row) {
                $out[] = $this->em->find($class, $row['id']);
           }

        }

        return $out;
    }


}
