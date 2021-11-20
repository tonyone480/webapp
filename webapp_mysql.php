<?php
class webapp_mysql extends mysqli implements IteratorAggregate
{
	public array $errors = [];
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
	function __invoke(...$query):static
	{
		//var_dump($this->sprintf(...$query));
		if ($this->real_query($this->sprintf(...$query)) === FALSE)
		{
			$this->errors[] = $this->error;
		}
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
		return $this->array(MYSQLI_NUM)[$index];
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
	function sprintf(string $format, ...$values):string
	{
		if ($values)
		{
			$index = 0;
			$offset = 0;
			$command = [];
			$length = strlen($format);
			while (($pos = strpos($format, '?', $offset)) !== FALSE && $pos < $length && array_key_exists($index, $values))
			{
				$command[] = substr($format, $offset, $pos - $offset);
				switch ($format[$pos + 1])
				{
					case 'A':
					case 'S':
						if (is_array($values[$index]))
						{
							$command[] = join(',', array_map([$this, $format[$pos + 1] === 'A' ? 'quote' : 'escape'], $values[$index++]));
							break;
						}
					case '?': $command[] = (string)$values[$index++]; break;
					case 'a': $command[] = $this->quote((string)$values[$index++]); break;
					case 's': $command[] = $this->escape((string)$values[$index++]); break;
					case 'i': $command[] = intval($values[$index++]); break;
					case 'f': $command[] = floatval($values[$index++]); break;
					case 'v': $values = [];
						foreach ($values[$index++] as $key => $value)
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
					default: $command[] = substr($format, $pos, 2);
				}
				$offset = $pos + 2;
			}
			if ($offset < $length)
			{
				$command[] = substr($format, $offset);
			}
			return join($command);
		}
		return $format;
	}
	

	function sync(Closure $submit, ...$params):bool
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
	function real_query(string $format, mixed ...$values):bool
	{
		return parent::real_query($this->sprintf($format, ...$values));
	}
	function prepare(string $format, ...$values):string
	{
		return $format;
	}
	function kill(int $pid = NULL):bool
	{
		return parent::kill($pid ?? $this->thread_id);
	}
	function processlist():iterable
	{
		return $this('SHOW PROCESSLIST');
	}
	// function engines(bool $all = FALSE)
	// {

	// 	print_r( $this->all('SHOW STORAGE ENGINES') );
	// }
	// function tables()
	// {

	// }

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
	function __invoke(...$cond):static
	{
		$this->cond = $this->mysql->sprintf(...$cond);
		return $this;
	}
	function __toString():string
	{
		//return [$this->cond, $this->cond = '', $this->fields = '*'][0];
		$cond = $this->cond;
		$this->cond = '';
		$this->fields = '*';
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


	function append($data):int
	{
		return $this->insert($data) ? $this->mysql->insert_id : 0;
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
		return $this->mysql('UPDATE ?a SET ????', $this->tablename, $this->mysql->sprintf(...is_array($data) ? ['?v', $data] : func_get_args()), (string)$this) ? $this->mysql->affected_rows : -1;
	}
	function select(array|string $fields):static
	{
		$this->fields = $this->mysql->sprintf('?A', $fields);
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