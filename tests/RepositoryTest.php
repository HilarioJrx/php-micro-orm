<?php

namespace Test;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\Factory;
use ByJG\MicroOrm\DeleteQuery;
use ByJG\MicroOrm\Exception\AllowOnlyNewValuesConstraintException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Literal;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\ObserverData;
use ByJG\MicroOrm\ObserverProcessorInterface;
use ByJG\MicroOrm\ORMSubject;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\QueryBasic;
use ByJG\MicroOrm\Repository;
use ByJG\MicroOrm\Union;
use ByJG\MicroOrm\Updatable;
use ByJG\MicroOrm\UpdateConstraint;
use ByJG\Util\Uri;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Model/Users.php';
require_once __DIR__ . '/Model/UsersMap.php';
require_once __DIR__ . '/Model/Info.php';

class RepositoryTest extends TestCase
{

    const URI='sqlite:///tmp/test.db';

    /**
     * @var Mapper
     */
    protected $userMapper;

    /**
     * @var Mapper
     */
    protected $infoMapper;

    /**
     * @var DbDriverInterface
     */
    protected $dbDriver;

    /**
     * @var Repository
     */
    protected $repository;

    public function setUp(): void
    {
        $this->dbDriver = Factory::getDbRelationalInstance(self::URI);

        $this->dbDriver->execute('create table users (
            id integer primary key  autoincrement,
            name varchar(45),
            createdate datetime);'
        );
        $this->dbDriver->execute("insert into users (name, createdate) values ('John Doe', '2017-01-02')");
        $this->dbDriver->execute("insert into users (name, createdate) values ('Jane Doe', '2017-01-04')");
        $this->dbDriver->execute("insert into users (name, createdate) values ('JG', '1974-01-26')");
        $this->userMapper = new Mapper(Users::class, 'users', 'Id');


