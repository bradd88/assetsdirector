<?php

/**
 * ServiceContainer is an autowiring dependency injection container and factory.
 * By calling the create method the ServiceContainer will recursivly instantiate and inject dependencies for the requested object, and return it.
 * An alias config can be supplied when the container is created to map dependencies for abstract classes to specific concrete classes.
 */
class ServiceContainer
{
    private array $objects = array();
    private array $aliases = array();

    public function __construct(?string $aliasConfigPath = NULL)
    {
        $aliasConfigPath = $aliasConfigPath ?? dirname(__DIR__, 2) . '/services.ini';
        $this->aliases = parse_ini_file($aliasConfigPath, TRUE, INI_SCANNER_TYPED);
    }

    /** Create a list of class dependencies for a specified class. */
    private function listDependencies(ReflectionClass $class): array
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

    /** Recursively create/retrieve object dependencies. Returns an object instance of the specified class. */
    public function create(string $className, ?array $parameters = NULL): mixed
    {
        //FIX: Return should be fixed to always return the same type: an object.
        $class = new ReflectionClass($className);
        if ($class->isInstantiable()) {

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

    /** Retrieve a previously instantiated object and return it */
    public function get(string $className): object
    {
        return $this->objects[$className];
    }

}

?>