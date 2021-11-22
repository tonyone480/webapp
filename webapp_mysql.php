<?php
class webapp_mysql extends mysqli implements IteratorAggregate
{
	public array $errors = [];
	private mysqli_result $result;
	function __construct(string $host = 'p:127.0.0.1:3306', string $user = 'root', string $password = NULL, string $database = NULL, private string $maptable = 'webapp_maptable_')
	{
		$this->init();
		//ini_set('mysqli.reconnect', TRUE);
		//$this->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
		//$this->real_connect($host, $user, $password, $database);
		$this->real_connect($host, $user, $password, $database, flags: MYSQLI_CLIENT_FOUND_ROWS | MYSQLI_CLIENT_INTERACTIVE);
	}
	function __destruct()
	{
		$this->close();
	}
	function __get(string $name):webapp_mysql_table
	{
		return $this->{$name} = class_exists($tablename = $this->maptable . $name, FALSE) ? new $tablename($this) : $this->table($name);
	}
	function __call(string $tablename, array $conditionals):webapp_mysql_table
	{
		return ($this->{$tablename})(...$conditionals);
	}
	function __invoke(mixed ...$commands):static
	{
		$this->real_query(...$commands);
		return $this;
	}
	function getIterator():Traversable
	{
		return $this->store_result();
	}
	function object(string $class = 'stdClass', array $constructor_args = []):object
	{
		return $this->use_result()->fetch_object($class, $constructor_args);
	}
	function array(int $mode = MYSQLI_ASSOC):array
	{
		return $this->use_result()->fetch_array($mode);
	}
	function value(int $index = 0):?string
	{
		return $this->array(MYSQLI_NUM)[$index] ?? NULL;
	}
	function all(int $mode = MYSQLI_ASSOC):array
	{
		return $this->use_result()->fetch_all($mode);
	}
	function column(string $key, string $index = NULL):array
	{
		return array_column($this->all(), $key, $index);
	}
	private function quote(string $name):string
	{
		return '`' . addcslashes($name, '`') . '`';
	}
	private function escape(string $value):string
	{
		return '\'' . $this->real_escape_string($value) . '\'';
	}
	private function iterator(iterable $contents):string
	{
		$query = [];
		foreach ($contents as $key => $value)
		{
			$query[] = $this->quote($key) . '=' . match (get_debug_type($value))
			{
				'null' => 'NULL',
				'bool' => intval($value),
				'int', 'float' => $value,
				default => $this->escape($value)
			};
		}
		return join(',', $query);
	}
	function format(string $query, iterable|string|int|float|bool ...$values):string
	{
		$offset = 0;
		$buffer = [];
		$length = strlen($query);
		foreach ($values as $value)
		{
			if (($pos = strpos($query, '?', $offset)) !== FALSE)
			{
				$buffer[] = substr($query, $offset, $pos - $offset) . match ($query[$pos + 1])
				{
					'i' => intval($value),
					'f' => floatval($value),
					'a' => $this->quote($value),
					's' => $this->escape($value),
					'v' => $this->iterator($value),
					'?', 'A', 'S' => is_iterable($value) ? join(',', array_map([$this, $query[$pos + 1] === 'A' ? 'quote' : 'escape'],
						is_array($value) ? $value : iterator_to_array($value))) : (string)$value,
					default => substr($query, $pos, 2)
				};
				$offset = $pos + 2;
				continue;
			}
			break;
		}
		if ($offset < $length)
		{
			$buffer[] = substr($query, $offset);
		}
		return join($buffer);
		// if ($values)
		// {
		// 	$index = 0;
		// 	$offset = 0;
		// 	$buffer = [];
		// 	$length = strlen($query);
		// 	while (($pos = strpos($query, '?', $offset)) !== FALSE && $pos < $length && array_key_exists($index, $values))
		// 	{
		// 		$buffer[] = substr($query, $offset, $pos - $offset) . match ($query[$pos + 1])
		// 		{
		// 			'i' => intval($values[$index++]),
		// 			'f' => floatval($values[$index++]),
		// 			'a' => $this->quote($values[$index++]),
		// 			's' => $this->escape($values[$index++]),
		// 			'v' => $this->iterator($values[$index++]),
		// 			'?', 'A', 'S' => is_iterable($values[$index]) ? join(',', array_map([$this, $query[$pos + 1] === 'A' ? 'quote' : 'escape'],
		// 				is_array($values[$index]) ? $values[$index++] : iterator_to_array($values[$index++]))) : (string)$values[$index++],
		// 			default => substr($query, $pos, 2)
		// 		};
		// 		$offset = $pos + 2;
		// 	}
		// 	if ($offset < $length)
		// 	{
		// 		$buffer[] = substr($query, $offset);
		// 	}
		// 	return join($buffer);
		// }
		// return $query;
	}
	function real_query(mixed ...$commands):bool
	{
		if (parent::real_query($this->format(...$commands)))
		{
			return TRUE;
		}
		$this->errors[] = $this->error;
		return FALSE;
	}
	function kill(int $pid = NULL):bool
	{
		return parent::kill($pid ?? $this->thread_id);
	}

