<?php

class TradeFactory
{
    public function __construct()
    {
    }

    public function create(): Trade
    {
        return new Trade();
    }

    public function cast(object $object): Trade
    {
        $trade = new Trade();
        foreach ($object as $property => $value) {
            $trade->$property = $value;
        }
        $reflection = new ReflectionClass('Trade');
        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            if (!isset($trade->$propertyName)) {
                throw new Exception('Unable to cast trade, missing property: ' . $propertyName);
            }
        }
        return $trade;
    }
}

?>