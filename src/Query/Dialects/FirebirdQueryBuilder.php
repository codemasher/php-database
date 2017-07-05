<?php
/**
 * Class FirebirdQueryBuilder
 *
 * @filesource   FirebirdQueryBuilder.php
 * @created      29.06.2017
 * @package      chillerlan\Database\Query\Dialects
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */

namespace chillerlan\Database\Query\Dialects;

use chillerlan\Database\Query\{
	QueryBuilderAbstract, QueryException
};

use chillerlan\Database\Query\Statements\{
	Alter, Create, CreateDatabase, CreateTable, Delete,
	Drop, Insert, Select, Update
};

class FirebirdQueryBuilder extends QueryBuilderAbstract{

	/**
	 * @inheritdoc
	 */
	protected $quotes = ['"', '"'];

	/**
	 * @inheritdoc
	 */
	public function select():Select{

		/**
		 * @link https://www.firebirdsql.org/file/documentation/reference_manuals/fblangref25-en/html/fblangref25-dml-select.html
		 */
		return new class($this->db, $this->options, $this->quotes) extends SelectAbstract{
			use FirebirdBindValuesTrait;

			/**
			 * @inheritdoc
			 */
			public function sql():string{

				if(empty($this->from)){
					throw new QueryException('no FROM expression specified');
				}

				$glue = ','.PHP_EOL."\t";

				if(is_null($this->offset)){
					$this->offset = 0;
				}

				$sql  = 'SELECT ';
				$sql .= !is_null($this->limit) ? 'FIRST ? SKIP ? ' : '';
				$sql .= $this->distinct ? 'DISTINCT ' : '';
				$sql .= !empty($this->cols) ? implode($glue , $this->cols).PHP_EOL : '* ';
				$sql .= 'FROM '.implode($glue , $this->from);
				$sql .= $this->_getWhere();
				$sql .= !empty($this->groupby) ? PHP_EOL.'GROUP BY '.implode($glue, $this->groupby) : '';
				$sql .= !empty($this->orderby) ? PHP_EOL.'ORDER BY '.implode($glue, $this->orderby) : '';

				return $sql;
			}

		};

	}

	/**
	 * @inheritdoc
	 */
	public function insert():Insert{

		/**
		 * @link https://www.firebirdsql.org/file/documentation/reference_manuals/fblangref25-en/html/fblangref25-dml-insert.html
		 */
		return new class($this->db, $this->options, $this->quotes) extends InsertAbstract{
			use FirebirdBindValuesTrait;
		};

	}

	/**
	 * @inheritdoc
	 */
	public function update():Update{

		/**
		 * @link https://www.firebirdsql.org/file/documentation/reference_manuals/fblangref25-en/html/fblangref25-dml-update.html
		 */
		return new class($this->db, $this->options, $this->quotes) extends UpdateAbstract{
			use FirebirdBindValuesTrait;
		};

	}

	/**
	 * @inheritdoc
	 */
	public function delete():Delete{

		/**
		 * @link https://www.firebirdsql.org/file/documentation/reference_manuals/fblangref25-en/html/fblangref25-dml-delete.html
		 */
		return new class($this->db, $this->options, $this->quotes) extends DeleteAbstract{
			use FirebirdBindValuesTrait;
		};

	}

