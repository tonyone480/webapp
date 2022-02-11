<?php
class webapp_nfas extends webapp
{
	const
	folder = 1 << 7,
	hidden = 1,
	readable = 1 << 6,
	modifiable = 1 << 5;
	const tablename = 'nfas', rootdir = './';
	function nfas(...$conditionals):webapp_mysql_table
	{
		return $conditionals
			? $this->mysql->{static::tablename}(...$conditionals)
			: $this->mysql->{static::tablename};
	}
	function file(string $hash):?string
	{
		return static::rootdir . chunk_split(substr($hash, 0, 6), 2, '/') . substr($hash, -6);
		//return strlen($hash) === 12 && !is_file($file = ) ? $file : NULL;
	}
	function access(string $hash)
	{
		return is_dir($dirname = dirname($this->file($hash))) || mkdir($dirname, recursive: TRUE);
	}
	function exists(string $hash):bool
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash) && is_file($this->file($hash));
	}
	function storage(array $fileinfo)
	{

	}


	function tracert(string $hash)
	{
		print_r(($this->mysql)('WITH RECURSIVE a AS (
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.hash=a.parent)SELECT * FROM a;',
			static::tablename, $hash, static::tablename, static::tablename)->all());
	}
	function create(string $name, string $parent = NULL, bool $file = FALSE):string
	{
		
		
		$hash = $this->hash($this->time . $parent . $name);


		//$this->mysql->nfas->insert();
	}
	function rename(string $hash, string $newname):bool
	{
		return $this->mysql->nfas('WHERE hash=?s LIMIT 1', $hash)->update('name=?s', $newname) === 1;
	}
	function append()
	{
		// $this->nfas->insert([
		// 	'hash' => 'K',
		// 	'flag' => 1,
		// 	'time' => $this->time,
		// 	'size' => 0,
		// 	'echo' => 0,
		// 	'type' => 'unknow',
		// 	'name' => 'K',
		// 	'parent' => 'a'
		// ]);
	}

	function delete()
	{

	}
	function moveto()
	{

	}
}