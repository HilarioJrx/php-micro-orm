<?php
/**
 * Created by PhpStorm.
 * User: jg
 * Date: 21/06/16
 * Time: 12:01
 */

namespace ByJG\MicroOrm;

class Query
{
    protected $fields = [];
    protected $table = "";
    protected $where = [];
    protected $groupBy = [];
    protected $orderBy = [];
    protected $join = [];
    
    protected $forUpdate = false;

    public static function getInstance()
    {
        return new Query();
    }

    /**
     * Example:
     *   $query->fields(['name', 'price']);
     * 
     * @param array $fields
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = array_merge($this->fields, (array)$fields);
        
        return $this;
    }

    /**
     * Example
     *    $query->table('product');
     * 
     * @param string $table
     * @return $this
     */
    public function table($table) 
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Example:
     *    $query->join('sales', 'product.id = sales.id');
     * 
     * @param string $table
     * @param string $filter
     * @return $this
     */
    public function join($table, $filter)
    {
        $this->join[] = [ 'table'=>$table, 'filter'=>$filter, 'type' => 'INNER'];
        return $this;
    }

    /**
     * Example:
     *    $query->join('sales', 'product.id = sales.id');
     *
     * @param string $table
     * @param string $filter
     * @return $this
     */
    public function leftJoin($table, $filter)
    {
        $this->join[] = [ 'table'=>$table, 'filter'=>$filter, 'type' => 'LEFT'];
        return $this;
    }

    /**
     * Example:
     *    $query->filter('price > [[amount]]', [ 'amount' => 1000] );
     * 
     * @param string $filter
     * @param array $params
     * @return $this
     */
    public function where($filter, array $params = [])
    {
        $this->where[] = [ 'filter' => $filter, 'params' => $params  ];
        return $this;
    }

    /**
     * Example:
     *    $query->groupBy(['name']);
     * 
     * @param array $fields
     * @return $this
     */
    public function groupBy(array $fields)
    {
        $this->groupBy = array_merge($this->groupBy, $fields);
    
        return $this;
    }

    /**
     * Example:
     *     $query->orderBy(['price desc']);
     * 
     * @param array $fields
     * @return $this
     */
    public function orderBy(array $fields)
    {
        $this->orderBy = array_merge($this->orderBy, $fields);

        return $this;
    }

    public function forUpdate()
    {
        $this->forUpdate = true;
        
        return $this;
    }
    
    protected function getFields()
    {
        if (empty($this->fields)) {
            return ' * ';
        }

        return ' ' . implode(', ', $this->fields) . ' ';
    }
    
    protected function getJoin()
    {
        $join = $this->table;
        foreach ($this->join as $item) {
            $join .= ' ' . $item['type'] . ' JOIN ' . $item['table'] . ' ON ' . $item['filter'];
        }
        return $join;
    }
    
    protected function getWhere() 
    {
        $where = [];
        $params = [];

        foreach ($this->where as $item) {
            $where[] = $item['filter'];
            $params = array_merge($params, $item['params']);
        }
        
        if (empty($where)) {
            return null;
        }
        
        return [ implode(' AND ', $where), $params ];
    }

    /**
     * @return array
     */
    public function build()
    {
        $sql = "SELECT " .
            $this->getFields() . 
            "FROM " . $this->getJoin();
        
        $where = $this->getWhere();
        $params = null;
        if (!is_null($where)) {
            $sql .= ' WHERE ' . $where[0];
            $params = $where[1];
        }
        
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        if ($this->forUpdate) {
            $sql .= ' FOR UPDATE ';
        }

        return [ 'sql' => $sql, 'params' => $params ];
    }
}
