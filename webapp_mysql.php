<?php
#?ini_set('mysqli.reconnect', TRUE);
class webapp_mysql_target implements ArrayAccess
{
	private $rawdata = [], $change = [];
	protected $table, $mysql, $tablename, $primary, $key;
	final function __construct(webapp_mysql_table $table, $mixed = NULL)
	{
		$this->table = $table;
		$this->mysql = $table->mysql;
		$this->tablename = &$table->tablename();
		$this->primary = &$table->primary();
		if (is_scalar($mixed))
		{
			if ($this->rawdata = $this->mysql->row('SELECT * FROM ?a WHERE ?a=?s LIMIT 1', $this->tablename, $this->primary, $mixed))
			{
				$this->key = $this->rawdata[$this->primary];
			}
		}
		else
		{
			if (is_array($mixed))
			{
				if (array_key_exists($this->primary, $mixed))
				{
					$this->key = $mixed[$this->primary];
				}
				$this->change = $mixed;
			}
		}
	}
	function __get(string $field)
	{
		return $this[$field];
	}
	function __set(string $field, $value)
	{
		return $this[$field] = $value;
	}
	final function offsetExists($key):bool
	{
		return array_key_exists($key, $this->rawdata) || array_key_exists($key, $this->change);
	}
	final function offsetGet($key)
	{
		return array_key_exists($key, $this->change) ? $this->change[$key] : $this->rawdata[$key] ?? NULL;
	}
	final function offsetSet($key, $value):void
	{
		$data = $value === NULL ? NULL : (string)$value;
		if (array_key_exists($key, $this->rawdata) === FALSE || $this->rawdata[$key] !== $data)
		{
			$this->change[$key] = $data;
		}
	}
	final function offsetUnset($key):void
	{
		$this[$key] = NULL;
	}

