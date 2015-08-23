<?php
/**
 * Created by PhpStorm.
 * User: rdelorier1
 * Date: 8/22/15
 * Time: 8:49 PM
 */

class EntryTest extends PHPUnit_Framework_TestCase {

    /** @test */
    public function entry_should_pad_account_to_17_chars()
    {
        $entry = \Nacha\Entry::create(\Nacha\Entry::DEPOSIT_CHECKING, '123456789','1','1','1',1);
        $this->assertEquals('1                ',$entry->getAttribute('account_number'));
    }

    /** @test */
    public function entry_should_trim_account_to_17_chars()
    {
        $account = '123123123123123123123123';
        $entry = \Nacha\Entry::create(\Nacha\Entry::DEPOSIT_CHECKING, '123456789',$account,'1','1',1);
        $this->assertEquals(substr($account,0,17),$entry->getAttribute('account_number'));
    }

    /**
     * @test
     * @expectedException \Nacha\Exceptions\RoutingNumberInvalid
     */
    public function entry_should_throw_exception_if_routing_number_less_than_9_chars()
    {
        \Nacha\Entry::create(\Nacha\Entry::DEPOSIT_CHECKING, '1','1','1','1',1);
    }

    /** @test */
    public function entry_should_trim_routing_number_to_9_chars()
    {
        $routing = '123123123123123123123123';
        $entry = \Nacha\Entry::create(\Nacha\Entry::DEPOSIT_CHECKING, $routing,'1','1','1',1);
        $this->assertEquals(substr($routing,0,9),$entry->getAttribute('routing_number'));
    }

    /**
     * @test
     */
    public function entry_should_multiply_and_pad_amount()
    {
        $entry = $this->newEntry();
        $this->assertEquals('0000000100', $entry->getAttribute('amount'));
    }

    /**
     * @test
     */
    public function entry_should_update_tracer_with_batch_and_item_number()
    {
        $entry = $this->newEntry();
        $entry->setBatchNumber(5)->setEntryNumber(6);
        $this->assertEquals('000000050000006', $entry->getAttribute('tracer_number'));
    }

    /**
     * @test
     */
    public function entry_should_trim_receiver_id_to_17_chars()
    {
        $entry = $this->newEntry();
        $id = '38898327498327489732894327urj83r8uruj38i9u389oiru';
        $entry->setReceiverId($id);
        $this->assertEquals(substr($id,0,17), $entry->getAttribute('receiver_id'));
    }

    /**
     * @test
     */
    public function entry_should_trim_receiver_name_to_22_chars()
    {
        $entry = $this->newEntry();
        $name = '38898327498327489732894327urj83r8uruj38i9u389oiru';
        $entry->setReceiverName($name);
        $this->assertEquals(substr($name,0,22), $entry->getAttribute('receiver_name'));
    }

    /**
     * @test
     * @expectedException \Nacha\Exceptions\InvalidTransactionCode
     */
    public function entry_should_throw_exception_if_bad_tranction_code_giver()
    {
        $this->newEntry()->setTransactionCode(1);
    }
    /**
     * @return \Nacha\Entry
     */

    private function newEntry()
    {
        return \Nacha\Entry::create(\Nacha\Entry::DEPOSIT_CHECKING, '123456789','1','1','1',1);
    }
}
