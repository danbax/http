<?php

namespace Utopia\Traits;

use Exception;
use Utopia\Hook;
use Utopia\Validator;

trait Hooks
{
    use Resources;

    /**
     * Errors
     *
     * Errors callbacks
     *
     * @var Hook[]
     */
    protected static array $errors = [];

    /**
     * Init
     *
     * A callback function that is initialized on application start
     *
     * @var Hook[]
     */
    protected static array $init = [];

    /**
     * Shutdown
     *
     * A callback function that is initialized on application end
     *
     * @var Hook[]
     */
    protected static array $shutdown = [];

    /**
     * Init
     *
     * Set a callback function that will be initialized on application start
     *
     * @return Hook
     */
    public static function init(): Hook
    {
        $hook = new Hook();
        $hook->groups(['*']);

        self::$init[] = $hook;

        return $hook;
    }

    /**
     * Shutdown
     *
     * Set a callback function that will be initialized on application end
     *
     * @return Hook
     */
    public static function shutdown(): Hook
    {
        $hook = new Hook();
        $hook->groups(['*']);

        self::$shutdown[] = $hook;

        return $hook;
    }

    /**
     * Error
     *
     * An error callback for failed or no matched requests
     *
     * @return Hook
     */
    public static function error(): Hook
    {
        $hook = new Hook();
        $hook->groups(['*']);

        self::$errors[] = $hook;

        return $hook;
    }

    /**
     * Call hooks by group.
     *
     * @param array $hooks 
     * @param string $group 
     * @param array $values 
     * @param array $params 
     * @return void 
     * @throws Exception 
     */
    public function callHooks(array $hooks, string $group, array $values, array $params): void
    {
        foreach ($hooks as $hook) {
            if (in_array($group, $hook->getGroups())) {
                $arguments = $this->getArguments($hook, $values, $params);
                \call_user_func_array($hook->getAction(), $arguments);
            }
        }
    }

    /**
     * Validate Param
     *
     * Creates an validator instance and validate given value with given rules.
     *
     * @param  string  $key
     * @param  array  $param
     * @param  mixed  $value
     * @return void
     *
     * @throws Exception
     */
    protected function validate(string $key, array $param, mixed $value): void
    {
        if ($param['optional'] && \is_null($value)) {
            return;
        }

        $validator = $param['validator']; // checking whether the class exists

        if (\is_callable($validator)) {
            $validator = \call_user_func_array($validator, $this->getResources($param['injections']));
        }

        if (!$validator instanceof Validator) { // is the validator object an instance of the Validator class
            throw new Exception('Validator object is not an instance of the Validator class', 500);
        }

        if (!$validator->isValid($value)) {
            throw new Exception('Invalid ' . $key . ': ' . $validator->getDescription(), 400);
        }
    }

    /**
     * Get Arguments
     *
     * @param  Hook  $hook
     * @param  array  $values
     * @param  array  $requestParams
     * @return array
     *
     * @throws Exception
     */
    protected function getArguments(Hook $hook, array $values, array $requestParams): array
    {
        $arguments = [];
        foreach ($hook->getParams() as $key => $param) { // Get value from route or request object
            $existsInRequest = \array_key_exists($key, $requestParams);
            $existsInValues = \array_key_exists($key, $values);
            $paramExists = $existsInRequest || $existsInValues;

            $arg = $existsInRequest ? $requestParams[$key] : $param['default'];
            $value = $existsInValues ? $values[$key] : $arg;

            if (!$param['skipValidation']) {
                if (!$paramExists && !$param['optional']) {
                    throw new Exception('Param "' . $key . '" is not optional.', 400);
                }

                if ($paramExists) {
                    $this->validate($key, $param, $value);
                }
            }

            $hook->setParamValue($key, $value);
            $arguments[$param['order']] = $value;
        }

        foreach ($hook->getInjections() as $key => $injection) {
            $arguments[$injection['order']] = $this->getResource($injection['name']);
        }

        return $arguments;
    }
}
