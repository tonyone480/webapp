<?php
class webapp_nfas extends webapp
{
	const
	folder = 1 << 7,
	hidden = 1,
	readable = 1 << 6,
	modifiable = 1 << 5;
	
	function create(string $name, string $parent = NULL, bool $file = FALSE):string
	{
		
		
		$hash = $this->hash($this->time . $parent . $name);


		//$this->mysql->nfas->insert();
	}
	function rename(string $hash, string $newname):bool
	{
		return $this->mysql->nfas('WHERE hash=?s LIMIT 1', $hash)->update('name=?s', $newname) === 1;
	}
	function append(string $folder)
	{

	}

	function delete()
	{

	}
	function moveto()
	{

	}
}