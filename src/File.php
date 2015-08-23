<?php

namespace Nacha;

use Nacha\Exceptions\CompanyIdInvalid;
use Nacha\Exceptions\RoutingNumberInvalid;
use Nacha\Traits\AllowsDebug;

class File
{

    use AllowsDebug;

    public static $debugMode = false;

    private $header = [
        "record_type_code" => '1',
        "priority_code"    => "01",
        "destination_id"   => "",
        "origin_id"        => "",
        "creation_date"    => null,
        "creation_time"    => null,
        "fileId_modifier"  => 'A',
        "record_size"      => "094",
        "blocking_factor"  => "10",
        "format_code"      => "1",
        "destination_name" => "",
        "origin_name"      => "",
        "reserved"         => "        ",
        'new_line'         => "\n"
    ];

    private $footer = [
        "record_type_code" => '9',
        "batch_count"      => "",
        "block_count"      => "",
        "record_count"     => "", //from batch
        "entry_hash"       => "", //from batch
        "debit_total"      => "", //from batch
        "credit_total"     => "", //from batch
        "reserved"         => "                                       ",
    ];

    /** @var Batch[] */
    private $batches       = [];
    private $originatingID = '';
    private $destinationRouting;

    function __construct()
    {
        $this->header['creation_date'] = date('ymd');
        $this->header['creation_time'] = date('Hi');
    }

    /**
     * Add a new batch to file
     * will set batch company id/batch number/ originating id
     *
     * @param Batch $batch
     * @return $this
     */
    public function addBatch(Batch $batch)
    {
        $batch
            ->setCompanyIdentification($this->header['origin_id'])
            ->setBatchNumber(count($this->batches) + 1)
            ->setOriginatingId($this->header['destination_id'])
            ->setCompanyName($this->header['origin_name']);

        $this->batches[] = $batch;
        return $this;
    }

    /**
     * convert file to string which can be submitted to the bank
     * @return string
     */
    public function toString()
    {
        return implode([
                           $this->getHeader(),
                           $this->getBatches(),
                           $this->getFooter()
                       ]);
    }

    /**
     * get file header
     * @return string
     */
    private function getHeader()
    {
        return implode($this->getImplodeGlue(),$this->header);
    }

    /**
     * get all batches as strings
     * @return mixed
     */
    private function getBatches()
    {
        return array_reduce($this->batches, function ($result, Batch $batch) {
            return $result . $batch->toString();
        }, "");
    }

    /**
     * get file footer
     * @return string
     */
    private function getFooter()
    {
        $this->updateFooter();
        return implode($this->getImplodeGlue(),$this->footer);
    }

    /**
     * Update footer data from batches
     */
    private function updateFooter()
    {
        $this->footer['batch_count']  = str_pad($this->getBatchCount(), 6, '0', STR_PAD_LEFT);
        $this->footer['block_count']  = str_pad($this->blockCount(), 6, '0', STR_PAD_LEFT);
        $this->footer['record_count'] = str_pad($this->recordCount(), 8, '0', STR_PAD_LEFT);
        $this->footer['debit_total']  = str_pad($this->debitTotal()*100, 12, '0', STR_PAD_LEFT);
        $this->footer['credit_total'] = str_pad($this->creditTotal()*100, 12, '0', STR_PAD_LEFT);
        $this->footer['entry_hash']   = str_pad($this->entryHash(), 10, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | Setters
    |--------------------------------------------------------------------------
    |
    | Set private attributes on file
    |
    */

    /**
     * Set company Identification
     * propagates to batches
     *
     * @param $id
     * @return $this
     * @throws CompanyIdInvalid
     */
    public function setCompanyId($id)
    {
        if (strlen($id) !== 10) {
            throw new CompanyIdInvalid();
        }

        $this->originatingID       = $id;
        $this->header['origin_id'] = $id;

        array_walk($this->batches, function (Batch $batch) use ($id) {
            $batch->setCompanyIdentification($id);
        });

        return $this;
    }

    /**
     * set destination routing number
     * this should be the routing number of your bank
     *
     * @param $number
     * @return $this
     * @throws RoutingNumberInvalid
     */
    public function setDestinationRouting($number)
    {
        if (strlen($number) !== 9) {
            throw new RoutingNumberInvalid();
        }

        $this->destinationRouting       = $number;
        $this->header['destination_id'] = " " . $number;

        array_walk($this->batches, function (Batch $batch) use ($number) {
            $batch->setOriginatingId($number);
        });

        return $this;
    }

    /**
     * set the destination name
     * should be the name of the bank
     * referenced by the destination routing number
     *
     * @param string $name
     * @return $this
     */
    public function setDestinationName($name)
    {
        $name                             = strtoupper(substr($name, 0, 23));
        $this->header['destination_name'] = str_pad($name, 23);
        return $this;
    }

    /**
     * set your company name
     *
     * @param $name
     * @return $this
     */
    public function setCompanyName($name)
    {
        $name                        = strtoupper(substr($name, 0, 23));
        $this->header['origin_name'] = str_pad($name, 23);

        array_walk($this->batches, function (Batch $batch) use ($name) {
            $batch->setCompanyName($name);
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    |
    | allow access to private attributes
    |
    */

    /**
     * get current batch count
     * @return int
     */
    public function getBatchCount()
    {
        return count($this->batches);
    }

    /**
     * get any header attribute
     * @param $key
     * @return mixed
     */
    public function getHeaderAttribute($key)
    {
        return $this->header[$key];
    }

    /**
     * get any footer attribute
     * @param $key
     * @return mixed
     */
    public function getFooterAttribute($key)
    {
        return $this->footer[$key];
    }

    /**
     * get sum of all batch record counts
     * @return mixed
     */
    public function recordCount()
    {
        return array_reduce($this->batches, function ($total, Batch $batch) {
            return $total + $batch->getEntryCount();
        }, 0);
    }

    /**
     * get sum of all batch debits
     * @return mixed
     */
    public function debitTotal()
    {
        return array_reduce($this->batches, function ($total, Batch $batch) {
            return $total + $batch->debitTotal();
        }, 0);
    }

    /**
     * get sum of all batch credits
     * @return mixed
     */
    public function creditTotal()
    {
        return array_reduce($this->batches, function ($total, Batch $batch) {
            return $total + $batch->creditTotal();
        }, 0);
    }

    /**
     * get sum of all batch routing numbers
     * @return string
     */
    public function entryHash()
    {
        $total = array_reduce($this->batches, function ($total, Batch $batch) {
            return $total + $batch->getRoutingSum();
        }, 0);

        return substr($total, -10);
    }

    public function blockCount()
    {
        $blocks = array_reduce($this->batches, function ($total, Batch $batch) {
            return $total + $batch->getBlockCount();
        }, 2);

        return ceil( $blocks / 10);
    }
}