        $this->dbDriver->execute('create table info (
            id integer primary key  autoincrement,
            iduser INTEGER,
            property number(10,2));'
        );
        $this->dbDriver->execute("insert into info (iduser, property) values (1, 30.4)");
        $this->dbDriver->execute("insert into info (iduser, property) values (1, 1250.96)");
        $this->dbDriver->execute("insert into info (iduser, property) values (3, '3.5')");
        $this->infoMapper = new Mapper(Info::class, 'info', 'id');
        $this->infoMapper->addFieldMapping(FieldMapping::create('value')->withFieldName('property'));

        $this->repository = new Repository($this->dbDriver, $this->userMapper);
        ORMSubject::getInstance()->clearObservers();
    }

    public function tearDown(): void
    {
        $uri = new Uri(self::URI);
        unlink($uri->getPath());
    }

    public function testGet()
    {
        $users = $this->repository->get(1);
        $this->assertEquals(1, $users->getId());
        $this->assertEquals('John Doe', $users->getName());
        $this->assertEquals('2017-01-02', $users->getCreatedate());

        $users = $this->repository->get(2);
        $this->assertEquals(2, $users->getId());
        $this->assertEquals('Jane Doe', $users->getName());
        $this->assertEquals('2017-01-04', $users->getCreatedate());

        $users = $this->repository->get(new Literal(1));
        $this->assertEquals(1, $users->getId());
        $this->assertEquals('John Doe', $users->getName());
        $this->assertEquals('2017-01-02', $users->getCreatedate());
    }

    public function testGetSelectFunction()
    {
        $this->userMapper = new Mapper(UsersMap::class, 'users', 'id');
        $this->repository = new Repository($this->dbDriver, $this->userMapper);

        $this->userMapper->addFieldMapping(FieldMapping::create('name')
            ->withSelectFunction(function ($value, $instance) {
                return '[' . strtoupper($value) . '] - ' . $instance->getCreatedate();
            }
            )
        );

        $this->userMapper->addFieldMapping(FieldMapping::create('year')
            ->withSelectFunction(function ($value, $instance) {
                $date = new \DateTime($instance->getCreatedate());
                return intval($date->format('Y'));
            })
        );

        $users = $this->repository->get(1);
        $this->assertEquals(1, $users->getId());
        $this->assertEquals('[JOHN DOE] - 2017-01-02', $users->getName());
        $this->assertEquals('2017-01-02', $users->getCreatedate());
        $this->assertEquals(2017, $users->getYear());

        $users = $this->repository->get(2);
        $this->assertEquals(2, $users->getId());
        $this->assertEquals('[JANE DOE] - 2017-01-04', $users->getName());
        $this->assertEquals('2017-01-04', $users->getCreatedate());
        $this->assertEquals(2017, $users->getYear());
    }

    public function testInsert()
    {
        $users = new Users();
        $users->setName('Bla99991919');
        $users->setCreatedate('2015-08-09');

        $this->assertEquals(null, $users->getId());
        $this->repository->save($users);
        $this->assertEquals(4, $users->getId());

        $users2 = $this->repository->get(4);

        $this->assertEquals(4, $users2->getId());
        $this->assertEquals('Bla99991919', $users2->getName());
        $this->assertEquals('2015-08-09', $users2->getCreatedate());
    }

    public function testInsertReadOnly()
    {
        $this->expectException(RepositoryReadOnlyException::class);

        $users = new Users();
        $users->setName('Bla99991919');
        $users->setCreatedate('2015-08-09');

        $this->assertEquals(null, $users->getId());
        $this->repository->setRepositoryReadOnly();
        $this->repository->save($users);
    }

    public function testInsert_beforeInsert()
    {
        $users = new Users();
        $users->setName('Bla');

        $this->repository->setBeforeInsert(function ($instance) {
            $instance['name'] .= "-add";
            $instance['createdate'] .= "2017-12-21";
            return $instance;
        });

        $this->assertEquals(null, $users->getId());
        $this->repository->save($users);
        $this->assertEquals(4, $users->getId());

        $users2 = $this->repository->get(4);

        $this->assertEquals(4, $users2->getId());
        $this->assertEquals('Bla-add', $users2->getName());
        $this->assertEquals('2017-12-21', $users2->getCreatedate());
    }

    public function testInsertLiteral()
    {
        $users = new Users();
        $users->setName(new Literal("X'6565'"));
        $users->setCreatedate('2015-08-09');

        $this->assertEquals(null, $users->getId());
        $this->repository->save($users);
        $this->assertEquals(4, $users->getId());

        $users2 = $this->repository->get(4);

        $this->assertEquals(4, $users2->getId());
        $this->assertEquals('ee', $users2->getName());
        $this->assertEquals('2015-08-09', $users2->getCreatedate());
    }

    public function testInsertKeyGen()
    {
        $this->infoMapper = new Mapper(
            Users::class,
            'users',
            'id'
        );
        $this->infoMapper->withPrimaryKeySeedFunction(function ($instance) {
            return 50;
        });
        $this->repository = new Repository($this->dbDriver, $this->infoMapper);

        $users = new Users();
        $users->setName('Bla99991919');
        $users->setCreatedate('2015-08-09');
        $this->assertEquals(null, $users->getId());
        $this->repository->save($users);
        $this->assertEquals(50, $users->getId());

        $users2 = $this->repository->get(50);

        $this->assertEquals(50, $users2->getId());
        $this->assertEquals('Bla99991919', $users2->getName());
        $this->assertEquals('2015-08-09', $users2->getCreatedate());
    }

    public function testInsertUpdateFunction()
    {
        $this->userMapper = new Mapper(UsersMap::class, 'users', 'id');
        $this->repository = new Repository($this->dbDriver, $this->userMapper);

        $this->userMapper->addFieldMapping(FieldMapping::create('name')
            ->withUpdateFunction(function ($value, $instance) {
                return 'Sr. ' . $value . ' - ' . $instance->getCreatedate();
            })
        );

        $this->userMapper->addFieldMapping(FieldMapping::create('year')
            ->withUpdateFunction(Mapper::doNotUpdateClosure())
            ->withSelectFunction(function ($value, $instance) {
                $date = new \DateTime($instance->getCreateDate());
                return intval($date->format('Y'));
            })
        );

        $users = new UsersMap();
        $users->setName('John Doe');
        $users->setCreatedate('2015-08-09');
        $users->setYear('NOT USED!');

        $this->assertEquals(null, $users->getId());
        $this->repository->save($users);
        $this->assertEquals(4, $users->getId());

        $users2 = $this->repository->get(4);

        $this->assertEquals(4, $users2->getId());
        $this->assertEquals('2015-08-09', $users2->getCreatedate());
        $this->assertEquals(2015, $users2->getYear());
        $this->assertEquals('Sr. John Doe - 2015-08-09', $users2->getName());
    }

    public function testUpdate()
    {
        $users = $this->repository->get(1);

        $users->setName('New Name');
        $users->setCreatedate('2016-01-09');
        $this->repository->save($users);

        $users2 = $this->repository->get(1);
        $this->assertEquals(1, $users2->getId());
        $this->assertEquals('New Name', $users2->getName());
        $this->assertEquals('2016-01-09', $users2->getCreatedate());

        $users2 = $this->repository->get(2);
        $this->assertEquals(2, $users2->getId());
        $this->assertEquals('Jane Doe', $users2->getName());
        $this->assertEquals('2017-01-04', $users2->getCreatedate());
    }

    public function testUpdateReadOnly()
    {
        $this->expectException(RepositoryReadOnlyException::class);

        $users = $this->repository->get(1);

        $users->setName('New Name');
        $users->setCreatedate('2016-01-09');
        $this->repository->setRepositoryReadOnly();
        $this->repository->save($users);
    }

    public function testUpdate_beforeUpdate()
    {
        $users = $this->repository->get(1);

        $users->setName('New Name');

        $this->repository->setBeforeUpdate(function ($instance) {
            $instance['name'] .= "-upd";
            $instance['createdate'] = "2017-12-21";
            return $instance;
        });

        $this->repository->save($users);

        $users2 = $this->repository->get(1);
        $this->assertEquals(1, $users2->getId());
        $this->assertEquals('New Name-upd', $users2->getName());
        $this->assertEquals('2017-12-21', $users2->getCreatedate());

        $users2 = $this->repository->get(2);
        $this->assertEquals(2, $users2->getId());
        $this->assertEquals('Jane Doe', $users2->getName());
        $this->assertEquals('2017-01-04', $users2->getCreatedate());
    }

    public function testUpdateLiteral()
    {
        $users = $this->repository->get(1);
        $users->setName(new Literal("X'6565'"));
        $this->repository->save($users);

        $users2 = $this->repository->get(1);

        $this->assertEquals(1, $users2->getId());
        $this->assertEquals('ee', $users2->getName());
        $this->assertEquals('2017-01-02', $users2->getCreatedate());
    }

    public function testUpdateFunction()
    {
        $this->userMapper = new Mapper(UsersMap::class, 'users', 'id');
        $this->repository = new Repository($this->dbDriver, $this->userMapper);

        $this->userMapper->addFieldMapping(FieldMapping::create('name')
            ->withUpdateFunction(function ($value, $instance) {
                return 'Sr. ' . $value;
            })
        );

        $this->userMapper->addFieldMapping(FieldMapping::create('year')
            ->withUpdateFunction(Mapper::doNotUpdateClosure())
            ->withSelectFunction(function ($value, $instance) {
                $date = new \DateTime($instance->getCreateDate());
                return intval($date->format('Y'));
            })
        );

        $users = $this->repository->get(1);

        $users->setName('New Name');
        $users->setCreatedate('2016-01-09');
        $this->repository->save($users);

        $users2 = $this->repository->get(1);
        $this->assertEquals(1, $users2->getId());
        $this->assertEquals('Sr. New Name', $users2->getName());
        $this->assertEquals('2016-01-09', $users2->getCreatedate());
        $this->assertEquals(2016, $users2->getYear());

        $users2 = $this->repository->get(2);
        $this->assertEquals(2, $users2->getId());
        $this->assertEquals('Jane Doe', $users2->getName());
        $this->assertEquals('2017-01-04', $users2->getCreatedate());
        $this->assertEquals(2017, $users2->getYear());
    }


    public function testDelete()
    {
        $this->repository->delete(1);
        $this->assertEmpty($this->repository->get(1));

        $users = $this->repository->get(2);
        $this->assertEquals(2, $users->getId());
        $this->assertEquals('Jane Doe', $users->getName());
        $this->assertEquals('2017-01-04', $users->getCreatedate());
    }

    public function testDeleteReadOnly()
    {
        $this->expectException(RepositoryReadOnlyException::class);
        $this->repository->setRepositoryReadOnly();
        $this->repository->delete(1);
    }

    public function testDeleteLiteral()
    {
        $this->repository->delete(new Literal(1));
        $this->assertEmpty($this->repository->get(1));

        $users = $this->repository->get(2);
        $this->assertEquals(2, $users->getId());
        $this->assertEquals('Jane Doe', $users->getName());
        $this->assertEquals('2017-01-04', $users->getCreatedate());
    }

    public function testDelete2()
    {
        $query = DeleteQuery::getInstance()
            ->table($this->userMapper->getTable())
            ->where('name like :name', ['name'=>'Jane%']);

        $this->repository->deleteByQuery($query);

        $users = $this->repository->get(1);
        $this->assertEquals(1, $users->getId());
        $this->assertEquals('John Doe', $users->getName());
        $this->assertEquals('2017-01-02', $users->getCreatedate());

        $users = $this->repository->get(2);
        $this->assertEmpty($users);
    }

    public function testGetByQueryNone()
    {
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>1000])
            ->orderBy(['property']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        $this->assertEquals(count($result), 0);
    }

    public function testGetByQueryOne()
    {
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>3])
            ->orderBy(['property']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        $this->assertEquals(count($result), 1);

        $this->assertEquals(3, $result[0]->getId());
        $this->assertEquals(3, $result[0]->getIduser());
        $this->assertEquals(3.5, $result[0]->getValue());

        // Set Zero
        $result[0]->setValue(0);
        $infoRepository->save($result[0]);

        $result = $infoRepository->getByQuery($query);
        $this->assertSame('0', (string)$result[0]->getValue());

        // Set Null
        $result[0]->setValue(null);
        $infoRepository->save($result[0]);

        $result = $infoRepository->getByQuery($query);
        $this->assertNull($result[0]->getValue());
    }

    public function testFilterInNone()
    {
        $result = $this->repository->filterIn([1000, 1002]);

        $this->assertEquals(count($result), 0);
    }

    public function testFilterInOne()
    {
        $result = $this->repository->filterIn(2);

        $this->assertEquals(count($result), 1);

        $this->assertEquals(2, $result[0]->getId());
        $this->assertEquals('Jane Doe', $result[0]->getName());
        $this->assertEquals('2017-01-04', $result[0]->getCreatedate());
    }

    public function testFilterInTwo()
    {
        $result = $this->repository->filterIn([2, 3, 1000, 1001]);

        $this->assertEquals(count($result), 2);

        $this->assertEquals(2, $result[0]->getId());
        $this->assertEquals('Jane Doe', $result[0]->getName());
        $this->assertEquals('2017-01-04', $result[0]->getCreatedate());

        $this->assertEquals(3, $result[1]->getId());
        $this->assertEquals('JG', $result[1]->getName());
        $this->assertEquals('1974-01-26', $result[1]->getCreatedate());
    }

    /**
     * @throws \Exception
     */
    public function testGetScalar()
    {
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->fields(['property'])
            ->where('iduser = :id', ['id'=>3]);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getScalar($query);

        $this->assertEquals(3.5, $result);
    }

    public function testGetByQueryMoreThanOne()
    {
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>1])
            ->orderBy(['property']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        $this->assertEquals(1, $result[0]->getId());
        $this->assertEquals(1, $result[0]->getIduser());
        $this->assertEquals(30.4, $result[0]->getValue());

        $this->assertEquals(2, $result[1]->getId());
        $this->assertEquals(1, $result[1]->getIduser());
        $this->assertEquals(1250.96, $result[1]->getValue());
    }

    public function testJoin()
    {
        $query = new Query();
        $query->table($this->userMapper->getTable())
            ->fields([
                'users.id',
                'users.name',
                'users.createdate',
                'info.property'
            ])
            ->join($this->infoMapper->getTable(), 'users.id = info.iduser')
            ->where('users.id = :id', ['id'=>1])
            ->orderBy(['users.id']);

        $result = $this->repository->getByQuery($query, [$this->infoMapper]);

        $this->assertEquals(1, $result[0][0]->getId());
        $this->assertEquals('John Doe', $result[0][0]->getName());
        $this->assertEquals('2017-01-02', $result[0][0]->getCreatedate());

        $this->assertEquals(1, $result[1][0]->getId());
        $this->assertEquals('John Doe', $result[1][0]->getName());
        $this->assertEquals('2017-01-02', $result[1][0]->getCreatedate());

        // - ------------------

        $this->assertEmpty($result[0][1]->getIduser());
        $this->assertEquals(30.4, $result[0][1]->getValue());

        $this->assertEmpty($result[1][1]->getIduser());
        $this->assertEquals(1250.96, $result[1][1]->getValue());

    }

    public function testLeftJoin()
    {
        $query = new Query();
        $query->table($this->userMapper->getTable())
            ->fields([
                'users.id',
                'users.name',
                'users.createdate',
                'info.property'
            ])
            ->leftJoin($this->infoMapper->getTable(), 'users.id = info.iduser')
            ->where('users.id = :id', ['id'=>2])
            ->orderBy(['users.id']);

        $result = $this->repository->getByQuery($query, [$this->infoMapper]);

        $this->assertEquals(2, $result[0][0]->getId());
        $this->assertEquals('Jane Doe', $result[0][0]->getName());
        $this->assertEquals('2017-01-04', $result[0][0]->getCreatedate());

        // - ------------------

        $this->assertEmpty($result[0][1]->getIduser());
        $this->assertEmpty($result[0][1]->getValue());
    }

    public function testTop()
    {
        $query = Query::getInstance()
            ->table($this->userMapper->getTable())
            ->top(1);

        $result = $this->repository->getByQuery($query);

        $this->assertEquals(1, $result[0]->getId());
        $this->assertEquals('John Doe', $result[0]->getName());
        $this->assertEquals('2017-01-02', $result[0]->getCreatedate());

        $this->assertEquals(1, count($result));
    }

    public function testLimit()
    {
        $query = Query::getInstance()
            ->table($this->userMapper->getTable())
            ->limit(1, 1);

        $result = $this->repository->getByQuery($query);

        $this->assertEquals(2, $result[0]->getId());
        $this->assertEquals('Jane Doe', $result[0]->getName());
        $this->assertEquals('2017-01-04', $result[0]->getCreatedate());

        $this->assertEquals(1, count($result));
    }

    public function testQueryRaw()
    {
        $query = Query::getInstance()
            ->fields([
                "name",
                "julianday('2020-06-28') - julianday(createdate) as days"
            ])
            ->table($this->userMapper->getTable())
            ->limit(1, 1);

        $result = $this->repository->getByQuery($query);

        $this->assertEquals(null, $result[0]->getId());
        $this->assertEquals('Jane Doe', $result[0]->getName());
        $this->assertEquals(null, $result[0]->getCreatedate());

        $this->assertEquals(1, count($result));

        $result = $this->repository->getByQueryRaw($query);
        $this->assertEquals([
            [
                "name" => "Jane Doe",
                "days" => 1271.0
            ]
        ], $result);
    }

    public $test = null;

    public function testObserverUpdate()
    {
        $this->test = null;

        $this->repository->addObserver(new class($this->infoMapper->getTable(), $this->repository, $this) implements ObserverProcessorInterface {
            private $table;
            private $parent;

            private $parentRepository;

            public function __construct($table, $parentRepository, $parent)
            {
                $this->table = $table;
                $this->parent = $parent;
                $this->parentRepository = $parentRepository;
            }

            public function process(ObserverData $observerData)
            {
                $this->parent->test = true;
                $this->parent->assertEquals('info', $observerData->getTable());
                $this->parent->assertEquals(ORMSubject::EVENT_UPDATE, $observerData->getEvent());
                $this->parent->assertInstanceOf(Info::class, $observerData->getData());
                $this->parent->assertEquals(0, $observerData->getData()->getValue());
                $this->parent->assertInstanceOf(Info::class, $observerData->getOldData());
                $this->parent->assertEquals(3.5, $observerData->getOldData()->getValue());
                $this->parent->assertEquals($this->parentRepository, $observerData->getRepository());
            }

            public function getObserverdTable(): string
            {
                return $this->table;
            }
        });

        // This update doesn't have observer
        $users = new Users();
        $users->setName('Bla99991919');
        $users->setCreatedate('2015-08-09');

        $this->assertEquals(null, $users->getId());
        $this->repository->save($users);
        $this->assertNull($this->test);


        // This update has an observer, and you change the `test` variable
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>3])
            ->orderBy(['property']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        // Set Zero
        $result[0]->setValue(0);
        $infoRepository->save($result[0]);
        $this->assertTrue($this->test);
    }

    public function testObserverDelete()
    {
        $this->test = null;

        $this->repository->addObserver(new class($this->infoMapper->getTable(), $this->repository, $this) implements ObserverProcessorInterface {
            private $table;
            private $parent;

            private $parentRepository;

            public function __construct($table, $parentRepository, $parent)
            {
                $this->table = $table;
                $this->parent = $parent;
                $this->parentRepository = $parentRepository;
            }

            public function process(ObserverData $observerData)
            {
                $this->parent->test = true;
                $this->parent->assertEquals('info', $observerData->getTable());
                $this->parent->assertEquals(ORMSubject::EVENT_DELETE, $observerData->getEvent());
                $this->parent->assertNull($observerData->getData());
                $this->parent->assertEquals(["idid" => 3], $observerData->getOldData());
                $this->parent->assertEquals($this->parentRepository, $observerData->getRepository());
            }

            public function getObserverdTable(): string
            {
                return $this->table;
            }
        });

        $this->assertNull($this->test);
        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->delete(3);
        $this->assertTrue($this->test);
    }

    public function testObserverInsert()
    {
        $test = null;

        $this->repository->addObserver(new class($this->infoMapper->getTable(), $this->repository, $this) implements ObserverProcessorInterface {
            private $table;
            private $parent;

            private $parentRepository;

            public function __construct($table, $parentRepository, $parent)
            {
                $this->table = $table;
                $this->parent = $parent;
                $this->parentRepository = $parentRepository;
            }

            public function process(ObserverData $observerData)
            {
                $this->parent->test = true;
                $this->parent->assertEquals('info', $observerData->getTable());
                $this->parent->assertEquals(ORMSubject::EVENT_INSERT, $observerData->getEvent());
                $this->parent->assertInstanceOf(Info::class, $observerData->getData());
                $this->parent->assertEquals(4, $observerData->getData()->getId());
                $this->parent->assertEquals(1, $observerData->getData()->getIdUser());
                $this->parent->assertEquals(3, $observerData->getData()->getValue());
                $this->parent->assertNull($observerData->getOldData());
                $this->parent->assertEquals($this->parentRepository, $observerData->getRepository());
            }

            public function getObserverdTable(): string
            {
                return $this->table;
            }
        });
        $info = new Info();
        $info->setValue("3");
        $info->setIduser(1);


        $this->assertNull($this->test);
        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $infoRepository->save($info);
        $this->assertTrue($this->test);
    }


    public function testConstraintDifferentValues()
    {
        // This update has an observer, and you change the `test` variable
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>3])
            ->orderBy(['property']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        // Define Constraint
        $updateConstraint = UpdateConstraint::instance()
            ->withAllowOnlyNewValuesForFields('value');

        // Set Zero
        $result[0]->setValue(2);
        $newInstance = $infoRepository->save($result[0], $updateConstraint);
        $this->assertEquals(2, $newInstance->getValue());
    }

    public function testConstraintSameValues()
    {
        $this->expectException(AllowOnlyNewValuesConstraintException::class);
        $this->expectExceptionMessage("You are not updating the property 'value'");

        // This update has an observer, and you change the `test` variable
        $query = new Query();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>3])
            ->orderBy(['property']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        // Define Constraint
        $updateConstraint = UpdateConstraint::instance()
            ->withAllowOnlyNewValuesForFields('value');

        // Set Zero
        $result[0]->setValue(3.5);
        $newInstance = $infoRepository->save($result[0], $updateConstraint);
    }

    public function testQueryBasic()
    {
        $query = new QueryBasic();
        $query->table($this->infoMapper->getTable())
            ->where('iduser = :id', ['id'=>3]);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($query);

        $this->assertEquals(count($result), 1);

        $this->assertEquals(3, $result[0]->getId());
        $this->assertEquals(3, $result[0]->getIduser());
        $this->assertEquals(3.5, $result[0]->getValue());
    }

    public function testUnion()
    {
        $query = new QueryBasic();
        $query->table($this->infoMapper->getTable())
            ->where('id = :id1', ['id1'=>3]);

        $query2 = new QueryBasic();
        $query2->table($this->infoMapper->getTable())
            ->where('id = :id2', ['id2'=>1]);


        $union = Union::getInstance()
            ->addQuery($query)
            ->addQuery($query2)
            ->orderBy(['iduser']);

        $infoRepository = new Repository($this->dbDriver, $this->infoMapper);
        $result = $infoRepository->getByQuery($union);

        $this->assertEquals(count($result), 2);

        $this->assertEquals(1, $result[0]->getId());
        $this->assertEquals(1, $result[0]->getIduser());
        $this->assertEquals(30.4, $result[0]->getValue());

        $this->assertEquals(3, $result[1]->getId());
        $this->assertEquals(3, $result[1]->getIduser());
        $this->assertEquals(3.5, $result[1]->getValue());
    }

}
