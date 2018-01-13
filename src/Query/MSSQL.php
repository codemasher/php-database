<?php
/**
 * Class MSSQL
 *
 * @filesource   MSSQL.php
 * @created      11.01.2018
 * @package      chillerlan\Database\Query
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2018 Smiley
 * @license      MIT
 */

namespace chillerlan\Database\Query;

class MSSQL extends DialectAbstract{

	protected $quotes = ['[', ']'];

	/** @inheritdoc */
	public function select(array $from, string $where = null, $limit = null, $offset = null, bool $distinct = null, array $groupby, array $orderby):array{
		$sql[] = 'SELECT';

		if($distinct){
			$sql[] = 'DISTINCT';
		}

		!empty($cols)
			? $sql[] = implode(', ', $cols)
			: $sql[] = '*';

		$sql[] = 'FROM';
		$sql[] = implode(', ', $from);
		$sql[] = $where;

		if(!empty($groupby)){
			$sql[] = 'GROUP BY';
			$sql[] = implode(', ', $groupby);
		}

		if(!empty($orderby)){
			$sql[] = 'ORDER BY';
			$sql[] = implode(', ', $orderby);
		}

		if($limit !== null){

			if(empty($orderby)){
				$sql[] = 'ORDER BY 1';
			}

			$sql[] = 'OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
		}

		return $sql;
	}

	/** @inheritdoc */
	public function createDatabase(string $dbname, bool $ifNotExists = null, string $collate = null):array{
		$sql[] = 'CREATE DATABASE ';
		$sql[] = $this->quote($dbname);

		if($collate){
			$sql[] = 'COLLATE';
			$sql[] = $collate;
		}

		return $sql;
	}

	/** @inheritdoc */
	public function createTable(string $table, array $cols, string $primaryKey = null, bool $ifNotExists, bool $temp, string $dir = null):array{
		$sql[] = 'CREATE TABLE';
		$sql[] = $this->quote($table);

		if(!empty($this->cols)){
			$sql[] = '(';
			$sql[] = implode(',', $cols);

			if($primaryKey){
				$sql[] = ', PRIMARY KEY ('.$this->quote($primaryKey).')';
			}

			$sql[] = ')';
		}

		return $sql;
	}

	/** @inheritdoc */
	public function fieldspec(string $name, string $type, $length = null, string $attribute = null, string $collation = null, bool $isNull = null, string $defaultType = null, $defaultValue = null, string $extra = null):string{
		$type = strtolower(trim($type));

		$field = [$this->quote(trim($name))];

		$type_translation = [
			'boolean'    => 'tinyint',
			'bool   '    => 'tinyint',
			'mediumint'  => 'int',
			'double'     => 'float',
			'tinytext'   => 'text',
			'mediumtext' => 'text',
			'longtext'   => 'text',
			'timestamp'  => 'datetime2',
		][$type] ?? $type;

		if((is_int($length) || is_string($length) && (count(explode(',', $length)) === 2 || $length === 'max'))
		   && in_array($type, ['char', 'varchar', 'nchar', 'nvarchar', 'decimal', 'numeric', 'datetime2', 'time'], true)){
			$field[] = $type_translation.'('.$length.')';
		}
		else{
			$field[] = $type_translation;
		}

		if($isNull !== null){
			$field[] = $isNull ? 'NULL' : 'NOT NULL';
		}

		$defaultType = strtoupper($defaultType);

		if($defaultType === 'USER_DEFINED'){

			// @todo
			switch(true){
				default:
					$field[] = 'DEFAULT \''.$defaultValue.'\'';
			}

		}
		elseif($defaultType === 'CURRENT_TIMESTAMP'){
			$field[] = 'DEFAULT CURRENT_TIMESTAMP';
		}
		elseif($defaultType === 'NULL' && $isNull === true){
			$field[] = 'DEFAULT NULL';
		}

		if($extra){
			$field[] = $extra;
		}

		return implode(' ', $field);
	}

}