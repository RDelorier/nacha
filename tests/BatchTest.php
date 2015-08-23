<?php
/**
 * Created by PhpStorm.
 * User: rdelorier1
 * Date: 8/22/15
 * Time: 9:27 PM
 */

class BatchTest extends PHPUnit_Framework_TestCase {

    public function setUp()
    {
        date_default_timezone_set("America/New_York");
    }

    /**
     * @test
     */
    public function batch_should_update_entry_count_when_entry_is_added()
    {
        $batch = $this->newBatch();
        $entry = $this->newEntry();
        $batch->addEntry($entry);

        $this->assertEquals(1, $batch->getEntryCount());
    }

    /**
     * @test
     */
    public function batch_should_set_batch_number_on_entries_when_added()
    {
        $batch = $this->newBatch();
        $batch->setBatchNumber(5);

        $entry = $this->newEntry();
        $batch->addEntry($entry);

        $this->assertEquals('5', $entry->getBatchNumber());
    }

    /**
     * @test
     */
    public function batch_should_cascade_batch_number_updates_to_entries()
    {
        $batch = $this->newBatch();
        $entry = $this->newEntry();
        $batch->addEntry($entry);
        $batch->setBatchNumber(5);

        $this->assertEquals('5', $entry->getBatchNumber());
    }

    /**
     * @test
     */
    public function batch_should_sum_entry_amount()
    {
        $batch = $this->newBatch();

        //Add Debits
        $batch->addEntry($this->newEntry(2));
        $batch->addEntry($this->newEntry(2));

        //Add Credits
        $batch->addEntry($this->newEntry(5, \Nacha\Entry::DEPOSIT_CHECKING));
        $batch->addEntry($this->newEntry(5, \Nacha\Entry::DEPOSIT_CHECKING));

        $this->assertEquals(4, $batch->debitTotal());
        $this->assertEquals(10, $batch->creditTotal());
    }

    /**
     * @test
     */
    public function batch_should_generate_entry_hash_when_to_string_is_called()
    {
        $batch = $this->newBatch();

        $batch->addEntry($this->newEntry()->setRoutingNumber('111111111'));
        $batch->addEntry($this->newEntry()->setRoutingNumber('111111111'));
        $batch->toString();

        $this->assertTrue('0222222222' === $batch->getFooterAttribute('entry_hash'));
        $this->assertEquals(222222222, $batch->getRoutingSum());
    }

    /**
     * @test
     */
    public function batch_should_correctly_determine_service_class_code()
    {
        $batch = $this->newBatch();

        //Add Debit
        $batch->addEntry($this->newEntry(2));
        $batch->toString();
        $this->assertEquals(\Nacha\Batch::DEBITS_ONLY, $batch->getHeaderAttribute('service_class_code'));

        //Add Credit
        $batch->addEntry($this->newEntry(5, \Nacha\Entry::DEPOSIT_CHECKING));
        $batch->toString();
        $this->assertEquals(\Nacha\Batch::MIXED_ENTRIES, $batch->getHeaderAttribute('service_class_code'));

        $batch = $this->newBatch();
        $batch->addEntry($this->newEntry(5, \Nacha\Entry::DEPOSIT_CHECKING));
        $batch->toString();
        $this->assertEquals(\Nacha\Batch::CREDIT_ONLY, $batch->getHeaderAttribute('service_class_code'));
    }

    /**
     * @test
     */
    public function batch_should_mutate_company_name_when_set()
    {
        $batch = $this->newBatch()->setCompanyName('test');
        $this->assertTrue('TEST            ' === $batch->getHeaderAttribute('company_name'));

        $name = 'sdfsdfsfsdfdsfsdfsdfsdfdsfdsf';
        $batch->setCompanyName($name);
        $this->assertTrue(strtoupper(substr($name,0,16)) === $batch->getHeaderAttribute('company_name'));
    }

    /**
     * @test
     */
    public function batch_should_mutate_entry_description_when_set()
    {
        $batch = $this->newBatch()->setEntryDescription('test');
        $this->assertTrue('TEST      ' === $batch->getHeaderAttribute('entry_description'));
    }

    /**
     * @return \Nacha\Batch
     */

    private function newBatch()
    {
        return new \Nacha\Batch();
    }

    private function newEntry($amount = 1, $tranCode = \Nacha\Entry::DEBIT_CHECKING)
    {
        return \Nacha\Entry::create($tranCode, '123456789','1','1','1',$amount);
    }
}
