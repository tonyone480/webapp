<?php
class webapp_nfas extends webapp
{
	const
	folder = 1 << 7,
	hidden = 1,
	readable = 1 << 6,
	modifiable = 1 << 5;
	const tablename = 'nfas', rootdir = 'D:/nfas/';
	function nfas(...$conditionals):webapp_mysql_table
	{
		return $conditionals
			? $this->mysql->{static::tablename}(...$conditionals)
			: $this->mysql->{static::tablename};
	}
	function file(string $hash):string
	{
		return static::rootdir . chunk_split(substr($hash, 0, 6), 2, '/') . substr($hash, -6);
	}
	function assign(string $file):bool
	{
		return is_dir($dirname = dirname($file)) || mkdir($dirname, recursive: TRUE);
	}
	function savefile(string $hash, int $size, string $type, string $name, string $node = NULL)
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash)
			&& $this->node($node)
			&& $this->nfas->insert([
				'hash' => $hash,
				'hits' => 0,
				'size' => $size,
				'time' => $this->time,
				'flag' => 0,
				'node' => $node,
				'type' => $type,
				'name' => $name]);
	}
	function node(string $hash = NULL):bool
	{
		return $hash === NULL || (preg_match('/^W[0-9A-V]{11}$/', $hash)
			&& $this->nfas('WHERE type IS NULL and hash=?s', $hash)->array());
	}
	function storage_address(string $hash)
	{
		return $this->assign($file = $this->file($hash)) ? $file : NULL;
	}
	function storage_uploadfile(string $name, string $node = NULL):array
	{
		foreach ($this->request_uploadedfile($name) as $file)
		{
			print_r($file);
		}
		return [];
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
				if ($this->node($hash) === FALSE)
				{
					break;
				}
			}


		} while (0);
		return FALSE;
	}



	function linkroot(string $hash):webapp_mysql
	{
		return ($this->mysql)('WITH RECURSIVE a AS(
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.hash=a.node)SELECT * FROM a',
			static::tablename, $hash, static::tablename, static::tablename);
	}
	function linktree(string $hash):webapp_mysql
	{
		return ($this->mysql)('WITH RECURSIVE a AS(
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.node=a.hash)SELECT * FROM a',
			static::tablename, $hash, static::tablename, static::tablename);
	}

	function create(string $name, string $node = NULL, string $type = NULL):?string
	{
		return $this->node($node) && $this->nfas->insert([
			'hash' => $hash = substr('W' . $this->hash($this->random(16)), $type === NULL ? 0 : 1, 12),
			'hits' => 0,
			'size' => 0,
			'time' => $this->time,
			'flag' => 0,
			'node' => $node,
			'type' => $type,
			'name' => $name]) && ($type === NULL
			|| ($this->assign($file = $this->file($hash)) && touch($file, $this->time))
			|| $this->nfas->delete('WHERE hash=?s LIMIT 1', $hash) > 1) ? $hash : NULL;
	}
	function rename(string $hash, string $name):bool
	{
		return $this->nfas('WHERE hash=?s LIMIT 1', $hash)->update('name=?s', $name) === 1;
	}
	function moveto(string $hash, string $node = NULL):bool
	{
		return $this->node($node) && $this->nfas('WHERE hash=?s LIMIT 1', $hash)->update(['node' => $node]) === 1;
	}
	function delete(string $hash)
	{
		if ($item = $this->nfas('WHERE hash=?s LIMIT 1', $hash)->array())
		{
			//$this->mysql->autocommit(FALSE);
			foreach ($item['type'] === NULL ? $this->linktree($hash) : [$item] as $item)
			{
				if ($this->nfas->delete('WHERE hash=?s LIMIT 1', $item['hash']))
				{
					if (str_starts_with($item['hash'], 'W') === FALSE)
					{
						is_file($file = $this->file($item['hash'])) === FALSE || unlink($file);
					}
				}
			}
		}
	}
	function tableinit():void
	{
		($this->mysql)(join(',', ['CREATE TABLE IF NOT EXISTS ?a(`hash` char(12) NOT NULL',
			'`hits` bigint unsigned NOT NULL',
			'`size` bigint unsigned NOT NULL',
			'`time` int unsigned NOT NULL',
			'`flag` tinyint unsigned NOT NULL',
			'`node` char(12) DEFAULT NULL',
			'`type` varchar(8) DEFAULT NULL',
			'`name` varchar(255) NOT NULL',
			'`json` json',
			'PRIMARY KEY (`hash`)',
			'KEY `node` (`node`)',
			'KEY `type` (`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4']), static::tablename);
	}
}