	// protected function mysql(...$query):webapp_mysql
	// {
	// 	return $this->table->mysql(...$query);
	// }
	protected function table(...$cond):webapp_mysql_table
	{
		return ($this->table)(...$cond);
	}
	function save(string $key = NULL):bool
	{
		if ($this->change)
		{
			if ($key === NULL)
			{
				$key = $this->key;
			}
			if ($this->table('WHERE ?a=?s LIMIT 1', $this->primary, $key)->update($this->change))
			{
				$this->key = array_key_exists($this->primary, $this->change) ? $this->change[$this->primary] : $key;
				$this->rawdata = $this->change + $this->rawdata;
				$this->change = [];
				return TRUE;
			}
			return FALSE;
		}
		return TRUE;
	}
	function saveas(string $key = NULL):bool
	{
		$data = $this->change + $this->rawdata;
		$data[$this->primary] = $key;
		if ($this->table->insert($data))
		{
			$this->key = $data[$this->primary] === NULL
				? $data[$this->primary] = $this->mysql->insert_id
				: $data[$this->primary];
			$this->rawdata = $data;
			$this->change = [];
			return TRUE;
		}
		return FALSE;
	}
	function exist():bool
	{
		return boolval($this->rawdata);
	}
	function empty():bool
	{
		return empty($this->rawdata);
	}
	function delete():bool
	{
		if ($this->table->delete('WHERE ?a=?s LIMIT 1', $this->primary, $this->key))
		{
			$this->change += $this->rawdata;
			$this->rawdata = [];
			return TRUE;
		}
		return FALSE;
	}
}
abstract class webapp_mysql_table implements IteratorAggregate, Countable
{
	private $cond, $paging, $fields = '*';
	protected $mysql, $tablename, $targetname = 'webapp_mysql_target';
	function __construct(webapp_mysql $mysql)
	{
		$this->mysql = $mysql;
	}
	function __isset(string $name):bool
	{
		return property_exists($this, $name);
	}
	function __get(string $name)
	{
		switch ($name)
		{
			case 'mysql': return $this->mysql;
			case 'tablename': return $this->tablename();
			case 'primary': return $this->primary();
			case 'paging': return $this->paging;
			case 'scalar':
			case 'row':
				if (preg_match('/LIMIT\s+\d+(?:,\d+)?$/i', $this->cond) === 0)
				{
					$this->cond .= ' LIMIT 1';
				}
			case 'all':
				return $this->mysql->{$name}('SELECT ?? FROM ?a??', $this->fields, $this->tablename, (string)$this);

			case 'create': return $this->mysql->row('SHOW CREATE TABLE ?a', $this->tablename)['Create Table'];
		}
	}
	function __invoke(...$cond):static
	{
		return [$this, $this->cond = $this->mysql->sprintf(...$cond)][0];
	}
	function __toString():string
	{
		return [is_string($this->cond) ? " {$this->cond}" : (string)$this->cond, $this->cond = NULL, $this->fields = '*'][0];
	}
	function getIterator():Traversable
	{
		return $this->mysql->list('SELECT ?? FROM ?a??', $this->fields, $this->tablename, (string)$this);
	}
	function count(?string &$cond = NULL):int
	{
		return [$cond = $this->cond, intval($this->mysql->scalar('SELECT SQL_NO_CACHE COUNT(1) FROM ?a?? LIMIT 1', $this->tablename, (string)$this))][1];
	}
	function &tablename():string
	{
		return $this->tablename;
	}
	function &primary():string
	{
		if (property_exists($this, 'primary') === FALSE)
		{
			$this->primary =
				$this->mysql->row('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'PRI')['Field'] ??
				$this->mysql->row('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'UNI')['Field'] ?? NULL;
		}
		return $this->primary;
	}
	function fieldinfo()
	{
		$fields = [];
		foreach ($this->mysql->list('SHOW FULL COLUMNS FROM ?a', $this->tablename) as $info)
		{
			$fields[$info['Field']] = array_change_key_case($info);
		}
	
		print_r($fields);
		// array_change_key_case()
		// $fields = [];
		// foreach ($this->mysql->list('SHOW FULL COLUMNS FROM ?a', $this->tablename) as $field)
		// {
		// 	print_r($field);
		// }
	}
	function mysql(...$query):bool
	{
		return ($this->mysql)(...$query);
	}
	function insert($data):bool
	{
		return $this->mysql('INSERT INTO ?a SET ??', $this->tablename, $this->mysql->sprintf(...is_array($data) ? ['?v', $data] : func_get_args())) && $this->mysql->affected_rows === 1;
	}
	function delete(...$query):int
	{
		return $this->mysql('DELETE FROM ?a??', $this->tablename, (string)($query ? $this(...$query) : $this)) ? $this->mysql->affected_rows : -1;
	}
	function update($data):int
	{
		return $this->mysql('UPDATE ?a SET ????', $this->tablename, $this->mysql->sprintf(...is_array($data) ? ['?v', $data] : func_get_args()), (string)$this) ? intval(substr($this->mysql->info, 14)) : -1;
	}
	function select($fields):static
	{
		return [$this, $this->fields = $this->mysql->sprintf('?A', $fields)][0];
	}
	function paging(int $index, int $rows = 21):static
	{
		$this->paging['count'] = $this->count($this->paging['cond']);
		$this->paging['max'] = ceil($this->paging['count'] / $rows);
		$this->paging['index'] = max(1, min($index, $this->paging['max']));
		$this->paging['skip'] = ($this->paging['index'] - 1) * $rows;
		$this->paging['rows'] = $rows;
		return $this(join(' ', [$this->paging['cond'], 'LIMIT', "{$this->paging['skip']},{$rows}"]));
	}
	function column(string ...$keys):array
	{
		return array_column($this->select($keys)->all, ...$keys);
	}

	function target($mixed = NULL):webapp_mysql_target
	{
		return new $this->targetname($this, $mixed);
	}
	function rename(string $name):bool
	{
		if ($this->mysql('ALTER TABLE ?a RENAME TO ?a', $this->tablename, $name))
		{
			$this->tablename = $name;
			return TRUE;
		}
		return FALSE;
	}
	function truncate():bool
	{
		return $this->mysql('TRUNCATE TABLE ?a', $this->tablename);
	}
	function drop():bool
	{
		return $this->mysql('DROP TABLE ?a', $this->tablename);
	}
}
class webapp_mysql extends mysqli
{
	public array $errors = [];
	//private $conninfo, $host, $user;
	function __construct(string $host = 'p:127.0.0.1:3306', string $user = 'root', string $password = '', string $database = 'mysql', private string $maptable = 'webapp_maptable_')
	{
		$this->init();
		//$this->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
		$this->real_connect($host, $user, $password, $database);
	}
	function __destruct()
	{
		$this->close();
	}
	function __get(string $name):webapp_mysql_table
	{
		return $this->{$name} = class_exists($tablename = $this->maptable . $name, FALSE) ? new $tablename($this) : $this->table($name);
	}
	function __call(string $tablename, array $cond):webapp_mysql_table
	{
		return ($this->{$tablename})(...$cond);
	}
	function __invoke(...$query)
	{
		//var_dump($this->sprintf(...$query));
		if ($this->real_query($this->sprintf(...$query)))
		{
			return TRUE;
		}
		$this->errors[] = $this->error;
		return FALSE;
	}
	private function quote(string $name):string
	{
		return '`' . addcslashes($name, '`') . '`';
	}
	private function escape(string $value):string
	{
		return '\'' . $this->real_escape_string($value) . '\'';
	}
	// function unique_array(array $values):array
	// {
	// 	$merge = [];
	// 	return array_walk_recursive($values, function(string $value) use(&$merge)
	// 	{
	// 		$merge[] = $value;
	// 	}) ? array_unique($merge) : [];
	// }
	function sprintf(string $query, ...$formats):string
	{
		if ($formats)
		{
			$index = 0;
			$offset = 0;
			$command = [];
			$length = strlen($query);
			while (($pos = strpos($query, '?', $offset)) !== FALSE && $pos < $length && array_key_exists($index, $formats))
			{
				$command[] = substr($query, $offset, $pos - $offset);
				switch ($query[$pos + 1])
				{
					case 'A':
					case 'S':
						if (is_array($formats[$index]))
						{
							$command[] = join(',', array_map([$this, $query[$pos + 1] === 'A' ? 'quote' : 'escape'], $formats[$index++]));
							break;
						}
					case '?': $command[] = (string)$formats[$index++]; break;
					case 'a': $command[] = $this->quote((string)$formats[$index++]); break;
					case 's': $command[] = $this->escape((string)$formats[$index++]); break;
					case 'd': $command[] = intval($formats[$index++]); break;
					case 'f': $command[] = floatval($formats[$index++]); break;
					case 'v': $values = [];
						foreach ($formats[$index++] as $key => $value)
						{
							switch (TRUE)
							{
								case is_string($value):		$values[] = $this->quote($key) . '=' . $this->escape($value); continue 2;
								case is_numeric($value):	$values[] = $this->quote($key) . '=' . $value; continue 2;
								case is_null($value):		$values[] = $this->quote($key) . '=NULL'; continue 2;
								//case is_array($value):		$values[] = $this->quote($key) . '=' . $this->escape(json_encode($value, JSON_UNESCAPED_UNICODE)); continue 2;
							}
						}
						$command[] = join(',', $values);
						break;
					default: $command[] = substr($query, $pos, 2);
				}
				$offset = $pos + 2;
			}
			if ($offset < $length)
			{
				$command[] = substr($query, $offset);
			}
			return join($command);
		}
		return $query;
	}
	function scalar(...$query)
	{
		return $this(...$query) ? $this->use_result()->fetch_row()[0] : NULL;
	}
	function row(...$query):array
	{
		return $this(...$query) ? ($this->use_result()->fetch_assoc() ?? []) : [];
	}
	function all(...$query):array
	{
		return $this(...$query) ? $this->use_result()->fetch_all(MYSQLI_ASSOC) : [];
	}
	function list(...$query):Traversable
	{
		if ($this(...$query))
		{
			foreach ($this->store_result() as $row)
			{
				yield $row;
			}
		}
	}
	function sync(closure $submit, ...$args):bool
	{
		if ($this->autocommit(FALSE))
		{
			if ($submit->call($this, ...$args))
			{
				$this->commit();
				$this->autocommit(TRUE);
				return TRUE;
			}
			$this->rollback();
			$this->autocommit(TRUE);
		}
		return FALSE;
	}
	function cond(array $fieldinfo, array $cond):ArrayObject
	{
		return new class($this, $fieldinfo, $cond) extends ArrayObject
		{
			const query = [
				'eq' => '?a=?s',
				'ne' => '?a!=?s',
				'gt' => '?a>?s',
				'ge' => '?a>=?s',
				'lt' => '?a<?s',
				'le' => '?a<=?s',
				'lk' => '?a LIKE ?s',
				'nl' => '?a NOT LIKE ?s',
				'in' => '?a IN(?S)',
				'ni' => '?a NOT IN(?S)'
			];
			private $mysql, $where = [], $merge = [], $append;
			function __construct(webapp_mysql $mysql, array $fieldinfo, array $cond)
			{
				$this->mysql = $mysql;
				$this->append = $cond;
				parent::__construct($fieldinfo);
			}
			function __toString():string
			{
				$where = $this->where;
				$allow = (array)$this;
				foreach ($this->append as $fieldinfo => $value)
				{
					preg_match('/(\w+)\.(eq|ne|gt|ge|lt|le|lk|nl|in|ni)/', $fieldinfo, $required);
					if (array_key_exists($required[1], $allow))
					{
						$where[] = $value === NULL
							? $this->mysql->sprintf('?a ?? NULL', $required[1], $required[2] === 'ne' ? 'IS NOT' : 'IS')
							: $this->mysql->sprintf(self::query[$required[2]], $required[1], in_array($required[2], ['in', 'ni']) ? explode(',', $value) : $value);
					}
				}
				$cond = [];
				if ($where)
				{
					$cond[] = 'WHERE';
					$cond[] = join(' AND ', $where);
				}
				if ($this->merge)
				{
					$cond[] = join(' ', $this->merge);
				}
				return join(' ', $cond);
			}
			function __invoke(string $tablename):webapp_mysql_table
			{
				return $this->mysql->{$tablename}($this);
			}
			function __call(string $name, array $params)
			{
				if (count($params))
				{
					switch ($name)
					{
						case 'eq':
						case 'ne':
						case 'gt':
						case 'ge':
						case 'lt':
						case 'le':
						case 'lk':
						case 'nl':
						case 'in':
						case 'ni':
							return $this->append["{$params[0]}.{$name}"] ?? NULL;
						case 'where':
						case 'merge':
							$this->{$name}[] = $this->mysql->sprintf(...$params);
							return $this;
					}
				}
			}
		};
	}
	function table(string $name, string $primary = NULL):webapp_mysql_table
	{
		return new class($this, $name, $primary) extends webapp_mysql_table
		{
			function __construct(webapp_mysql $mysql, string $name, string $primary = NULL)
			{
				$this->tablename = $name;
				if (strlen($primary))
				{
					$this->primary = $primary;
				}
				parent::__construct($mysql);
			}
		};
	}




	// function engines(bool $all = FALSE)
	// {

	// 	print_r( $this->all('SHOW STORAGE ENGINES') );
	// }
	// function tables()
	// {

	// }
	// function databases()
	// {
		
	// }
}


