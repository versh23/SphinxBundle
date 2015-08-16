<?php

namespace Versh\SphinxBundle\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Doctrine\ORM\EntityManager;
use Foolz\SphinxQL\SphinxQL;
use Versh\SphinxBundle\Annotation\Attr;
use Versh\SphinxBundle\Annotation\Dql;
use Versh\SphinxBundle\Annotation\Field;
use Versh\SphinxBundle\Annotation\Schema;

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
     * @return Connection
     */
    public function getConnection()
    {
        return $this->sphinxCon;
    }

    public function toEntity($index, $results, $type = self::FETCH_TYPE_SORTING)
    {
        $class = $this->indexes[$index];
        $uniqFiled = $this->em->getMetadataFactory()->getMetadataFor($class)->getIdentifierFieldNames();
        $getter = 'get' . ucfirst($uniqFiled[0]);
        $out = [];

        if ($type == self::FETCH_TYPE_SORTING) {
            $geoIds = [];
            $sort = 0;
            foreach ($results as $el) {
                $geoIds[$el['id']] = $sort;
                $sort++;
            }
            $entities = $this->em->getRepository($class)->findBy([
                'id' => array_keys($geoIds)
            ]);

            $ids2 = [];
            foreach ($entities as $en) {
                $eid = $en->$getter();
                $sortId = $geoIds[$eid];
                $ids2[$sortId] = $en;
            }
            ksort($ids2);

            $out = $ids2;
        } else {
            foreach ($results as $row) {
                $out[] = $this->em->find($class, $row['id']);
            }

        }

        return $out;
    }

    public function rtDelete($entity)
    {

        $id = $entity->getId();

        $nameRt = $this->getRtName($entity);

        $builder = $this->getBuilder();

        $builder->delete()
            ->from($nameRt)
            ->where('id', '=', $id);

        return $builder->execute();
    }

    private function getRtName($entity)
    {
        $nameRt = null;

        foreach ($this->indexes as $k => $v) {
            if ($v['class'] == get_class($entity) && $v['rt'])
                $nameRt = $k . '_rt';
        }

        if (!$nameRt)
            throw new \Exception('not found rt index for class ' . get_class($entity));

        return $nameRt;
    }

    /**
     * @return SphinxQL
     */
    public function getBuilder()
    {
        return SphinxQL::create($this->sphinxCon);
    }

    public function rtInsert($entity)
    {
        $allFileds = $this->getFileds($entity);

        $nameRt = $this->getRtName($entity);

        $builder = $this->getBuilder();

        $builder->insert()
            ->into($nameRt)
            ->values(array_values($allFileds))
            ->columns(array_keys($allFileds));

        return $builder->execute();
    }

    private function getFileds($entity)
    {
        list($attributes, $fields, $dql) = $this->loadClassMetadata($entity);

        $allFileds = [];

        foreach ($fields as $k => $v) {
            $allFileds[$k] = $this->get($entity, $v['source']);
        }
        foreach ($attributes as $k => $v) {
            $allFileds[$k] = $this->get($entity, $v['source']);
        }

        $allFileds['id'] = $entity->getId();


        return $allFileds;
    }

    public function loadClassMetadata($class)
    {
        $attributes = [];
        $fields = [];
        $dql = null;

        $reflClass = new \ReflectionClass($class);

        $reader = new AnnotationReader();

        $annotations = $reader->getClassAnnotations($reflClass);

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

    public function get($obj, $str)
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

    public function rtUpdate($entity)
    {
        $allFileds = $this->getFileds($entity);

        $nameRt = $this->getRtName($entity);

        $builder = $this->getBuilder();

        $builder->replace()
            ->into($nameRt)
            ->values(array_values($allFileds))
            ->columns(array_keys($allFileds));

        return $builder->execute();
    }

}
