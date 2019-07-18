<?php
namespace wiggum\services\db;

class Grammar {
	
	/**
	 * Wrap a value in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrap($value) {

		if (strpos(strtolower($value), 'null') !== false) {
			return $value;
		}

		// If the value being wrapped has a column alias we will need to separate out
		// the pieces so we can wrap each of the segments of the expression on it
		// own, and then joins them both back together with the "as" connector.
		if (stripos($value, ' as ') !== false) {
		    return $this->wrapAliasedValue($value);
		}
	
		if ($this->isJsonSelector($value)) {
		    return $this->wrapJsonSelector($value);
		}
		
		return $this->wrapSegments(explode('.', $value));
	}
	
	/**
	 * 
	 * @param string $value
	 * @return string
	 */
	protected function wrapValue($value) {
	    if ($value === '*') {
	        return $value;
	    }
	    
		return '`'.str_replace('`', '', $value).'`';
	}
	
	/**
	 * Create query parameter place-holders for an array.
	 *
	 * @param  array $values
	 * @return string
	 */
	protected function parameterize(array $values) {
		return implode(', ', array_fill(0 , count($values), '?'));
	}
	
	/**
	 * Convert an array of column names into a delimited string.
	 *
	 * @param  array $columns
	 * @return string
	 */
	protected function columnize(array $columns) {
		return implode(', ', array_map([$this, 'wrap'], $columns));
	}
	
	/**
	 * Wrap an array of values.
	 *
	 * @param  array  $values
	 * @return array
	 */
	protected function wrapArray(array $values) {
		return array_map(array($this, 'wrap'), $values);
	}
	
	/**
	 * Remove the leading boolean from a statement.
	 *
	 * @param string $value        	
	 * @return string
	 */
	protected function removeLeadingBoolean($value) {
		return preg_replace('/and |or /i', '', $value, 1);
	}
	
	/**
	 * Wrap a value that has an alias.
	 *
	 * @param  string  $value
	 * @param  bool  $prefixAlias
	 * @return string
	 */
	protected function wrapAliasedValue($value)
	{
	    $segments = explode(' ', $value);
	    
	    return $this->wrap($segments[0]).' as '.$this->wrapValue($segments[2]);
	}
	
	/**
	 * Wrap the given value segments.
	 *
	 * @param  array  $segments
	 * @return string
	 */
	protected function wrapSegments($segments)
	{
	    $wrapped = [];
	    
	    // If the value is not an aliased table expression, we'll just wrap it like
	    // normal, so if there is more than one segment, we will wrap the first
	    // segments as if it was a table and the rest as just regular values.
	    foreach ($segments as $key => $segment) {
	        $wrapped[] = $this->wrapValue($segment);
	    }
	    
	    return implode('.', $wrapped);
	}
	
	/**
	 * Wrap the given JSON selector.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapJsonSelector($value)
	{
	    throw new \RuntimeException('This database engine does not support JSON operations.');    
	}
	
	/**
	 * Split the given JSON selector into the field and the optional path and wrap them separately.
	 *
	 * @param  string  $column
	 * @return array
	 */
	protected function wrapJsonFieldAndPath($column, $delimiter = '->')
	{
	    $parts = explode($delimiter, $column, 2);
	    
	    $field = $this->wrap($parts[0]);
	    
	    $path = count($parts) > 1 ? ', '.$this->wrapJsonPath($parts[1], '->') : '';
	    
	    return [$field, $path];
	}
	
	/**
	 * Wrap the given JSON path.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapJsonPath($value, $delimiter = '->')
	{
	    $value = preg_replace("/([\\\\]+)?\\'/", "\\'", $value);
	    
	    return '\'$."'.str_replace($delimiter, '"."', $value).'"\'';
	}
	
	/**
	 * Determine if the given string is a JSON selector.
	 *
	 * @param  string  $value
	 * @return bool
	 */
	protected function isJsonSelector($value)
	{
	    return strpos($value, '->') !== false;
	}
	
}
