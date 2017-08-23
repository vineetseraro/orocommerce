<?php

namespace Oro\Bundle\FedexShippingBundle\Client\RateService\Response;

class FedexRateServiceResponse implements FedexRateServiceResponseInterface
{
    const SEVERITY_SUCCESS = 'SUCCESS';
    const SEVERITY_NOTE = 'NOTE';
    const SEVERITY_WARNING = 'WARNING';
    const SEVERITY_ERROR = 'ERROR';
    const SEVERITY_FAILURE = 'FAILURE';

    const CONNECTION_ERROR = 111;
    const NO_SERVICES_ERROR = 556;
    const AUTHORIZATION_ERROR = 1000;

    /**
     * @var string
     */
    protected $severityType;

    /**
     * @var int
     */
    protected $severityCode;

    /**
     * @var array
     */
    protected $prices;

    /**
     * @param string $severityType
     * @param int    $severityCode
     * @param array  $prices
     */
    public function __construct(string $severityType, int $severityCode, array $prices = [])
    {
        $this->severityType = $severityType;
        $this->severityCode = $severityCode;
        $this->prices = $prices;
    }

    /**
     * {@inheritDoc}
     */
    public function getSeverityType(): string
    {
        return $this->severityType;
    }

    /**
     * {@inheritDoc}
     */
    public function getSeverityCode(): int
    {
        return $this->severityCode;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrices(): array
    {
        return $this->prices;
    }

    /**
     * {@inheritDoc}
     */
    public function isSuccessful(): bool
    {
        return $this->getSeverityType() === FedexRateServiceResponse::SEVERITY_SUCCESS ||
            $this->getSeverityType() === FedexRateServiceResponse::SEVERITY_NOTE;
    }
}
