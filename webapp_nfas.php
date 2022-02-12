<?php
class webapp_nfas extends webapp
{
	const
	folder = 1 << 7,
	hidden = 1,
	readable = 1 << 6,
	modifiable = 1 << 5;
	const tablename = 'nfas', rootdir = 'D:/';
	function nfas(...$conditionals):webapp_mysql_table
	{
		return $conditionals
			? $this->mysql->{static::tablename}(...$conditionals)
			: $this->mysql->{static::tablename};
	}
	function file(string $hash):string
	{
		return static::rootdir . chunk_split(substr($hash, 0, 6), 2, '/') . substr($hash, -6);
		//return strlen($hash) === 12 && !is_file($file = ) ? $file : NULL;
	}
	function assign(string $hash):bool
	{
		return is_dir($dirname = dirname($this->file($hash))) || mkdir($dirname, recursive: TRUE);
	}


	function exists(string $hash):bool
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash) && is_file($this->file($hash));
	}
	function storage(array $fileinfo, string $hash = NULL)
	{
		do
		{
			if ($hash)
			{
				if ($this->isdir($hash) === FALSE)
				{
					break;
				}
			}


		} while (0);
		return FALSE;
	}
	function isdir(string $hash):bool
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash)
			&& $this->nfas('WHERE type IS NULL and hash=?s', $hash)->count();
	}


	function linkroot(string $hash):webapp_mysql
	{
		return ($this->mysql)('WITH RECURSIVE a AS(
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.hash=a.parent)SELECT * FROM a',
			static::tablename, $hash, static::tablename, static::tablename);
	}
	function linktree(string $hash):webapp_mysql
	{
		return ($this->mysql)('WITH RECURSIVE a AS(
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.parent=a.hash)SELECT * FROM a',
			static::tablename, $hash, static::tablename, static::tablename);
	}

	function create(string $name, string $folder = NULL, string $type = NULL):?string
	{
		return ($folder === NULL || $this->isdir($folder)) && $this->nfas->insert([
			'hash' => $hash = $type === NULL ? 'W' . substr($this->hash($this->random(16)), 1) : $this->hash($this->random(16)),
			'flag' => 0,
			'time' => $this->time,
			'size' => 0,
			'echo' => 0,
			'parent' => $folder,
			'type' => $type,
			'name' => $name]) && ($type === NULL || ($this->assign($hash) && touch($this->file($hash), $this->time) ) ) ? $hash : NULL;
	}
	function rename(string $hash, string $name):bool
	{
		return $this->nfas('WHERE hash=?s LIMIT 1', $hash)->update('name=?s', $name) === 1;
	}
	function moveto(string $hash, string $dst = NULL):bool
	{
		return $this->nfas('WHERE hash=?s LIMIT 1', $hash)->update(['parent' => $dst]) === 1;
	}
	function delete(string $hash)
	{
		if ($item = $this->nfas('WHERE hash=?s LIMIT 1', $hash)->array())
		{
			if ($item['type'] !== NULL)
			{
				return $this->nfas->delete('WHERE hash=?s LIMIT 1', $item['hash'])
					&& (is_file($file = $this->file($item['hash'])) === FALSE || unlink($file));
			}

			foreach ($this->linktree($hash) as $item)
			{
				print_r($item);
			}


		}
	}

}