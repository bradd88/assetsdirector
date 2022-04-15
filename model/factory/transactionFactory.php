<?php

/** Creates Transaction objects. */
class TransactionFactory
{
    public function cast(object $object): Transaction
    {
        $transaction = new Transaction();
        foreach ($object as $property => $value) {
            $transaction->$property = $value;
        }
        $reflection = new ReflectionClass('Transaction');
        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            if (!isset($transaction->$propertyName)) {
                throw new Exception('Unable to cast transaction, missing property: ' . $propertyName);
            }
        }
        return $transaction;
    }
}

?>