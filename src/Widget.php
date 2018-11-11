<?php

namespace Humming;

use \Psr\SimpleCache\CacheInterface;

class Widget implements \ArrayAccess
{
    /**
     * Widget Parameters
     * @var array
     */
    protected $params = array();

    /**
     * History Called
     * eg. array('method_p1_p2' => array(...))
     * @var array
     */
    protected $results = array();

    /**
     * Widget Instance List
     * @var array
     */
    protected static $instances = array();

    /**
     * Cache
     * @var CacheInterface
     */
    protected static $cache;

    public function __construct(CacheInterface $cache = null)
    {
        if (!empty($cache))
            self::$cache = $cache;
    }

    /**
     * Set Parameters
     * @param array $params
     */
    public function setParameters(array $params)
    {
        $this->params = array_map('strval', $params);
    }

    /**
     * TestAtHumming => test_at_humming
     * @param $value
     * @param string $delimiter
     * @return string
     */
    public static function snake($value, $delimiter = '_')
    {
        $replace = '$1' . $delimiter . '$2';
        return ctype_lower($value) ? $value : strtolower(preg_replace('/(.)([A-Z])/', $replace, $value));
    }

    /**
     * test_at_humming => TestAtHumming
     * @param $value
     * @return mixed
     */
    public static function studly($value)
    {
        $value = ucwords(str_replace(array('-', '_'), ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * Get Widget Result
     * @param mixed $offset
     * @return array|mixed
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        $class = get_class($this);
        //Base Class Called
        if ('Humming\Widget' == $class) {
            if (isset(self::$instances[$offset])) {
                return self::$instances[$offset];
            }
            $class = self::studly($offset);
            return self::$instances[$offset] = new $class;
        }
        //Sub Class Called
        $method = sprintf('get%s', self::studly($offset));
        $called = sprintf('widget.%s.%s.', array_search($this, self::$instances), $offset);

        if (!method_exists($this, $method)) {
            throw new \RuntimeException("$called is not defined!");
        }
        $ttl = -1;
        if (isset($this->params['cache']) && false !== filter_var($this->params['cache'], FILTER_VALIDATE_INT)) {
            $ttl = intval($this->params['cache']);
            unset($this->params['cache']);
        }
        $parameters = array();
        if (!empty($this->params)) {
            $function = new \ReflectionMethod ($this, $method);
            foreach ($function->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (array_key_exists($name, $this->params)) {
                    $parameters [] = $this->params[$name];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $parameters [] = $parameter->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException (sprintf('%s* parameter %s not found!', $called, $name));
                }
            }
        }

        if (!empty($parameters)) {
            $called .= implode('.', $parameters);
        }

        if (isset($this->results[$called])) {
            return $this->results[$called];
        }

        if ($ttl >= 0 && !is_null(self::$cache)) {
            $result = self::$cache->get($called);
            if (false !== $result) {
                $result = json_decode($result, true);
                return $this->results[$called] = $result['items'];
            } else {
                $this->results[$called] = isset($function) ? $function->invokeArgs($this, $parameters) : $this->$method();
                $result = array(
                    'params' => $parameters,
                    'created_at' => date("Y-m-d H:i:s"),
                    'items' => $this->results[$called]
                );
                self::$cache->set($called, json_encode($result), $ttl);
                return $this->results[$called];
            }
        }
        return $this->results[$called] = isset($function) ? $function->invokeArgs($this, $parameters) : $this->$method();
    }

    public function offsetSet($offset, $value)
    {

    }

    public function offsetUnset($offset)
    {

    }

    public function offsetExists($offset)
    {
        $class = get_class($this);
        if ('Humming\Widget' == $class) {
            return true;
        } else {
            return method_exists($this, sprintf('get%s', self::studly($offset)));
        }
    }
}