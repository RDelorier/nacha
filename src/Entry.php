<?php
/**
 * Created by PhpStorm.
 * User: rdelorier1
 * Date: 8/22/15
 * Time: 6:25 PM
 */

namespace Nacha;


use Nacha\Exceptions\InvalidTransactionCode;
use Nacha\Exceptions\RoutingNumberInvalid;
use Nacha\Traits\AllowsDebug;

class Entry
{
    use AllowsDebug;

    const DEPOSIT_CHECKING = 22;
    const DEBIT_CHECKING   = 27;

    private $attributes = [
        'record_type_code'         => 6,
        'transaction_code'         => '',
        'routing_number'           => '',
        'account_number'           => '',
        'amount'                   => '',
        'receiver_id'              => '',
        'receiver_name'            => '',
        'empty'                    => '  ',
        'addenda_record_indicator' => '0',
        'tracer_number'            => "",
        'new_line'                 => "\n"
    ];

    private $entryNumber = 1;
    private $batchNumber = 1;
    private $amount      = 0;

    /**
     * Create a new Entry and assign the required fields
     *
     * @param string $tranCode use one of the class constants DEBIT_CHECKING|DEPOSIT_CHECKING
     * @param string $routing receivers routing number
     * @param string $account receivers account number
     * @param string $receiverId receivers id
     * @param string $receiverName receivers name
     * @param float $amount amount of transaction
     * @return Entry
     */
    public static function create($tranCode, $routing, $account, $receiverId, $receiverName, $amount)
    {
        $instance = new Entry();
        $instance
            ->setTransactionCode($tranCode)
            ->setRoutingNumber($routing)
            ->setAccountNumber($account)
            ->setReceiverId($receiverId)
            ->setReceiverName($receiverName)
            ->setAmount($amount);

        return $instance;
    }

    /**
     * Returns string to be inserted in bank file
     * @return string
     */
    public function toString()
    {
        $this->updateTracer();
        return implode($this->getImplodeGlue(), $this->attributes);
    }

    /**
     * update tracer with batch and entry numbers
     */
    private function updateTracer()
    {
        $data = [
            '0',
            str_pad($this->batchNumber, 7, '0', STR_PAD_LEFT),
            str_pad($this->entryNumber, 7, '0', STR_PAD_LEFT)
        ];

        $this->attributes['tracer_number'] = implode($data);
    }

    /*
    |--------------------------------------------------------------------------
    | Setters
    |--------------------------------------------------------------------------
    |
    | These will all validate/trim/pad the values given before putting them
    | into the attributes array
    |
    */
    /**
     * set the entry number of this exact entry and update tracer
     * @param int $number
     * @return $this
     */
    public function setEntryNumber($number)
    {
        $this->entryNumber = $number;
        $this->updateTracer();
        return $this;
    }

    /**
     * set receivers routing number
     *
     * @param string $number should be 9 characters long
     * @return $this
     * @throws RoutingNumberInvalid
     */
    public function setRoutingNumber($number)
    {
        if (strlen($number) < 9) {
            throw new RoutingNumberInvalid();
        }

        $this->attributes['routing_number'] = substr($number, 0, 9);
        return $this;
    }

    /**
     * set receivers account number
     *
     * @param string $number can be up to 17 characters long
     * @return $this
     */
    public function setAccountNumber($number)
    {
        $number                             = substr($number, 0, 17);
        $this->attributes['account_number'] = str_pad($number, 17, ' ');
        return $this;
    }

    /**
     * set dollar amount of transaction
     *
     * @param float $amount DOLLAR amount of transaction, will be converted to cents
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount               = $amount;
        $this->attributes['amount'] = str_pad($amount * 100, 10, '0', STR_PAD_LEFT);
        return $this;
    }

    /**
     * set transaction code
     *
     * @param int $code should be one of DEBIT_CHECKING|DEPOSIT_CHECKING
     * @return $this
     * @throws InvalidTransactionCode
     */
    public function setTransactionCode($code)
    {
        if(!in_array($code,[self::DEBIT_CHECKING,self::DEPOSIT_CHECKING])){
            throw new InvalidTransactionCode();
        }

        $this->attributes['transaction_code'] = $code;
        return $this;
    }

    /**
     * set receiver id, should be how you reference the receiver and will appear on bank statements
     *
     * @param string $id
     * @return $this
     */
    public function setReceiverId($id)
    {
        $id                              = substr($id, 0, 17);
        $this->attributes['receiver_id'] = str_pad($id, 15, ' ', STR_PAD_LEFT);
        return $this;
    }

    /**
     * set receiver name, will appear on back statements
     * @param string $name
     * @return $this
     */
    public function setReceiverName($name)
    {
        $name                              = strtoupper(substr($name, 0, 22));
        $this->attributes['receiver_name'] = str_pad($name, 22, ' ');
        return $this;
    }

    /**
     * set batch number and update tracer
     *
     * @param int $number
     * @return $this
     */
    public function setBatchNumber($number)
    {
        $this->batchNumber = $number;
        $this->updateTracer();
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    |
    | provide access to private attributes
    |
    */
    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return int
     */
    public function getRouting()
    {
        return $this->attributes['routing_number'];
    }

    /**
     * @return int
     */
    public function getBatchNumber()
    {
        return $this->batchNumber;
    }

    /**
     * provider a way of getting any attributes which are used
     * to build the final string
     *
     * @param $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        return $this->attributes[$key];
    }

    /**
     * @return bool
     */
    public function isDebit()
    {
        return $this->attributes['transaction_code'] == self::DEBIT_CHECKING;
    }

    /**
     * @return bool
     */
    public function isCredit()
    {
        return !$this->isDebit();
    }

}