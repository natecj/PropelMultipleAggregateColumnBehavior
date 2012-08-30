<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once 'MultipleAggregateColumnRelationBehavior.php';

/**
 * Keeps an aggregate column updated with related table
 *
 * @author     Nathan Jacobson
 * @version    $Revision: 1.3 $
 * @package    propel.generator.behavior.multiple_aggregate_column
 */
class MultipleAggregateColumnBehavior extends Behavior
{

	/**
	 * Parameter defaults for this behavior
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $parameters = array(
		'count'           => 0,
	);

	/**
	 * Modify the primary table - add aggregate column and aggregate column relation behavior
	 *
	 * @access public
	 * @return void
	 */
	public function modifyTable() {

        // Loop through items
        for( $x = 1; $x <= intval( $this->getParameter('count') ); $x++ ) {

    		$table = $this->getTable();
    		if (!$columnName = $this->getParameter('name'.$x)) {
    			throw new InvalidArgumentException(sprintf('You must define a \'name$x\' parameter for the \'aggregate_column\' behavior in the \'%s\' table', $table->getName()));
    		}

    		// add the aggregate column if not present
    		if(!$this->getTable()->containsColumn($columnName)) {
    			$this->getTable()->addColumn(array(
    				'name'    => $columnName,
    				'type'    => 'INTEGER',
    			));
    		}

    		// add a behavior in the foreign table to autoupdate the aggregate column
    		$foreignTable = $this->getForeignTable( $x );
    		if (!$foreignTable->hasBehavior('concrete_inheritance_parent')) {
    			$relationBehavior = new MultipleAggregateColumnRelationBehavior();
    			$relationBehavior->setName('aggregate_column_relation');
    			$relationBehavior->addParameter(array('name' => 'foreign_table', 'value' => $table->getName()));
    			$relationBehavior->addParameter(array('name' => 'update_method', 'value' => 'update' . $this->getColumn( $x )->getPhpName()));
    			$foreignTable->addBehavior($relationBehavior);
    		}

        } // end for loop

	}

	/**
	 * Add the object methods using templates.
	 *
	 * @access public
	 * @param mixed $builder
	 * @return void
	 */
	public function objectMethods( $builder ) {

		$script = '';

        // Loop through items
        for( $x = 1; $x <= intval( $this->getParameter('count') ); $x++ ) {

    		if (!$this->getParameter('foreign_table'.$x)) {
    			throw new InvalidArgumentException(sprintf('You must define a \'foreign_table$x\' parameter for the \'aggregate_column\' behavior in the \'%s\' table', $this->getTable()->getName(), $builder));
    		}
    		$script .= $this->addObjectCompute( $x );
    		$script .= $this->addObjectUpdate( $x );

        } // end for loop

		return $script;
	}


	/**
	 * Build the objectCompute partial template.
	 *
	 * @access protected
	 * @param mixed $x index of the template
	 * @return void
	 */
	protected function addObjectCompute( $x ) {

		// Build the where conditions and bindings
		$conditions = array();
		$bindings = array();
		foreach ($this->getForeignKey( $x )->getColumnObjectsMapping() as $index => $columnReference) {
			$conditions[] = $columnReference['local']->getFullyQualifiedName() . ' = :p' . ($index + 1);
			$bindings[$index + 1]   = $columnReference['foreign']->getPhpName();
		}

		// Add soft_delete condition to foreign table if that behavior is used
		if ( $this->getForeignTable( $x )->hasBehavior('soft_delete') )
    		$conditions[] = $this->getParameter('foreign_table'.$x).".DELETED_AT IS NULL";

        // Determine the table to query
		$database = $this->getTable()->getDatabase();
		$tableName = $database->getTablePrefix() . $this->getParameter('foreign_table'.$x);
		if ($database->getPlatform()->supportsSchemas() && $this->getParameter('foreign_schema'.$x)) {
			$tableName = $this->getParameter('foreign_schema'.$x).'.'.$tableName;
		}

		// Build the actual SQL query
		$sql = sprintf(
            'SELECT %s FROM %s WHERE %s',
            $this->getParameter('expression'.$x),
            $database->getPlatform()->quoteIdentifier($tableName),
            implode(' AND ', $conditions)
		);

		// Return the objectCompute partial template
		return $this->renderTemplate('objectCompute', array(
			'column'   => $this->getColumn( $x ),
			'sql'      => $sql,
			'bindings' => $bindings,
		));

	}

	/**
	 * Build the objectUpdate partial template.
	 *
	 * @access protected
	 * @param mixed $x index of the template
	 * @return void
	 */
	protected function addObjectUpdate( $x ) {
		return $this->renderTemplate('objectUpdate', array(
			'column'  => $this->getColumn( $x ),
		));
	}

	/**
	 * Get the foreign table by index.
	 *
	 * @access protected
	 * @param mixed $x index of the foreign table
	 * @return void
	 */
	protected function getForeignTable( $x ) {

		$database = $this->getTable()->getDatabase();
		$tableName = $database->getTablePrefix() . $this->getParameter('foreign_table'.$x);
		if ($database->getPlatform()->supportsSchemas() && $this->getParameter('foreign_schema'.$x)) {
			$tableName = $this->getParameter('foreign_schema'.$x). '.' . $tableName;
		}
		return $database->getTable($tableName);

	}

	/**
	 * Get the foreign key by index.
	 *
	 * @access protected
	 * @param mixed $x index of the foreign key
	 * @return void
	 */
	protected function getForeignKey( $x ) {

		$foreignTable = $this->getForeignTable( $x );
		// let's infer the relation from the foreign table
		$fks = $foreignTable->getForeignKeysReferencingTable($this->getTable()->getName());
		if (!$fks) {
			throw new InvalidArgumentException(sprintf('You must define a foreign key to the \'%s\' table in the \'%s\' table to enable the \'aggregate_column\' behavior', $this->getTable()->getName(), $foreignTable->getName()));
		}
		// FIXME doesn't work when more than one fk to the same table
		return array_shift($fks);

	}

	/**
	 * Get the column by index.
	 *
	 * @access protected
	 * @param mixed $x index of the column
	 * @return void
	 */
	protected function getColumn( $x ) {
		return $this->getTable()->getColumn($this->getParameter('name'.$x));
	}

}