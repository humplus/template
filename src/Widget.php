<?php

namespace Humming;

class Widget implements \ArrayAccess
{

    /**
     * History Called
     * eg. array('method_p1_p2' => array(...))
     * @var array
     */
    protected $results = array();

    /**
     * My Thigh
     * @var Thigh
     */
    protected $thigh;

    /**
     * Set My Thigh
     * @param Thigh $thigh
     */
    public function setThigh(Thigh $thigh)
    {
        $this->thigh = $thigh;
    }

    /**
     * Get Widget Result
     * @param mixed $offset
     * @return mixed|object
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        $method = sprintf('get%s', Thigh::studly($offset));
        $called = sprintf('widget.%s.%s', Thigh::snake(get_class($this)), $offset);

        if (!method_exists($this, $method)) {
            throw new \RuntimeException("$called is not defined!");
        }

        $params = $this->thigh->getParameters();
        $ttl = -1;
        if (isset($params['cache']) && false !== filter_var($params['cache'], FILTER_VALIDATE_INT)) {
            $ttl = intval($params['cache']);
            unset($params['cache']);
        }

        $parameters = array();
        if (!empty($params)) {
            $function = new \ReflectionMethod ($this, $method);
            foreach ($function->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (array_key_exists($name, $params)) {
                    $parameters [] = $params[$name];
                    $called .= ".{$params[$name]}";
                } elseif (!is_null($this->thigh->getContainer()) && $this->thigh->getContainer()->has($name)) {
                    $parameters [] = $this->thigh->getContainer()->get($name);
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $parameters [] = $parameter->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException (sprintf('%s* parameter %s not found!', $called, $name));
                }
            }
        }

        if (isset($this->results[$called])) {
            return $this->results[$called];
        }

        if ($ttl >= 0 && !is_null($this->thigh->getCache())) {
            $result = $this->thigh->getCache()->get($called);
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
                $this->thigh->getCache()->set($called, json_encode($result), $ttl);
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
        return method_exists($this, sprintf('get%s', Thigh::studly($offset)));
    }
}