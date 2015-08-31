<?php

namespace Versh\SphinxBundle\Subscriber;

use Doctrine\ORM\QueryBuilder;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Knp\Component\Pager\Event\ItemsEvent;

class PaginateSphinxQbSubscriber implements EventSubscriberInterface
{

    public function items(ItemsEvent $event)
    {
        $target = $event->target;
        if (is_array($target) && count($target) == 3 && $target[0] instanceof QueryBuilder && $target[1] instanceof SphinxQL && is_string($target[2]))
        {

            $alias = $target[2];


            //modify sphinx
            /**
             * @var SphinxQL $sphinxQl
             */
            $sphinxQl = $target[1];
            $sphinxQl->limit($event->getOffset(), $event->getLimit());

            //execute
            $rawRes = $sphinxQl->execute()->fetchAllAssoc();

            $meta = Helper::create($sphinxQl->getConnection())->showMeta()->execute()->fetchAllAssoc();
            $total = $meta[1]['Value'];

            $event->count = (int) $total;
            $ids = [];
            foreach ( $rawRes as $row) {
                $ids[] = $row['id'];
            }


            if(count($ids))
            {
                /**
                 * @var QueryBuilder $qb
                 */
                $qb = $target[0];

                $qb->andWhere($alias . '.id in (:ids)')
                    ->setParameter('ids', $ids);

                $event->items = $qb->getQuery()->getResult();

            }else
            {
                $event->items = [];
            }



            $event->stopPropagation();
        }
    }
    public static function getSubscribedEvents()
    {
        return [
            'knp_pager.items' => ['items', 1]
        ];
    }
}