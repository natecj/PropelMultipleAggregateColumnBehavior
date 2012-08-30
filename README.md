PropelMultipleAggregateColumnBehavior

This behavior is an almost exact copy of the bundled aggregate_column behavior, with the addition of allowingmultiple aggregate columns on a single table - something not possible with the existing behavior.

For anyone reading this who wants some basic info on usage, here is the schema I am currently using:

<behavior name="multiple_aggregate_column">
    <parameter name="count" value="2" />
    <parameter name="name1" value="amount_total" />
    <parameter name="foreign_table1" value="invoice_item" />
    <parameter name="expression1" value="SUM(price)" />
    <parameter name="name2" value="amount_paid" />
    <parameter name="foreign_table2" value="invoice_payment" />
    <parameter name="expression2" value="SUM(amount)" />
</behavior>

Basically, the required fields are:
	count - integer, the number of aggregate columns that you are using with this table starting at 1
	nameX - the name of the column (see aggregate_column behavior for more info)
	foreign_tableX - the foreign table used for the expression (see aggregate_column behavior for more info)
	expressionX - the select part of the SQL to get the aggregate value (see aggregate_column behavior for more info)



