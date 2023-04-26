<?php

namespace ByJG\MicroOrm;

use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\Factory;
use ByJG\MicroOrm\Exception\TransactionException;

class TransactionManager
{

    /**
     * @var DbDriverInterface[]
     */
    protected static $connectionList = [];

    /**
     * It has an active transaction?
     *
     * @var bool
     */
    protected static $transaction = false;

    /**
     * Add or reuse a connection
     *
     * @param $uriString
     * @return \ByJG\AnyDataset\Db\DbDriverInterface
     */
    public function addConnection($uriString)
    {
        $dbDriver = Factory::getDbRelationalInstance($uriString);
        $this->addDbDriver($dbDriver);
        return self::$connectionList[$uriString];
    }

    public function addDbDriver(DbDriverInterface $dbDriver)
    {
        $uriString = $dbDriver->getUri()->__toString();

        if (!isset(self::$connectionList[$uriString])) {
            self::$connectionList[$uriString] = $dbDriver;

            if (self::$transaction) {
                self::$connectionList[$uriString]->beginTransaction();
            }
        } elseif (self::$connectionList[$uriString] !== $dbDriver) {
            throw new \InvalidArgumentException("The connection already exists with a different instance");
        }
    }

    public function addRepository(Repository $repository)
    {
        $this->addDbDriver($repository->getDbDriver());
    }

    /**
     * @return int
     */
    public function count()
    {
        return count(self::$connectionList);
    }

    /**
     * Start a database transaction with the opened connections
     *
     * @throws \ByJG\MicroOrm\Exception\TransactionException
     */
    public function beginTransaction()
    {
        if (self::$transaction) {
            throw new TransactionException("Transaction Already Started");
        }

        self::$transaction = true;
        foreach (self::$connectionList as $dbDriver) {
            $dbDriver->beginTransaction();
        }
    }


    /**
     * Commit all open transactions
     *
     * @throws \ByJG\MicroOrm\Exception\TransactionException
     */
    public function commitTransaction()
    {
        if (!self::$transaction) {
            throw new TransactionException("There is no Active Transaction");
        }

        self::$transaction = false;
        foreach (self::$connectionList as $dbDriver) {
            $dbDriver->commitTransaction();
        }
    }


    /**
     * Rollback all open transactions
     *
     * @throws \ByJG\MicroOrm\Exception\TransactionException
     */
    public function rollbackTransaction()
    {
        if (!self::$transaction) {
            throw new TransactionException("There is no Active Transaction");
        }

        self::$transaction = false;
        foreach (self::$connectionList as $dbDriver) {
            $dbDriver->rollbackTransaction();
        }
    }

    /**
     * Destroy all connections
     */
    public function destroy()
    {
        foreach (self::$connectionList as $dbDriver) {
            if (self::$transaction) {
                $dbDriver->commitTransaction();
            }
        }
        self::$transaction = false;
        self::$connectionList = [];
    }
}
