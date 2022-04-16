<?php

/** Uses the output buffer to parse html/php to generate a page view. */
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
        if (isset($parameters)) {
            foreach ($parameters as $key => $value) {
                $$key = $value;
            }
        }
        $path = $this->appSettings->rootDir . '/view/' . $name;
        ob_start();
        require $path;
        $view = ob_get_contents();
        ob_end_clean();
        return $view;
    }
}

?>