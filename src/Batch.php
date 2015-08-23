<?php
/**
 * Created by PhpStorm.
 * User: rdelorier1
 * Date: 8/22/15
 * Time: 6:03 PM
 */

namespace Nacha;


use Nacha\Traits\AllowsDebug;

class Batch
{
    use AllowsDebug;

    const MIXED_ENTRIES = 200;
    const CREDIT_ONLY   = 220;
    const DEBITS_ONLY   = 225;

    private $header = [
        'record_type_code'     => '5',
        'service_class_code'   => '200', //update from entries
        'company_name'         => '',
        'discretionary_data'   => '                    ',
        'company_id'           => '',
        'entry_class'          => 'PPD',
        'entry_description'    => '',
        'descriptive_date'     => '      ',
        'effective_entry_date' => null,
        'reserved'             => '   ',
        'status_code'          => '1',
        'originating_id'       => '',
        'batch_number'         => "",
        'new_line'             => "\n"
    ];

    private $footer = [
        'record_type_code'   => '8',
        'service_class_code' => '200',
        'entry_count'        => '',
        'entry_hash'         => '',
        "debit_total"        => "000000000000", //from batch
        "credit_total"       => "000000000000", //from batch
        'company_id'         => '',
        'reserved'           => '                   ',
        'reserved_2'         => '      ',
        'originating_id'     => '',
        'batch_number'       => '',
        'new_line'           => "\n"

    ];

    private $entries     = [];
    private $batchNumber = 1;

    function __construct()
    {
        $this->header['effective_entry_date'] = date('ymd');
    }

    /**
     * add a new Entry to this batch
     *
     * @param Entry $entry
     * @return $this
     */
    public function addEntry(Entry $entry)
    {
        $entry
            ->setBatchNumber($this->batchNumber)
            ->setEntryNumber(count($this->entries) + 1);

        $this->entries[] = $entry;
        return $this;
    }

    /**
     * convert batch and all entries withing to a single string
     * for use in the ach file
     *
     * @return string
     */
    public function toString()
    {
        $this->updateServiceClassCode();
        return implode([
                           $this->getHeader(),
                           $this->getEntries(),
                           $this->getFooter()
                       ]);
    }

    /**
     * Check all the entries to determine the service class
     * that should be assigned to batch
     */
    private function updateServiceClassCode()
    {
        $isCredit = $this->entries[0]->isCredit();

        foreach ($this->entries as $entry) {
            if ($entry->isCredit() != $isCredit) {
                $this->setServiceClassCode(self::MIXED_ENTRIES);
                return;
            }
        }

        $this->setServiceClassCode($isCredit ? self::CREDIT_ONLY : self::DEBITS_ONLY);
    }

    /**
     * get batch header
     * @return string
     */
    private function getHeader()
    {
        return implode($this->getImplodeGlue(), $this->header);
    }

    /**
     * get entries as string
     * @return mixed
     */
    private function getEntries()
    {
        return array_reduce($this->entries, function ($result, Entry $entry) {
            return $result . $entry->toString();
        }, "");
    }

    /**
     * get batch footer
     * @return string
     */
    private function getFooter()
    {
        $this->updateFooter();
        return implode($this->getImplodeGlue(), $this->footer);
    }

    /**
     * update some footer values based on current entries
     */
    private function updateFooter()
    {
        $this->footer['entry_hash']   = str_pad($this->entryHash(), 10, '0', STR_PAD_LEFT);
        $this->footer['debit_total']  = str_pad($this->debitTotal()*100, 12, '0', STR_PAD_LEFT);
        $this->footer['credit_total'] = str_pad($this->creditTotal()*100, 12, '0', STR_PAD_LEFT);
        $this->footer['entry_count']  = str_pad($this->getEntryCount(), 6, '0', STR_PAD_LEFT);
    }

    /**
     * get the entry has for this batch
     *
     * @return string
     */
    private function entryHash()
    {
        $total = array_reduce($this->entries, function ($total, Entry $entry) {
            return $total + $entry->getRouting();
        }, 0);

        return substr($total, -10);
    }

    /*
    |--------------------------------------------------------------------------
    | Setters
    |--------------------------------------------------------------------------
    |
    | Methods to allow setting and mutating attributes
    |
    */

    /**
     * Set your 10-digit company identification code. Should match
     * field 4 of the file header unless multiple companies
     * have been included in file
     *
     * @param string $id
     * @return $this
     */
    public function setCompanyIdentification($id)
    {
        $this->header['company_id'] = $id;
        $this->footer['company_id'] = $id;
        return $this;
    }

    /**
     * Set batch number. propagates to entries
     * @param $number
     * @return $this
     */
    public function setBatchNumber($number)
    {
        $this->batchNumber            = $number;
        $this->header['batch_number'] = str_pad($number, 7, '0', STR_PAD_LEFT);
        $this->footer['batch_number'] = str_pad($number, 7, '0', STR_PAD_LEFT);

        foreach ($this->entries as $entry) {
            $entry->setBatchNumber($number);
        }

        return $this;
    }

    /**
     * Originating Financial Institution Routing number
     * only first 8 characters are used
     *
     * @param $id
     * @return $this
     */
    public function setOriginatingId($id)
    {
        $id = substr(trim($id), 0, 8);
        $this->header['originating_id'] = $id;
        $this->footer['originating_id'] = $id;
        return $this;
    }

    /**
     * set your company name
     *
     * @param string $name up to 16 chars, will be mutated
     * @return $this
     */
    public function setCompanyName($name)
    {
        $name                         = strtoupper(substr($name, 0, 16));
        $this->header['company_name'] = str_pad($name, 16);
        return $this;
    }

    /**
     * set the text describing what this batch is for
     *
     * @param string $description
     * @return $this
     */
    public function setEntryDescription($description)
    {
        $description                       = strtoupper(substr($description, 0, 10));
        $this->header['entry_description'] = str_pad($description, 10);
        return $this;
    }

    /**
     * @param $code
     */
    private function setServiceClassCode($code)
    {
        $this->header['service_class_code'] = $code;
        $this->footer['service_class_code'] = $code;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    |
    | Provides access to private and calculated values
    |
    */
    /**
     * return the current entry count
     * @return int
     */
    public function getEntryCount()
    {
        return count($this->entries);
    }

    /**
     * return the sum of all debit entries
     * @return mixed
     */
    public function debitTotal()
    {
        return array_reduce($this->entries, function ($total, Entry $entry) {
            return $total + ($entry->isDebit() ? $entry->getAmount() : 0);
        }, 0);
    }

    /**
     * return the sum of all credit entries
     * @return mixed
     */
    public function creditTotal()
    {
        return array_reduce($this->entries, function ($total, Entry $entry) {
            return $total + ($entry->isCredit() ? $entry->getAmount() : 0);
        }, 0);
    }

    /**
     * return the sum of all entry routing numbers
     * @return mixed
     */
    public function getRoutingSum()
    {
        return array_reduce($this->entries, function ($total, Entry $entry) {
            return $total + $entry->getRouting();
        }, 0);
    }

    /**
     * return requested header attribute
     * @param $key
     * @return mixed
     */
    public function getHeaderAttribute($key)
    {
        return $this->header[$key];
    }

    /**
     * return requested footer attribute
     * @param $key
     * @return mixed
     */
    public function getFooterAttribute($key)
    {
        return $this->footer[$key];
    }

    public function getBlockCount()
    {
        return $this->getEntryCount()+2;
    }
}