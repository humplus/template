<?php

namespace Humming;


class Thigh implements \ArrayAccess
{
    /**
     * Parameters
     * @var array
     */
    protected $params = array();

    /**
     * Widgets Instance Sets
     * @var array
     */
    protected static $widgets = array();

    /**
     * Cache
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $cache;

    /**
     * Container
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * Thigh constructor.
     * @param \Psr\SimpleCache\CacheInterface|null $cache
     * @param \Psr\Container\ContainerInterface|null $container
     */
    public function __construct(\Psr\SimpleCache\CacheInterface $cache = null, \Psr\Container\ContainerInterface $container = null)
    {
        $this->cache = $cache;
        $this->container = $container;
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
     * Get Parameters
     * @return array
     */
    public function getParameters()
    {
        return $this->params;
    }

    /**
     * Get Container
     * @return null|\Psr\Container\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get Cache
     * @return null|\Psr\SimpleCache\CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Get Widget Result
     * @param mixed $offset
     * @return mixed
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        if (!isset(self::$widgets[$offset])) {
            $class = self::studly($offset);
            $reflector = new \ReflectionClass($class);
            $constructor = $reflector->getConstructor();
            if (is_null($constructor)) {
                self::$widgets[$offset] = new $class;
            }
            $params = array();
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (!is_null($this->container) && $this->container->has($name)) {
                    $params [] = $this->container->get($name);
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $params [] = $parameter->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException ("Class {$class} require parameter {$name}!");
                }
            }

            self::$widgets[$offset] = $reflector->newInstanceArgs($params);
        }
        if (!self::$widgets[$offset] instanceof Widget) {
            throw new \LogicException("Widget must inherit \Humming\Widget class");
        }
        self::$widgets[$offset]->setThigh($this);
        return self::$widgets[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        self::$widgets[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset(self::$widgets[$offset]);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset(self::$widgets[$offset]);
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
}