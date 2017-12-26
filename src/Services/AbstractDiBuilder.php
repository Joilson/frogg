<?php

namespace Frogg\Services;

use Phalcon\Di\FactoryDefault;

abstract class AbstractDiBuilder extends FactoryDefault
{
    /**
     * AbstractService constructor.
     *
     * @param $config
     * @param $bugsnag
     */
    public function __construct($config, $bugsnag = null)
    {
        parent::__construct();
        if ($bugsnag) {
            $this->set('bugsnag', $bugsnag);
        }
        $this->setShared('config', $config);
        $this->bindServices();
        $this->bindAppServices();
    }

    /**
     * All services with business rule for application
     *
     * @return array
     */
    abstract protected function appServices();

    /**
     *  Register services in di, all methods with prefix [init, initShared]
     */
    protected function bindServices()
    {
        $reflection = new \ReflectionObject($this);
        $methods    = $reflection->getMethods();
        foreach ($methods as $method) {
            if ((strlen($method->name) > 10) && (strpos($method->name, 'initShared') === 0)) {
                $this->setShared(lcfirst(substr($method->name, 10)), $method->getClosure($this));
                continue;
            }
            if ((strlen($method->name) > 4) && (strpos($method->name, 'init') === 0)) {
                $this->set(lcfirst(substr($method->name, 4)), $method->getClosure($this));
            }
        }
    }

    /**
     * Register application services class on di and resolve dependency injection for constructor
     */
    private function bindAppServices()
    {
        /* @var $serviceClassPath AbstractService */
        foreach ($this->appServices() as $serviceClassPath) {
            $serviceReflectionClass      = new \ReflectionClass($serviceClassPath);
            $serviceConstructorArguments = $this->getConstructorArguments($serviceReflectionClass);

            $this->set(
                $serviceClassPath::getName(),
                [
                    'className' => $serviceClassPath,
                    'arguments' => $serviceConstructorArguments,
                ]
            );
        }
    }

    private function getConstructorArguments(\ReflectionClass $reflectionClass)
    {
        /* @var $constructor \ReflectionMethod */
        $constructor = $reflectionClass->getConstructor();
        $arguments   = [];

        if (!$constructor) {
            return $arguments;
        }

        /* @var $param \ReflectionParameter */
        foreach ($constructor->getParameters() as $param) {
            if ($param->isOptional()) {
                continue;
            }
            array_push(
                $arguments,
                $this->resolveArgumentDi($param)
            );
        }

        return $arguments;
    }

    private function resolveArgumentDi(\ReflectionParameter $param)
    {
        if (!$this->isParamService($param)) {
            if (!$this->has($param->name)) {
                throw new \Exception("Param '{$param->name}' in constructor of {$param->getDeclaringClass()->name} not exists on di container");
            }

            return [
                'type'  => 'parameter',
                'value' => $this->get($param->name),
            ];
        }

        $class = $param->getClass()->name;
        return [
            'type' => 'service',
            'name' => $class::getName(),
        ];
    }

    protected function isParamService(\ReflectionParameter $param)
    {
        $parameterClass = $param->getClass();

        return $parameterClass && in_array([ApplicationServiceInterface::class], [$parameterClass->getInterfaceNames()]);
    }

}