	function sync(Closure $submit, mixed ...$params):bool
	{
		if ($this->autocommit(FALSE))
		{
			if ($submit->call($this, ...$params))
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
		return new class($this, $fieldinfo, $cond) extends ArrayObject implements Stringable
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
			private array $where = [], $merge = [], $append;
			function __construct(private webapp_mysql $mysql, array $fieldinfo, array $cond)
			{
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
	function fields(bool $detailed = FALSE):array
	{
		$fields = $this->use_result()->fetch_fields();
		return $detailed ? $fields : array_column($fields, 'name');
	}
	function table(string $name):webapp_mysql_table
	{
		return new class($this, $name) extends webapp_mysql_table
		{
			function __construct(webapp_mysql $mysql, string $name)
			{
				unset($this->primary);
				$this->tablename = $name;
				parent::__construct($mysql);
			}
		};
	}

	function show(mixed ...$commands):static
	{
		return $this('SHOW ' . $this->format(...$commands));
	}
	function processlist():static
	{
		return $this->show('PROCESSLIST');
	}
}
abstract class webapp_mysql_table implements IteratorAggregate, Countable, Stringable
{
	private array $paging = [];
	private string $cond = '', $fields = '*';
	protected ?string $tablename, $primary;
	function __construct(protected webapp_mysql $mysql)
	{
	}
	function __get(string $name)
	{
		var_dump('--');
		return match ($name)
		{
			'tablename' => $this->tablename,
			'primary' => $this->primary =
				($this->mysql)('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'PRI')->value() ??
				($this->mysql)('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'UNI')->value(),
			'create' => ($this->mysql)('SHOW CREATE TABLE ?a', $this->tablename)->value(1)
		};
	}
	function __invoke(mixed ...$conditionals):static
	{
		$this->cond = $this->mysql->format(...$conditionals);
		return $this;
	}
	function __toString():string
	{
		//return [$this->cond, $this->cond = '', $this->fields = '*'][0];
		$cond = $this->cond;
		$this->fields = '*';
		$this->cond = '';
		return $cond;
	}
	function count(string &$cond = NULL):int
	{
		$cond = $this->cond;
		return intval(($this->mysql)('SELECT SQL_NO_CACHE COUNT(1) FROM ?a?? LIMIT 1', $this->tablename, (string)$this)->value());
	}
	function getIterator():Traversable
	{
		return ($this->mysql)('SELECT ?? FROM ?a??', $this->fields, $this->tablename, (string)$this);
	}
	function object(string $class = 'stdClass', array $constructor_args = []):object
	{
		return $this->getIterator()->object($class, $constructor_args);
	}
	function array(int $mode = MYSQLI_ASSOC):array
	{
		return $this->getIterator()->array($mode);
	}
	function value(int $index = 0):?string
	{
		return $this->array(MYSQLI_NUM)[$index] ?? NULL;
	}
	function all(int $mode = MYSQLI_ASSOC):array
	{
		return $this->getIterator()->all($mode);
	}
	function column(string $key, string $index = NULL):array
	{
		return array_column($this->all(), $key, $index);
	}


	// function &tablename():string
	// {
	// 	return $this->tablename;
	// }
	// function &primary():string
	// {
	// 	if (property_exists($this, 'primary') === FALSE)
	// 	{
	// 		$this->primary =
	// 			$this->mysql->row('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'PRI')['Field'] ??
	// 			$this->mysql->row('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'UNI')['Field'] ?? NULL;
	// 	}
	// 	return $this->primary;
	// }


	function append(mixed ...$params):int
	{
		$this->insert(...$params);
		return $this->mysql->insert_id;
	}
	function insert(iterable|string $data):bool
	{
		return ($this->mysql)('INSERT INTO ?a SET ??', $this->tablename, $this->mysql->format(...is_iterable($data) ? ['?v', $data] : func_get_args()))->affected_rows === 1;
	}
	function delete(mixed ...$conditionals):int
	{
		return ($this->mysql)('DELETE FROM ?a??', $this->tablename, (string)($conditionals ? $this(...$conditionals) : $this))->affected_rows;
	}
	function update(iterable|string $data):int
	{
		return ($this->mysql)('UPDATE ?a SET ????', $this->tablename, $this->mysql->format(...is_iterable($data) ? ['?v', $data] : func_get_args()), (string)$this)->affected_rows;
	}
	function select(array|string $fields):static
	{
		$this->fields = $this->mysql->format('?A', $fields);
		return $this;
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
	// function column(string ...$keys):array
	// {
	// 	return array_column($this->select($keys)->all, ...$keys);
	// }

	// function target($mixed = NULL):webapp_mysql_target
	// {
	// 	return new $this->targetname($this, $mixed);
	// }
	function fieldinfo()
	{
		return ($this->mysql)('SHOW FULL COLUMNS FROM ?a', $this->tablename);
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
/*
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
*/