	/**
	 * @inheritdoc
	 */
	public function create():Create{

		return new class($this->db, $this->options, $this->quotes) extends StatementAbstract implements Create{

			/**
			 * @inheritdoc
			 */
			public function database(string $dbname = null):CreateDatabase{

				/**
				 * @link https://www.firebirdsql.org/file/documentation/reference_manuals/fblangref25-en/html/fblangref25-ddl-db.html#fblangref25-ddl-db-create
				 */
				return (new class($this->db, $this->options, $this->quotes) extends CreateDatabaseAbstract{

					/**
					 * @inheritdoc
					 */
					public function sql():string{

						if(empty($this->name)){
							throw new QueryException('no name specified');
						}

						$charset = explode('_', $this->collate, 2);

						$collate = 'DEFAULT CHARACTER SET '.strtoupper($charset[0]);

						if($charset[0] !== $this->collate && count($charset) === 2){
							$collate .= ' COLLATION '.strtoupper($charset[1]);
						}

						$sql = 'CREATE DATABASE ';
						$sql .= $this->quote($this->name);
						$sql .= $this->collate ? ' '.$collate : '';

						return $sql;
					}

				})->name($dbname); // new class end
			}

			/**
			 * @inheritdoc
			 */
			public function table(string $tablename = null):CreateTable{

				/**
				 * @link https://www.firebirdsql.org/file/documentation/reference_manuals/fblangref25-en/html/fblangref25-ddl-tbl.html#fblangref25-ddl-tbl-create
				 */
				return (new class($this->db, $this->options, $this->quotes) extends CreateTableAbstract{

					/**
					 * @inheritdoc
					 */
					public function sql():string{

						if(empty($this->name)){
							throw new QueryException('no name specified');
						}

						$sql = $this->ifNotExists ? 'RECREATE ' : 'CREATE '; // nasty
						$sql .= $this->temp ? 'GLOBAL TEMPORARY ' : '';
						$sql .= 'TABLE ';

						$n = explode('.', $this->name);

						$sql .= $this->quote($n[count($n)-1]);

						$cols = [];

						if(!empty($this->cols)){

							foreach($this->cols as $name => $col){

								if(strtolower($name) === strtolower($this->primaryKey)){
									$x = explode(' NOT NULL', $col, 2);

									if(count($x) > 0){
										$col = $x[0].' NOT NULL PRIMARY KEY';
										$col .= $x[1] ?? '';
									}

								}

								$cols[] = $col;
							}

							$sql .= ' ('.PHP_EOL."\t".implode(','.PHP_EOL."\t", $cols).PHP_EOL.')';
						}

						$sql .= '';

						return $sql;
					}

					/**
					 * @inheritdoc
					 */
					protected function fieldspec(string $name, string $type, $length = null, string $constraint = null, string $collation = null, bool $isNull = null, string $defaultType = null, $defaultValue = null, string $extra = null){
						$name = trim($name);
						$type = strtoupper(trim($type));

						$field = ['"'.$name.'"'];

						$type_translation = [
							'TINYINT'    => 'SMALLINT',
							'MEDIUMINT'  => 'INT',
							'BIGINT'     => 'INT64',
							'REAL'       => 'DOUBLE PRECISION',
							'DOUBLE'     => 'DOUBLE PRECISION',
							'BOOLEAN'    => 'CHAR(1)',
							'BINARY'     => 'CHAR',
							'VARBINARY'  => 'CHAR',
							'TINYTEXT'   => 'VARCHAR(255)',
							'DATETIME'   => 'TIMESTAMP',
							'IMAGE'      => 'BLOB',
							'TEXT'       => 'BLOB SUB_TYPE TEXT',
							'MEDIUMTEXT' => 'BLOB SUB_TYPE TEXT',
							'LONGTEXT'   => 'BLOB SUB_TYPE TEXT',
						][$type] ?? false;

						if($type_translation){
							$field[] = $type_translation;
						}
						else if(in_array($type, ['CHAR', 'VARCHAR', 'DECIMAL', 'NUMERIC'])){
							$field[] = $type.'('. $length . ')';
						}
						else{
							$field[] = $type;
						}

						if($isNull ===  false && !in_array($type, ['DATE', 'TIME', 'TIMESTAMP'])){
							$field[] = 'NOT NULL';
						}

						$defaultType = strtoupper($defaultType);

						if($defaultType === 'USER_DEFINED'){

							switch(true){
								case $type === 'TIMESTAMP' && intval($defaultValue) === 0:
									$field[] = 'DEFAULT 0';
									break;
								case strtoupper($defaultValue) === 'NULL' && $isNull === true:
									$field[] = 'DEFAULT NULL';
									break;
								default:
									$field[] = 'DEFAULT \''.$defaultValue.'\'';
							}

						}
						else if($defaultType === 'CURRENT_TIMESTAMP'){
							$field[] = 'DEFAULT CURRENT_TIMESTAMP';
						}
						else if($defaultType === 'NULL' && $isNull === true){
							$field[] = 'DEFAULT NULL';
						}

						if($constraint){
							$field[] = $constraint;
						}

						if($extra){
							$field[] = $extra;
						}

						return implode(' ', $field);
					}

				})->name($tablename); // new class end

			}

		};

	}

	/**
	 * @inheritdoc
	 */
	public function alter():Alter{
		return new class($this->db, $this->options, $this->quotes) extends StatementAbstract implements Alter{};
	}

	/**
	 * @inheritdoc
	 */
	public function drop():Drop{
		return new class($this->db, $this->options, $this->quotes) extends StatementAbstract implements Drop{};
	}

}
