<?php
namespace TYPO3\Eel;

/*                                                                        *
 * This script belongs to the FLOW3 package "TYPO3.Eel".                  *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Something like a variable container with
 * wrapping of return values for safe access without warnings.
 */
class Context {

	/**
	 * @var mixed
	 */
	protected $value;

	/**
	 * @param mixed $value
	 */
	public function __construct($value = NULL) {
		$this->value = $value;
	}

	/**
	 * Get a value of the context
	 *
	 * This basically acts as a safe access to non-existing properties, unified array and
	 * property access (using getters) and access to the current value (empty path).
	 *
	 * If a property or key did not exist this method will return NULL.
	 *
	 * @param string|Context $path The path as string or Context value, will be unwrapped for convenience
	 * @return mixed The value
	 */
	public function get($path) {
		if ($path instanceof \TYPO3\Eel\Context) {
			$path = $path->unwrap();
		}
		if ($path === NULL) {
			return NULL;
		} else {
			if (is_array($this->value)) {
				return array_key_exists($path, $this->value) ? $this->value[$path] : NULL;
			} elseif (is_object($this->value)) {
				return \TYPO3\FLOW3\Reflection\ObjectAccess::getProperty($this->value, $path);
			}
		}
	}

	/**
	 * Get a value by path and wrap it into another context
	 *
	 * @param string $path
	 * @return \TYPO3\Eel\Context The wrapped value
	 */
	public function getAndWrap($path = NULL) {
		return $this->wrap($this->get($path));
	}

	/**
	 * Call a method on this context
	 *
	 * @param string $method
	 * @param array $arguments Arguments to the method, if of type Context they will be unwrapped
	 * @return mixed
	 * @throws \Exception
	 */
	public function call($method, array $arguments = array()) {
		for ($i = 0; $i < count($arguments); $i++) {
			if ($arguments[$i] instanceof Context) {
				$arguments[$i] = $arguments[$i]->unwrap();
			}
		}
		if (is_object($this->value)) {
			$callback = array($this->value, $method);
		} elseif (is_array($this->value)) {
			if (!array_key_exists($method, $this->value)) {
					// TODO Return NULL or throw Exception?
				return NULL;
			}
			$callback = $this->value[$method];
		} else {
				// TODO Better general error handling (return NULL?)
			throw new \Exception('Needs object or array to call method, but has ' . gettype($this->value));
		}
		if (!is_callable($callback)) {
				// TODO Better general error handling (return NULL?)
			throw new \Exception('Method "' . $method . '" does not exist');
		}
		return call_user_func_array($callback, $arguments);
	}

	/**
	 * Call a method and wrap the result
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 */
	public function callAndWrap($method, array $arguments = array()) {
		return $this->wrap($this->call($method, $arguments));
	}

	/**
	 * Wraps the given value in a new Context
	 *
	 * @param mixed $value
	 * @return \TYPO3\Eel\Context
	 */
	public function wrap($value) {
		return new Context($value);
	}

	/**
	 * Unwrap the context value recursively
	 *
	 * @return mixed
	 */
	public function unwrap() {
		if (is_array($this->value)) {
			return array_map(function($value) {
				if ($value instanceof Context) {
					return $value->unwrap();
				} else {
					return $value;
				}
			}, $this->value);
		} else {
			return $this->value;
		}
	}

	/**
	 * Push an entry to the context
	 *
	 * Is used to build array instances inside the evaluator.
	 *
	 * @param mixed $value
	 * @return \TYPO3\Eel\Context
	 */
	public function push($value) {
		if (!is_array($this->value)) {
			throw new \Exception('Array operation on non-array context');
		}
		$this->value[] = $value;
		return $this;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		if (is_object($this->value) && !method_exists($this->value, '__toString')) {
			return '[object ' . get_class($this->value) . ']';
		}
		return (string)$this->value;
	}

}
?>