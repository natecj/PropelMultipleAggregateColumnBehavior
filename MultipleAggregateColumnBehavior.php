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
 * @author     François Zaninotto
 * @version    $Revision: 2090 $
 * @package    propel.generator.behavior.aggregate_column
 */
class MultipleAggregateColumnBehavior extends Behavior
{
	
	// default parameters value
	protected $parameters = array(
		'count'           => 0,
	);
	
	/**
	 * Add the aggregate key to the current table
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
    			$column = $this->getTable()->addColumn(array(
    				'name'    => $columnName,
    				'type'    => 'INTEGER',
    			));
    		}
    		
    		// add a behavior in the foreign table to autoupdate the aggregate column
    		$foreignTable = $this->getForeignTable( $x );
    		if (!$foreignTable->hasBehavior('concrete_inheritance_parent')) {
    			$relationBehavior = new MultipleAggregateColumnRelationBehavior();
    			$relationBehavior->setName('aggregate_column_relation');
    			$foreignKey = $this->getForeignKey( $x );
    			$relationBehavior->addParameter(array('name' => 'foreign_table', 'value' => $table->getName()));
    			$relationBehavior->addParameter(array('name' => 'update_method', 'value' => 'update' . $this->getColumn( $x )->getPhpName()));
    			$foreignTable->addBehavior($relationBehavior);
    		}

        } // end for loop

	}
	
	public function objectMethods( $builder ) {
	
		$script = '';
	
        // Loop through items
        for( $x = 1; $x <= intval( $this->getParameter('count') ); $x++ ) {
	
    		if (!$foreignTableName = $this->getParameter('foreign_table'.$x)) {
    			throw new InvalidArgumentException(sprintf('You must define a \'foreign_table$x\' parameter for the \'aggregate_column\' behavior in the \'%s\' table', $this->getTable()->getName()));
    		}
    		$script .= $this->addObjectCompute();
    		$script .= $this->addObjectUpdate();
		
        } // end for loop
		
		return $script;
	}
	
	protected function addObjectCompute() {
	
		$script = '';
	
        // Loop through items
        for( $x = 1; $x <= intval( $this->getParameter('count') ); $x++ ) {

    		$conditions = array();
    		$bindings = array();
    		$database = $this->getTable()->getDatabase();
    		foreach ($this->getForeignKey( $x )->getColumnObjectsMapping() as $index => $columnReference) {
    			$conditions[] = $columnReference['local']->getFullyQualifiedName() . ' = :p' . ($index + 1);
    			$bindings[$index + 1]   = $columnReference['foreign']->getPhpName();
    		}
    		$tableName = $database->getTablePrefix() . $this->getParameter('foreign_table'.$x);
    		if ($database->getPlatform()->supportsSchemas() && $this->getParameter('foreign_schema'.$x)) {
    			$tableName = $this->getParameter('foreign_schema'.$x).'.'.$tableName;
    		}
    		$sql = sprintf('SELECT %s FROM %s WHERE %s',
    			$this->getParameter('expression'.$x),
    			$database->getPlatform()->quoteIdentifier($tableName),
    			implode(' AND ', $conditions)
    		);
    		
    		$script .= $this->renderTemplate('objectCompute', array(
    			'column'   => $this->getColumn( $x ),
    			'sql'      => $sql,
    			'bindings' => $bindings,
    		));
		
        } // end for loop
		
		return $script;
	}
	
	protected function addObjectUpdate() {
	
		$script = '';
	
        // Loop through items
        for( $x = 1; $x <= intval( $this->getParameter('count') ); $x++ ) {
	
    		$script .= $this->renderTemplate('objectUpdate', array(
    			'column'  => $this->getColumn( $x ),
    		));
		
        } // end for loop
		
		return $script;
		
	}
	
	protected function getForeignTable( $x ) {
	
		$database = $this->getTable()->getDatabase();
		$tableName = $database->getTablePrefix() . $this->getParameter('foreign_table'.$x);
		if ($database->getPlatform()->supportsSchemas() && $this->getParameter('foreign_schema'.$x)) {
			$tableName = $this->getParameter('foreign_schema'.$x). '.' . $tableName;
		}
		return $database->getTable($tableName);
		
	}

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
	
	protected function getColumn( $x ) {
		return $this->getTable()->getColumn($this->getParameter('name'.$x));
	}
	
}