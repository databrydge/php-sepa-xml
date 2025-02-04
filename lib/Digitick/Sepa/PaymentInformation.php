<?php
/**
 * SEPA file generator.
 *
 * @copyright © Digitick <www.digitick.net> 2012-2013
 * @copyright © Blage <www.blage.net> 2013
 * @license GNU Lesser General Public License v3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Lesser Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Digitick\Sepa;

use Digitick\Sepa\DomBuilder\DomBuilderInterface;
use Digitick\Sepa\Exception\InvalidArgumentException;
use Digitick\Sepa\TransferInformation\TransferInformationInterface;
use Digitick\Sepa\Util\StringHelper;

class PaymentInformation
{
    /**
     * The first drawn from several recurring debits
     */
    const S_FIRST = 'FRST';

    /**
     * A recurring direct debit in a number of direct debits
     */
    const S_RECURRING = 'RCUR';

    /**
     * A one time non-recurring debit
     */
    const S_ONEOFF = 'OOFF';

    /**
     * The last direct debit in a series of recurring debits
     */
    const S_FINAL = 'FNAL';

    /**
     * @var string Unambiguously identify the payment.
     */
    public $id;

    /**
     * @var string Purpose of the transaction(s).
     */
    public $categoryPurposeCode;

    /**
     * @var string Debtor's name.
     */
    public $originName;

    /**
     * Unique identification of an organisation, as assigned by an institution, using an identification scheme.
     * @var string
     */
    public $originBankPartyIdentification;

    /**
     * Name of the identification scheme, in a coded form as published in an external list. 1-4 characters.
     * @var string
     */
    public $originBankPartyIdentificationScheme;

    /**
     * @var string Debtor's account IBAN.
     */
    public $originAccountIBAN;

    /**
     * @var string Debtor's account bank BIC code.
     */
    public $originAgentBIC;

    /**
     * @var string Debtor's account ISO currency code.
     */
    protected $originAccountCurrency;

    /**
     * @var string Payment method.
     */
    protected $paymentMethod;

    /**
     * @var string Local service instrument code.
     */
    protected $localInstrumentCode;

    /**
     * Date of payment execution
     *
     * @var \DateTime
     */
    protected $dueDate;

    /**
     * @var string Instruction priority.
     */
    protected $instructionPriority;

    /**
     * @var integer
     */
    protected $controlSumCents = 0;

    /**
     * @var integer Number of payment transactions.
     */
    protected $numberOfTransactions = 0;

    /**
     * @var array<TransferInformationInterface>
     */
    protected $transfers = [];

    /**
     * Valid Payment Methods set by the TransferFile
     *
     * @var
     */
    protected $validPaymentMethods;

    /**
     * @var string
     */
    protected $creditorId;

    /**
     * @var
     */
    protected $sequenceType;

    /**
     * Should the bank book multiple transaction as a batch
     *
     * @var int
     */
    protected $batchBooking = null;

    /**
     * @var \DateTime
     */
    protected $mandateSignDate;

    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * Servicelevel used in payment, default is SEPA
     *
     * @var string
     */
    protected $serviceLevel = 'SEPA';

    /**
     * IBAN or BBAN
     *
     * @var string
     */
    protected $schemaName = 'IBAN';

    /**
     * @var bool
     */
    protected $hideControlSum = false;

    /**
     * @var bool
     */
    protected $hideOriginAccountIBAN = false;

    /**
     * @var bool
     */
    protected $hideGeneralSettings = false;

    /**
     * @var string
     */
    protected $country;

    /**
     * @param string $id
     * @param string $originAccountIBAN This is your IBAN
     * @param string $originAgentBIC This is your BIC
     * @param string $originName This is your Name
     * @param string $originAccountCurrency
     */
    public function __construct($id, $originAccountIBAN, $originAgentBIC, $originName, $originAccountCurrency = 'EUR')
    {
        $this->id = $id;
        $this->originAccountIBAN = $originAccountIBAN;
        $this->originAgentBIC = $originAgentBIC;
        $this->originName = StringHelper::sanitizeString($originName);
        $this->originAccountCurrency = $originAccountCurrency;
        $this->dueDate = new \DateTime();
    }

    /**
     * @param TransferInformationInterface $transfer
     */
    public function addTransfer(TransferInformationInterface $transfer)
    {
        $this->transfers[] = $transfer;
        $this->numberOfTransactions++;
        $this->controlSumCents += $transfer->getTransferAmount();
    }

    /**
     * @return array
     */
    public function getTransfers()
    {
        return $this->transfers;
    }

    /**
     * The domBuilder accept this Object
     *
     * @param DomBuilderInterface $domBuilder
     */
    public function accept(DomBuilderInterface $domBuilder)
    {
        $domBuilder->visitPaymentInformation($this);
        /** @var $transfer TransferInformationInterface */
        foreach ($this->getTransfers() as $transfer) {
            $transfer->accept($domBuilder);
        }
    }

    /**
     * Set the payment method.
     * @param string $method
     * @throws InvalidArgumentException
     */
    public function setPaymentMethod($method)
    {
        $method = strtoupper($method);
        if (!in_array($method, $this->validPaymentMethods)) {
            throw new InvalidArgumentException("Invalid Payment Method: $method, must be one of " . implode(
                ',',
                $this->validPaymentMethods
            ));
        }
        $this->paymentMethod = $method;
    }

    /**
     * @param string $localInstrumentCode
     * @throws InvalidArgumentException
     */
    public function setLocalInstrumentCode($localInstrumentCode)
    {
        $localInstrumentCode = strtoupper($localInstrumentCode);
        if (!in_array($localInstrumentCode, ['B2B', 'CORE', 'COR1', 'IN', 'ONCL'])) {
            throw new InvalidArgumentException("Invalid Local Instrument Code: $localInstrumentCode");
        }
        $this->localInstrumentCode = $localInstrumentCode;
    }

    /**
     * @param mixed $validPaymentMethods
     */
    public function setValidPaymentMethods($validPaymentMethods)
    {
        $this->validPaymentMethods = $validPaymentMethods;
    }

    /**
     * @param string $categoryPurposeCode
     */
    public function setCategoryPurposeCode($categoryPurposeCode)
    {
        $this->categoryPurposeCode = $categoryPurposeCode;
    }

    /**
     * @return string
     */
    public function getCategoryPurposeCode()
    {
        return $this->categoryPurposeCode;
    }

    /**
     * @param \DateTime $dueDate
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
    }

    /**
     * @return string
     */
    public function getDueDate()
    {
        return $this->dueDate->format($this->dateFormat);
    }

    /**
     * @param string $instructionPriority
     */
    public function setInstructionPriority($instructionPriority)
    {
        $instructionPriority = strtoupper($instructionPriority);
        if (!in_array($instructionPriority, ['NORM', 'HIGH'])) {
            throw new InvalidArgumentException("Invalid Instruction Priority: $instructionPriority");
        }
        $this->instructionPriority = $instructionPriority;
    }

    /**
     * @return string
     */
    public function getInstructionPriority()
    {
        return $this->instructionPriority;
    }

    /**
     * @param \DateTime $mandateSignDate
     */
    public function setMandateSignDate($mandateSignDate)
    {
        $this->mandateSignDate = $mandateSignDate;
    }

    /**
     * @return \DateTime
     */
    public function getMandateSignDate()
    {
        return $this->mandateSignDate;
    }

    /**
     * @param string $originName
     */
    public function setOriginName($originName)
    {
        $this->originName = StringHelper::sanitizeString($originName);
    }

    /**
     * @return string
     */
    public function getOriginName()
    {
        return $this->originName;
    }

    /**
     * @param string $id
     */
    public function setOriginBankPartyIdentification($id)
    {
        $this->originBankPartyIdentification = StringHelper::sanitizeString($id);
    }

    /**
     * @return string
     */
    public function getOriginBankPartyIdentification()
    {
        return $this->originBankPartyIdentification;
    }

    /**
     * @param string $id
     */
    public function setOriginBankPartyIdentificationScheme($scheme)
    {
        $this->originBankPartyIdentificationScheme = StringHelper::sanitizeString($scheme);
    }

    /**
     * @return string
     */
    public function getOriginBankPartyIdentificationScheme()
    {
        return $this->originBankPartyIdentificationScheme;
    }

    /**
     * @param string $originAgentBIC
     */
    public function setOriginAgentBIC($originAgentBIC)
    {
        $this->originAgentBIC = $originAgentBIC;
    }

    /**
     * @return string
     */
    public function getOriginAgentBIC()
    {
        return $this->originAgentBIC;
    }

    /**
     * @param string $originAccountIBAN
     */
    public function setOriginAccountIBAN($originAccountIBAN)
    {
        $this->originAccountIBAN = $originAccountIBAN;
    }

    /**
     * @return string
     */
    public function getOriginAccountIBAN()
    {
        return $this->originAccountIBAN;
    }

    /**
     * @param string $originAccountCurrency
     */
    public function setOriginAccountCurrency($originAccountCurrency)
    {
        $this->originAccountCurrency = $originAccountCurrency;
    }

    /**
     * @return string
     */
    public function getOriginAccountCurrency()
    {
        return $this->originAccountCurrency;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getControlSumCents()
    {
        return $this->controlSumCents;
    }

    /**
     * @return string
     */
    public function getLocalInstrumentCode()
    {
        return $this->localInstrumentCode;
    }

    /**
     * @return int
     */
    public function getNumberOfTransactions()
    {
        return $this->numberOfTransactions;
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * @param string $creditorSchemeId
     */
    public function setCreditorId($creditorSchemeId)
    {
        $this->creditorId = StringHelper::sanitizeString($creditorSchemeId);
    }

    /**
     * @return string
     */
    public function getCreditorId()
    {
        return $this->creditorId;
    }

    /**
     * @param mixed $sequenceType
     */
    public function setSequenceType($sequenceType)
    {
        $this->sequenceType = $sequenceType;
    }

    /**
     * @return mixed
     */
    public function getSequenceType()
    {
        return $this->sequenceType;
    }

    /**
     * @param boolean $batchBooking
     */
    public function setBatchBooking($batchBooking)
    {
        $this->batchBooking = $batchBooking;
    }

    /**
     * @return int|null
     */
    public function getBatchBooking()
    {
        return $this->batchBooking;
    }

    /**
     * @param string $format
     */
    public function setDueDateFormat($format)
    {
        $this->dateFormat = $format;
    }

    /**
     * @param $serviceLevel
     *
     * @throws InvalidArgumentException
     */
    public function setServiceLevel($serviceLevel)
    {
        $serviceLevel = strtoupper($serviceLevel);
        if (!in_array($serviceLevel, ['SEPA', 'NURG'])) {
            throw new InvalidArgumentException("Invalid Service Level: $serviceLevel");
        }

        $this->serviceLevel = $serviceLevel;
    }

    /**
     * @return string
     */
    public function getServiceLevel()
    {
        return $this->serviceLevel;
    }

    public function hideOriginAccountIBAN()
    {
        $this->hideOriginAccountIBAN = true;
    }

    public function hasHiddenOriginAccountIBAN()
    {
        return $this->hideOriginAccountIBAN;
    }

    public function hideGeneralSettings()
    {
        $this->hideGeneralSettings = true;
    }

    public function hasHiddenGeneralSettings()
    {
        return $this->hideGeneralSettings;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry(string $country)
    {
        $this->country = $country;
    }
}
