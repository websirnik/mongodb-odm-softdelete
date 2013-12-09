<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Configuration as ODMConfiguration;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable;
use Doctrine\ODM\MongoDB\SoftDelete\Events;
use Doctrine\ODM\MongoDB\SoftDelete\Event\LifecycleEventArgs;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\EventManager;
use Doctrine\MongoDB\Connection;
use PHPUnit_Framework_TestCase;

class FunctionalTest extends PHPUnit_Framework_TestCase
{
    private $sdm;
    private $dm;

    public function setUp()
    {
        $this->sdm = $this->getTestSoftDeleteManager();
        $this->dm = $this->sdm->getDocumentManager();
        $this->dm->getDocumentCollection(__NAMESPACE__.'\Seller')->drop();
        $this->dm->getDocumentCollection(__NAMESPACE__.'\Sellable')->drop();
    }

    public function testDelete()
    {
        $seller = $this->getTestSeller('jwage');
        $this->dm->persist($seller);
        $this->dm->flush();

        $this->sdm->delete($seller);
        $this->sdm->flush();

        $this->assertInstanceOf('DateTime', $seller->getDeleted());

        $check = $this->dm->getDocumentCollection(get_class($seller))->findOne();
        $this->assertTrue(isset($check['deleted']));
        $this->assertInstanceOf('MongoDate', $check['deleted']);
    }

    public function testDeleteMultiple()
    {
        $seller1 = $this->getTestSeller('jwage1');
        $seller2 = $this->getTestSeller('jwage2');
        $this->dm->persist($seller1);
        $this->dm->persist($seller2);
        $this->dm->flush();

        $this->sdm->delete($seller1);
        $this->sdm->delete($seller2);
        $this->sdm->flush();

        $this->assertInstanceOf('DateTime', $seller1->getDeleted());
        $this->assertInstanceOf('DateTime', $seller2->getDeleted());

        $check1 = $this->dm->getDocumentCollection(get_class($seller1))->findOne();
        $this->assertTrue(isset($check1['deleted']));
        $this->assertInstanceOf('MongoDate', $check1['deleted']);

        $check2 = $this->dm->getDocumentCollection(get_class($seller2))->findOne();
        $this->assertTrue(isset($check2['deleted']));
        $this->assertInstanceOf('MongoDate', $check2['deleted']);

        $this->sdm->restore($seller1);
        $this->sdm->restore($seller2);
        $this->sdm->flush();

        $check1 = $this->dm->getDocumentCollection(get_class($seller1))->findOne();
        $this->assertFalse(isset($check1['deleted']));

        $check2 = $this->dm->getDocumentCollection(get_class($seller2))->findOne();
        $this->assertFalse(isset($check2['deleted']));
    }

    public function testRestore()
    {
        $seller = $this->getTestSeller('jwage');
        $this->dm->persist($seller);
        $this->dm->flush();

        $this->sdm->delete($seller);
        $this->sdm->flush();

        $check = $this->dm->getDocumentCollection(get_class($seller))->findOne();
        $this->assertTrue(isset($check['deleted']));
        $this->assertInstanceOf('MongoDate', $check['deleted']);

        $this->sdm->restore($seller);
        $this->sdm->flush();

        $this->assertNull($seller->getDeleted());

        $check = $this->dm->getDocumentCollection(get_class($seller))->findOne();
        $this->assertFalse(isset($check['deleted']));
    }

    public function testEvents()
    {
        $eventManager = $this->sdm->getEventManager();
        $eventSubscriber = new TestEventSubscriber();
        $eventManager->addEventSubscriber($eventSubscriber);

        $seller = $this->getTestSeller('jwage');
        $this->dm->persist($seller);
        $this->dm->flush();

        $this->sdm->delete($seller);
        $this->sdm->flush();

        $this->assertEquals(array('preSoftDelete', 'postSoftDelete'), $eventSubscriber->called);

        $eventSubscriber->called = array();

        $this->sdm->restore($seller);
        $this->sdm->flush();

        $this->assertEquals(array('preRestore', 'postRestore'), $eventSubscriber->called);
    }

