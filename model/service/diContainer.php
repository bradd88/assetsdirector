<?php

/**
 * DIContainer is an autowiring dependency injection container.
 * By calling the create method the DIContainer will recursivly instantiate and inject dependencies for the requested object, and return it.
 * An alias config can be supplied when the container is created to map dependencies for abstract classes to specific concrete classes.
 */
class DIContainer
{
    private array $objects = array();
    private array $aliases = array();

    // Load aliases for abstract classes
    public function __construct(string $aliasConfigPath = NULL)
    {
        $aliasConfigPath = $aliasConfigPath ?? dirname(__DIR__, 2) . '/services.ini';
        $this->aliases = parse_ini_file($aliasConfigPath, TRUE, INI_SCANNER_TYPED);
    }

    private function listDependencies(ReflectionClass $class)
    {
        $classDependencies = array();
        $constructor = $class->getConstructor();
        if (isset($constructor)) {
            if ($constructor->getNumberOfParameters() > 0) {
                foreach ($constructor->getParameters() as $parameter) {
                    $dependencyName = (string) $parameter->getType();
                    if (class_exists($dependencyName)) {
                        $classDependencies[] = $dependencyName;
                    }
                }
            }
        }
        return $classDependencies;
    }

    public function create(string $className, array $parameters = NULL)
    {
        $class = new ReflectionClass($className);
        if ($class->isInstantiable()) {

            // Recursively create and retrieve object dependencies.
            $dependencyObjects = array();
            $dependencyNames = $this->listDependencies($class);
            if ($dependencyNames > 0) {
                foreach ($dependencyNames as $dependencyName) {
                    if (array_key_exists($dependencyName, $this->aliases)) {
                        $dependencyName = $this->aliases[$dependencyName];
                    }
                    if (!array_key_exists($dependencyName, $this->objects)) {
                        $this->create($dependencyName);
                    }
                    $dependencyObjects[] = $this->objects[$dependencyName];
                }
            }

            // Instantiate the requested object by injecting dependencies, store it for future use, and return it.
            $instanceArgs = (isset($parameters)) ? array_merge($dependencyObjects, $parameters) : $dependencyObjects;
            $object = $class->newInstanceArgs($instanceArgs);
            $this->objects[$className] = $object;
            return $object;
        }
    }

    public function get(string $className)
    {
        return $this->objects[$className];
    }

}

?>