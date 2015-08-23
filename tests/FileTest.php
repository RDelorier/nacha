<?php

class FileTest extends PHPUnit_Framework_TestCase
{

    /**
     * @test
     */
    public function file_should_update_calculate_batch_count()
    {
        $file = new \Nacha\File();
        $this->assertEquals(0, $file->getBatchCount());
        $file->addBatch(new \Nacha\Batch());
        $this->assertEquals(1, $file->getBatchCount());
    }

    /**
     * @test
     */
    public function file_should_calculate_record_count_correctly()
    {
        $batch = $this->newBatch()
                      ->addEntry($this->newEntry())
                      ->addEntry($this->newEntry());

        $file = $this->newFile()->addBatch($batch);

        $this->assertEquals(2, $file->recordCount());

        $file->addBatch(
            $this->newBatch()
                 ->addEntry($this->newEntry())
        );

        $this->assertEquals(3, $file->recordCount());
    }

    /**
     * @test
     */
    public function file_should_calculate_totals_correctly()
    {
        $batch = $this->newBatch();

        //Add some debits
        $batch
            ->addEntry($this->newEntry(50))
            ->addEntry($this->newEntry(50));

        //Add some credits
        $batch
            ->addEntry($this->newEntry(50, Nacha\Entry::DEPOSIT_CHECKING))
            ->addEntry($this->newEntry(25, Nacha\Entry::DEPOSIT_CHECKING));

        $file = $this->newFile()->addBatch($batch);

        $this->assertEquals(100, $file->debitTotal());
        $this->assertEquals(75, $file->creditTotal());

        //add another batch to make sure it totals correctly
        $file->addBatch(
            $this->newBatch()
                 ->addEntry($this->newEntry(10))
                 ->addEntry($this->newEntry(10, Nacha\Entry::DEPOSIT_CHECKING))
        );

        $this->assertEquals(110, $file->debitTotal());
        $this->assertEquals(85, $file->creditTotal());
    }

    /**
     * @test
     */
    public function file_should_generate_entry_hash_using_all_batches()
    {
        $file = $this->newFile();

        $file->addBatch(
            $this->newBatch()
                 ->addEntry($this->newEntry()->setRoutingNumber('111111111'))
                 ->addEntry($this->newEntry()->setRoutingNumber('111111111'))
        );

        $file->addBatch(
            $this->newBatch()
                 ->addEntry($this->newEntry()->setRoutingNumber('111111111'))
                 ->addEntry($this->newEntry()->setRoutingNumber('222222222'))
        );
        $file->toString();

        $this->assertTrue('0555555555' === $file->getFooterAttribute('entry_hash'));
    }

    /**
     * @test
     */
    public function file_should_update_batches_with_company_id_when_set()
    {
        $batch = $this->newBatch();
        $file = $this->newFile()->setCompanyId('1111111111');
        $file->addBatch($batch);

        $this->assertTrue($file->getHeaderAttribute('origin_id') === '1111111111');
        $this->assertTrue($batch->getHeaderAttribute('company_id') === '1111111111');
    }

    /**
     * @test
     * @expectedException \Nacha\Exceptions\CompanyIdInvalid
     */
    public function file_should_throw_exception_if_company_id_length_is_not_10_chars()
    {
        $this->newFile()->setCompanyId('1');
    }

    /**
     * @test
     */
    public function file_should_mutate_destination_routing_when_set()
    {
        $file = $this->newFile()->setDestinationRouting('111111111');
        $this->assertTrue(' 111111111' === $file->getHeaderAttribute('destination_id'));
    }

    /**
     * @test
     * @expectedException \Nacha\Exceptions\RoutingNumberInvalid
     */
    public function file_should_throw_exception_if_routing_number_incorrect()
    {
        $this->newFile()->setDestinationRouting(1);
    }

    /**
     * @test
     */
    public function file_should_mutate_destination_name_when_set()
    {
        $file = $this->newFile()->setDestinationName('test');
        $this->assertTrue('TEST                   ' === $file->getHeaderAttribute('destination_name'));
    }

    /**
     * @test
     */
    public function file_should_mutate_origin_name_when_set()
    {
        $file = $this->newFile()->setCompanyName('test');
        $this->assertTrue('TEST                   ' === $file->getHeaderAttribute('origin_name'));
    }

    /**
     * @return \Nacha\File
     */

    private function newFile()
    {
        return new \Nacha\File();
    }

    /**
     * @return \Nacha\Batch
     */
    private function newBatch()
    {
        return new \Nacha\Batch();
    }

    /**
     * @param int $amount
     * @param int $tranCode
     * @return \Nacha\Entry
     */
    private function newEntry($amount = 1, $tranCode = \Nacha\Entry::DEBIT_CHECKING)
    {
        return \Nacha\Entry::create($tranCode, '123456789', '1', '1', '1', $amount);
    }
}