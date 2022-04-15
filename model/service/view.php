<?php

class View
{
    private object $appSettings;
    public function __construct(Config $config)
    {
        $this->appSettings = $config->getSettings('application');
    }
    /** Generate presentation object code for the specefied view. */
    public function get(string $name, ?array $parameters = NULL): string
    {
        // Move variables out of the optional parameters array for easier use.
        if (isset($parameters)) {
            foreach ($parameters as $key => $value) {
                $$key = $value;
            }
        }
        // Use the internal buffer to parse the view file and return it as a string.
        $path = $this->appSettings->rootDir . '/view/' . $name;
        ob_start();
        require $path;
        $view = ob_get_contents();
        ob_end_clean();
        return $view;
    }
}

?>