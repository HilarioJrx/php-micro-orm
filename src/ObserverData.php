<?php

namespace ByJG\MicroOrm;

class ObserverData
{
    // The table name that was affected
    protected string $table;

    // The event that was triggered. Can be 'insert', 'update' or 'delete'
    protected string $event;

    // The data that was inserted or updated. It is null in case of delete.
    protected mixed $data;

    // The data before update. In case of insert comes null, and in case of delete comes with the param filters.
    protected mixed $oldData;

    // The repository is listening to the event (the same as $myRepository)
    protected Repository $repository;

    public function __construct(string $table, string $event, mixed $data, mixed $oldData, Repository $repository)
    {
        $this->table = $table;
        $this->event = $event;
        $this->data = $data;
        $this->oldData = $oldData;
        $this->repository = $repository;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return mixed
     */
    public function getOldData(): mixed
    {
        return $this->oldData;
    }

    /**
     * @return Repository
     */
    public function getRepository(): Repository
    {
        return $this->repository;
    }
}