    public function testCascading()
    {
        $eventManager = $this->sdm->getEventManager();
        $eventSubscriber = new TestCascadeDeleteAndRestore();
        $eventManager->addEventSubscriber($eventSubscriber);

        $seller = $this->getTestSeller('jwage');
        $sellable1 = $this->getTestSellable($seller);
        $sellable2 = $this->getTestSellable($seller);
        $sellable3 = $this->getTestSellable($seller);
        $this->dm->persist($seller);
        $this->dm->persist($sellable1);
        $this->dm->persist($sellable2);
        $this->dm->persist($sellable3);
        $this->dm->flush();

        $this->sdm->delete($sellable3);
        $this->sdm->delete($seller);
        $this->sdm->flush();

        $count = $this->dm->createQueryBuilder(get_class($seller))
            ->field('deleted')->exists(false)
            ->getQuery()
            ->count();
        $this->assertEquals(0, $count);

        $count = $this->dm->createQueryBuilder(get_class($seller))
            ->field('deleted')->exists(true)
            ->getQuery()
            ->count();
        $this->assertEquals(1, $count);

        $count = $this->dm->createQueryBuilder(get_class($sellable1))
            ->field('deleted')->exists(false)
            ->getQuery()
            ->count();
        $this->assertEquals(0, $count);

        $count = $this->dm->createQueryBuilder(get_class($sellable1))
            ->field('deleted')->exists(true)
            ->getQuery()
            ->count();
        $this->assertEquals(3, $count);

        $this->sdm->restore($seller);
        $this->sdm->flush();

        $count = $this->dm->createQueryBuilder(get_class($seller))
            ->field('deleted')->exists(false)
            ->getQuery()
            ->count();
        $this->assertEquals(1, $count);

        $count = $this->dm->createQueryBuilder(get_class($sellable1))
            ->field('deleted')->exists(false)
            ->getQuery()
            ->count();
        $this->assertEquals(2, $count);
    }

    private function getTestDocumentManager()
    {
        $configuration = new ODMConfiguration();
        $configuration->setHydratorDir(__DIR__);
        $configuration->setHydratorNamespace('TestHydrator');
        $configuration->setProxyDir(__DIR__);
        $configuration->setProxyNamespace('TestProxy');
        $configuration->setMetadataDriverImpl($configuration->newDefaultAnnotationDriver());

        $conn = new Connection(null, array(), $configuration);
        return DocumentManager::create($conn, $configuration);
    }

    public function getTestSeller($name)
    {
        return new Seller($name);
    }

    public function getTestSellable(Seller $seller)
    {
        return new Sellable($seller);
    }

    public function getTestConfiguration()
    {
        return new Configuration();
    }

    public function getTestEventManager()
    {
        return new EventManager();
    }

    private function getTestSoftDeleteManager()
    {
        $dm = $this->getTestDocumentManager();
        $configuration = $this->getTestConfiguration();
        $eventManager = $this->getTestEventManager();
        return new SoftDeleteManager($dm, $configuration, $eventManager);
    }
}

class TestCascadeDeleteAndRestore implements \Doctrine\Common\EventSubscriber
{
    public function preSoftDelete(LifecycleEventArgs $args)
    {
        $sdm = $args->getSoftDeleteManager();
        $dm = $sdm->getDocumentManager();
        $document = $args->getDocument();
        if ($document instanceof Seller) {
            $sdm->deleteBy(
                __NAMESPACE__.'\Sellable',
                array('seller.id' => $document->getId()),
                array('cascadeDeletedBy' => $dm->createDBRef($document))
            );
        }
    }

    public function preRestore(LifecycleEventArgs $args)
    {
        $sdm = $args->getSoftDeleteManager();
        $dm = $sdm->getDocumentManager();
        $document = $args->getDocument();
        if ($document instanceof Seller) {
            $sdm->restoreBy(
                __NAMESPACE__.'\Sellable',
                array('seller.id' => $document->getId()),
                array('cascadeDeletedBy' => $dm->createDbRef($document))
            );
        }
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::preSoftDelete,
            Events::preRestore
        );
    }
}

class TestEventSubscriber implements \Doctrine\Common\EventSubscriber
{
    public $called = array();

    public function preSoftDelete(LifecycleEventArgs $args)
    {
        $this->called[] = 'preSoftDelete';
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $this->called[] = 'postSoftDelete';
    }

    public function preRestore(LifecycleEventArgs $args)
    {
        $this->called[] = 'preRestore';
    }

    public function postRestore(LifecycleEventArgs $args)
    {
        $this->called[] = 'postRestore';
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::preSoftDelete,
            Events::postSoftDelete,
            Events::preRestore,
            Events::postRestore
        );
    }
}

/** @ODM\Document */
class Seller implements SoftDeleteable
{
    /** @ODM\Id */
    private $id;

    /** @ODM\Date @ODM\Index */
    private $deleted;

    /** @ODM\String */
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDeleted()
    {
        return $this->deleted;
    }
}

/** @ODM\Document */
class Sellable implements SoftDeleteable
{
    /** @ODM\Id */
    private $id;

    /** @ODM\Date @ODM\Index */
    private $deleted;

    /** @ODM\ReferenceOne(targetDocument="Seller") */
    private $seller;

    public function __construct(Seller $seller)
    {
        $this->seller = $seller;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSeller()
    {
        return $this->seller;
    }

    public function getDeleted()
    {
        return $this->deleted;
    }
}
