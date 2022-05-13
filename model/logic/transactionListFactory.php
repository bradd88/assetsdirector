<?php 



class TransactionListFactory
{


    public function __construct()
    {
    }

    public function create(): TransactionList
    {
        return new TransactionList();
    }

}

?>